@php
    $modoCriacao = !empty($modoCriacao);
    $categoriasLista = $categorias ?? [
        'financeiro' => 'Financeiro & Faturas',
        'loja' => 'Loja & Rastreamento',
        'portal' => 'Portal do Cliente',
        'suporte' => 'Chamados & Suporte',
        'sistema' => 'Sistema & Alertas',
    ];
    $actionForm = $modoCriacao
        ? route('admin.templates-email.store')
        : route('admin.templates-email.update', $template->chave);
@endphp
@extends('admin.layouts.app')
@section('title', $modoCriacao ? 'Novo Template de E-mail' : 'Editar Template: ' . $template->nome)
@section('page_title', $modoCriacao ? 'Criar template de e-mail · preview ao vivo' : 'Editor ao vivo · ' . $template->nome)

@section('page_actions')
    <a href="{{ route('admin.templates-email.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> Todos os Templates
    </a>
    <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-palette" aria-hidden="true"></i> Editar Layout Global
    </a>
@endsection

@section('content')
<div class="row g-3">
    {{-- Coluna Esquerda: Editor de Conteúdo (Separado do Layout) --}}
    <div class="col-12 col-xl-6">
        <form id="formTemplateEmail" method="POST" action="{{ $actionForm }}">
            @csrf
            @if(!$modoCriacao)
                @method('PUT')
            @endif

            <x-admin.card :title="$modoCriacao ? 'Novo Template de E-mail' : $template->nome" icon="fa-code"
                :descricao="$modoCriacao ? 'Rota dedicada será criada automaticamente após salvar.' : ('Rota dedicada: ' . route('admin.templates-email.edit', $template->chave, false))" class="mb-3">
                <x-slot:actions>
                    <span class="badge-status tone-gold">{{ $modoCriacao ? 'NOVO' : $template->chave }}</span>
                </x-slot:actions>

                <div class="row g-3">
                    @if($modoCriacao)
                        <x-admin.field name="chave" label="Chave única da rota (slug)" :value="old('chave', $template->chave)" col="col-12 col-md-5" :required="true"
                            hint="Apenas letras minúsculas, números, hífen ou underline." />
                        <x-admin.field name="nome" label="Nome de identificação" :value="old('nome', $template->nome)" col="col-12 col-md-7" :required="true" />
                    @else
                        <x-admin.field name="nome" label="Nome de identificação" :value="old('nome', $template->nome)" col="col-12 col-md-7" :required="true" />
                        <div class="col-12 col-md-5 d-flex flex-column justify-content-end gap-2">
                            <x-admin.switch name="usar_layout" label="Usar Layout Global Separado" :checked="$template->usar_layout" />
                            <x-admin.switch name="ativo" label="Template Ativo" :checked="$template->ativo" />
                        </div>
                    @endif

                    <div class="col-12 col-md-5">
                        <label for="categoria" class="form-label small fw-semibold">Categoria</label>
                        <select id="categoria" name="categoria" class="form-select form-select-sm @error('categoria') is-invalid @enderror">
                            @foreach($categoriasLista as $slug => $rotulo)
                                <option value="{{ $slug }}" @selected(old('categoria', $template->categoria) === $slug)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('categoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <x-admin.field name="descricao" label="Descrição interna" :value="old('descricao', $template->descricao)" col="col-12 col-md-7" :opcional="true" />

                    @if($modoCriacao)
                        <div class="col-12 d-flex flex-wrap gap-4">
                            <x-admin.switch name="usar_layout" label="Usar Layout Global Separado" :checked="$template->usar_layout" />
                            <x-admin.switch name="ativo" label="Template Ativo" :checked="$template->ativo" />
                        </div>
                    @endif

                    <x-admin.field name="assunto" label="Assunto do E-mail" :value="old('assunto', $template->assunto)" col="col-12" :required="true"
                        hint="Atualiza o preview do assunto em tempo real conforme você digita." />

                    <x-admin.field name="pre_cabecalho" label="Texto Pré-Cabeçalho (Preheader Mobile)" :value="old('pre_cabecalho', $template->pre_cabecalho)" col="col-12" :opcional="true"
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

                    <div class="col-12">
                        <details class="border rounded-3 p-2 bg-body-tertiary">
                            <summary class="small fw-semibold user-select-none" style="cursor: pointer;">
                                <i class="fas fa-sliders me-1 text-gold" aria-hidden="true"></i>
                                Dados de Teste / Variáveis no Preview (Tempo Real)
                            </summary>
                            <div class="row g-2 mt-2">
                                @php
                                    $tokensSimulacao = array_keys(is_array($template->variaveis ?? null) ? $template->variaveis : []);
                                    if ($tokensSimulacao === []) {
                                        $tokensSimulacao = ['cliente_nome', 'empresa_nome', 'fatura_numero', 'fatura_valor'];
                                    }
                                @endphp
                                @foreach($tokensSimulacao as $token)
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label mb-1 text-secondary" style="font-size: 11px;">
                                            <code>&#123;&#123;{{ $token }}&#125;&#125;</code>
                                        </label>
                                        <input type="text"
                                            name="variaveis_preview[{{ $token }}]"
                                            value="{{ $exemplos[$token] ?? '' }}"
                                            class="form-control form-control-sm">
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-golden">
                            <i class="fas fa-floppy-disk" aria-hidden="true"></i> {{ $modoCriacao ? 'Criar Template' : 'Salvar Template' }}
                        </button>
                        <button type="button" id="btnForcarPreview" class="btn btn-outline-secondary">
                            <i class="fas fa-rotate" aria-hidden="true"></i> Atualizar Preview
                        </button>
                    </div>
                </div>
            </x-admin.card>
        </form>

        @if(!$modoCriacao)
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
        @endif
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

            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 small">
                <div class="min-w-0 flex-grow-1">
                    <div class="text-truncate">
                        <strong class="text-secondary">Template:</strong>
                        <span id="previewNomeLive" class="fw-semibold">{{ $template->nome }}</span>
                    </div>
                    <div class="text-truncate">
                        <strong class="text-secondary">Assunto:</strong>
                        <span id="previewAssuntoLive" class="fw-semibold">{{ $previewInicial['assunto'] }}</span>
                    </div>
                    <div class="text-truncate">
                        <strong class="text-secondary">Pré-cabeçalho:</strong>
                        <span id="previewPreCabecalhoLive" class="text-secondary">{{ ($previewInicial['pre_cabecalho'] ?? '') !== '' ? $previewInicial['pre_cabecalho'] : '—' }}</span>
                    </div>
                </div>
                <span id="statusPreviewLive" class="badge-status tone-green flex-shrink-0">Ao vivo</span>
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
    const nomeOut = document.getElementById('previewNomeLive');
    const assuntoOut = document.getElementById('previewAssuntoLive');
    const preCabecalhoOut = document.getElementById('previewPreCabecalhoLive');
    const statusBadge = document.getElementById('statusPreviewLive');
    const textarea = document.getElementById('corpo_html');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const previewUrl = @json($modoCriacao ? route('admin.templates-email.preview-livre') : route('admin.templates-email.preview', $template->chave));

    if (!form || !iframe) return;

    let timer = null;
    let controller = null;

    async function atualizarPreview() {
        if (controller) controller.abort();
        controller = new AbortController();

        if (window.GoldenSummernote && typeof window.GoldenSummernote.syncForm === 'function') {
            window.GoldenSummernote.syncForm(form);
        } else if (textarea) {
            const editor = textarea.nextElementSibling;
            const editable = editor?.querySelector?.('.note-editable');
            if (editable && !editor.classList.contains('codeview')) {
                textarea.value = editable.innerHTML;
            }
        }

        statusBadge.textContent = 'Renderizando...';
        statusBadge.className = 'badge-status tone-amber flex-shrink-0';

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
            if (typeof data.nome === 'string' && nomeOut) {
                nomeOut.textContent = data.nome;
            }
            if (typeof data.assunto === 'string' && assuntoOut) {
                assuntoOut.textContent = data.assunto;
            }
            if (typeof data.pre_cabecalho === 'string' && preCabecalhoOut) {
                preCabecalhoOut.textContent = data.pre_cabecalho || '—';
            }
            statusBadge.textContent = 'Ao vivo';
            statusBadge.className = 'badge-status tone-green flex-shrink-0';
        } catch (err) {
            if (err.name !== 'AbortError') {
                statusBadge.textContent = 'Erro no preview';
                statusBadge.className = 'badge-status tone-red flex-shrink-0';
            }
        }
    }

    function agendarPreview() {
        clearTimeout(timer);
        timer = setTimeout(atualizarPreview, 120);
    }

    ['input', 'keyup', 'paste', 'cut', 'golden:summernote-input'].forEach((ev) => {
        form.addEventListener(ev, agendarPreview);
    });
    form.addEventListener('change', atualizarPreview);

    document.getElementById('btnForcarPreview')?.addEventListener('click', atualizarPreview);

    document.querySelectorAll('[data-inserir-token]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!textarea) return;
            const token = btn.getAttribute('data-inserir-token') || '';
            if (window.GoldenSummernote && textarea.dataset.summernoteAtivo === '1') {
                window.GoldenSummernote.inserirTexto(textarea, token);
                agendarPreview();
                return;
            }
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
