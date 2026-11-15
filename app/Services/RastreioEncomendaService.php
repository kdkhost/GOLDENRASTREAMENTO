<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Correios\CorreiosService;
use App\Services\MelhorEnvio\MelhorEnvioService;
use Illuminate\Support\Facades\Cache;

/**
 * Orquestrador unificado de rastreamento de encomendas em tempo real.
 *
 * Opera de acordo com as modalidades de logística ativas no painel administrativo
 * (Correios, Melhor Envio, Mercado Envios, Retirada/Logística Própria) e monta o
 * trajeto corrente completo da transportadora responsável.
 */
final class RastreioEncomendaService
{
    public function __construct(
        private readonly CorreiosService $correios,
        private readonly MelhorEnvioService $melhorEnvio,
    ) {}

    /**
     * Lista as modalidades de logística atualmente ativas no painel administrativo.
     *
     * @return array<int, array{chave: string, label: string, icone: string}>
     */
    public function logisticasAtivas(): array
    {
        $ativas = [];

        if (Setting::get('correios_ativo', '1') === '1') {
            $ativas[] = [
                'chave' => 'correios',
                'label' => Setting::get('correios_possui_contrato') === '1' ? 'Correios (Contrato CWS)' : 'Correios',
                'icone' => 'fa-truck-fast',
            ];
        }

        if ($this->melhorEnvio->ativo()) {
            $ativas[] = [
                'chave' => 'melhor_envio',
                'label' => $this->melhorEnvio->sandbox() ? 'Melhor Envio (Sandbox)' : 'Melhor Envio',
                'icone' => 'fa-boxes-stacked',
            ];
        }

        if (Setting::get('mercado_livre_envio_ativo', '0') === '1') {
            $ativas[] = [
                'chave' => 'mercado_livre',
                'label' => 'Mercado Envios',
                'icone' => 'fa-bag-shopping',
            ];
        }

        if (Setting::get('loja_retirada', '1') === '1') {
            $ativas[] = [
                'chave' => 'retira',
                'label' => 'Retirada na Empresa',
                'icone' => 'fa-store',
            ];
        }

        if (Setting::get('loja_livre', '0') === '1' || empty($ativas)) {
            $ativas[] = [
                'chave' => 'propria',
                'label' => 'Logística Golden',
                'icone' => 'fa-shield-halved',
            ];
        }

        return $ativas;
    }

    /**
     * Consulta o rastreamento em tempo real respeitando a transportadora do pedido
     * e as integrações de logística ativas no painel admin.
     *
     * @return array<string, mixed>
     */
    public function rastrear(string $identificador, bool $forcarAtualizacao = false): array
    {
        $termo = strtoupper(trim($identificador));

        $pedido = Order::where('codigo_rastreio', $termo)
            ->orWhere('numero', $termo)
            ->first();

        $codigo = trim((string) ($pedido?->codigo_rastreio ?: $termo));
        $codigoLimpo = preg_replace('/[^A-Z0-9-]/i', '', $codigo) ?: $codigo;
        $cacheKey = 'rastreio_unificado_v2_'.md5($codigoLimpo.'|'.($pedido?->id ?? 0).'|'.($pedido?->transportadora ?? ''));

        if ($forcarAtualizacao) {
            Cache::forget($cacheKey);
            Cache::forget('rastreio_correios_'.preg_replace('/[^A-Z0-9]/', '', strtoupper($codigo)));
        }

        return Cache::remember($cacheKey, 60, function () use ($codigo, $pedido): array {
            return $this->consultarAgora($codigo, $pedido);
        });
    }

    /**
     * Executa a consulta na API da transportadora responsável conforme logística ativa.
     *
     * @return array<string, mixed>
     */
    private function consultarAgora(string $codigo, ?Order $pedido): array
    {
        $correiosAtivo = Setting::get('correios_ativo', '1') === '1';
        $melhorEnvioAtivo = $this->melhorEnvio->ativo();
        $mercadoLivreAtivo = Setting::get('mercado_livre_envio_ativo', '0') === '1';
        $eCodigoCorreios = (bool) preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', strtoupper($codigo));

        $transportadoraPedido = (string) ($pedido?->transportadora ?? '');
        $resultado = null;
        $modalidade = 'propria';

        // 1. Melhor Envio (quando selecionado no pedido ou ativo no painel e não for explicitamente outra transportadora)
        $tentarMelhorEnvio = $melhorEnvioAtivo && (
            $transportadoraPedido === 'melhor_envio'
            || $transportadoraPedido === ''
            || (! str_starts_with($transportadoraPedido, 'correios') && $transportadoraPedido !== 'mercado_livre' && $transportadoraPedido !== 'retira')
        );

        if ($tentarMelhorEnvio) {
            $me = $this->melhorEnvio->rastrear($codigo);
            if ($me !== null) {
                $resultado = $me;
                $modalidade = 'melhor_envio';
            }
        }

        // 2. Correios (quando selecionado no pedido, ou ativo no painel para códigos Correios/gerais)
        if ($resultado === null && ($correiosAtivo || str_starts_with($transportadoraPedido, 'correios') || $eCodigoCorreios)) {
            $correiosResp = $this->correios->rastrear($codigo);
            $resultado = [
                'codigo' => $correiosResp['codigo'] ?? $codigo,
                'transportadora' => $pedido ? $pedido->transportadoraLabel() : ($correiosResp['transportadora'] ?? 'Correios'),
                'status_geral' => $correiosResp['status_geral'] ?? ($correiosResp['status'] ?? 'Em acompanhamento'),
                'entregue' => (bool) ($correiosResp['entregue'] ?? false),
                'eventos' => (array) ($correiosResp['eventos'] ?? []),
                'link_externo' => $eCodigoCorreios ? "https://rastreamento.correios.com.br/app/index.php?objetos={$codigo}" : null,
                'tempo_real' => $eCodigoCorreios,
            ];
            $modalidade = 'correios';
        }

        // 3. Mercado Envios (quando selecionado no pedido ou única modalidade ativa)
        if ($resultado === null && ($transportadoraPedido === 'mercado_livre' || $mercadoLivreAtivo)) {
            $resultado = $this->montarRastreioMercadoEnvios($codigo, $pedido);
            $modalidade = 'mercado_livre';
        }

        // 4. Retirada na Empresa ou Logística Própria Golden
        if ($resultado === null) {
            $resultado = $this->montarRastreioProprio($codigo, $pedido);
            $modalidade = $transportadoraPedido === 'retira' ? 'retira' : 'propria';
        }

        $eventos = $this->mesclarEventosPedido((array) ($resultado['eventos'] ?? []), $pedido);
        $entregue = (bool) ($resultado['entregue'] ?? false) || $pedido?->status === 'entregue';
        $statusGeral = (string) ($resultado['status_geral'] ?? ($eventos[0]['status'] ?? 'Em acompanhamento'));
        $etapa = $this->calcularEtapa($statusGeral, $entregue, $eventos);

        $origem = $this->resolverOrigem($modalidade);
        $destino = $this->resolverDestino($pedido, $eventos);
        $trajeto = $this->montarTrajeto($eventos, $origem, $destino);

        return [
            'encontrado' => true,
            'codigo' => (string) ($resultado['codigo'] ?? $codigo),
            'transportadora' => (string) ($resultado['transportadora'] ?? ($pedido?->transportadoraLabel() ?? 'Logística Golden')),
            'modalidade_ativa' => $modalidade,
            'logisticas_ativas' => $this->logisticasAtivas(),
            'status' => $statusGeral,
            'status_geral' => $statusGeral,
            'cor' => $etapa['cor'],
            'icone' => $etapa['icone'],
            'etapa_indice' => $etapa['indice'],
            'progresso' => $etapa['progresso'],
            'entregue' => $entregue,
            'pedido' => $pedido,
            'origem' => $origem,
            'destino' => $destino,
            'trajeto' => $trajeto,
            'eventos' => $eventos,
            'link_externo' => $resultado['link_externo'] ?? null,
            'instrucoes' => $this->resolverInstrucoes($modalidade),
            'tempo_real' => (bool) ($resultado['tempo_real'] ?? false),
            'atualizado_em' => now()->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarRastreioMercadoEnvios(string $codigo, ?Order $pedido): array
    {
        $eventos = [];
        $entregue = $pedido?->status === 'entregue';

        if ($entregue) {
            $eventos[] = [
                'data' => ($pedido?->updated_at ?: now())->format('d/m/Y'),
                'hora' => ($pedido?->updated_at ?: now())->format('H:i'),
                'local' => $pedido?->cidade ? "{$pedido->cidade} / {$pedido->uf}" : 'Destino final',
                'status' => 'Objeto entregue pelo Mercado Envios',
                'detalhe' => 'Entrega confirmada no endereço do comprador.',
                'destaque' => true,
            ];
        }

        if ($pedido?->enviado_em || $pedido?->status === 'enviado' || $entregue) {
            $dt = $pedido?->enviado_em ?: ($pedido?->updated_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => 'Ponto de Coleta / Hub Mercado Envios',
                'status' => 'Objeto despachado via Mercado Envios',
                'detalhe' => 'Encomenda em rota pela malha logística do Mercado Livre.',
                'destaque' => ! $entregue,
            ];
        } else {
            $eventos[] = [
                'data' => now()->format('d/m/Y'),
                'hora' => now()->format('H:i'),
                'local' => 'Central Golden Rastreamento',
                'status' => 'Preparação para postagem no Mercado Envios',
                'detalhe' => trim((string) Setting::get('mercado_livre_instrucoes', 'Envio gerenciado com código e garantia pelo Mercado Livre.')),
                'destaque' => true,
            ];
        }

        $linkLoja = trim((string) Setting::get('mercado_livre_link_loja', ''));

        return [
            'codigo' => $codigo,
            'transportadora' => 'Mercado Envios (Mercado Livre)',
            'status_geral' => $eventos[0]['status'],
            'entregue' => $entregue,
            'eventos' => $eventos,
            'link_externo' => $linkLoja !== '' ? $linkLoja : 'https://www.mercadolivre.com.br/',
            'tempo_real' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarRastreioProprio(string $codigo, ?Order $pedido): array
    {
        $retirada = $pedido?->transportadora === 'retira' || $pedido?->entrega === 'retira';
        $entregue = $pedido?->status === 'entregue';
        $origem = trim((string) Setting::get('empresa_endereco', 'Sede Golden Rastreamento'));
        $eventos = [];

        if ($entregue) {
            $dt = $pedido?->updated_at ?: now();
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $retirada ? $origem : ($pedido?->cidade ? "{$pedido->cidade} / {$pedido->uf}" : 'Destino'),
                'status' => $retirada ? 'Pedido retirado na empresa' : 'Objeto entregue ao destinatário',
                'detalhe' => $retirada ? 'Retirada presencial concluída na sede da Golden.' : 'Entrega confirmada ao destinatário.',
                'destaque' => true,
            ];
        }

        if ($pedido?->enviado_em || $pedido?->status === 'enviado') {
            $dt = $pedido?->enviado_em ?: ($pedido?->updated_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $origem,
                'status' => $retirada ? 'Disponível para retirada na sede' : 'Em rota de entrega (Logística Golden)',
                'detalhe' => $retirada ? 'Equipamento testado e pronto para retirada no endereço oficial.' : 'Encomenda expedida pela logística da Golden Rastreamento.',
                'destaque' => ! $entregue,
            ];
        }

        if ($pedido?->pago_em) {
            $eventos[] = [
                'data' => $pedido->pago_em->format('d/m/Y'),
                'hora' => $pedido->pago_em->format('H:i'),
                'local' => 'Central Golden Rastreamento',
                'status' => 'Pagamento confirmado — separação e testes',
                'detalhe' => 'Pedido aprovado e em bancada de configuração/testes.',
                'destaque' => empty($eventos),
            ];
        }

        if (empty($eventos)) {
            $eventos[] = [
                'data' => ($pedido?->created_at ?: now())->format('d/m/Y'),
                'hora' => ($pedido?->created_at ?: now())->format('H:i'),
                'local' => 'Central Golden Rastreamento',
                'status' => 'Pedido registrado',
                'detalhe' => 'Aguardando confirmação e liberação para expedição.',
                'destaque' => true,
            ];
        }

        return [
            'codigo' => $codigo,
            'transportadora' => $retirada ? 'Retirada na Empresa (Golden)' : ($pedido?->transportadoraLabel() ?? 'Logística Golden'),
            'status_geral' => $eventos[0]['status'],
            'entregue' => $entregue,
            'eventos' => $eventos,
            'link_externo' => null,
            'tempo_real' => false,
        ];
    }

    /**
     * Mescla eventuais eventos salvos no histórico do pedido quando a API externa não retornou detalhes.
     *
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array<int, array<string, mixed>>
     */
    private function mesclarEventosPedido(array $eventos, ?Order $pedido): array
    {
        $salvos = is_array($pedido?->rastreio_historico) ? $pedido->rastreio_historico : [];

        if (count($eventos) <= 1 && count($salvos) > count($eventos)) {
            $normalizados = [];
            foreach ($salvos as $idx => $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $normalizados[] = [
                    'data' => (string) ($ev['data'] ?? now()->format('d/m/Y')),
                    'hora' => (string) ($ev['hora'] ?? now()->format('H:i')),
                    'local' => (string) ($ev['local'] ?? 'Transportadora'),
                    'status' => (string) ($ev['status'] ?? 'Movimentação'),
                    'detalhe' => (string) ($ev['detalhe'] ?? ''),
                    'destaque' => $idx === 0,
                ];
            }

            if (! empty($normalizados)) {
                return $normalizados;
            }
        }

        return array_values($eventos);
    }

    /**
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array{indice: int, progresso: int, cor: string, icone: string}
     */
    private function calcularEtapa(string $statusGeral, bool $entregue, array $eventos): array
    {
        $texto = mb_strtolower($statusGeral.' '.implode(' ', array_column($eventos, 'status')));

        if ($entregue || str_contains($texto, 'entregue') || str_contains($texto, 'retirado')) {
            return ['indice' => 4, 'progresso' => 100, 'cor' => 'success', 'icone' => 'fa-circle-check'];
        }

        if (str_contains($texto, 'saiu para entrega') || str_contains($texto, 'em rota de entrega') || str_contains($texto, 'disponível para retirada')) {
            return ['indice' => 3, 'progresso' => 82, 'cor' => 'warning', 'icone' => 'fa-truck-fast'];
        }

        if (str_contains($texto, 'trânsito') || str_contains($texto, 'encaminhado') || str_contains($texto, 'despachado') || count($eventos) > 1) {
            return ['indice' => 2, 'progresso' => 58, 'cor' => 'primary', 'icone' => 'fa-route'];
        }

        if (str_contains($texto, 'postado') || str_contains($texto, 'coletado')) {
            return ['indice' => 1, 'progresso' => 34, 'cor' => 'primary', 'icone' => 'fa-box-open'];
        }

        return ['indice' => 0, 'progresso' => 16, 'cor' => 'secondary', 'icone' => 'fa-box'];
    }

    private function resolverOrigem(string $modalidade): string
    {
        $cep = $modalidade === 'melhor_envio'
            ? (Setting::get('melhor_envio_cep_origem') ?: Setting::get('correios_cep_origem'))
            : Setting::get('correios_cep_origem');

        $cepFormatado = $cep ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D/', '', (string) $cep)) : '';
        $cidade = trim((string) Setting::get('empresa_cidade', ''));

        if ($cidade !== '' && $cepFormatado !== '') {
            return "{$cidade} (CEP {$cepFormatado})";
        }

        return $cepFormatado !== '' ? "Expedição Golden (CEP {$cepFormatado})" : 'Central Golden Rastreamento';
    }

    /**
     * @param  array<int, array<string, mixed>>  $eventos
     */
    private function resolverDestino(?Order $pedido, array $eventos): string
    {
        if ($pedido?->cidade) {
            return trim("{$pedido->cidade} / {$pedido->uf}");
        }

        if (! empty($eventos[0]['local'])) {
            return (string) $eventos[0]['local'];
        }

        return 'Endereço do destinatário';
    }

    /**
     * Monta o trajeto corrente em ordem cronológica (da origem até a última parada registrada).
     *
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array<int, array<string, mixed>>
     */
    private function montarTrajeto(array $eventos, string $origem, string $destino): array
    {
        $cronologicos = array_reverse($eventos);
        $total = count($cronologicos);
        $pontos = [];

        foreach ($cronologicos as $idx => $ev) {
            $pontos[] = [
                'ordem' => $idx + 1,
                'local' => $ev['local'] ?: ($idx === 0 ? $origem : $destino),
                'status' => $ev['status'],
                'data_hora' => trim("{$ev['data']} {$ev['hora']}"),
                'atual' => $idx === ($total - 1),
            ];
        }

        return $pontos;
    }

    private function resolverInstrucoes(string $modalidade): ?string
    {
        if ($modalidade === 'mercado_livre') {
            $txt = trim((string) Setting::get('mercado_livre_instrucoes', ''));

            return $txt !== '' ? $txt : null;
        }

        if ($modalidade === 'correios' && Setting::get('correios_coleta_empresa') === '1') {
            $txt = trim((string) Setting::get('correios_instrucoes_coleta', ''));

            return $txt !== '' ? $txt : null;
        }

        return null;
    }
}
