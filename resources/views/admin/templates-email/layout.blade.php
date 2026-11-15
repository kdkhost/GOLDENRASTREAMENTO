@extends('admin.layouts.app')
@section('title', 'Layout Responsivo dos E-mails')
@section('page_title', 'Studio do layout global de e-mails')

@section('page_actions')
    <a href="{{ route('admin.templates-email.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> Templates de Conteúdo
    </a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-xl-6">
        <form id="formLayoutEmail" method="POST" action="{{ route('admin.templates-email.layout.update') }}">
            @csrf
            @method('PUT')

            <x-admin.card title="Estrutura Externa Separada & Identidade" icon="fa-palette"
                descricao="Mudanças aqui atualizam automaticamente todos os templates marcados com 'Usar Layout Separado'." class="mb-3">
                <div class="row g-3">
                    <x-admin.field name="marca_nome" label="Título / Marca no Cabeçalho" :value="$layout['marca_nome']" col="col-12 col-md-5" :required="true" />
                    <x-admin.field name="subtitulo" label="Subtítulo Institucional" :value="$layout['subtitulo']" col="col-12 col-md-7" :opcional="true" />
                    <x-admin.field name="logo_url" label="URL da Logomarca (opcional)" :value="$layout['logo_url']" col="col-12" :opcional="true"
                        placeholder="https://goldenrastreamento.com.br/img/logo.png" />

                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Alinhamento do Cabeçalho</label>
                        <select name="alinhamento_cabecalho" class="form-select form-select-sm">
                            <option value="left" @selected($layout['alinhamento_cabecalho'] === 'left')>À esquerda</option>
                            <option value="center" @selected($layout['alinhamento_cabecalho'] === 'center')>Centralizado</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Largura Máx. (px)</label>
                        <input type="number" name="largura_maxima_px" min="440" max="720" value="{{ $layout['largura_maxima_px'] }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Raio Borda (px)</label>
                        <input type="number" name="raio_borda_px" min="0" max="28" value="{{ $layout['raio_borda_px'] }}" class="form-control form-control-sm">
                    </div>

                    @php
                        $camposCor = [
                            'cor_fundo_pagina' => 'Fundo Externo',
                            'cor_cartao' => 'Cartão Central',
                            'cor_cabecalho_bg' => 'Cabeçalho (Fundo)',
                            'cor_cabecalho_texto' => 'Cabeçalho (Texto)',
                            'cor_destaque' => 'Destaque / Gold',
                            'cor_texto' => 'Texto Corpo',
                            'cor_botao_bg' => 'Botão (Fundo)',
                            'cor_botao_texto' => 'Botão (Texto)',
                            'cor_rodape_bg' => 'Rodapé (Fundo)',
                            'cor_rodape_texto' => 'Rodapé (Texto)',
                        ];
                    @endphp
                    @foreach($camposCor as $campo => $rotulo)
                        <div class="col-6 col-md-4 col-lg-3">
                            <label class="form-label small fw-semibold mb-1">{{ $rotulo }}</label>
                            <input type="color" name="{{ $campo }}" value="{{ $layout[$campo] }}" class="form-control form-control-color w-100" style="height: 36px;">
                        </div>
                    @endforeach

                    <div class="col-12">
                        <div class="row g-2">
                            <x-admin.switch name="mostrar_barra_superior" label="Faixa Superior Gold" :checked="$layout['mostrar_barra_superior']" col="col-6" />
                            <x-admin.switch name="mostrar_pre_cabecalho" label="Pré-Cabeçalho Oculto (Caixa Entrada)" :checked="$layout['mostrar_pre_cabecalho']" col="col-6" />
                            <x-admin.switch name="mostrar_whatsapp" label="Exibir WhatsApp no Rodapé" :checked="$layout['mostrar_whatsapp']" col="col-6" />
                            <x-admin.switch name="mostrar_links_rodape" label="Links Site / Portal / Rastreio" :checked="$layout['mostrar_links_rodape']" col="col-6" />
                        </div>
                    </div>

                    <x-admin.field name="texto_rodape" label="Mensagem do Rodapé" :value="$layout['texto_rodape']" col="col-12" :opcional="true" />
                    <x-admin.field name="rodape_legal" label="Assinatura Legal" :value="$layout['rodape_legal']" col="col-12" :opcional="true" />

                    <div class="col-12">
                        <label for="css_adicional" class="form-label small fw-semibold">CSS Responsivo Extra (opcional)</label>
                        <textarea id="css_adicional" name="css_adicional" rows="3" class="form-control font-monospace small">{{ $layout['css_adicional'] }}</textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 pt-3 border-top">
                    <button type="submit" class="btn btn-golden">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Layout Global
                    </button>
                </div>
            </x-admin.card>
        </form>

        <x-admin.card title="Restaurar Padrão do Layout" icon="fa-undo">
            <form method="POST" action="{{ route('admin.templates-email.layout.restaurar') }}" data-confirmar="Restaurar cores e estrutura do layout global para o padrão de fábrica?">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-rotate-left" aria-hidden="true"></i> Voltar ao Layout Golden Padrão
                </button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-12 col-xl-6">
        <x-admin.card title="Preview Ao Vivo do Layout Responsivo" icon="fa-desktop" class="sticky-top" style="top: 72px;">
            <x-slot:actions>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-golden active" data-layout-viewport="desktop">Desktop</button>
                    <button type="button" class="btn btn-outline-secondary" data-layout-viewport="mobile">Mobile</button>
                </div>
            </x-slot:actions>

            <div class="mb-2 d-flex align-items-center justify-content-between gap-2">
                <select id="selectTemplateExemplo" class="form-select form-select-sm" style="max-width: 300px;">
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->chave }}">{{ $tpl->nome }}</option>
                    @endforeach
                </select>
                <span id="statusLayoutLive" class="badge-status tone-green">Sincronizado</span>
            </div>

            <div class="preview-shell-layout">
                <iframe id="iframePreviewLayout" title="Preview ao vivo do layout" class="preview-frame-layout"
                    sandbox="allow-same-origin" srcdoc="{{ $previewInicial['html'] }}"></iframe>
            </div>
        </x-admin.card>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formLayoutEmail');
    const iframe = document.getElementById('iframePreviewLayout');
    const sel = document.getElementById('selectTemplateExemplo');
    const statusEl = document.getElementById('statusLayoutLive');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urlPreview = @json(route('admin.templates-email.layout.preview'));

    if (!form || !iframe) return;

    let timer = null;
    let controller = null;

    async function renderizar() {
        if (controller) controller.abort();
        controller = new AbortController();

        if (window.GoldenSummernote && typeof window.GoldenSummernote.syncForm === 'function') {
            window.GoldenSummernote.syncForm(form);
        }

        statusEl.textContent = 'Atualizando...';
        statusEl.className = 'badge-status tone-amber';
        const fd = new FormData(form);
        fd.set('template_exemplo', sel ? sel.value : 'segunda_via_fatura');
        ['mostrar_barra_superior', 'mostrar_pre_cabecalho', 'mostrar_whatsapp', 'mostrar_links_rodape'].forEach((k) => {
            const cb = form.querySelector(`input[name="${k}"][type="checkbox"]`);
            fd.set(k, cb && cb.checked ? '1' : '0');
        });
        try {
            const resp = await fetch(urlPreview, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
                signal: controller.signal,
            });
            const data = await resp.json();

            let scrollAnterior = 0;
            try {
                scrollAnterior = iframe.contentWindow?.scrollY || iframe.contentDocument?.documentElement?.scrollTop || 0;
            } catch {}

            if (typeof data.html === 'string') {
                if (scrollAnterior > 0) {
                    iframe.addEventListener('load', () => {
                        try { iframe.contentWindow?.scrollTo(0, scrollAnterior); } catch {}
                    }, { once: true });
                }
                iframe.srcdoc = data.html;
            }
            statusEl.textContent = 'Ao vivo';
            statusEl.className = 'badge-status tone-green';
        } catch (err) {
            if (err?.name !== 'AbortError') {
                statusEl.textContent = 'Erro';
                statusEl.className = 'badge-status tone-red';
            }
        }
    }

    const agendar = () => {
        clearTimeout(timer);
        timer = setTimeout(renderizar, 120);
    };

    ['input', 'keyup', 'paste', 'cut', 'golden:summernote-input'].forEach((ev) => {
        form.addEventListener(ev, agendar);
    });
    form.addEventListener('change', renderizar);
    sel?.addEventListener('change', renderizar);

    document.querySelectorAll('[data-layout-viewport]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modo = btn.getAttribute('data-layout-viewport');
            document.querySelectorAll('[data-layout-viewport]').forEach((b) => {
                b.classList.remove('btn-golden', 'active');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-golden', 'active');
            iframe.classList.toggle('is-mobile', modo === 'mobile');
        });
    });
})();
</script>
@endsection
