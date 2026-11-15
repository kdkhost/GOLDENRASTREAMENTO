<?php

namespace App\Services\MelhorEnvio;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integração completa com a API v2 do Melhor Envio para cotação em tempo real
 * (Correios, Jadlog, Azul Cargo, Loggi, etc.) e validação de conexão.
 */
final class MelhorEnvioService
{
    private const PROD_URL = 'https://melhorenvio.com.br';

    private const SANDBOX_URL = 'https://sandbox.melhorenvio.com.br';

    public function ativo(): bool
    {
        return Setting::get('melhor_envio_ativo', '0') === '1' && $this->token() !== '';
    }

    public function sandbox(): bool
    {
        return Setting::get('melhor_envio_sandbox', '0') === '1';
    }

    public function baseUrl(): string
    {
        return $this->sandbox() ? self::SANDBOX_URL : self::PROD_URL;
    }

    public function token(): string
    {
        return trim((string) Setting::get('melhor_envio_token', ''));
    }

    /**
     * Calcula opções de frete reais via Melhor Envio API v2 (`/api/v2/me/shipment/calculate`).
     *
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, array<string, mixed>>
     */
    public function calcularFrete(string $cepDestino, int $subtotalCents = 0, array $itens = []): array
    {
        if (! $this->ativo()) {
            return [];
        }

        $cepLimpo = preg_replace('/\D/', '', $cepDestino) ?: '';
        if (strlen($cepLimpo) !== 8) {
            return [];
        }

        $cepOrigem = preg_replace(
            '/\D/',
            '',
            (string) (Setting::get('melhor_envio_cep_origem', '') ?: Setting::get('correios_cep_origem', '35500000'))
        ) ?: '35500000';

        if (strlen($cepOrigem) !== 8) {
            $cepOrigem = '35500000';
        }

        $servicosFiltro = trim((string) Setting::get('melhor_envio_servicos', ''));
        $produtos = $this->montarProdutos($itens, $subtotalCents);
        $chaveCache = 'melhor_envio_frete_'.md5(json_encode([
            $this->sandbox(),
            $cepOrigem,
            $cepLimpo,
            $subtotalCents,
            $servicosFiltro,
            $produtos,
        ]));

        return Cache::remember($chaveCache, 600, function () use ($cepOrigem, $cepLimpo, $servicosFiltro, $produtos, $subtotalCents): array {
            $payload = [
                'from' => ['postal_code' => $cepOrigem],
                'to' => ['postal_code' => $cepLimpo],
                'products' => $produtos,
                'options' => [
                    'receipt' => false,
                    'own_hand' => false,
                    'collect' => false,
                ],
            ];

            if ($servicosFiltro !== '') {
                $payload['services'] = $servicosFiltro;
            }

            try {
                $response = $this->cliente()->post($this->baseUrl().'/api/v2/me/shipment/calculate', $payload);

                if (! $response->successful()) {
                    Log::warning('Melhor Envio retornou erro HTTP na cotação.', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 500),
                    ]);

                    return [];
                }

                $dados = $response->json();
                if (! is_array($dados)) {
                    return [];
                }

                return $this->normalizarOpcoes($dados, $subtotalCents);
            } catch (Throwable $e) {
                Log::warning('Falha ao consultar API do Melhor Envio.', [
                    'erro' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Testa a autenticidade do token e a conectividade com o ambiente configurado.
     *
     * @return array{ok: bool, mensagem: string, usuario?: string}
     */
    public function testarConexao(): array
    {
        if ($this->token() === '') {
            return [
                'ok' => false,
                'mensagem' => 'Informe o Token de Acesso (API v2) do Melhor Envio antes de testar.',
            ];
        }

        try {
            $response = $this->cliente()->get($this->baseUrl().'/api/v2/me');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'mensagem' => 'Melhor Envio recusou a conexão (HTTP '.$response->status().'). Confira o token e se o modo Sandbox está correto.',
                ];
            }

            $user = $response->json();
            $nome = trim((string) (($user['firstname'] ?? '').' '.($user['lastname'] ?? '')));
            $email = (string) ($user['email'] ?? '');
            $ambiente = $this->sandbox() ? 'Sandbox' : 'Produção';

            return [
                'ok' => true,
                'usuario' => $nome !== '' ? $nome : $email,
                'mensagem' => "Conexão validada no ambiente {$ambiente}!".($nome !== '' ? " Conta: {$nome}".($email !== '' ? " ({$email})" : '').'.' : ''),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'mensagem' => 'Erro de rede ao conectar ao Melhor Envio: '.$e->getMessage(),
            ];
        }
    }

    private function cliente(): PendingRequest
    {
        $emailContato = trim((string) Setting::get('loja_email_equipe', 'contato@goldenrastreamento.com.br'))
            ?: 'contato@goldenrastreamento.com.br';

        return Http::timeout(10)
            ->acceptJson()
            ->withToken($this->token())
            ->withHeaders([
                'User-Agent' => "Golden Rastreamento ({$emailContato})",
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, array<string, mixed>>
     */
    private function montarProdutos(array $itens, int $subtotalCents): array
    {
        $pesoPadrao = max(0.05, (float) Setting::get('melhor_envio_peso_kg', '0.35'));
        $larguraPadrao = max(11, (int) Setting::get('melhor_envio_largura_cm', 16));
        $alturaPadrao = max(2, (int) Setting::get('melhor_envio_altura_cm', 8));
        $comprimentoPadrao = max(16, (int) Setting::get('melhor_envio_comprimento_cm', 22));

        if ($itens === []) {
            return [[
                'id' => 'golden_pacote',
                'width' => $larguraPadrao,
                'height' => $alturaPadrao,
                'length' => $comprimentoPadrao,
                'weight' => $pesoPadrao,
                'insurance_value' => max(1.0, round($subtotalCents / 100, 2)),
                'quantity' => 1,
            ]];
        }

        $produtos = [];
        foreach ($itens as $item) {
            $qtd = max(1, (int) ($item['quantidade'] ?? 1));
            $precoUnitCents = (int) ($item['preco'] ?? 0);

            $produtos[] = [
                'id' => (string) ($item['id'] ?? 'item'),
                'width' => $larguraPadrao,
                'height' => $alturaPadrao,
                'length' => $comprimentoPadrao,
                'weight' => $pesoPadrao,
                'insurance_value' => max(1.0, round($precoUnitCents / 100, 2)),
                'quantity' => $qtd,
            ];
        }

        return $produtos;
    }

    /**
     * @param  array<int, mixed>  $dados
     * @return array<int, array<string, mixed>>
     */
    private function normalizarOpcoes(array $dados, int $subtotalCents): array
    {
        $prazoExtra = max(0, (int) Setting::get('melhor_envio_prazo_adicional', 1));
        $taxaAdicionalCents = max(0, (int) Setting::get('melhor_envio_taxa_adicional_cents', 0));
        $freteGratisAcima = (int) Setting::get('loja_frete_gratis_acima_cents', 0);

        $opcoes = [];
        foreach ($dados as $item) {
            if (! is_array($item) || ! empty($item['error'])) {
                continue;
            }

            $serviceId = (int) ($item['id'] ?? 0);
            $precoStr = (string) ($item['custom_price'] ?? $item['price'] ?? '');
            if ($serviceId <= 0 || $precoStr === '') {
                continue;
            }

            $precoBaseCents = (int) round(((float) str_replace(',', '.', $precoStr)) * 100);
            $valorCents = max(0, $precoBaseCents + $taxaAdicionalCents);

            $prazoBase = (int) ($item['custom_delivery_time'] ?? $item['delivery_time'] ?? 0);
            $prazoTotal = max(1, $prazoBase + $prazoExtra);

            $companyName = trim((string) data_get($item, 'company.name', 'Transportadora'));
            $serviceName = trim((string) ($item['name'] ?? 'Envio'));
            $ehGratis = $freteGratisAcima > 0 && $subtotalCents >= $freteGratisAcima;
            $valorFinal = $ehGratis ? 0 : $valorCents;

            $opcoes[] = [
                'id' => 'melhor_envio_'.$serviceId,
                'codigo' => 'melhor_envio_'.$serviceId,
                'nome' => "{$companyName} - {$serviceName}",
                'transportadora' => $companyName,
                'servico_id' => $serviceId,
                'valor_cents' => $valorFinal,
                'valor_formatado' => $ehGratis ? 'Grátis' : brl($valorFinal),
                'prazo_dias' => $prazoTotal,
                'prazo_texto' => $prazoTotal === 1 ? '1 dia útil' : "{$prazoTotal} dias úteis",
                'gratis' => $ehGratis,
                'descricao' => "Cotação em tempo real via Melhor Envio ({$companyName}).",
                'tipo' => 'melhor_envio',
            ];
        }

        usort($opcoes, fn (array $a, array $b): int => $a['valor_cents'] <=> $b['valor_cents']);

        return $opcoes;
    }
}
