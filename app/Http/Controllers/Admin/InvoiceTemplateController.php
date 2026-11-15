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

        $faturaDemo = $this->pdfService->faturaExemplo();
        $htmlFolhaPreview = $this->pdfService->renderizarHtmlCirurgico($faturaDemo, $modelo, false);

        return view('admin.faturas-modelo.index', [
            'modelo' => $modelo,
            'estilo' => $modelo['estilo'],
            'blocos' => $modelo['blocos'],
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

        return redirect()
            ->route('admin.faturas-modelo.index')
            ->with('sucesso', 'Modelo cirúrgico de fatura PDF A4 salvo com sucesso.');
    }

    public function preview(Request $request): JsonResponse
    {
        $modelo = $this->pdfService->configuracaoAtual();
        $payloadJson = (string) $request->input('modelo_json', '');

        if ($payloadJson !== '') {
            $decodificado = json_decode($payloadJson, true);
            if (is_array($decodificado)) {
                $modelo = [
                    'estilo' => array_merge($modelo['estilo'], is_array($decodificado['estilo'] ?? null) ? $decodificado['estilo'] : []),
                    'blocos' => is_array($decodificado['blocos'] ?? null) ? $decodificado['blocos'] : $modelo['blocos'],
                ];
            }
        }

        $faturaId = (int) $request->input('whmcs_invoice_id', 0);
        $fatura = $faturaId > 0 ? ($this->whmcs->detalharFatura($faturaId) ?? $this->pdfService->faturaExemplo()) : $this->pdfService->faturaExemplo();

        $html = $this->pdfService->renderizarHtmlCirurgico($fatura, $modelo, false);

        return response()->json(['html' => $html]);
    }

    public function imprimir(Request $request): Response
    {
        $faturaId = (int) $request->query('fatura_id', 0);
        $fatura = $faturaId > 0
            ? ($this->whmcs->detalharFatura($faturaId) ?? $this->pdfService->faturaExemplo())
            : $this->pdfService->faturaExemplo();

        return response($this->pdfService->renderizarHtmlCirurgico($fatura));
    }

    public function pdf(Request $request): Response
    {
        $faturaId = (int) $request->query('fatura_id', 0);
        $fatura = $faturaId > 0
            ? ($this->whmcs->detalharFatura($faturaId) ?? $this->pdfService->faturaExemplo())
            : $this->pdfService->faturaExemplo();

        $binario = $this->pdfService->gerarPdfBinario($fatura);
        $nomeArquivo = 'fatura-'.preg_replace('/[^A-Za-z0-9\-]/', '-', (string) ($fatura['numero'] ?? 'modelo')).'.pdf';

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
        $faturaBruta = $faturaId > 0 ? $this->whmcs->detalharFatura($faturaId) : null;
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

    public function restaurar(): RedirectResponse
    {
        $this->pdfService->restaurarConfiguracao();

        return redirect()
            ->route('admin.faturas-modelo.index')
            ->with('sucesso', 'Modelo de fatura A4 restaurado ao padrão cirúrgico original.');
    }
}
