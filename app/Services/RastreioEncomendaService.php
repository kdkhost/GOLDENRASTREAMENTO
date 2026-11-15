<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Correios\CorreiosService;
use App\Services\MelhorEnvio\MelhorEnvioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

        // 2. Correios (quando selecionado no pedido, ou ativo no painel para códigos no formato Correios)
        if ($resultado === null && ($correiosAtivo && ($eCodigoCorreios || str_starts_with($transportadoraPedido, 'correios') || $pedido !== null))) {
            $correiosResp = $this->correios->rastrear($codigo);
            if (! empty($correiosResp['encontrado'])) {
                $resultado = [
                    'codigo' => $correiosResp['codigo'] ?? $codigo,
                    'transportadora' => $pedido ? $pedido->transportadoraLabel() : ($correiosResp['transportadora'] ?? 'Correios'),
                    'status_geral' => $correiosResp['status_geral'] ?? ($correiosResp['status'] ?? 'Em acompanhamento'),
                    'entregue' => (bool) ($correiosResp['entregue'] ?? false),
                    'eventos' => (array) ($correiosResp['eventos'] ?? []),
                    'link_externo' => $eCodigoCorreios ? "https://rastreamento.correios.com.br/app/index.php?objetos={$codigo}" : null,
                    'tempo_real' => (bool) ($correiosResp['tempo_real'] ?? false),
                ];
                $modalidade = 'correios';
            }
        }

        // 2b. API Universal de Rastreamento (Wonca / Seu Rastreio — quando configurada)
        if ($resultado === null) {
            $woncaResp = $this->consultarWonca($codigo, $pedido, $eCodigoCorreios);
            if ($woncaResp !== null) {
                $resultado = $woncaResp;
                $modalidade = $eCodigoCorreios || str_starts_with($transportadoraPedido, 'correios') ? 'correios' : 'propria';
            }
        }

        // 3. Mercado Envios (API oficial quando token configurado, ou marcos reais quando vinculado a um pedido)
        if ($resultado === null && ($mercadoLivreAtivo || $transportadoraPedido === 'mercado_livre')) {
            $mlResp = $this->consultarMercadoLivre($codigo, $pedido);
            if ($mlResp !== null) {
                $resultado = $mlResp;
                $modalidade = 'mercado_livre';
            } elseif ($pedido !== null) {
                $resultado = $this->montarRastreioMercadoEnvios($codigo, $pedido);
                $modalidade = 'mercado_livre';
            }
        }

        // 4. Retirada na Empresa ou Logística Própria Golden (somente quando vinculado a um pedido real)
        if ($resultado === null && $pedido !== null) {
            $resultado = $this->montarRastreioProprio($codigo, $pedido);
            $modalidade = $transportadoraPedido === 'retira' ? 'retira' : 'propria';
        }

        // 5. Nenhum dado real encontrado (nem na API das transportadoras ativas nem nos pedidos do sistema)
        if ($resultado === null) {
            return $this->respostaNaoEncontrado($codigo, $eCodigoCorreios, $correiosAtivo, $melhorEnvioAtivo, $mercadoLivreAtivo);
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
     * Consulta a API universal Wonca / Seu Rastreio quando a Apikey estiver configurada no painel.
     *
     * @return array<string, mixed>|null
     */
    private function consultarWonca(string $codigo, ?Order $pedido, bool $eCodigoCorreios): ?array
    {
        $token = trim((string) Setting::get('rastreio_wonca_token', ''));
        if ($token === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => 'Apikey '.$token,
                    'Accept' => 'application/json',
                ])
                ->get('https://api-labs.wonca.com.br/v1/tracks/'.urlencode($codigo));

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            $rawEvents = $json['events']
                ?? $json['eventos']
                ?? ($json['data']['events'] ?? null)
                ?? ($json['data']['eventos'] ?? null)
                ?? [];

            if (! is_array($rawEvents) || $rawEvents === []) {
                return null;
            }

            $eventos = [];
            foreach ($rawEvents as $ev) {
                if (! is_array($ev)) {
                    continue;
                }

                $status = trim((string) ($ev['description'] ?? $ev['status'] ?? $ev['descricao'] ?? ''));
                if ($status === '') {
                    continue;
                }

                $detalhe = trim((string) ($ev['detail'] ?? $ev['detalhe'] ?? ''));
                $local = trim((string) ($ev['location'] ?? $ev['local'] ?? ''));
                if ($local === '' && ! empty($ev['unidade']) && is_array($ev['unidade'])) {
                    $cidade = trim((string) ($ev['unidade']['endereco']['cidade'] ?? ''));
                    $uf = trim((string) ($ev['unidade']['endereco']['uf'] ?? ''));
                    $tipo = trim((string) ($ev['unidade']['tipo'] ?? ''));
                    $cidadeUf = trim($cidade.($uf !== '' ? "/{$uf}" : ''));
                    $local = trim($tipo.($tipo !== '' && $cidadeUf !== '' ? ' - ' : '').$cidadeUf);
                }

                [$dataStr, $horaStr] = $this->formatarDataHoraWonca(
                    $ev['date'] ?? $ev['data'] ?? $ev['dtHrCriado'] ?? null,
                    $ev['time'] ?? $ev['hora'] ?? null,
                );

                $lower = mb_strtolower($status);
                $cor = 'warning';
                $icone = 'fa-truck';
                if (str_contains($lower, 'entregue') || str_contains($lower, 'entrega efetuada')) {
                    $cor = 'success';
                    $icone = 'fa-circle-check';
                } elseif (str_contains($lower, 'saiu para entrega')) {
                    $cor = 'info';
                    $icone = 'fa-motorcycle';
                } elseif (str_contains($lower, 'postado') || str_contains($lower, 'coletado')) {
                    $cor = 'primary';
                    $icone = 'fa-box';
                } elseif (str_contains($lower, 'devolvido') || str_contains($lower, 'cancelado') || str_contains($lower, 'extraviado')) {
                    $cor = 'danger';
                    $icone = 'fa-triangle-exclamation';
                }

                $eventos[] = [
                    'data' => $dataStr,
                    'hora' => $horaStr,
                    'local' => $local !== '' ? $local : 'Brasil',
                    'status' => $status,
                    'detalhe' => $detalhe,
                    'cor' => $cor,
                    'icone' => $icone,
                    'concluido' => true,
                ];
            }

            if ($eventos === []) {
                return null;
            }

            $primeiroStatus = mb_strtolower((string) ($eventos[0]['status'] ?? ''));
            $entregue = str_contains($primeiroStatus, 'entregue') || str_contains($primeiroStatus, 'entrega efetuada');

            return [
                'codigo' => strtoupper($codigo),
                'transportadora' => $pedido ? $pedido->transportadoraLabel() : ($eCodigoCorreios ? 'Correios' : (string) ($json['carrier'] ?? $json['transportadora'] ?? 'Transportadora')),
                'status_geral' => (string) $eventos[0]['status'],
                'entregue' => $entregue,
                'eventos' => $eventos,
                'link_externo' => $eCodigoCorreios ? "https://rastreamento.correios.com.br/app/index.php?objetos={$codigo}" : null,
                'tempo_real' => true,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function formatarDataHoraWonca(mixed $rawDate, mixed $rawTime): array
    {
        $rawDateStr = is_string($rawDate) ? trim($rawDate) : '';
        $rawTimeStr = is_string($rawTime) ? trim($rawTime) : '';

        if ($rawDateStr !== '') {
            try {
                $dt = Carbon::parse($rawDateStr);

                return [
                    $dt->format('d/m/Y'),
                    $rawTimeStr !== '' ? substr($rawTimeStr, 0, 5) : $dt->format('H:i'),
                ];
            } catch (\Throwable) {
                // Mantém o valor bruto se não for parseável pelo Carbon.
            }
        }

        return [
            $rawDateStr !== '' ? $rawDateStr : now()->format('d/m/Y'),
            $rawTimeStr !== '' ? substr($rawTimeStr, 0, 5) : '--:--',
        ];
    }

    /**
     * Consulta a API oficial de Shipments do Mercado Livre quando o Access Token estiver configurado.
     *
     * @return array<string, mixed>|null
     */
    private function consultarMercadoLivre(string $codigo, ?Order $pedido): ?array
    {
        $token = trim((string) Setting::get('mercado_livre_access_token', ''));
        $shipmentId = preg_replace('/\D+/', '', $codigo) ?? '';

        if ($token === '' || $shipmentId === '') {
            return null;
        }

        try {
            $resp = Http::timeout(8)
                ->withToken($token)
                ->acceptJson()
                ->get("https://api.mercadolibre.com/shipments/{$shipmentId}");

            if (! $resp->successful()) {
                return null;
            }

            $shipment = $resp->json();
            if (! is_array($shipment) || empty($shipment['id'])) {
                return null;
            }

            $statusMapa = [
                'pending' => 'Aguardando postagem',
                'handling' => 'Em preparação para envio',
                'ready_to_ship' => 'Etiqueta pronta para envio',
                'shipped' => 'Objeto em trânsito',
                'delivered' => 'Objeto entregue ao destinatário',
                'not_delivered' => 'Não entregue — verifique ocorrência',
                'cancelled' => 'Envio cancelado',
            ];

            $statusBruto = mb_strtolower((string) ($shipment['status'] ?? ''));
            $subStatus = trim((string) ($shipment['substatus'] ?? ''));
            $entregue = $statusBruto === 'delivered';

            $eventos = [];
            $respHist = Http::timeout(8)
                ->withToken($token)
                ->acceptJson()
                ->get("https://api.mercadolibre.com/shipments/{$shipmentId}/history");

            if ($respHist->successful() && is_array($respHist->json())) {
                $histList = array_reverse($respHist->json());
                foreach ($histList as $idx => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $st = mb_strtolower((string) ($item['status'] ?? ''));
                    $sub = trim((string) ($item['substatus'] ?? ''));
                    $dtRaw = (string) ($item['date'] ?? '');
                    $dt = $dtRaw !== '' ? Carbon::parse($dtRaw) : now();

                    $eventos[] = [
                        'data' => $dt->format('d/m/Y'),
                        'hora' => $dt->format('H:i'),
                        'local' => 'Mercado Envios',
                        'status' => $statusMapa[$st] ?? ucfirst($st !== '' ? $st : 'Atualização'),
                        'detalhe' => $sub !== '' ? "Substatus: {$sub}" : '',
                        'cor' => $st === 'delivered' ? 'success' : ($st === 'cancelled' || $st === 'not_delivered' ? 'danger' : 'primary'),
                        'icone' => $st === 'delivered' ? 'fa-circle-check' : 'fa-truck',
                        'concluido' => true,
                    ];
                }
            }

            if ($eventos === []) {
                $dtRaw = (string) ($shipment['last_updated'] ?? $shipment['date_created'] ?? '');
                $dt = $dtRaw !== '' ? Carbon::parse($dtRaw) : now();
                $cidade = trim((string) data_get($shipment, 'receiver_address.city.name', ''));
                $uf = trim((string) data_get($shipment, 'receiver_address.state.name', ''));
                $local = $cidade !== '' ? trim("{$cidade}".($uf !== '' ? "/{$uf}" : '')) : 'Mercado Envios';

                $eventos[] = [
                    'data' => $dt->format('d/m/Y'),
                    'hora' => $dt->format('H:i'),
                    'local' => $local,
                    'status' => $statusMapa[$statusBruto] ?? 'Em acompanhamento no Mercado Envios',
                    'detalhe' => $subStatus !== '' ? "Substatus: {$subStatus}" : '',
                    'cor' => $entregue ? 'success' : 'primary',
                    'icone' => $entregue ? 'fa-circle-check' : 'fa-truck',
                    'concluido' => true,
                ];
            }

            return [
                'codigo' => (string) $shipment['id'],
                'transportadora' => $pedido ? $pedido->transportadoraLabel() : 'Mercado Envios',
                'status_geral' => (string) $eventos[0]['status'],
                'entregue' => $entregue,
                'eventos' => $eventos,
                'link_externo' => trim((string) Setting::get('mercado_livre_link_loja', '')) ?: null,
                'tempo_real' => true,
            ];
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Retorno honesto quando nenhuma API ativa retornou eventos e não existe pedido no banco.
     *
     * @return array<string, mixed>
     */
    private function respostaNaoEncontrado(
        string $codigo,
        bool $eCodigoCorreios,
        bool $correiosAtivo,
        bool $melhorEnvioAtivo,
        bool $mercadoLivreAtivo,
    ): array {
        $linksConsulta = [];

        if ($eCodigoCorreios || $correiosAtivo) {
            $linksConsulta[] = [
                'rotulo' => 'Consultar nos Correios',
                'url' => 'https://rastreamento.correios.com.br/app/index.php?objetos='.rawurlencode($codigo),
            ];
        }

        if ($melhorEnvioAtivo) {
            $linksConsulta[] = [
                'rotulo' => 'Consultar no Melhor Rastreio',
                'url' => 'https://www.melhorrastreio.com.br/rastreio/'.rawurlencode($codigo),
            ];
        }

        if ($mercadoLivreAtivo) {
            $linksConsulta[] = [
                'rotulo' => 'Acompanhar no Mercado Livre',
                'url' => 'https://www.mercadolivre.com.br/vendas/compras',
            ];
        }

        return [
            'encontrado' => false,
            'codigo' => $codigo,
            'transportadora' => 'Não identificado nas integrações ativas',
            'logisticas_ativas' => $this->logisticasAtivas(),
            'status' => 'Não localizado',
            'status_geral' => 'Sem movimentações retornadas',
            'mensagem' => 'Não encontramos movimentações reais para este código nos pedidos da loja nem nas APIs de rastreamento ativas no painel. Códigos externos do Mercado Envios ou postagens sem integração CWS/Melhor Envio podem ser consultados diretamente no portal da transportadora.',
            'links_consulta' => $linksConsulta,
            'tempo_real' => false,
            'atualizado_em' => now()->format('d/m/Y H:i:s'),
        ];
    }
}
