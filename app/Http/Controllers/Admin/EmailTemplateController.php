<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailFila;
use App\Models\EmailTemplate;
use App\Services\Mail\EmailTemplateService;
use App\Services\Mail\FilaDeEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Painel administrativo para gestão de templates de e-mail com rotas dedicadas,
 * editor separado de layout responsivo, preview em tempo real durante digitação
 * e monitor da fila cadenciada anti-spam.
 */
class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateService $templateService,
        private FilaDeEmailService $filaService,
    ) {}

    public function index(Request $request): View
    {
        $templates = $this->templateService->garantirPadroes();
        $categoria = trim((string) $request->query('categoria', ''));

        if ($categoria !== '') {
            $templates = $templates->where('categoria', $categoria)->values();
        }

        $estatisticasFila = $this->filaService->estatisticas();
        $layout = $this->templateService->layoutAtual();
        $categorias = [
            'financeiro' => 'Financeiro & Faturas',
            'loja' => 'Loja & Rastreamento',
            'portal' => 'Portal do Cliente',
            'suporte' => 'Chamados & Suporte',
            'sistema' => 'Sistema & Alertas',
        ];

        return view('admin.templates-email.index', [
            'templates' => $templates,
            'categorias' => $categorias,
            'categoriaAtual' => $categoria,
            'estatisticasFila' => $estatisticasFila,
            'layout' => $layout,
        ]);
    }

    public function edit(string $chave): View
    {
        $this->templateService->garantirPadroes();
        $template = EmailTemplate::where('chave', $chave)->firstOrFail();
        $exemplos = $this->templateService->exemplosVariaveis();
        $previewInicial = $this->templateService->renderizarPorChave($template->chave, $exemplos);

        return view('admin.templates-email.edit', [
            'template' => $template,
            'todosTemplates' => EmailTemplate::orderBy('categoria')->orderBy('nome')->get(),
            'exemplos' => $exemplos,
            'previewInicial' => $previewInicial,
            'layout' => $this->templateService->layoutAtual(),
        ]);
    }

    public function update(Request $request, string $chave): RedirectResponse
    {
        $template = EmailTemplate::where('chave', $chave)->firstOrFail();

        $dados = $request->validate([
            'nome' => 'required|string|max:140',
            'assunto' => 'required|string|max:220',
            'pre_cabecalho' => 'nullable|string|max:220',
            'corpo_html' => 'required|string',
            'corpo_texto' => 'nullable|string',
            'usar_layout' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
        ]);

        $template->update([
            'nome' => $dados['nome'],
            'assunto' => $dados['assunto'],
            'pre_cabecalho' => $dados['pre_cabecalho'] ?? '',
            'corpo_html' => $dados['corpo_html'],
            'corpo_texto' => $dados['corpo_texto'] ?? null,
            'usar_layout' => $request->boolean('usar_layout'),
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()
            ->route('admin.templates-email.edit', $template->chave)
            ->with('sucesso', "Template \"{$template->nome}\" atualizado com sucesso.");
    }

    public function preview(Request $request, string $chave): JsonResponse
    {
        $this->templateService->garantirPadroes();
        $template = EmailTemplate::where('chave', $chave)->firstOrFail();

        $renderizado = $this->templateService->renderizarPorChave(
            $template->chave,
            $this->templateService->exemplosVariaveis(),
            null,
            $request->has('corpo_html') ? (string) $request->input('corpo_html') : null,
            $request->has('assunto') ? (string) $request->input('assunto') : null,
            $request->has('pre_cabecalho') ? (string) $request->input('pre_cabecalho') : null,
            $request->has('usar_layout') ? $request->boolean('usar_layout') : null,
        );

        return response()->json([
            'assunto' => $renderizado['assunto'],
            'html' => $renderizado['html'],
        ]);
    }

    public function enviarTeste(Request $request, string $chave): RedirectResponse
    {
        $template = EmailTemplate::where('chave', $chave)->firstOrFail();
        $dados = $request->validate([
            'email_destino' => 'required|email|max:190',
            'modo_envio' => 'nullable|in:fila,imediato',
        ]);

        $exemplos = $this->templateService->exemplosVariaveis();
        $itemFila = $this->filaService->enfileirarTemplate($template->chave, $dados['email_destino'], $exemplos, [
            'prioridade' => 2,
            'metadados' => [
                'origem' => 'teste_painel',
                'usuario_id' => $request->user()?->id,
            ],
        ]);

        $modo = $dados['modo_envio'] ?? 'fila';
        if ($modo === 'imediato') {
            $enviou = $this->filaService->despacharItem($itemFila);

            return back()->with(
                $enviou ? 'sucesso' : 'erro',
                $enviou
                    ? "E-mail de teste disparado imediatamente para {$dados['email_destino']}."
                    : 'Falha ao enviar o teste imediato. Consulte o log na fila de e-mails.'
            );
        }

        return back()->with(
            'sucesso',
            "E-mail de teste adicionado à fila anti-spam (#{$itemFila->id}) com despacho programado para ".($itemFila->agendado_para?->format('H:i:s') ?? 'breve').'.'
        );
    }

    public function restaurar(string $chave): RedirectResponse
    {
        $template = EmailTemplate::where('chave', $chave)->firstOrFail();
        $this->templateService->restaurarTemplate($template);

        return redirect()
            ->route('admin.templates-email.edit', $template->chave)
            ->with('sucesso', 'Template restaurado para o conteúdo original.');
    }

    public function layout(): View
    {
        $layout = $this->templateService->layoutAtual();
        $templates = $this->templateService->garantirPadroes();
        $templateExemplo = $templates->first() ?? new EmailTemplate(['chave' => 'segunda_via_fatura']);
        $previewInicial = $this->templateService->renderizarPorChave(
            $templateExemplo->chave ?: 'segunda_via_fatura',
            $this->templateService->exemplosVariaveis(),
            $layout
        );

        return view('admin.templates-email.layout', [
            'layout' => $layout,
            'previewInicial' => $previewInicial,
            'templates' => $templates,
        ]);
    }

    public function salvarLayout(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'marca_nome' => 'required|string|max:80',
            'subtitulo' => 'nullable|string|max:140',
            'logo_url' => 'nullable|string|max:255',
            'cor_fundo_pagina' => 'required|string|max:20',
            'cor_cartao' => 'required|string|max:20',
            'cor_cabecalho_bg' => 'required|string|max:20',
            'cor_cabecalho_texto' => 'required|string|max:20',
            'cor_destaque' => 'required|string|max:20',
            'cor_texto' => 'required|string|max:20',
            'cor_botao_bg' => 'required|string|max:20',
            'cor_botao_texto' => 'required|string|max:20',
            'cor_rodape_bg' => 'required|string|max:20',
            'cor_rodape_texto' => 'required|string|max:20',
            'largura_maxima_px' => 'required|integer|min:440|max:720',
            'raio_borda_px' => 'required|integer|min:0|max:28',
            'alinhamento_cabecalho' => 'required|in:left,center',
            'cabecalho_extra_html' => 'nullable|string|max:1500',
            'texto_rodape' => 'nullable|string|max:400',
            'rodape_legal' => 'nullable|string|max:300',
            'css_adicional' => 'nullable|string|max:3000',
        ]);

        $dados['mostrar_barra_superior'] = $request->boolean('mostrar_barra_superior');
        $dados['mostrar_pre_cabecalho'] = $request->boolean('mostrar_pre_cabecalho');
        $dados['mostrar_whatsapp'] = $request->boolean('mostrar_whatsapp');
        $dados['mostrar_links_rodape'] = $request->boolean('mostrar_links_rodape');

        $this->templateService->salvarLayout($dados);

        return redirect()
            ->route('admin.templates-email.layout')
            ->with('sucesso', 'Layout responsivo global atualizado. Todos os templates que usam layout já refletem a mudança.');
    }

    public function previewLayout(Request $request): JsonResponse
    {
        $this->templateService->garantirPadroes();
        $chave = (string) $request->input('template_exemplo', 'segunda_via_fatura');

        $override = [
            'marca_nome' => (string) $request->input('marca_nome', 'GOLDEN'),
            'subtitulo' => (string) $request->input('subtitulo', ''),
            'logo_url' => (string) $request->input('logo_url', ''),
            'cor_fundo_pagina' => (string) $request->input('cor_fundo_pagina', '#f4f1e9'),
            'cor_cartao' => (string) $request->input('cor_cartao', '#ffffff'),
            'cor_cabecalho_bg' => (string) $request->input('cor_cabecalho_bg', '#12161a'),
            'cor_cabecalho_texto' => (string) $request->input('cor_cabecalho_texto', '#cfd6dd'),
            'cor_destaque' => (string) $request->input('cor_destaque', '#e0a33e'),
            'cor_texto' => (string) $request->input('cor_texto', '#1b2127'),
            'cor_botao_bg' => (string) $request->input('cor_botao_bg', '#e0a33e'),
            'cor_botao_texto' => (string) $request->input('cor_botao_texto', '#12161a'),
            'cor_rodape_bg' => (string) $request->input('cor_rodape_bg', '#faf7f0'),
            'cor_rodape_texto' => (string) $request->input('cor_rodape_texto', '#6b7280'),
            'largura_maxima_px' => (int) $request->input('largura_maxima_px', 580),
            'raio_borda_px' => (int) $request->input('raio_borda_px', 16),
            'alinhamento_cabecalho' => (string) $request->input('alinhamento_cabecalho', 'left'),
            'mostrar_barra_superior' => $request->boolean('mostrar_barra_superior'),
            'mostrar_pre_cabecalho' => $request->boolean('mostrar_pre_cabecalho'),
            'cabecalho_extra_html' => (string) $request->input('cabecalho_extra_html', ''),
            'texto_rodape' => (string) $request->input('texto_rodape', ''),
            'rodape_legal' => (string) $request->input('rodape_legal', ''),
            'mostrar_whatsapp' => $request->boolean('mostrar_whatsapp'),
            'mostrar_links_rodape' => $request->boolean('mostrar_links_rodape'),
            'css_adicional' => (string) $request->input('css_adicional', ''),
        ];

        $renderizado = $this->templateService->renderizarPorChave(
            $chave,
            $this->templateService->exemplosVariaveis(),
            $override,
            null,
            null,
            null,
            true
        );

        return response()->json([
            'html' => $renderizado['html'],
        ]);
    }

    public function restaurarLayout(): RedirectResponse
    {
        $this->templateService->restaurarLayout();

        return redirect()
            ->route('admin.templates-email.layout')
            ->with('sucesso', 'Layout global de e-mail restaurado ao padrão Golden.');
    }

    public function fila(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));

        $itens = EmailFila::query()
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.templates-email.fila', [
            'itens' => $itens,
            'statusAtual' => $status,
            'estatisticas' => $this->filaService->estatisticas(),
            'configFila' => $this->filaService->configuracoes(),
        ]);
    }

    public function salvarConfiguracoesFila(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'intervalo_entre_envios_seg' => 'required|integer|min:0|max:600',
            'limite_por_minuto' => 'required|integer|min:1|max:120',
            'limite_por_hora' => 'required|integer|min:1|max:2000',
            'max_tentativas' => 'required|integer|min:1|max:10',
        ]);
        $dados['ativo'] = $request->boolean('ativo');

        $this->filaService->salvarConfiguracoes($dados);

        return redirect()
            ->route('admin.templates-email.fila')
            ->with('sucesso', 'Regras anti-spam e cadência da fila atualizadas.');
    }

    public function processarFila(): RedirectResponse
    {
        $resultado = $this->filaService->processarLote(20, true);

        if ($resultado['motivo'] !== null) {
            return back()->with('erro', $resultado['motivo']);
        }

        return back()->with(
            'sucesso',
            "Lote processado: {$resultado['enviados']} enviado(s), {$resultado['falhas']} falha(s)."
        );
    }

    public function enviarItemFila(EmailFila $emailFila): RedirectResponse
    {
        $ok = $this->filaService->despacharItem($emailFila);

        return back()->with(
            $ok ? 'sucesso' : 'erro',
            $ok
                ? "E-mail #{$emailFila->id} enviado para {$emailFila->destinatario_email}."
                : "Falha no envio do item #{$emailFila->id}: {$emailFila->ultimo_erro}"
        );
    }

    public function cancelarItemFila(EmailFila $emailFila): RedirectResponse
    {
        if ($emailFila->status !== EmailFila::ENVIADO) {
            $emailFila->update(['status' => EmailFila::CANCELADO]);
        }

        return back()->with('sucesso', "Item #{$emailFila->id} marcado como cancelado.");
    }
}
