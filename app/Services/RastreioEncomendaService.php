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

        $pedido = Order::with('histories')
            ->where('codigo_rastreio', $termo)
            ->orWhere('numero', $termo)
            ->first();

        $codigo = trim((string) ($pedido?->codigo_rastreio ?: $termo));
        $codigoLimpo = preg_replace('/[^A-Z0-9-]/i', '', $codigo) ?: $codigo;
        $hashHistorico = md5(json_encode($pedido?->rastreio_historico ?? []) ?: '');
        $cacheKey = 'rastreio_unificado_v3_'.md5(
            $codigoLimpo
            .'|'.($pedido?->id ?? 0)
            .'|'.($pedido?->transportadora ?? '')
            .'|'.($pedido?->status ?? '')
            .'|'.($pedido?->updated_at?->timestamp ?? 0)
            .'|'.$hashHistorico
        );

        if ($forcarAtualizacao) {
            Cache::forget($cacheKey);
            Cache::forget('rastreio_correios_'.preg_replace('/[^A-Z0-9]/', '', strtoupper($codigo)));
        }

        return Cache::remember($cacheKey, 60, function () use ($codigo, $pedido): array {
            return $this->consultarAgora($codigo, $pedido);
        });
    }

    /**
     * Executa a consulta na API da transportadora responsável conforme logística ativa
     * e consolida todas as paradas registradas do pedido.
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

        // 1. Melhor Envio API v2
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

        // 2. Correios API Oficial SRO CWS
        $tentarCorreiosCws = $resultado === null
            && $correiosAtivo
            && ($eCodigoCorreios || str_starts_with($transportadoraPedido, 'correios'));

        if ($tentarCorreiosCws) {
            $eventosCws = $this->correios->consultarSroCws($codigo);
            if (! empty($eventosCws)) {
                $primeiroStatus = (string) ($eventosCws[0]['status'] ?? 'Em acompanhamento');
                $resultado = [
                    'codigo' => $codigo,
                    'transportadora' => $pedido ? $pedido->transportadoraLabel() : 'Correios',
                    'status_geral' => $primeiroStatus,
                    'entregue' => str_contains(mb_strtolower($primeiroStatus), 'entregue'),
                    'eventos' => $eventosCws,
                    'link_externo' => $eCodigoCorreios ? "https://rastreamento.correios.com.br/app/index.php?objetos={$codigo}" : null,
                    'tempo_real' => true,
                ];
                $modalidade = 'correios';
            }
        }

        // 2b. API Universal de Rastreamento (Wonca / Seu Rastreio — quando configurada)
        if ($resultado === null && $transportadoraPedido !== 'mercado_livre' && $transportadoraPedido !== 'retira') {
            $woncaResp = $this->consultarWonca($codigo, $pedido, $eCodigoCorreios);
            if ($woncaResp !== null) {
                $resultado = $woncaResp;
                $modalidade = ($eCodigoCorreios || str_starts_with($transportadoraPedido, 'correios')) ? 'correios' : ($transportadoraPedido === 'melhor_envio' ? 'melhor_envio' : 'propria');
            }
        }

        // 3. Mercado Envios (API Oficial Shipments quando token configurado)
        if ($resultado === null && ($mercadoLivreAtivo || $transportadoraPedido === 'mercado_livre')) {
            $mlResp = $this->consultarMercadoLivre($codigo, $pedido);
            if ($mlResp !== null) {
                $resultado = $mlResp;
                $modalidade = 'mercado_livre';
            }
        }

        // Sincroniza paradas reais obtidas da API no histórico persistente do pedido
        if ($resultado !== null && $pedido !== null && ! empty($resultado['tempo_real']) && ! empty($resultado['eventos'])) {
            $this->sincronizarHistoricoPedido($pedido, (array) $resultado['eventos']);
        }

        // 4. Fallback estruturado para pedido real cadastrado no sistema (de acordo com a transportadora)
        if ($resultado === null && $pedido !== null) {
            if ($transportadoraPedido === 'mercado_livre') {
                $resultado = $this->montarRastreioMercadoEnvios($codigo, $pedido);
                $modalidade = 'mercado_livre';
            } elseif ($transportadoraPedido === 'retira' || $transportadoraPedido === 'propria' || $pedido->entrega === 'retira') {
                $resultado = $this->montarRastreioProprio($codigo, $pedido);
                $modalidade = $transportadoraPedido === 'retira' || $pedido->entrega === 'retira' ? 'retira' : 'propria';
            } elseif ($transportadoraPedido === 'melhor_envio') {
                $resultado = $this->montarRastreioMelhorEnvioPedido($codigo, $pedido);
                $modalidade = 'melhor_envio';
            } elseif (str_starts_with($transportadoraPedido, 'correios') || $correiosAtivo) {
                $resultado = $this->montarRastreioCorreiosPedido($codigo, $pedido);
                $modalidade = 'correios';
            } else {
                $resultado = $this->montarRastreioProprio($codigo, $pedido);
                $modalidade = 'propria';
            }
        }

        // 5. Nenhum dado real encontrado (nem na API das transportadoras ativas nem nos pedidos do sistema)
        if ($resultado === null) {
            return $this->respostaNaoEncontrado($codigo, $eCodigoCorreios, $correiosAtivo, $melhorEnvioAtivo, $mercadoLivreAtivo);
        }

        $eventos = $this->mesclarEventosPedido((array) ($resultado['eventos'] ?? []), $pedido, (string) ($resultado['transportadora'] ?? ($pedido?->transportadoraLabel() ?? 'Transportadora')));
        $entregue = (bool) ($resultado['entregue'] ?? false) || $pedido?->status === 'entregue';
        $statusGeral = (string) ($eventos[0]['status'] ?? ($resultado['status_geral'] ?? 'Em acompanhamento'));
        $etapa = $this->calcularEtapa($statusGeral, $entregue, $eventos);

        $origem = $this->resolverOrigem($modalidade);
        $destino = $this->resolverDestino($pedido, $eventos);
        $trajeto = $this->montarTrajeto($eventos, $origem, $destino, $entregue);

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
            'total_paradas' => count($eventos),
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
                $unidade = trim((string) ($ev['unidade']['tipo'] ?? ($ev['unit'] ?? '')));
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
                    'unidade' => $unidade !== '' ? $unidade : ($pedido ? $pedido->transportadoraLabel() : ($eCodigoCorreios ? 'Correios' : 'Transportadora')),
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
                        'local' => 'Hub Logístico Mercado Envios',
                        'unidade' => 'Mercado Envios',
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
                    'unidade' => 'Mercado Envios',
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
     * Sincroniza paradas reais obtidas da transportadora dentro do campo rastreio_historico do pedido.
     *
     * @param  array<int, array<string, mixed>>  $eventosApi
     */
    private function sincronizarHistoricoPedido(Order $pedido, array $eventosApi): void
    {
        $existentes = is_array($pedido->rastreio_historico) ? $pedido->rastreio_historico : [];
        $chaves = [];
        foreach ($existentes as $ev) {
            if (is_array($ev)) {
                $chaves[$this->chaveEvento($ev)] = true;
            }
        }

        $alterado = false;
        foreach (array_reverse($eventosApi) as $ev) {
            if (! is_array($ev)) {
                continue;
            }
            $normalizado = [
                'data' => (string) ($ev['data'] ?? now()->format('d/m/Y')),
                'hora' => (string) ($ev['hora'] ?? now()->format('H:i')),
                'local' => (string) ($ev['local'] ?? 'Transportadora'),
                'unidade' => (string) ($ev['unidade'] ?? $pedido->transportadoraLabel()),
                'status' => (string) ($ev['status'] ?? 'Movimentação'),
                'detalhe' => (string) ($ev['detalhe'] ?? ''),
                'transportadora' => $pedido->transportadoraLabel(),
            ];

            $chave = $this->chaveEvento($normalizado);
            if (! isset($chaves[$chave])) {
                array_unshift($existentes, $normalizado);
                $chaves[$chave] = true;
                $alterado = true;
            }
        }

        if ($alterado) {
            $pedido->rastreio_historico = array_values($existentes);
            $pedido->saveQuietly();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montarRastreioCorreiosPedido(string $codigo, Order $pedido): array
    {
        $origem = $this->resolverOrigem('correios');
        $destino = $pedido->cidade ? "{$pedido->cidade} / {$pedido->uf}" : 'Endereço do destinatário';
        $entregue = $pedido->status === 'entregue';
        $coletaEmpresa = Setting::get('correios_coleta_empresa') === '1';
        $eventos = [];

        if ($entregue) {
            $dt = $pedido->updated_at ?: now();
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $destino,
                'unidade' => 'Unidade de Distribuição Correios',
                'status' => 'Objeto entregue ao destinatário',
                'detalhe' => 'Entrega finalizada no endereço de destino cadastrado no pedido.',
                'destaque' => true,
            ];
        }

        if ($pedido->enviado_em || $pedido->status === 'enviado' || $entregue) {
            $dt = $pedido->enviado_em ?: ($pedido->updated_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $origem,
                'unidade' => $coletaEmpresa ? 'Coleta Correios — Sede Golden' : 'Agência dos Correios',
                'status' => $coletaEmpresa ? 'Objeto coletado na empresa pelos Correios' : 'Objeto postado nos Correios',
                'detalhe' => "Postagem registrada com código {$codigo} via {$pedido->transportadoraLabel()} com destino a {$destino}.",
                'destaque' => ! $entregue,
            ];
        } else {
            $dt = $pedido->updated_at ?: ($pedido->created_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $origem,
                'unidade' => 'Expedição Golden Rastreamento',
                'status' => 'Objeto em separação para postagem nos Correios',
                'detalhe' => 'Etiqueta em emissão e equipamentos em conferência antes da entrega aos Correios.',
                'destaque' => true,
            ];
        }

        $eCodigoCorreios = (bool) preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', strtoupper($codigo));

        return [
            'codigo' => $codigo,
            'transportadora' => $pedido->transportadoraLabel(),
            'status_geral' => $eventos[0]['status'],
            'entregue' => $entregue,
            'eventos' => $eventos,
            'link_externo' => $eCodigoCorreios ? "https://rastreamento.correios.com.br/app/index.php?objetos={$codigo}" : null,
            'tempo_real' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarRastreioMelhorEnvioPedido(string $codigo, Order $pedido): array
    {
        $origem = $this->resolverOrigem('melhor_envio');
        $destino = $pedido->cidade ? "{$pedido->cidade} / {$pedido->uf}" : 'Endereço do destinatário';
        $entregue = $pedido->status === 'entregue';
        $eventos = [];

        if ($entregue) {
            $dt = $pedido->updated_at ?: now();
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $destino,
                'unidade' => 'Transportadora Parceira (Melhor Envio)',
                'status' => 'Objeto entregue ao destinatário',
                'detalhe' => 'Entrega concluída pela transportadora contratada via Melhor Envio.',
                'destaque' => true,
            ];
        }

        if ($pedido->enviado_em || $pedido->status === 'enviado' || $entregue) {
            $dt = $pedido->enviado_em ?: ($pedido->updated_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $origem,
                'unidade' => 'Ponto / Hub Melhor Envio',
                'status' => 'Objeto postado / em trânsito (Melhor Envio)',
                'detalhe' => "Etiqueta {$codigo} despachada na malha do Melhor Envio em direção a {$destino}.",
                'destaque' => ! $entregue,
            ];
        } else {
            $dt = $pedido->updated_at ?: ($pedido->created_at ?: now());
            $eventos[] = [
                'data' => $dt->format('d/m/Y'),
                'hora' => $dt->format('H:i'),
                'local' => $origem,
                'unidade' => 'Expedição Golden Rastreamento',
                'status' => 'Etiqueta Melhor Envio em preparação',
                'detalhe' => 'Equipamentos em conferência e embalagem para postagem.',
                'destaque' => true,
            ];
        }

        return [
            'codigo' => $codigo,
            'transportadora' => $pedido->transportadoraLabel(),
            'status_geral' => $eventos[0]['status'],
            'entregue' => $entregue,
            'eventos' => $eventos,
            'link_externo' => 'https://www.melhorrastreio.com.br/rastreio/'.rawurlencode($codigo),
            'tempo_real' => false,
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
                'unidade' => 'Entrega Final Mercado Envios',
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
                'unidade' => 'Centro Logístico Mercado Envios',
                'status' => 'Objeto despachado via Mercado Envios',
                'detalhe' => 'Encomenda em rota pela malha logística do Mercado Livre.',
                'destaque' => ! $entregue,
            ];
        } else {
            $eventos[] = [
                'data' => ($pedido?->created_at ?: now())->format('d/m/Y'),
                'hora' => ($pedido?->created_at ?: now())->format('H:i'),
                'local' => 'Central Golden Rastreamento',
                'unidade' => 'Expedição Golden',
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
                'unidade' => $retirada ? 'Recepção Golden Rastreamento' : 'Logística Golden',
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
                'unidade' => $retirada ? 'Balcão de Retirada Golden' : 'Expedição Golden',
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
                'unidade' => 'Bancada Técnica Golden',
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
                'unidade' => 'Atendimento Golden',
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
     * Unifica e deduplica movimentações da API da transportadora, paradas registradas manualmente
     * em `rastreio_historico` e marcos reais do pedido em `histories`, preservando a ordem cronológica decrescente.
     *
     * @param  array<int, array<string, mixed>>  $eventosApi
     * @return array<int, array<string, mixed>>
     */
    private function mesclarEventosPedido(array $eventosApi, ?Order $pedido, string $transportadoraPadrao = 'Transportadora'): array
    {
        $lista = [];
        $chaves = [];

        $adicionar = function (array $ev) use (&$lista, &$chaves, $transportadoraPadrao): void {
            $status = trim((string) ($ev['status'] ?? $ev['descricao'] ?? 'Movimentação'));
            if ($status === '') {
                return;
            }

            $item = [
                'data' => (string) ($ev['data'] ?? now()->format('d/m/Y')),
                'hora' => (string) ($ev['hora'] ?? '00:00'),
                'local' => trim((string) ($ev['local'] ?? '')) ?: 'Em trânsito',
                'unidade' => trim((string) ($ev['unidade'] ?? '')) ?: $transportadoraPadrao,
                'status' => $status,
                'detalhe' => trim((string) ($ev['detalhe'] ?? '')),
                'destaque' => false,
            ];

            $chave = $this->chaveEvento($item);
            if (! isset($chaves[$chave])) {
                $chaves[$chave] = true;
                $lista[] = $item;
            }
        };

        // 1. Paradas da API externa (prioritárias por virem direto da transportadora)
        foreach ($eventosApi as $ev) {
            if (is_array($ev)) {
                $adicionar($ev);
            }
        }

        // 2. Paradas registradas no histórico persistente do pedido (`rastreio_historico`)
        if ($pedido !== null && is_array($pedido->rastreio_historico)) {
            foreach ($pedido->rastreio_historico as $ev) {
                if (is_array($ev)) {
                    $adicionar($ev);
                }
            }
        }

        // 3. Marcos reais da linha do tempo do pedido (`OrderHistory`), quando não houver paradas equivalentes
        if ($pedido !== null && $pedido->relationLoaded('histories')) {
            $origemEmpresa = trim((string) Setting::get('empresa_cidade', 'Central Golden Rastreamento'));
            foreach ($pedido->histories->sortByDesc('created_at') as $h) {
                if (! in_array($h->status, ['pago', 'separando', 'enviado', 'entregue'], true)) {
                    continue;
                }
                $ts = $h->created_at ?: now();
                $adicionar([
                    'data' => $ts->format('d/m/Y'),
                    'hora' => $ts->format('H:i'),
                    'local' => $h->status === 'entregue' && $pedido->cidade
                        ? "{$pedido->cidade} / {$pedido->uf}"
                        : $origemEmpresa,
                    'unidade' => in_array($h->status, ['enviado', 'entregue'], true) ? $pedido->transportadoraLabel() : 'Central Golden Rastreamento',
                    'status' => Order::STATUS[$h->status] ?? ucfirst($h->status),
                    'detalhe' => $h->observacao ?: '',
                ]);
            }
        }

        // Ordena do evento mais recente para o mais antigo preservando desempate
        usort($lista, function (array $a, array $b): int {
            return $this->timestampEvento($b) <=> $this->timestampEvento($a);
        });

        $total = count($lista);
        foreach ($lista as $idx => &$item) {
            $item['destaque'] = $idx === 0;
            $item['parada_numero'] = $total - $idx;
        }
        unset($item);

        return array_values($lista);
    }

    /**
     * @param  array<string, mixed>  $ev
     */
    private function chaveEvento(array $ev): string
    {
        return md5(
            mb_strtolower(trim((string) ($ev['status'] ?? '')))
            .'|'.mb_strtolower(trim((string) ($ev['local'] ?? '')))
            .'|'.trim((string) ($ev['data'] ?? ''))
            .'|'.substr(trim((string) ($ev['hora'] ?? '')), 0, 5)
        );
    }

    /**
     * @param  array<string, mixed>  $ev
     */
    private function timestampEvento(array $ev): int
    {
        try {
            $data = trim((string) ($ev['data'] ?? ''));
            $hora = trim((string) ($ev['hora'] ?? '00:00'));
            if ($data !== '') {
                return Carbon::createFromFormat('d/m/Y H:i', "{$data} ".substr($hora !== '' ? $hora : '00:00', 0, 5))->timestamp;
            }
        } catch (\Throwable) {
            // Ignora formato inválido e usa fallback neutro.
        }

        return 0;
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
    private function montarTrajeto(array $eventos, string $origem, string $destino, bool $entregue = false): array
    {
        $cronologicos = array_reverse($eventos);
        $total = count($cronologicos);
        $pontos = [];

        foreach ($cronologicos as $idx => $ev) {
            $pontos[] = [
                'ordem' => $idx + 1,
                'local' => $ev['local'] ?: ($idx === 0 ? $origem : $destino),
                'unidade' => $ev['unidade'] ?? 'Transportadora',
                'status' => $ev['status'],
                'detalhe' => $ev['detalhe'] ?? '',
                'data_hora' => trim("{$ev['data']} {$ev['hora']}"),
                'atual' => $idx === ($total - 1),
                'previsto' => false,
            ];
        }

        if (! $entregue && $total > 0) {
            $pontos[] = [
                'ordem' => $total + 1,
                'local' => $destino,
                'unidade' => 'Destino Final',
                'status' => 'Previsão de entrega ao destinatário',
                'detalhe' => 'Aguardando próxima atualização da transportadora até o endereço final.',
                'data_hora' => 'Em andamento',
                'atual' => false,
                'previsto' => true,
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
