@extends('admin.layouts.app')
@section('title', 'Editar Template: ' . $template->nome)
@section('page_title', 'Editor ao vivo · ' . $template->nome)

@section('page_actions')
    <a href="{{ route('admin.templates-email.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> Todos os Templates
    </a>
    <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-palette" aria-hidden="true"></i> Editar Layout Global
    </a>
@endsection

@section('styles')
<style>
    .preview-shell {
        background: repeating-conic-gradient(rgba(128,128,128,.08) 0% 25%, transparent 0% 50%) 50% / 18px 18px;
        border: 1px solid var(--golden-line, #e2ddd1);
        border-radius: 12px;
        padding: 14px;
        display: flex;
        justify-content: center;
        overflow: auto;
    }
    .preview-frame {
        border: 1px solid rgba(18, 22, 26, 0.14);
        border-radius: 10px;
        background: #f4f1e9;
        width: 100%;
        height: 640px;
        transition: width .22s ease;
    }
    .preview-frame.is-mobile {
        width: 375px;
        max-width: 100%;
        box-shadow: 0 10px 28px rgba(0, 0, 0, .18);
    }
    .var-chip {
        cursor: pointer;
        border: 1px dashed rgba(224, 163, 62, 0.55);
        background: rgba(224, 163, 62, 0.10);
        color: inherit;
        border-radius: 8px;
        padding: 4px 8px;
        font-size: 12px;
        font-family: monospace;
    }
    .var-chip:hover {
        background: rgba(224, 163, 62, 0.24);
    }
</style>
@endsection

@section('content')
<div class="row g-3">
    {{-- Coluna Esquerda: Editor de Conteúdo (Separado do Layout) --}}
    <div class="col-12 col-xl-6">
        <form id="formTemplateEmail" method="POST" action="{{ route('admin.templates-email.update', $template->chave) }}">
            @csrf
            @method('PUT')

            <x-admin.card :title="$template->nome" icon="fa-code"
                :descricao="'Rota dedicada: ' . route('admin.templates-email.edit', $template->chave, false)" class="mb-3">
                <x-slot:actions>
                    <span class="badge-status tone-gold">{{ $template->chave }}</span>
                </x-slot:actions>

                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome de identificação" :value="$template->nome" col="col-12 col-md-7" :required="true" />
                    <div class="col-12 col-md-5 d-flex flex-column justify-content-end gap-2">
                        <x-admin.switch name="usar_layout" label="Usar Layout Global Separado" :checked="$template->usar_layout" />
                        <x-admin.switch name="ativo" label="Template Ativo" :checked="$template->ativo" />
                    </div>

                    <x-admin.field name="assunto" label="Assunto do E-mail" :value="$template->assunto" col="col-12" :required="true"
                        hint="Atualiza o preview do assunto em tempo real conforme você digita." />

                    <x-admin.field name="pre_cabecalho" label="Texto Pré-Cabeçalho (Preheader Mobile)" :value="$template->pre_cabecalho" col="col-12" :opcional="true"
                        hint="Resumo visível na caixa de entrada ao lado do assunto." />

                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-1">Variáveis disponíveis (clique para inserir no HTML)</label>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach(($template->variaveis ?? []) as $token => $desc)
                                <button type="button" class="var-chip" data-inserir-token="&#123;&#123;{{ $token }}&#125;&#125;" title="{{ $desc }}">
                                    &#123;&#123;{{ $token }}&#125;&#125;
                                </button>
                            @endforeach
                        </div>

                        <label for="corpo_html" class="form-label fw-semibold">Corpo HTML do Template (conteúdo puro, sem cabeçalho/rodapé)</label>
                        <textarea id="corpo_html" name="corpo_html" rows="15"
                            class="form-control font-monospace small @error('corpo_html') is-invalid @enderror"
                            required>{{ old('corpo_html', $template->corpo_html) }}</textarea>
                        @error('corpo_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-golden">
                            <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Template
                        </button>
                        <button type="button" id="btnForcarPreview" class="btn btn-outline-secondary">
                            <i class="fas fa-rotate" aria-hidden="true"></i> Atualizar Preview
                        </button>
                    </div>
                </div>
            </x-admin.card>
        </form>

        {{-- Restaurar Padrão + Teste via Fila Anti-Spam --}}
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <x-admin.card title="Disparar Teste (Fila Anti-Spam ou Imediato)" icon="fa-paper-plane" class="h-100">
                    <form method="POST" action="{{ route('admin.templates-email.testar', $template->chave) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-12 col-sm-7">
                            <label for="email_destino_teste" class="form-label small fw-semibold">E-mail de destino</label>
                            <input type="email" id="email_destino_teste" name="email_destino" required
                                value="{{ auth()->user()?->email }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-5">
                            <label for="modo_envio_teste" class="form-label small fw-semibold">Canal</label>
                            <select id="modo_envio_teste" name="modo_envio" class="form-select form-select-sm">
                                <option value="fila">Fila Anti-Spam (recomendado)</option>
                                <option value="imediato">Envio Imediato</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-golden w-100">
                                <i class="fas fa-envelope" aria-hidden="true"></i> Disparar E-mail de Teste
                            </button>
                        </div>
                    </form>
                </x-admin.card>
            </div>
            <div class="col-12 col-md-4">
                <x-admin.card title="Resetar" icon="fa-clock-rotate-left" class="h-100">
                    <p class="small text-secondary mb-2">Volta o conteúdo para o padrão de fábrica sem alterar o layout global.</p>
                    <form method="POST" action="{{ route('admin.templates-email.restaurar', $template->chave) }}"
                        data-confirmar="Restaurar este template ao modelo original?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="fas fa-undo" aria-hidden="true"></i> Restaurar Padrão
                        </button>
                    </form>
                </x-admin.card>
            </div>
        </div>
    </div>

    {{-- Coluna Direita: Preview Responsivo em Tempo Real Durante Digitação --}}
    <div class="col-12 col-xl-6">
        <x-admin.card title="Preview em Tempo Real (Layout + Conteúdo)" icon="fa-eye" class="sticky-top" style="top: 72px;">
            <x-slot:actions>
                <div class="btn-group btn-group-sm" role="group" aria-label="Modo do preview">
                    <button type="button" class="btn btn-golden active" data-viewport-preview="desktop">
                        <i class="fas fa-desktop" aria-hidden="true"></i> Desktop
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-viewport-preview="mobile">
                        <i class="fas fa-mobile-screen-button" aria-hidden="true"></i> Mobile (375px)
                    </button>
                </div>
            </x-slot:actions>

            <div class="d-flex justify-content-between align-items-center mb-2 small">
                <div class="text-truncate">
                    <strong class="text-secondary">Assunto:</strong>
                    <span id="previewAssuntoLive" class="fw-semibold">{{ $previewInicial['assunto'] }}</span>
                </div>
                <span id="statusPreviewLive" class="badge-status tone-green">Sincronizado</span>
            </div>

            <div class="preview-shell">
                <iframe id="iframePreviewEmail" title="Preview ao vivo do e-mail responsivo"
                    class="preview-frame" sandbox="allow-same-origin"
                    srcdoc="{{ $previewInicial['html'] }}"></iframe>
            </div>
        </x-admin.card>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formTemplateEmail');
    const iframe = document.getElementById('iframePreviewEmail');
    const assuntoOut = document.getElementById('previewAssuntoLive');
    const statusBadge = document.getElementById('statusPreviewLive');
    const textarea = document.getElementById('corpo_html');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const previewUrl = @json(route('admin.templates-email.preview', $template->chave));

    if (!form || !iframe) return;

    let timer = null;
    let controller = null;

    async function atualizarPreview() {
        if (controller) controller.abort();
        controller = new AbortController();

        statusBadge.textContent = 'Renderizando...';
        statusBadge.className = 'badge-status tone-amber';

        const fd = new FormData(form);
        fd.set('usar_layout', form.querySelector('input[name="usar_layout"]')?.checked ? '1' : '0');

        try {
            const resp = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: fd,
                signal: controller.signal,
            });
            const data = await resp.json();
            if (typeof data.html === 'string') {
                iframe.srcdoc = data.html;
            }
            if (typeof data.assunto === 'string' && assuntoOut) {
                assuntoOut.textContent = data.assunto;
            }
            statusBadge.textContent = 'Ao vivo';
            statusBadge.className = 'badge-status tone-green';
        } catch (err) {
            if (err.name !== 'AbortError') {
                statusBadge.textContent = 'Erro no preview';
                statusBadge.className = 'badge-status tone-red';
            }
        }
    }

    function agendarPreview() {
        clearTimeout(timer);
        timer = setTimeout(atualizarPreview, 180);
    }

    form.querySelectorAll('input, textarea, select').forEach((el) => {
        el.addEventListener('input', agendarPreview);
        el.addEventListener('change', atualizarPreview);
    });

    document.getElementById('btnForcarPreview')?.addEventListener('click', atualizarPreview);

    document.querySelectorAll('[data-inserir-token]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!textarea) return;
            const token = btn.getAttribute('data-inserir-token') || '';
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + token.length;
            agendarPreview();
        });
    });

    document.querySelectorAll('[data-viewport-preview]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modo = btn.getAttribute('data-viewport-preview');
            document.querySelectorAll('[data-viewport-preview]').forEach((b) => {
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
