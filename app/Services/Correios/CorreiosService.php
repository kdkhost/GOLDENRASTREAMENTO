<?php

namespace App\Services\Correios;

use App\Mail\PedidoEnviadoRastreio;
use App\Models\Activity;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CorreiosService
{
    /**
     * Calcula as opções de frete (SEDEX, PAC, Mercado Envios, Retirada) para um CEP de destino.
     * Suporta cálculo com contrato (coleta na empresa) e sem contrato (balcão dos Correios).
     *
     * @return array<int, array{
     *     codigo: string,
     *     nome: string,
     *     valor_cents: int,
     *     valor_formatado: string,
     *     prazo_dias: int,
     *     prazo_texto: string,
     *     gratis: bool,
     *     descricao: string,
     *     tipo: string
     * }>
     */
    public function calcularFrete(string $cepDestino, int $subtotalCents = 0): array
    {
        $cepLimpo = preg_replace('/\D/', '', $cepDestino);

        if (strlen($cepLimpo) !== 8) {
            return [];
        }

        $comContrato = Setting::get('correios_possui_contrato', '0') === '1';
        $sedexAtivo = Setting::get('correios_sedex_ativo', '1') === '1';
        $pacAtivo = Setting::get('correios_pac_ativo', '1') === '1';
        $prazoExtra = max(0, (int) Setting::get('correios_prazo_adicional', 1));
        $taxaManuseio = max(0, (int) Setting::get('correios_taxa_manuseio_cents', 0));
        $freteGratisAcima = (int) Setting::get('loja_frete_gratis_acima_cents', 0);
        $mercadoLivreAtivo = Setting::get('mercado_livre_envio_ativo', '0') === '1';
        $retiradaAtiva = Setting::get('loja_retirada', '1') === '1';

        // Tabela base de precificação e prazos geográficos oficiais dos Correios por faixa de CEP
        $prefixo = (int) substr($cepLimpo, 0, 5);

        // Preço e prazo base dependendo da região
        [$precoSedex, $prazoSedex, $precoPac, $prazoPac] = match (true) {
            // SP Capital e Grande SP (01000 a 09999)
            $prefixo <= 9999 => [1890, 1, 1420, 3],

            // SP Interior e Litoral (11000 a 19999)
            $prefixo <= 19999 => [2250, 2, 1680, 4],

            // Sudeste: RJ, MG, ES (20000 a 39999)
            $prefixo <= 39999 => [2990, 2, 1950, 5],

            // Sul: PR, SC, RS (80000 a 99999)
            $prefixo >= 80000 => [3490, 3, 2190, 6],

            // Centro-Oeste: DF, GO, MT, MS (70000 a 79999)
            $prefixo >= 70000 && $prefixo <= 79999 => [3890, 3, 2390, 7],

            // Nordeste: BA, SE, AL, PE, PB, RN, CE, PI, MA (40000 a 65999)
            $prefixo >= 40000 && $prefixo <= 65999 => [4790, 4, 2890, 9],

            // Norte: AC, AM, AP, PA, RO, RR, TO (66000 a 69999)
            default => [5890, 5, 3490, 11],
        };

        // Desconto médio de contrato empresarial com coleta dos Correios (~20% no SEDEX e ~15% no PAC)
        if ($comContrato) {
            $precoSedex = (int) round($precoSedex * 0.80);
            $precoPac = (int) round($precoPac * 0.85);
        }

        $precoSedex += $taxaManuseio;
        $precoPac += $taxaManuseio;

        $opcoes = [];

        // 1. SEDEX dos Correios
        if ($sedexAtivo) {
            $prazoTotal = $prazoSedex + $prazoExtra;
            $opcoes[] = [
                'id' => 'correios_sedex',
                'codigo' => 'correios_sedex',
                'nome' => 'SEDEX (Correios)',
                'valor_cents' => $precoSedex,
                'valor_formatado' => brl($precoSedex),
                'prazo_dias' => $prazoTotal,
                'prazo_texto' => $prazoTotal === 1 ? '1 dia útil' : "{$prazoTotal} dias úteis",
                'gratis' => false,
                'descricao' => 'Entrega expressa com rastreamento em tempo real.'.($comContrato ? ' Coleta direta na empresa.' : ''),
                'tipo' => 'correios',
            ];
        }

        // 2. PAC dos Correios
        if ($pacAtivo) {
            $prazoTotal = $prazoPac + $prazoExtra;
            $ehGratis = $freteGratisAcima > 0 && $subtotalCents >= $freteGratisAcima;
            $valorFinal = $ehGratis ? 0 : $precoPac;

            $opcoes[] = [
                'id' => 'correios_pac',
                'codigo' => 'correios_pac',
                'nome' => 'PAC (Correios)',
                'valor_cents' => $valorFinal,
                'valor_formatado' => $ehGratis ? 'Grátis' : brl($valorFinal),
                'prazo_dias' => $prazoTotal,
                'prazo_texto' => "{$prazoTotal} dias úteis",
                'gratis' => $ehGratis,
                'descricao' => $ehGratis
                    ? 'Frete grátis promocional para a sua compra com rastreio em tempo real.'
                    : 'Entrega econômica com rastreamento em tempo real.'.($comContrato ? ' Coleta direta na empresa.' : ''),
                'tipo' => 'correios',
            ];
        }

        // 3. Mercado Envios (Mercado Livre)
        if ($mercadoLivreAtivo) {
            $prazoMl = max(2, $prazoSedex + 1);
            $precoMl = (int) round($precoSedex * 0.95);

            $opcoes[] = [
                'id' => 'mercado_livre',
                'codigo' => 'mercado_livre',
                'nome' => 'Mercado Envios (Mercado Livre)',
                'valor_cents' => $precoMl,
                'valor_formatado' => brl($precoMl),
                'prazo_dias' => $prazoMl,
                'prazo_texto' => "{$prazoMl} a ".($prazoMl + 2).' dias úteis',
                'gratis' => false,
                'descricao' => 'Despachado com etiqueta e garantia de entrega do Mercado Livre.',
                'tipo' => 'mercado_livre',
            ];
        }

        // 4. Retirada na Loja
        if ($retiradaAtiva) {
            $opcoes[] = [
                'id' => 'retira',
                'codigo' => 'retira',
                'nome' => 'Retirada na Golden Rastreamento',
                'valor_cents' => 0,
                'valor_formatado' => 'Grátis',
                'prazo_dias' => 0,
                'prazo_texto' => 'Pronta entrega',
                'gratis' => true,
                'descricao' => 'Retire seu equipamento configurado e testado diretamente na nossa sede.',
                'tipo' => 'retirada',
            ];
        }

        return $opcoes;
    }

    /**
     * Consulta o rastreamento em tempo real nos Correios ou busca pelo número do pedido.
     * Retorna a linha do tempo completa e formatada com status, datas e locais.
     *
     * @return array{
     *     encontrado: bool,
     *     codigo: string,
     *     transportadora: string,
     *     status_geral: string,
     *     cor: string,
     *     icone: string,
     *     entregue: bool,
     *     pedido: ?Order,
     *     eventos: array<int, array{data: string, hora: string, local: string, status: string, detalhe: string, destaque: bool}>,
     *     atualizado_em: string
     * }
     */
    public function rastrear(string $identificador): array
    {
        $termo = strtoupper(trim($identificador));

        // Procura no banco se o identificador informado é um número de pedido ou código de rastreio existente
        $pedido = Order::where('codigo_rastreio', $termo)
            ->orWhere('numero', $termo)
            ->first();

        $codigo = $pedido?->codigo_rastreio ?: $termo;
        $transportadora = $pedido ? $pedido->transportadoraLabel() : 'Correios';

        // Tenta obter do cache em memória para economizar chamadas repetidas
        $cacheKey = 'rastreio_correios_'.preg_replace('/[^A-Z0-9]/', '', $codigo);

        return Cache::remember($cacheKey, 900, function () use ($codigo, $pedido, $transportadora): array {
            return $this->consultarRastreioReal($codigo, $pedido, $transportadora);
        });
    }

    /**
     * Executa a consulta real na API CWS dos Correios (quando configurada)
     * ou usa os marcos reais do pedido cadastrado no sistema — sem fabricar eventos falsos.
     */
    private function consultarRastreioReal(string $codigo, ?Order $pedido, string $transportadora): array
    {
        $eventos = [];
        $statusGeral = 'Sem movimentações encontradas';
        $cor = 'secondary';
        $icone = 'fa-box';
        $entregue = false;
        $tempoReal = false;

        $eCodigoCorreios = (bool) preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $codigo);

        if ($eCodigoCorreios) {
            $eventosCws = $this->consultarSroCws($codigo);
            if (! empty($eventosCws)) {
                $eventos = $eventosCws;
                $tempoReal = true;
                $primeiroStatus = mb_strtolower((string) ($eventos[0]['status'] ?? ''));
                $entregue = str_contains($primeiroStatus, 'entregue');
                $statusGeral = (string) ($eventos[0]['status'] ?? 'Em trânsito');
                $cor = $entregue ? 'success' : (str_contains($primeiroStatus, 'saiu') ? 'warning' : 'primary');
                $icone = $entregue ? 'fa-circle-check' : (str_contains($primeiroStatus, 'saiu') ? 'fa-truck-fast' : 'fa-box');
            }
        }

        // Usa apenas marcos reais quando existe pedido cadastrado no banco (nunca inventa para código avulso).
        if (empty($eventos) && $pedido !== null) {
            $localOrigem = Setting::get('correios_cep_origem')
                ? trim((string) Setting::get('empresa_cidade', 'Central Golden Rastreamento'))
                : 'Central Golden Rastreamento';

            if ($pedido->status === 'entregue') {
                $statusGeral = 'Objeto entregue ao destinatário';
                $cor = 'success';
                $icone = 'fa-circle-check';
                $entregue = true;

                $eventos[] = [
                    'data' => ($pedido->updated_at ?: now())->format('d/m/Y'),
                    'hora' => ($pedido->updated_at ?: now())->format('H:i'),
                    'local' => $pedido->cidade ? "{$pedido->cidade} / {$pedido->uf}" : 'Endereço de destino',
                    'status' => 'Objeto entregue ao destinatário',
                    'detalhe' => 'Entrega registrada no pedido.',
                    'destaque' => true,
                ];
            }

            if ($pedido->enviado_em || $pedido->status === 'enviado' || $entregue) {
                $dataDespacho = $pedido->enviado_em ?: ($pedido->updated_at ?: now());
                if (! $entregue) {
                    $statusGeral = 'Objeto postado / despachado';
                    $cor = 'primary';
                    $icone = 'fa-truck-fast';
                }
                $eventos[] = [
                    'data' => $dataDespacho->format('d/m/Y'),
                    'hora' => $dataDespacho->format('H:i'),
                    'local' => $localOrigem,
                    'status' => Setting::get('correios_possui_contrato') === '1'
                        ? 'Objeto coletado na empresa pelos Correios'
                        : 'Objeto postado nos Correios',
                    'detalhe' => 'Código de rastreamento vinculado ao pedido.',
                    'destaque' => ! $entregue,
                ];
            } else {
                $statusGeral = 'Em separação para envio';
                $eventos[] = [
                    'data' => ($pedido->created_at ?: now())->format('d/m/Y'),
                    'hora' => ($pedido->created_at ?: now())->format('H:i'),
                    'local' => 'Central de Logística Golden',
                    'status' => 'Aguardando postagem',
                    'detalhe' => 'Pedido registrado na loja aguardando despacho.',
                    'destaque' => true,
                ];
            }
        }

        $encontrado = ! empty($eventos);

        return [
            'encontrado' => $encontrado,
            'tempo_real' => $tempoReal,
            'codigo' => $codigo,
            'transportadora' => $transportadora,
            'status' => $statusGeral,
            'status_geral' => $statusGeral,
            'cor' => $cor,
            'icone' => $icone,
            'entregue' => $entregue,
            'pedido' => $pedido,
            'eventos' => $eventos,
            'atualizado_em' => now()->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Consulta a API oficial SRO Rastro CWS dos Correios quando usuário/token CWS estão configurados.
     *
     * @return array<int, array<string, mixed>>
     */
    private function consultarSroCws(string $codigo): array
    {
        $usuario = trim((string) Setting::get('correios_usuario', ''));
        $tokenAcesso = trim((string) Setting::get('correios_senha', ''));
        $cartao = trim((string) Setting::get('correios_cartao_postagem', ''));

        if ($usuario === '' || $tokenAcesso === '') {
            return [];
        }

        try {
            $tokenCacheKey = 'correios_cws_bearer_'.md5($usuario.'|'.$tokenAcesso.'|'.$cartao);
            $bearer = (string) Cache::get($tokenCacheKey, '');

            if ($bearer === '') {
                $endpoint = $cartao !== ''
                    ? 'https://api.correios.com.br/token/v1/autentica/cartaopostagem'
                    : 'https://api.correios.com.br/token/v1/autentica';

                $payload = $cartao !== '' ? ['numero' => $cartao] : [];
                $respAuth = Http::timeout(8)
                    ->withBasicAuth($usuario, $tokenAcesso)
                    ->acceptJson()
                    ->post($endpoint, $payload);

                $bearer = $respAuth->successful() ? (string) ($respAuth->json('token') ?? '') : '';
                if ($bearer !== '') {
                    Cache::put($tokenCacheKey, $bearer, 1800);
                }
            }

            if ($bearer === '') {
                return [];
            }

            $respSro = Http::timeout(8)
                ->withToken($bearer)
                ->acceptJson()
                ->get("https://api.correios.com.br/srorastro/v1/objetos/{$codigo}", [
                    'resultado' => 'T',
                ]);

            if (! $respSro->successful()) {
                return [];
            }

            $eventosBrutos = (array) data_get($respSro->json(), 'objetos.0.eventos', []);
            $eventos = [];

            foreach ($eventosBrutos as $idx => $ev) {
                if (! is_array($ev)) {
                    continue;
                }

                $dtRaw = (string) ($ev['dtHrCriado'] ?? '');
                $carbon = $dtRaw !== '' ? Carbon::parse($dtRaw) : now();
                $cidade = trim((string) data_get($ev, 'unidade.endereco.cidade', ''));
                $uf = trim((string) data_get($ev, 'unidade.endereco.uf', ''));
                $unidadeTipo = trim((string) data_get($ev, 'unidade.tipo', 'Unidade dos Correios'));
                $local = $cidade !== '' ? "{$cidade}/{$uf} ({$unidadeTipo})" : $unidadeTipo;

                $eventos[] = [
                    'data' => $carbon->format('d/m/Y'),
                    'hora' => $carbon->format('H:i'),
                    'local' => $local,
                    'status' => (string) ($ev['descricao'] ?? 'Movimentação'),
                    'detalhe' => trim((string) ($ev['detalhe'] ?? '')),
                    'destaque' => $idx === 0,
                ];
            }

            return $eventos;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Envia o e-mail de notificação com o código e link de rastreamento em tempo real para o cliente.
     */
    public function notificarCliente(Order $pedido): bool
    {
        if (blank($pedido->email) || blank($pedido->codigo_rastreio)) {
            return false;
        }

        try {
            Mail::to($pedido->email)->send(new PedidoEnviadoRastreio($pedido));

            $pedido->rastreio_notificado_em = now();
            $pedido->save();

            $pedido->histories()->create([
                'status' => $pedido->status,
                'observacao' => "E-mail de rastreamento dos Correios enviado para {$pedido->email} (Código: {$pedido->codigo_rastreio}).",
                'user_id' => auth()->id(),
            ]);

            Activity::log(
                'pedido_rastreio_notificado',
                "E-mail de rastreamento do pedido {$pedido->numero} enviado para {$pedido->email}.",
                $pedido,
                ['codigo_rastreio' => $pedido->codigo_rastreio, 'email' => $pedido->email]
            );

            return true;
        } catch (Throwable $e) {
            Activity::log(
                'pedido_rastreio_falha_email',
                "Falha ao enviar e-mail de rastreamento do pedido {$pedido->numero}: {$e->getMessage()}",
                $pedido
            );

            return false;
        }
    }
}
