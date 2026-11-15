<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Fatura\FaturaPdfService;
use App\Services\Mail\FilaDeEmailService;
use App\Services\Whmcs\Cliente as WhmcsCliente;
use App\Services\Whmcs\WhmcsPluginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Estúdio visual arrasta-e-solta de faturas A4 cirúrgicas + PDF nativo +
 * integração com gateways de pagamento e plugin instalável do WHMCS.
 */
class InvoiceTemplateController extends Controller
{
    public function __construct(
        private FaturaPdfService $pdfService,
        private WhmcsCliente $whmcs,
        private WhmcsPluginService $whmcsPlugin,
        private FilaDeEmailService $filaService,
    ) {}

    public function index(): View
    {
        $modelo = $this->pdfService->configuracaoAtual();
        $gatewaysWhmcs = $this->whmcs->configurado() ? $this->whmcs->metodosDePagamento() : [];
        $statusWhmcs = $this->whmcs->statusPluginWhmcs();
        $segredoWebhook = $this->whmcsPlugin->obterOuGerarSegredo();

        $faturaDemo = $this->pdfService->normalizarFatura($this->pdfService->faturaExemplo());
        $htmlFolhaPreview = $this->pdfService->renderizarHtmlCirurgico($faturaDemo, $modelo, false);

        return view('admin.faturas-modelo.index', [
            'modelo' => $modelo,
            'estilo' => $modelo['estilo'],
            'blocos' => $modelo['blocos'],
            'faturaDemo' => $faturaDemo,
            'gatewaysWhmcs' => $gatewaysWhmcs,
            'statusWhmcs' => $statusWhmcs,
            'webhookUrl' => url('/api/webhooks/whmcs'),
            'segredoWebhook' => $segredoWebhook,
            'htmlFolhaPreview' => $htmlFolhaPreview,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $atual = $this->pdfService->configuracaoAtual();

        if ($request->filled('modelo_json')) {
            $decodificado = json_decode((string) $request->input('modelo_json'), true);
            if (! is_array($decodificado)) {
                return back()->with('erro', 'Estrutura de blocos da fatura inválida.');
            }
            $this->pdfService->salvarConfiguracao($decodificado);
        } else {
            $estiloDados = $request->validate([
                'emitente_nome' => 'required|string|max:140',
                'emitente_documento' => 'nullable|string|max:60',
                'emitente_endereco' => 'nullable|string|max:240',
                'emitente_contato' => 'nullable|string|max:180',
                'logo_url' => 'nullable|string|max:255',
                'cor_primaria' => 'required|string|max:20',
                'cor_destaque' => 'required|string|max:20',
                'cor_fundo_bloco' => 'required|string|max:20',
                'cor_borda' => 'required|string|max:20',
                'cor_texto' => 'required|string|max:20',
                'margem_topo_mm' => 'required|integer|min:6|max:25',
                'margem_lateral_mm' => 'required|integer|min:6|max:25',
                'margem_inferior_mm' => 'required|integer|min:6|max:25',
                'tamanho_fonte_pt' => 'required|numeric|min:8|max:12',
                'raio_borda_px' => 'required|integer|min:0|max:14',
                'gateway_padrao_modulo' => 'nullable|string|max:80',
                'pix_chave_fallback' => 'nullable|string|max:255',
                'rodape_texto' => 'nullable|string|max:300',
            ]);

            $this->pdfService->salvarConfiguracao([
                'estilo' => array_merge($atual['estilo'], $estiloDados),
                'blocos' => $atual['blocos'],
            ]);
        }

        cache()->forget($this->chaveCacheRascunho($request));

        return redirect()
            ->route('admin.faturas-modelo.index')
            ->with('sucesso', 'Modelo cirúrgico de fatura PDF A4 salvo com sucesso.');
    }

    /**
     * Retorna a chave de cache do rascunho ao vivo do estúdio para o usuário atual.
     */
    private function chaveCacheRascunho(?Request $request = null): string
    {
        $usuarioId = auth()->id() ?? ($request?->user()?->getKey()) ?? 'guest';

        return 'fatura_studio_draft_'.$usuarioId;
    }

    /**
     * Mescla campos digitados/editados em tempo real sobre a fatura base sem perder estrutura aninhada.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $sobrescrita
     * @return array<string, mixed>
     */
    private function mesclarFaturaSobrescrita(array $base, array $sobrescrita): array
    {
        $clienteBase = is_array($base['cliente'] ?? null) ? $base['cliente'] : [];
        $clienteNovo = is_array($sobrescrita['cliente'] ?? null) ? $sobrescrita['cliente'] : [];

        $mesclada = array_merge($base, $sobrescrita);
        $mesclada['cliente'] = array_merge($clienteBase, $clienteNovo);

        if (array_key_exists('pagamento_data', $sobrescrita) && ! array_key_exists('paga_em', $sobrescrita)) {
            $mesclada['paga_em'] = $sobrescrita['pagamento_data'];
        }

        if (isset($sobrescrita['status']) && ! array_key_exists('status_rotulo', $sobrescrita)) {
            unset($mesclada['status_rotulo']);
        }

        if (array_key_exists('itens', $sobrescrita) && is_array($sobrescrita['itens'])) {
            $subtotalCents = 0;
            $itensNormalizados = [];

            foreach ($sobrescrita['itens'] as $indice => $itemBruto) {
                if (! is_array($itemBruto)) {
                    continue;
                }

                $quantidade = max(1, (int) ($itemBruto['quantidade'] ?? 1));
                $unitarioCents = max(0, (int) ($itemBruto['valor_unitario_cents'] ?? $itemBruto['valor_cents'] ?? 0));
                $totalItemCents = array_key_exists('valor_cents', $itemBruto)
                    ? max(0, (int) $itemBruto['valor_cents'])
                    : ($quantidade * $unitarioCents);

                $subtotalCents += $totalItemCents;
                $itensNormalizados[] = [
                    'id' => (string) ($itemBruto['id'] ?? 'item-'.($indice + 1)),
                    'descricao' => (string) ($itemBruto['descricao'] ?? 'Item'),
                    'tipo' => (string) ($itemBruto['tipo'] ?? 'servico'),
                    'quantidade' => $quantidade,
                    'valor_unitario_cents' => $unitarioCents,
                    'valor_cents' => $totalItemCents,
                ];
            }

            $mesclada['itens'] = $itensNormalizados;
            $mesclada['subtotal_cents'] = $subtotalCents;
            $credito = max(0, (int) ($mesclada['credito_cents'] ?? 0));
            $imposto = max(0, (int) ($mesclada['imposto_cents'] ?? 0));
            $totalCents = max(0, $subtotalCents - $credito + $imposto);
            $mesclada['total_cents'] = $totalCents;
            $mesclada['saldo_cents'] = (($mesclada['status'] ?? '') === 'Paid') ? 0 : $totalCents;
        } elseif (array_key_exists('credito_cents', $sobrescrita) || array_key_exists('imposto_cents', $sobrescrita)) {
            $subtotal = max(0, (int) ($mesclada['subtotal_cents'] ?? 0));
            $credito = max(0, (int) ($mesclada['credito_cents'] ?? 0));
            $imposto = max(0, (int) ($mesclada['imposto_cents'] ?? 0));
            $totalCents = max(0, $subtotal - $credito + $imposto);
            $mesclada['total_cents'] = $totalCents;
            $mesclada['saldo_cents'] = (($mesclada['status'] ?? '') === 'Paid') ? 0 : $totalCents;
        }

        return $mesclada;
    }

    /**
     * Resolve modelo e dados de fatura para preview ao vivo, impressão ou download PDF.
     *
     * @return array{modelo: array<string, mixed>, fatura: array<string, mixed>}
     */
    private function resolverEstadoPreview(Request $request, bool $usarRascunhoCache = false): array
    {
        $modelo = $this->pdfService->configuracaoAtual();
        $rascunho = $usarRascunhoCache ? cache()->get($this->chaveCacheRascunho($request)) : null;

        if (is_array($rascunho) && is_array($rascunho['modelo'] ?? null)) {
            $modelo = [
                'estilo' => array_merge(
                    $modelo['estilo'],
                    is_array($rascunho['modelo']['estilo'] ?? null) ? $rascunho['modelo']['estilo'] : []
                ),
                'blocos' => is_array($rascunho['modelo']['blocos'] ?? null)
                    ? $rascunho['modelo']['blocos']
                    : $modelo['blocos'],
            ];
        }

        $payloadJson = (string) $request->input('modelo_json', '');

        if ($payloadJson !== '') {
            $decodificado = json_decode($payloadJson, true);
            if (is_array($decodificado)) {
                $modelo = [
                    'estilo' => array_merge(
                        $modelo['estilo'],
                        is_array($decodificado['estilo'] ?? null) ? $decodificado['estilo'] : []
                    ),
                    'blocos' => is_array($decodificado['blocos'] ?? null)
                        ? $decodificado['blocos']
                        : $modelo['blocos'],
                ];
            }
        }

        $faturaId = (int) $request->input('whmcs_invoice_id', $request->query('fatura_id', 0));
        $fatura = $faturaId > 0
            ? ($this->whmcs->detalharFatura($faturaId) ?? $this->pdfService->faturaExemplo())
            : $this->pdfService->faturaExemplo();

        if ($faturaId <= 0 && is_array($rascunho) && is_array($rascunho['fatura'] ?? null)) {
            $fatura = $this->mesclarFaturaSobrescrita($fatura, $rascunho['fatura']);
        }

        $faturaJson = (string) $request->input('fatura_json', '');

        if ($faturaJson !== '') {
            $decodificadoFatura = json_decode($faturaJson, true);
            if (is_array($decodificadoFatura)) {
                $fatura = $this->mesclarFaturaSobrescrita($fatura, $decodificadoFatura);
            }
        }

        return [
            'modelo' => $modelo,
            'fatura' => $fatura,
        ];
    }

    public function preview(Request $request): JsonResponse
    {
        $estado = $this->resolverEstadoPreview($request, false);
        $modelo = $estado['modelo'];
        $fatura = $estado['fatura'];

        cache()->put($this->chaveCacheRascunho($request), [
            'modelo' => $modelo,
            'fatura' => $fatura,
        ], now()->addHours(2));

        $faturaNormalizada = $this->pdfService->normalizarFatura($fatura);
        $html = $this->pdfService->renderizarHtmlCirurgico($fatura, $modelo, false);

        return response()->json([
            'html' => $html,
            'numero' => $faturaNormalizada['numero'],
            'total_formatado' => 'R$ '.number_format($faturaNormalizada['total_cents'] / 100, 2, ',', '.'),
        ]);
    }

    public function imprimir(Request $request): Response
    {
        $usarRascunho = $request->boolean('rascunho') || $request->hasAny(['modelo_json', 'fatura_json']);
        $estado = $this->resolverEstadoPreview($request, $usarRascunho);

        return response($this->pdfService->renderizarHtmlCirurgico($estado['fatura'], $estado['modelo']));
    }

    public function pdf(Request $request): Response
    {
        $usarRascunho = $request->boolean('rascunho') || $request->hasAny(['modelo_json', 'fatura_json']);
        $estado = $this->resolverEstadoPreview($request, $usarRascunho);
        $faturaNormalizada = $this->pdfService->normalizarFatura($estado['fatura']);

        $binario = $this->pdfService->gerarPdfBinario($estado['fatura'], $estado['modelo']);
        $nomeArquivo = 'fatura-'.preg_replace('/[^A-Za-z0-9\-]/', '-', (string) ($faturaNormalizada['numero'] ?? 'modelo')).'.pdf';

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomeArquivo.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function baixarPluginWhmcs(): Response
    {
        $zipBytes = $this->whmcsPlugin->gerarPacoteZip();

        return response($zipBytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="golden-whmcs-gateway-plugin.zip"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function enviarTeste(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email_destino' => 'required|email|max:190',
            'whmcs_invoice_id' => 'nullable|integer|min:1',
        ]);

        $faturaId = (int) ($dados['whmcs_invoice_id'] ?? 0);
        if ($faturaId > 0) {
            $faturaBruta = $this->whmcs->detalharFatura($faturaId);
        } else {
            $estadoRascunho = $this->resolverEstadoPreview($request, true);
            $faturaBruta = $estadoRascunho['fatura'] ?? null;
        }
        $fatura = $this->pdfService->normalizarFatura($faturaBruta ?? $this->pdfService->faturaExemplo());

        $valorFormatado = 'R$ '.number_format($fatura['total_cents'] / 100, 2, ',', '.');
        $pixTexto = $fatura['pix_copia_cola'] !== ''
            ? $fatura['pix_copia_cola']
            : 'Consulte o QR Code no PDF em anexo.';

        $item = $this->filaService->enfileirarTemplate('fatura_pdf_anexo', $dados['email_destino'], [
            'cliente_nome' => $fatura['cliente']['nome'] ?: 'Cliente Golden',
            'fatura_numero' => $fatura['numero'],
            'fatura_valor' => $valorFormatado,
            'fatura_vencimento' => $fatura['vencimento'],
            'gateway_nome' => $fatura['metodo_rotulo'],
            'pix_copia_cola' => $pixTexto,
            'linha_digitavel' => $fatura['linha_digitavel'],
            'fatura_url' => $fatura['checkout_url'],
        ], [
            'nome' => $fatura['cliente']['nome'] ?: 'Cliente Golden',
            'anexos' => [[
                'tipo' => 'fatura_pdf',
                'nome' => 'fatura-'.$fatura['numero'].'.pdf',
                'dados' => $fatura,
            ]],
            'prioridade' => 2,
            'metadados' => [
                'origem' => 'fatura_pdf_painel',
                'whmcs_invoice_id' => $fatura['id'],
            ],
        ]);

        return back()->with(
            'sucesso',
            "Fatura #{$fatura['numero']} em PDF adicionada à fila anti-spam (#{$item->id}) para {$dados['email_destino']}."
        );
    }

    public function restaurar(Request $request): RedirectResponse
    {
        $this->pdfService->restaurarConfiguracao();
        cache()->forget($this->chaveCacheRascunho($request));

        return redirect()
            ->route('admin.faturas-modelo.index')
            ->with('sucesso', 'Modelo de fatura A4 restaurado ao padrão cirúrgico original.');
    }
}
