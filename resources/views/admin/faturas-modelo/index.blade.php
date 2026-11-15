@extends('admin.layouts.app')
@section('title', 'Personalização de Faturas PDF (A4)')
@section('page_title', 'Estúdio de faturas PDF · arrasta e solta A4')

@section('page_actions')
    <a href="{{ route('admin.faturas-modelo.imprimir') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-print" aria-hidden="true"></i> Impressão Cirúrgica A4
    </a>
    <a href="{{ route('admin.faturas-modelo.pdf') }}" class="btn btn-sm btn-golden">
        <i class="fas fa-file-pdf" aria-hidden="true"></i> Baixar PDF Real
    </a>
@endsection

@section('content')
<div class="abas" data-abas="faturas-modelo">
    <div class="abas-barra mb-3">
        <ul class="nav abas-nav" role="tablist" aria-label="Configuração cirúrgica de faturas PDF">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" role="tab" id="aba-montador-btn"
                    data-bs-toggle="tab" data-bs-target="#aba-montador" data-aba="montador"
                    aria-controls="aba-montador" aria-selected="true">
                    <i class="fas fa-grip-vertical" aria-hidden="true"></i><span>Montador Arrasta &amp; Solta (A4)</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-identidade-btn"
                    data-bs-toggle="tab" data-bs-target="#aba-identidade" data-aba="identidade"
                    aria-controls="aba-identidade" aria-selected="false">
                    <i class="fas fa-ruler-combined" aria-hidden="true"></i><span>Emitente, Margens &amp; Tipografia</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-whmcs-btn"
                    data-bs-toggle="tab" data-bs-target="#aba-whmcs" data-aba="whmcs"
                    aria-controls="aba-whmcs" aria-selected="false">
                    <i class="fas fa-plug" aria-hidden="true"></i><span>Gateway WHMCS &amp; Plugin</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- Aba 1: Arrasta e Solta + Folha Cirúrgica A4 --}}
        <div class="tab-pane fade show active" id="aba-montador" role="tabpanel" aria-labelledby="aba-montador-btn">
            <div class="row g-3">
                <div class="col-12 col-xl-5">
                    <x-admin.card title="Blocos da Fatura A4 (Arraste para Reordenar)" icon="fa-up-down-left-right"
                        descricao="A ordem, largura (50% lado a lado ou 100% linha inteira), cores e visibilidade são aplicadas 1:1 na impressão A4 e no arquivo PDF.">
                        <x-slot:actions>
                            <span id="statusDndSync" class="badge-status tone-green">Sincronizado</span>
                        </x-slot:actions>

                        <div id="listaBlocosDnd" class="mb-3"></div>

                        <form id="formSalvarDnd" method="POST" action="{{ route('admin.faturas-modelo.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="modelo_json" id="inputModeloJson">

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-golden flex-grow-1">
                                    <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Montagem Cirúrgica
                                </button>
                                <button type="button" id="btnPreviewManualDnd" class="btn btn-outline-secondary" title="Atualizar preview agora">
                                    <i class="fas fa-rotate" aria-hidden="true"></i>
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.faturas-modelo.restaurar') }}" class="mt-2"
                            data-confirmar="Restaurar a ordem e as dimensões originais de fábrica dos blocos da fatura?">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                <i class="fas fa-undo" aria-hidden="true"></i> Restaurar Montagem Padrão
                            </button>
                        </form>
                    </x-admin.card>
                </div>

                <div class="col-12 col-xl-7">
                    <x-admin.card title="Espelho Cirúrgico A4 (210mm × 297mm)" icon="fa-file-invoice"
                        descricao="Mesmo HTML e geometria usados na impressão direta e no PDF gerado em anexo.">
                        <x-slot:actions>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Zoom A4">
                                <button type="button" class="btn btn-outline-secondary" data-a4-zoom="0.52">52%</button>
                                <button type="button" class="btn btn-golden active" data-a4-zoom="0.68">68%</button>
                                <button type="button" class="btn btn-outline-secondary" data-a4-zoom="0.88">88%</button>
                            </div>
                        </x-slot:actions>

                        <div class="a4-preview-stage">
                            <div id="a4ZoomWrap" class="a4-iframe-wrap" style="transform: scale(0.68); margin-bottom: -360px;">
                                <iframe id="iframePreviewA4" class="a4-iframe" title="Folha A4 cirúrgica"
                                    sandbox="allow-same-origin" srcdoc="{{ $htmlFolhaPreview }}"></iframe>
                            </div>
                        </div>
                    </x-admin.card>
                </div>
            </div>
        </div>

        {{-- Aba 2: Dados do Emitente, Margens Cirúrgicas e Paleta --}}
        <div class="tab-pane fade" id="aba-identidade" role="tabpanel" aria-labelledby="aba-identidade-btn">
            <form method="POST" action="{{ route('admin.faturas-modelo.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="gateway_padrao_modulo" value="{{ $estilo['gateway_padrao_modulo'] ?? '' }}">
                <input type="hidden" name="pix_chave_fallback" value="{{ $estilo['pix_chave_fallback'] ?? '' }}">

                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <x-admin.card title="Dados do Emitente na Fatura PDF" icon="fa-building" class="h-100">
                            <div class="row g-3">
                                <x-admin.field name="emitente_nome" label="Razão Social / Marca Emitente" :value="$estilo['emitente_nome']" col="col-12 col-md-7" :required="true" />
                                <x-admin.field name="emitente_documento" label="CNPJ / CPF" :value="$estilo['emitente_documento']" col="col-12 col-md-5" :opcional="true" />
                                <x-admin.field name="emitente_endereco" label="Endereço Completo" :value="$estilo['emitente_endereco']" col="col-12" :opcional="true" />
                                <x-admin.field name="emitente_contato" label="Contato Financeiro (WhatsApp & E-mail)" :value="$estilo['emitente_contato']" col="col-12" :opcional="true" />
                                <x-admin.field name="logo_url" label="URL da Logomarca na Fatura" :value="$estilo['logo_url']" col="col-12" :opcional="true"
                                    placeholder="https://goldenrastreamento.com.br/img/logo.png" />
                                <x-admin.field name="rodape_texto" label="Texto Fixo do Rodapé A4" :value="$estilo['rodape_texto']" col="col-12" :opcional="true" />
                            </div>
                        </x-admin.card>
                    </div>

                    <div class="col-12 col-lg-5">
                        <x-admin.card title="Geometria Cirúrgica (mm / pt) & Cores" icon="fa-ruler" class="h-100">
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label small fw-semibold">Margem Topo (mm)</label>
                                    <input type="number" name="margem_topo_mm" min="6" max="25" value="{{ $estilo['margem_topo_mm'] }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-semibold">Lateral (mm)</label>
                                    <input type="number" name="margem_lateral_mm" min="6" max="25" value="{{ $estilo['margem_lateral_mm'] }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-semibold">Inferior (mm)</label>
                                    <input type="number" name="margem_inferior_mm" min="6" max="25" value="{{ $estilo['margem_inferior_mm'] }}" class="form-control form-control-sm">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Fonte Base (pt)</label>
                                    <input type="number" step="0.5" name="tamanho_fonte_pt" min="8" max="12" value="{{ $estilo['tamanho_fonte_pt'] }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Raio Borda (px)</label>
                                    <input type="number" name="raio_borda_px" min="0" max="14" value="{{ $estilo['raio_borda_px'] }}" class="form-control form-control-sm">
                                </div>

                                @foreach(['cor_primaria' => 'Primária', 'cor_destaque' => 'Destaque Gold', 'cor_fundo_bloco' => 'Fundo Cards', 'cor_borda' => 'Bordas', 'cor_texto' => 'Texto'] as $campo => $rotulo)
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">{{ $rotulo }}</label>
                                        <input type="color" name="{{ $campo }}" value="{{ $estilo[$campo] }}" class="form-control form-control-color w-100" style="height: 36px;">
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 pt-3 border-top">
                                <button type="submit" class="btn btn-golden w-100">
                                    <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Identidade e Margens A4
                                </button>
                            </div>
                        </x-admin.card>
                    </div>
                </div>
            </form>
        </div>

        {{-- Aba 3: Gateway de Pagamento do WHMCS & Plugin Instalável --}}
        <div class="tab-pane fade" id="aba-whmcs" role="tabpanel" aria-labelledby="aba-whmcs-btn">
            <div class="row g-3">
                <div class="col-12 col-xl-7">
                    <x-admin.card title="Plugin Golden para WHMCS (.zip Instalável)" icon="fa-download"
                        descricao="Expõe todos os gateways locais, PIX Copia e Cola, linha digitável e webhooks automáticos do seu WHMCS para a fila anti-spam." class="mb-3">
                        <div class="callout mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong>Status no WHMCS conectado:</strong>
                                    @if($statusWhmcs['plugin_instalado'])
                                        <x-admin.status rotulo="Plugin Ativo v{{ $statusWhmcs['versao_plugin'] }}" tom="green" />
                                    @elseif($statusWhmcs['conectado'])
                                        <x-admin.status rotulo="API Conectada · Plugin Pendente" tom="amber" />
                                    @else
                                        <x-admin.status rotulo="WHMCS Não Conectado" tom="red" />
                                    @endif
                                </div>
                                <div class="small text-secondary">{{ $statusWhmcs['mensagem'] }}</div>
                            </div>
                            <a href="{{ route('admin.faturas-modelo.whmcs-plugin') }}" class="btn btn-golden">
                                <i class="fas fa-file-zipper" aria-hidden="true"></i> Baixar Plugin WHMCS (.zip)
                            </a>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-7">
                                <label class="form-label small fw-semibold">URL do Webhook Pré-configurada no Plugin</label>
                                <input type="text" readonly value="{{ $webhookUrl }}" class="form-control form-control-sm font-monospace">
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-semibold">Chave Secreta HMAC</label>
                                <input type="text" readonly value="{{ $segredoWebhook }}" class="form-control form-control-sm font-monospace">
                            </div>
                        </div>

                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">
                            Gateways Lidos do WHMCS ({{ count($gatewaysWhmcs) }})
                        </h6>
                        <x-admin.table>
                            <x-slot:head>
                                <tr>
                                    <th>Módulo WHMCS</th>
                                    <th>Nome de Exibição</th>
                                    <th class="text-end">Tipo Identificado</th>
                                </tr>
                            </x-slot:head>
                            @forelse($gatewaysWhmcs as $gw)
                                <tr>
                                    <td><code class="small text-gold">{{ $gw['modulo'] }}</code></td>
                                    <td class="small fw-semibold">{{ $gw['nome'] }}</td>
                                    <td class="text-end">
                                        <span class="badge-status tone-teal text-uppercase">{{ $gw['tipo'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-secondary small py-3">
                                        Configure as credenciais do WHMCS em Sistema → Configurações para listar os gateways em tempo real.
                                    </td>
                                </tr>
                            @endforelse
                        </x-admin.table>
                    </x-admin.card>
                </div>

                <div class="col-12 col-xl-5">
                    <x-admin.card title="Preferência de Gateway & Chave PIX Fallback" icon="fa-qrcode" class="mb-3">
                        <form method="POST" action="{{ route('admin.faturas-modelo.update') }}" class="row g-3">
                            @csrf
                            @method('PUT')
                            @foreach(['emitente_nome', 'emitente_documento', 'emitente_endereco', 'emitente_contato', 'logo_url', 'cor_primaria', 'cor_destaque', 'cor_fundo_bloco', 'cor_borda', 'cor_texto', 'margem_topo_mm', 'margem_lateral_mm', 'margem_inferior_mm', 'tamanho_fonte_pt', 'raio_borda_px', 'rodape_texto'] as $hk)
                                <input type="hidden" name="{{ $hk }}" value="{{ $estilo[$hk] ?? '' }}">
                            @endforeach

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Gateway Preferencial WHMCS</label>
                                <select name="gateway_padrao_modulo" class="form-select form-select-sm">
                                    <option value="">Usar automaticamente o gateway de cada fatura WHMCS</option>
                                    @foreach($gatewaysWhmcs as $gw)
                                        <option value="{{ $gw['modulo'] }}" @selected(($estilo['gateway_padrao_modulo'] ?? '') === $gw['modulo'])>
                                            {{ $gw['nome'] }} ({{ $gw['modulo'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <x-admin.field name="pix_chave_fallback" label="Chave / Código PIX Fallback (opcional)"
                                :value="$estilo['pix_chave_fallback'] ?? ''" col="col-12" :opcional="true"
                                hint="Usado apenas se a fatura no WHMCS ainda não possuir QR/EMV próprio do gateway." />

                            <div class="col-12">
                                <button type="submit" class="btn btn-golden w-100">
                                    <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Integração Gateway
                                </button>
                            </div>
                        </form>
                    </x-admin.card>

                    <x-admin.card title="Disparar Fatura PDF pela Fila Anti-Spam" icon="fa-paper-plane">
                        <form method="POST" action="{{ route('admin.faturas-modelo.enviar-teste') }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small fw-semibold">E-mail do destinatário</label>
                                <input type="email" name="email_destino" required value="{{ auth()->user()?->email }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">ID da Fatura no WHMCS (opcional)</label>
                                <input type="number" name="whmcs_invoice_id" min="1" placeholder="Vazio = usa dados de demonstração" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 mt-2">
                                <button type="submit" class="btn btn-sm btn-golden w-100">
                                    <i class="fas fa-envelope-circle-check" aria-hidden="true"></i> Gerar PDF &amp; Enfileirar Envio
                                </button>
                            </div>
                        </form>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const listaEl = document.getElementById('listaBlocosDnd');
    const iframe = document.getElementById('iframePreviewA4');
    const inputJson = document.getElementById('inputModeloJson');
    const statusSync = document.getElementById('statusDndSync');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const previewUrl = @json(route('admin.faturas-modelo.preview'));

    if (!listaEl || !iframe || !inputJson) return;

    let modelo = @json($modelo);
    let dragIndex = null;
    let previewTimer = null;

    function sincronizarJson() {
        modelo.blocos.forEach((b, idx) => { b.ordem = idx + 1; });
        inputJson.value = JSON.stringify(modelo);
    }

    async function atualizarPreviewA4() {
        sincronizarJson();
        statusSync.textContent = 'Atualizando folha A4...';
        statusSync.className = 'badge-status tone-amber';

        try {
            const resp = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ modelo_json: JSON.stringify(modelo) }),
            });
            const data = await resp.json();
            if (typeof data.html === 'string') {
                iframe.srcdoc = data.html;
            }
            statusSync.textContent = 'Cirúrgico A4';
            statusSync.className = 'badge-status tone-green';
        } catch {
            statusSync.textContent = 'Erro preview';
            statusSync.className = 'badge-status tone-red';
        }
    }

    function agendarPreview() {
        sincronizarJson();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(atualizarPreviewA4, 160);
    }

    function moverBloco(de, para) {
        if (de === para || de < 0 || para < 0 || de >= modelo.blocos.length || para >= modelo.blocos.length) return;
        const [item] = modelo.blocos.splice(de, 1);
        modelo.blocos.splice(para, 0, item);
        renderLista();
        agendarPreview();
    }

    function renderLista() {
        sincronizarJson();
        listaEl.innerHTML = '';

        modelo.blocos.forEach((bloco, index) => {
            const card = document.createElement('div');
            card.className = `bloco-dnd-item ${bloco.visivel ? '' : 'is-hidden-block'}`;
            card.setAttribute('draggable', 'true');
            card.dataset.index = String(index);

            card.innerHTML = `
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-start gap-2 flex-grow-1">
                        <span class="drag-handle" title="Arraste para reordenar"><i class="fas fa-grip-vertical"></i></span>
                        <div>
                            <div class="fw-semibold small text-body">${index + 1}. ${bloco.nome}</div>
                            <div class="text-secondary" style="font-size:11px;">${bloco.descricao || ''}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <button type="button" class="btn btn-sm ${Number(bloco.largura) === 50 ? 'btn-golden' : 'btn-outline-secondary'}"
                            data-acao="largura" title="Alternar 50% (duas colunas) / 100% (largura inteira)">
                            ${Number(bloco.largura) === 50 ? '50%' : '100%'}
                        </button>
                        <button type="button" class="btn btn-sm ${bloco.visivel ? 'btn-outline-secondary' : 'btn-outline-danger'}"
                            data-acao="visivel" title="Mostrar ou ocultar este bloco">
                            <i class="fas ${bloco.visivel ? 'fa-eye' : 'fa-eye-slash'}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-acao="subir" title="Mover acima">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-acao="descer" title="Mover abaixo">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-2 pt-2 border-top border-secondary-subtle align-items-center">
                    <div class="col-3">
                        <label class="form-label mb-0 text-secondary" style="font-size:10px;">Fundo</label>
                        <input type="color" class="form-control form-control-color w-100" style="height:26px;" data-prop="fundo" value="${bloco.fundo}">
                    </div>
                    <div class="col-3">
                        <label class="form-label mb-0 text-secondary" style="font-size:10px;">Texto</label>
                        <input type="color" class="form-control form-control-color w-100" style="height:26px;" data-prop="texto_cor" value="${bloco.texto_cor}">
                    </div>
                    <div class="col-3">
                        <label class="form-label mb-0 text-secondary" style="font-size:10px;">Espaço</label>
                        <select class="form-select form-select-sm py-0" style="font-size:11px;height:26px;" data-prop="padding">
                            <option value="compacto" ${bloco.padding === 'compacto' ? 'selected' : ''}>Compacto</option>
                            <option value="padrao" ${bloco.padding === 'padrao' ? 'selected' : ''}>Padrão</option>
                            <option value="amplo" ${bloco.padding === 'amplo' ? 'selected' : ''}>Amplo</option>
                        </select>
                    </div>
                    <div class="col-3 text-end">
                        <label class="form-check form-switch d-inline-flex align-items-center gap-1 mb-0 small">
                            <input class="form-check-input m-0" type="checkbox" data-prop="borda" ${bloco.borda ? 'checked' : ''}>
                            <span style="font-size:11px;">Borda</span>
                        </label>
                    </div>
                </div>
            `;

            card.addEventListener('dragstart', () => {
                dragIndex = index;
                card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', () => {
                dragIndex = null;
                card.classList.remove('is-dragging');
                listaEl.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
            });
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                card.classList.add('is-drop-target');
            });
            card.addEventListener('dragleave', () => {
                card.classList.remove('is-drop-target');
            });
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('is-drop-target');
                if (dragIndex !== null && dragIndex !== index) {
                    moverBloco(dragIndex, index);
                }
            });

            card.querySelector('[data-acao="largura"]').addEventListener('click', () => {
                bloco.largura = Number(bloco.largura) === 50 ? 100 : 50;
                renderLista();
                agendarPreview();
            });
            card.querySelector('[data-acao="visivel"]').addEventListener('click', () => {
                bloco.visivel = !bloco.visivel;
                renderLista();
                agendarPreview();
            });
            card.querySelector('[data-acao="subir"]').addEventListener('click', () => moverBloco(index, index - 1));
            card.querySelector('[data-acao="descer"]').addEventListener('click', () => moverBloco(index, index + 1));

            card.querySelectorAll('[data-prop]').forEach((el) => {
                const prop = el.getAttribute('data-prop');
                el.addEventListener('input', () => {
                    bloco[prop] = el.type === 'checkbox' ? el.checked : el.value;
                    agendarPreview();
                });
                el.addEventListener('change', () => {
                    bloco[prop] = el.type === 'checkbox' ? el.checked : el.value;
                    agendarPreview();
                });
            });

            listaEl.appendChild(card);
        });
    }

    document.getElementById('btnPreviewManualDnd')?.addEventListener('click', atualizarPreviewA4);

    document.querySelectorAll('[data-a4-zoom]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const zoom = parseFloat(btn.getAttribute('data-a4-zoom') || '0.68');
            const wrap = document.getElementById('a4ZoomWrap');
            document.querySelectorAll('[data-a4-zoom]').forEach((b) => {
                b.classList.remove('btn-golden', 'active');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-golden', 'active');
            if (wrap) {
                wrap.style.transform = `scale(${zoom})`;
                wrap.style.marginBottom = `${Math.round((zoom - 1) * 1122)}px`;
            }
        });
    });

    renderLista();
})();
</script>
@endsection
