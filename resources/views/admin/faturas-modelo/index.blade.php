@extends('admin.layouts.app')
@section('title', 'Personalização de Faturas PDF (A4)')
@section('page_title', 'Estúdio de faturas PDF · arrasta e solta A4')

@section('page_actions')
    <a href="{{ route('admin.faturas-modelo.imprimir', ['rascunho' => 1]) }}" id="btnImprimirA4Top" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-print" aria-hidden="true"></i> Impressão Cirúrgica A4
    </a>
    <a href="{{ route('admin.faturas-modelo.pdf', ['rascunho' => 1]) }}" id="btnBaixarPdfTop" class="btn btn-sm btn-golden">
        <i class="fas fa-file-pdf" aria-hidden="true"></i> Baixar PDF Real
    </a>
@endsection

@section('content')
<div class="abas row g-3 align-items-start" data-abas="faturas-modelo">
    <div class="col-12 col-xl-5">
        <div class="abas-barra mb-3">
            <ul class="nav abas-nav" role="tablist" aria-label="Configuração cirúrgica de faturas PDF">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" role="tab" id="aba-montador-btn"
                        data-bs-toggle="tab" data-bs-target="#aba-montador" data-aba="montador"
                        aria-controls="aba-montador" aria-selected="true">
                        <i class="fas fa-grip-vertical" aria-hidden="true"></i><span>Montador (A4)</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-identidade-btn"
                        data-bs-toggle="tab" data-bs-target="#aba-identidade" data-aba="identidade"
                        aria-controls="aba-identidade" aria-selected="false">
                        <i class="fas fa-ruler-combined" aria-hidden="true"></i><span>Emitente &amp; Layout</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-dados-fatura-btn"
                        data-bs-toggle="tab" data-bs-target="#aba-dados-fatura" data-aba="dados-fatura"
                        aria-controls="aba-dados-fatura" aria-selected="false">
                        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i><span>Dados da Fatura</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-whmcs-btn"
                        data-bs-toggle="tab" data-bs-target="#aba-whmcs" data-aba="whmcs"
                        aria-controls="aba-whmcs" aria-selected="false">
                        <i class="fas fa-plug" aria-hidden="true"></i><span>WHMCS &amp; Gateway</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            {{-- Aba 1: Arrasta e Solta --}}
            <div class="tab-pane fade show active" id="aba-montador" role="tabpanel" aria-labelledby="aba-montador-btn">
                <x-admin.card title="Blocos da Fatura A4 (Arraste para Reordenar)" icon="fa-up-down-left-right"
                    descricao="A ordem, largura (50% lado a lado ou 100% linha inteira), cores e visibilidade são aplicadas 1:1 no espelho A4 ao lado e no PDF.">
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

            {{-- Aba 2: Dados do Emitente, Margens Cirúrgicas e Paleta --}}
            <div class="tab-pane fade" id="aba-identidade" role="tabpanel" aria-labelledby="aba-identidade-btn">
                <form id="formIdentidadeFatura" method="POST" action="{{ route('admin.faturas-modelo.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="gateway_padrao_modulo" value="{{ $estilo['gateway_padrao_modulo'] ?? '' }}" data-estilo-mirror="gateway_padrao_modulo">
                    <input type="hidden" name="pix_chave_fallback" value="{{ $estilo['pix_chave_fallback'] ?? '' }}" data-estilo-mirror="pix_chave_fallback">

                    <x-admin.card title="Dados do Emitente na Fatura PDF" icon="fa-building" class="mb-3"
                        descricao="Cada letra digitada reflete em tempo real no espelho A4 ao lado.">
                        <div class="row g-3">
                            <x-admin.field name="emitente_nome" label="Razão Social / Marca Emitente" :value="$estilo['emitente_nome']" col="col-12" :required="true" />
                            <x-admin.field name="emitente_documento" label="CNPJ / CPF" :value="$estilo['emitente_documento']" col="col-12" :opcional="true" />
                            <x-admin.field name="emitente_endereco" label="Endereço Completo" :value="$estilo['emitente_endereco']" col="col-12" :opcional="true" />
                            <x-admin.field name="emitente_contato" label="Contato Financeiro (WhatsApp & E-mail)" :value="$estilo['emitente_contato']" col="col-12" :opcional="true" />
                            <x-admin.field name="logo_url" label="URL da Logomarca na Fatura" :value="$estilo['logo_url']" col="col-12" :opcional="true"
                                placeholder="https://goldenrastreamento.com.br/img/logo.png" />
                            <x-admin.field name="rodape_texto" label="Texto Fixo do Rodapé A4" :value="$estilo['rodape_texto']" col="col-12" :opcional="true" />
                        </div>
                    </x-admin.card>

                    <x-admin.card title="Geometria Cirúrgica (mm / pt) & Cores" icon="fa-ruler">
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
                </form>
            </div>

            {{-- Aba 3: Dados da Fatura · Edição / Criação em Tempo Real --}}
            <div class="tab-pane fade" id="aba-dados-fatura" role="tabpanel" aria-labelledby="aba-dados-fatura-btn">
                <x-admin.card title="Dados da Fatura · Edição / Criação" icon="fa-file-invoice-dollar"
                    descricao="Edite qualquer campo ou crie uma fatura do zero e acompanhe a renderização em tempo real no arquivo A4 ao lado e no PDF.">
                    <x-slot:actions>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" id="btnLimparCriarFatura" class="btn btn-sm btn-outline-secondary" title="Iniciar uma fatura em branco">
                                <i class="fas fa-file-circle-plus" aria-hidden="true"></i> Nova
                            </button>
                            <button type="button" id="btnResetarDemoFatura" class="btn btn-sm btn-outline-secondary" title="Restaurar dados de demonstração">
                                <i class="fas fa-rotate-left" aria-hidden="true"></i> Demo
                            </button>
                        </div>
                    </x-slot:actions>

                    <div id="painelDadosFaturaLive">
                        <div class="d-flex align-items-center justify-content-between rounded border px-2 py-1 mb-3 small">
                            <span class="text-secondary">Resumo calculado ao vivo:</span>
                            <strong id="faturaDadosResumoLive" class="text-gold"></strong>
                        </div>

                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Identificação, Status &amp; Valores</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Número da Fatura</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-campo="numero" value="{{ $faturaDemo['numero'] }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Status</label>
                                <select class="form-select form-select-sm" data-fatura-campo="status">
                                    <option value="Unpaid" @selected($faturaDemo['status'] === 'Unpaid')>Em aberto</option>
                                    <option value="Paid" @selected($faturaDemo['status'] === 'Paid')>Paga</option>
                                    <option value="Overdue" @selected($faturaDemo['status'] === 'Overdue')>Vencida</option>
                                    <option value="Cancelled" @selected($faturaDemo['status'] === 'Cancelled')>Cancelada</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Emissão</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-campo="emitida" value="{{ $faturaDemo['emitida'] }}" placeholder="dd/mm/aaaa">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Vencimento</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-campo="vencimento" value="{{ $faturaDemo['vencimento'] }}" placeholder="dd/mm/aaaa">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Desconto / Crédito (R$)</label>
                                <input type="text" class="form-control form-control-sm" id="faturaCreditoBrl" data-fatura-brl="credito_cents"
                                    value="{{ number_format(($faturaDemo['credito_cents'] ?? 0) / 100, 2, ',', '') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Imposto / Taxa (R$)</label>
                                <input type="text" class="form-control form-control-sm" id="faturaImpostoBrl" data-fatura-brl="imposto_cents"
                                    value="{{ number_format(($faturaDemo['imposto_cents'] ?? 0) / 100, 2, ',', '') }}">
                            </div>
                        </div>

                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Cliente / Sacado</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Nome Completo / Contato</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="nome" value="{{ $faturaDemo['cliente']['nome'] }}">
                            </div>
                            <div class="col-7">
                                <label class="form-label small fw-semibold mb-1">Empresa</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="empresa" value="{{ $faturaDemo['cliente']['empresa'] }}">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">CPF / CNPJ</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="documento" value="{{ $faturaDemo['cliente']['documento'] }}">
                            </div>
                            <div class="col-7">
                                <label class="form-label small fw-semibold mb-1">E-mail</label>
                                <input type="email" class="form-control form-control-sm" data-fatura-cliente="email" value="{{ $faturaDemo['cliente']['email'] }}">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">Telefone</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="telefone" value="{{ $faturaDemo['cliente']['telefone'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Endereço</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="endereco" value="{{ $faturaDemo['cliente']['endereco'] }}">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">Cidade</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="cidade" value="{{ $faturaDemo['cliente']['cidade'] }}">
                            </div>
                            <div class="col-2">
                                <label class="form-label small fw-semibold mb-1">UF</label>
                                <input type="text" maxlength="2" class="form-control form-control-sm text-uppercase" data-fatura-cliente="uf" value="{{ $faturaDemo['cliente']['uf'] }}">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">CEP</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-cliente="cep" value="{{ $faturaDemo['cliente']['cep'] }}">
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold small text-uppercase text-secondary mb-0">Itens / Serviços da Fatura</h6>
                            <button type="button" id="btnAdicionarItemFatura" class="btn btn-sm btn-golden">
                                <i class="fas fa-plus" aria-hidden="true"></i> Adicionar Item
                            </button>
                        </div>
                        <div id="listaItensFaturaLive" class="d-flex flex-column gap-2 mb-3"></div>

                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Pagamento, PIX, Boleto &amp; Observações</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Forma / Gateway Exibido</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-campo="metodo_rotulo" value="{{ $faturaDemo['metodo_rotulo'] }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Nosso Número</label>
                                <input type="text" class="form-control form-control-sm font-monospace" data-fatura-campo="nosso_numero" value="{{ $faturaDemo['nosso_numero'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Link de Pagamento / Portal (Checkout URL)</label>
                                <input type="text" class="form-control form-control-sm" data-fatura-campo="checkout_url" value="{{ $faturaDemo['checkout_url'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">PIX Copia e Cola (EMV)</label>
                                <input type="text" class="form-control form-control-sm font-monospace" data-fatura-campo="pix_copia_cola" value="{{ $faturaDemo['pix_copia_cola'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Linha Digitável do Boleto</label>
                                <input type="text" class="form-control form-control-sm font-monospace" data-fatura-campo="linha_digitavel" value="{{ $faturaDemo['linha_digitavel'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Observações e Instruções Legais (HTML / Texto)</label>
                                <textarea id="faturaObservacoesLive" name="fatura_observacoes_live" class="form-control form-control-sm" rows="4"
                                    data-fatura-campo="observacoes" data-summernote-altura="140">{{ $faturaDemo['observacoes'] }}</textarea>
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            {{-- Aba 4: Gateway de Pagamento do WHMCS & Plugin Instalável --}}
            <div class="tab-pane fade" id="aba-whmcs" role="tabpanel" aria-labelledby="aba-whmcs-btn">
                <x-admin.card title="Preferência de Gateway & Chave PIX Fallback" icon="fa-qrcode" class="mb-3"
                    descricao="Alterações aqui refletem imediatamente no espelho A4 ao lado.">
                    <form id="formWhmcsFatura" method="POST" action="{{ route('admin.faturas-modelo.update') }}" class="row g-3">
                        @csrf
                        @method('PUT')
                        @foreach(['emitente_nome', 'emitente_documento', 'emitente_endereco', 'emitente_contato', 'logo_url', 'cor_primaria', 'cor_destaque', 'cor_fundo_bloco', 'cor_borda', 'cor_texto', 'margem_topo_mm', 'margem_lateral_mm', 'margem_inferior_mm', 'tamanho_fonte_pt', 'raio_borda_px', 'rodape_texto'] as $hk)
                            <input type="hidden" name="{{ $hk }}" value="{{ $estilo[$hk] ?? '' }}" data-estilo-mirror="{{ $hk }}">
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
                            hint="Usado quando a fatura no WHMCS ou o rascunho não possuir QR/EMV próprio." />

                        <div class="col-12">
                            <button type="submit" class="btn btn-golden w-100">
                                <i class="fas fa-floppy-disk" aria-hidden="true"></i> Salvar Integração Gateway
                            </button>
                        </div>
                    </form>
                </x-admin.card>

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

                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">URL do Webhook Pré-configurada no Plugin</label>
                            <input type="text" readonly value="{{ $webhookUrl }}" class="form-control form-control-sm font-monospace">
                        </div>
                        <div class="col-12">
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
                                <th class="text-end">Tipo</th>
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

                <x-admin.card title="Disparar Fatura PDF pela Fila Anti-Spam" icon="fa-paper-plane">
                    <form method="POST" action="{{ route('admin.faturas-modelo.enviar-teste') }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label small fw-semibold">E-mail do destinatário</label>
                            <input type="email" name="email_destino" required value="{{ auth()->user()?->email }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">ID da Fatura no WHMCS (opcional · pré-visualiza ao digitar)</label>
                            <input type="number" id="inputWhmcsInvoicePreview" name="whmcs_invoice_id" min="1"
                                placeholder="Vazio = usa dados de demonstração ou aba Dados da Fatura" class="form-control form-control-sm">
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

    {{-- Coluna Persistente: Espelho Cirúrgico A4 sempre visível em todas as abas --}}
    <div class="col-12 col-xl-7">
        <div class="sticky-top" style="top: 72px; z-index: 2;">
            <x-admin.card title="Espelho Cirúrgico A4 (210mm × 297mm)" icon="fa-file-invoice"
                descricao="Mesmo HTML e geometria usados na impressão direta e no PDF gerado com o rascunho ao vivo.">
                <x-slot:actions>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span id="espelhoFaturaResumo" class="badge-status tone-teal"></span>
                        <span id="statusPreviewTempoReal" class="badge-status tone-green">Tempo real</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Zoom A4">
                            <button type="button" class="btn btn-outline-secondary" data-a4-zoom="0.52">52%</button>
                            <button type="button" class="btn btn-golden active" data-a4-zoom="0.68">68%</button>
                            <button type="button" class="btn btn-outline-secondary" data-a4-zoom="0.88">88%</button>
                        </div>
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

<script>
(() => {
    const listaEl = document.getElementById('listaBlocosDnd');
    const iframe = document.getElementById('iframePreviewA4');
    const inputJson = document.getElementById('inputModeloJson');
    const statusSync = document.getElementById('statusDndSync');
    const statusLive = document.getElementById('statusPreviewTempoReal');
    const espelhoResumo = document.getElementById('espelhoFaturaResumo');
    const resumoFaturaDadosEl = document.getElementById('faturaDadosResumoLive');
    const listaItensEl = document.getElementById('listaItensFaturaLive');
    const formIdentidade = document.getElementById('formIdentidadeFatura');
    const formWhmcs = document.getElementById('formWhmcsFatura');
    const painelDadosFatura = document.getElementById('painelDadosFaturaLive');
    const inputWhmcsInvoice = document.getElementById('inputWhmcsInvoicePreview');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const previewUrl = @json(route('admin.faturas-modelo.preview'));

    if (!listaEl || !iframe || !inputJson) {
        return;
    }

    const clonar = (obj) => JSON.parse(JSON.stringify(obj));

    let modelo = @json($modelo);
    const faturaDemoPadrao = @json($faturaDemo);
    let faturaDados = clonar(faturaDemoPadrao);
    let faturaEditadaManual = false;
    let dragIndex = null;
    let previewTimer = null;
    let previewAbort = null;

    function atualizarIndicadoresStatus(texto, classeTom) {
        if (statusSync) {
            statusSync.textContent = texto;
            statusSync.className = `badge-status ${classeTom}`;
        }
        if (statusLive) {
            statusLive.textContent = texto;
            statusLive.className = `badge-status ${classeTom}`;
        }
    }

    function formatarBrl(cents) {
        const reais = (Number(cents || 0) / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        return `R$ ${reais}`;
    }

    function centsParaInput(cents) {
        return (Number(cents || 0) / 100).toFixed(2).replace('.', ',');
    }

    function inputParaCents(valor) {
        const texto = String(valor || '').trim();
        if (!texto) {
            return 0;
        }
        const normalizado = texto.includes(',')
            ? texto.replace(/\./g, '').replace(',', '.')
            : texto;
        const numero = parseFloat(normalizado.replace(/[^\d.-]/g, ''));

        return Number.isFinite(numero) && numero > 0 ? Math.round(numero * 100) : 0;
    }

    function definirValorTextareaComEditor(el, valor) {
        if (!el) {
            return;
        }
        el.value = valor || '';
        if (window.jQuery && window.jQuery(el).data('goldenSummernoteIniciado')) {
            window.jQuery(el).summernote('code', el.value);
        }
    }

    function sincronizarJson() {
        modelo.blocos.forEach((b, idx) => { b.ordem = idx + 1; });
        inputJson.value = JSON.stringify(modelo);
    }

    function sincronizarEspelhosEstilo() {
        if (!modelo.estilo || typeof modelo.estilo !== 'object') {
            return;
        }
        document.querySelectorAll('[data-estilo-mirror]').forEach((hiddenEl) => {
            const chave = hiddenEl.getAttribute('data-estilo-mirror');
            if (chave && Object.prototype.hasOwnProperty.call(modelo.estilo, chave)) {
                hiddenEl.value = modelo.estilo[chave] ?? '';
            }
        });
    }

    function coletarEstiloDeFormulario(formEl) {
        if (!formEl) {
            return;
        }
        formEl.querySelectorAll('input[name], select[name], textarea[name]').forEach((campoEl) => {
            if (campoEl.type === 'hidden') {
                return;
            }
            const nome = campoEl.name;
            if (!nome || nome === '_token' || nome === '_method') {
                return;
            }
            if (!Object.prototype.hasOwnProperty.call(modelo.estilo, nome)) {
                return;
            }
            modelo.estilo[nome] = campoEl.value;
            if (nome === 'gateway_padrao_modulo' && campoEl.tagName === 'SELECT') {
                const opcaoSelecionada = campoEl.options[campoEl.selectedIndex];
                modelo.estilo.gateway_padrao_rotulo = campoEl.value && opcaoSelecionada
                    ? opcaoSelecionada.text.trim()
                    : '';
            }
        });
    }

    function recalcularTotaisFaturaDados() {
        const itens = Array.isArray(faturaDados.itens) ? faturaDados.itens : [];
        let subtotal = 0;

        itens.forEach((item) => {
            const qtd = Math.max(1, parseInt(item.quantidade, 10) || 1);
            const unit = Math.max(0, parseInt(item.valor_unitario_cents, 10) || 0);
            item.quantidade = qtd;
            item.valor_unitario_cents = unit;
            item.valor_cents = qtd * unit;
            subtotal += item.valor_cents;
        });

        const credito = Math.max(0, parseInt(faturaDados.credito_cents, 10) || 0);
        const imposto = Math.max(0, parseInt(faturaDados.imposto_cents, 10) || 0);
        faturaDados.subtotal_cents = subtotal;
        faturaDados.credito_cents = credito;
        faturaDados.imposto_cents = imposto;
        faturaDados.total_cents = Math.max(0, subtotal - credito + imposto);
        faturaDados.saldo_cents = faturaDados.status === 'Paid' ? 0 : faturaDados.total_cents;

        if (resumoFaturaDadosEl) {
            resumoFaturaDadosEl.textContent = `Subtotal ${formatarBrl(subtotal)} · Total ${formatarBrl(faturaDados.total_cents)}`;
        }
        if (espelhoResumo) {
            espelhoResumo.textContent = `Fatura #${faturaDados.numero || 'MODELO'} · ${formatarBrl(faturaDados.total_cents)}`;
        }
    }

    function preencherInputsFaturaDados() {
        if (!painelDadosFatura) {
            return;
        }
        painelDadosFatura.querySelectorAll('[data-fatura-campo]').forEach((el) => {
            const campo = el.getAttribute('data-fatura-campo');
            const valor = faturaDados[campo] ?? '';
            if (el.tagName === 'TEXTAREA') {
                definirValorTextareaComEditor(el, valor);
            } else {
                el.value = valor;
            }
        });
        painelDadosFatura.querySelectorAll('[data-fatura-brl]').forEach((el) => {
            const campo = el.getAttribute('data-fatura-brl');
            el.value = centsParaInput(faturaDados[campo] ?? 0);
        });
        const cliente = faturaDados.cliente || {};
        painelDadosFatura.querySelectorAll('[data-fatura-cliente]').forEach((el) => {
            const campo = el.getAttribute('data-fatura-cliente');
            el.value = cliente[campo] ?? '';
        });
        renderizarItensFatura();
        recalcularTotaisFaturaDados();
    }

    function renderizarItensFatura() {
        if (!listaItensEl) {
            return;
        }
        listaItensEl.innerHTML = '';
        const itens = Array.isArray(faturaDados.itens) ? faturaDados.itens : [];

        if (itens.length === 0) {
            const vazio = document.createElement('div');
            vazio.className = 'text-secondary small text-center border rounded py-2';
            vazio.textContent = 'Nenhum item na fatura. Clique em "Adicionar Item" para incluir linhas.';
            listaItensEl.appendChild(vazio);

            return;
        }

        itens.forEach((item, index) => {
            const box = document.createElement('div');
            box.className = 'border rounded p-2';

            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end';

            const colDesc = document.createElement('div');
            colDesc.className = 'col-12';
            const lblDesc = document.createElement('label');
            lblDesc.className = 'form-label small fw-semibold mb-1';
            lblDesc.textContent = `Item ${index + 1} · Descrição`;
            const inpDesc = document.createElement('input');
            inpDesc.type = 'text';
            inpDesc.className = 'form-control form-control-sm';
            inpDesc.value = item.descricao || '';
            colDesc.append(lblDesc, inpDesc);

            const colQtd = document.createElement('div');
            colQtd.className = 'col-3';
            const lblQtd = document.createElement('label');
            lblQtd.className = 'form-label small fw-semibold mb-1';
            lblQtd.textContent = 'Qtd';
            const inpQtd = document.createElement('input');
            inpQtd.type = 'number';
            inpQtd.min = '1';
            inpQtd.className = 'form-control form-control-sm';
            inpQtd.value = String(item.quantidade || 1);
            colQtd.append(lblQtd, inpQtd);

            const colUnit = document.createElement('div');
            colUnit.className = 'col-5';
            const lblUnit = document.createElement('label');
            lblUnit.className = 'form-label small fw-semibold mb-1';
            lblUnit.textContent = 'Unitário (R$)';
            const inpUnit = document.createElement('input');
            inpUnit.type = 'text';
            inpUnit.className = 'form-control form-control-sm';
            inpUnit.value = centsParaInput(item.valor_unitario_cents || 0);
            colUnit.append(lblUnit, inpUnit);

            const colTotal = document.createElement('div');
            colTotal.className = 'col-4 d-flex align-items-center justify-content-between gap-1';
            const totalText = document.createElement('span');
            totalText.className = 'small fw-bold text-gold';
            totalText.textContent = formatarBrl((item.quantidade || 1) * (item.valor_unitario_cents || 0));
            const btnRem = document.createElement('button');
            btnRem.type = 'button';
            btnRem.className = 'btn btn-sm btn-outline-danger';
            btnRem.title = 'Remover item';
            btnRem.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
            colTotal.append(totalText, btnRem);

            const atualizarItem = () => {
                faturaEditadaManual = true;
                item.descricao = inpDesc.value;
                item.quantidade = Math.max(1, parseInt(inpQtd.value, 10) || 1);
                item.valor_unitario_cents = inputParaCents(inpUnit.value);
                item.valor_cents = item.quantidade * item.valor_unitario_cents;
                totalText.textContent = formatarBrl(item.valor_cents);
                recalcularTotaisFaturaDados();
                agendarPreview();
            };

            inpDesc.addEventListener('input', atualizarItem);
            inpQtd.addEventListener('input', atualizarItem);
            inpQtd.addEventListener('change', atualizarItem);
            inpUnit.addEventListener('input', atualizarItem);
            btnRem.addEventListener('click', () => {
                faturaEditadaManual = true;
                faturaDados.itens.splice(index, 1);
                renderizarItensFatura();
                recalcularTotaisFaturaDados();
                agendarPreview();
            });

            row.append(colDesc, colQtd, colUnit, colTotal);
            box.appendChild(row);
            listaItensEl.appendChild(box);
        });
    }

    async function atualizarPreviewA4() {
        if (window.GoldenSummernote) {
            window.GoldenSummernote.syncForm(painelDadosFatura || document);
        }

        const obsTextarea = document.getElementById('faturaObservacoesLive');
        if (obsTextarea && faturaEditadaManual) {
            faturaDados.observacoes = obsTextarea.value;
        }

        coletarEstiloDeFormulario(formIdentidade);
        coletarEstiloDeFormulario(formWhmcs);
        sincronizarEspelhosEstilo();
        sincronizarJson();
        recalcularTotaisFaturaDados();

        if (previewAbort) {
            previewAbort.abort();
        }
        previewAbort = new AbortController();

        atualizarIndicadoresStatus('Atualizando A4...', 'tone-amber');

        const whmcsInvoiceId = !faturaEditadaManual && inputWhmcsInvoice
            ? (parseInt(inputWhmcsInvoice.value, 10) || 0)
            : 0;

        try {
            const payload = {
                modelo_json: JSON.stringify(modelo),
                whmcs_invoice_id: whmcsInvoiceId,
            };
            if (faturaEditadaManual || whmcsInvoiceId <= 0) {
                const faturaPayload = clonar(faturaDados);
                delete faturaPayload.status_rotulo;
                payload.fatura_json = JSON.stringify(faturaPayload);
            }

            const resp = await fetch(previewUrl, {
                method: 'POST',
                signal: previewAbort.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();
            if (typeof data.html === 'string') {
                let ultimoScrollY = 0;
                try {
                    ultimoScrollY = iframe.contentWindow?.scrollY || 0;
                } catch {}
                if (ultimoScrollY > 0) {
                    iframe.addEventListener('load', () => {
                        try { iframe.contentWindow?.scrollTo(0, ultimoScrollY); } catch {}
                    }, { once: true });
                }
                iframe.srcdoc = data.html;
            }
            if (espelhoResumo && data.numero && data.total_formatado) {
                espelhoResumo.textContent = `Fatura #${data.numero} · ${data.total_formatado}`;
            }
            atualizarIndicadoresStatus('Cirúrgico A4 · Ao vivo', 'tone-green');
        } catch (erro) {
            if (erro?.name === 'AbortError') {
                return;
            }
            atualizarIndicadoresStatus('Erro preview', 'tone-red');
        }
    }

    function agendarPreview() {
        sincronizarJson();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(atualizarPreviewA4, 150);
    }

    function moverBloco(de, para) {
        if (de === para || de < 0 || para < 0 || de >= modelo.blocos.length || para >= modelo.blocos.length) {
            return;
        }
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

    function vincularEventosEstiloFormulario(formEl) {
        if (!formEl) {
            return;
        }
        ['input', 'keyup', 'paste', 'cut', 'change'].forEach((ev) => {
            formEl.addEventListener(ev, () => {
                coletarEstiloDeFormulario(formEl);
                sincronizarEspelhosEstilo();
                agendarPreview();
            });
        });
    }

    vincularEventosEstiloFormulario(formIdentidade);
    vincularEventosEstiloFormulario(formWhmcs);

    if (painelDadosFatura) {
        const tratarAlteracaoFatura = (evento) => {
            const alvo = evento.target;
            if (!alvo) {
                return;
            }
            const campo = alvo.getAttribute('data-fatura-campo');
            const campoBrl = alvo.getAttribute('data-fatura-brl');
            const campoCliente = alvo.getAttribute('data-fatura-cliente');

            if (campo) {
                faturaEditadaManual = true;
                faturaDados[campo] = alvo.value;
                recalcularTotaisFaturaDados();
                agendarPreview();
            } else if (campoBrl) {
                faturaEditadaManual = true;
                faturaDados[campoBrl] = inputParaCents(alvo.value);
                recalcularTotaisFaturaDados();
                agendarPreview();
            } else if (campoCliente) {
                faturaEditadaManual = true;
                if (!faturaDados.cliente || typeof faturaDados.cliente !== 'object') {
                    faturaDados.cliente = {};
                }
                faturaDados.cliente[campoCliente] = alvo.value;
                agendarPreview();
            }
        };

        ['input', 'keyup', 'paste', 'cut', 'change', 'golden:summernote-input'].forEach((ev) => {
            painelDadosFatura.addEventListener(ev, tratarAlteracaoFatura);
        });
    }

    document.getElementById('btnAdicionarItemFatura')?.addEventListener('click', () => {
        faturaEditadaManual = true;
        if (!Array.isArray(faturaDados.itens)) {
            faturaDados.itens = [];
        }
        faturaDados.itens.push({
            id: `novo-${Date.now()}`,
            descricao: 'Novo serviço / monitoramento Golden',
            tipo: 'servico',
            quantidade: 1,
            valor_unitario_cents: 5000,
            valor_cents: 5000,
        });
        renderizarItensFatura();
        recalcularTotaisFaturaDados();
        agendarPreview();
    });

    document.getElementById('btnLimparCriarFatura')?.addEventListener('click', () => {
        const hoje = new Date();
        const vencimento = new Date(hoje.getTime() + 5 * 86400000);
        const fmt = (d) => `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;

        faturaEditadaManual = true;
        faturaDados = {
            id: 0,
            numero: `GR-${hoje.getFullYear()}-0001`,
            status: 'Unpaid',
            emitida: fmt(hoje),
            vencimento: fmt(vencimento),
            pagamento_data: '',
            moeda_prefixo: 'R$',
            subtotal_cents: 10000,
            credito_cents: 0,
            imposto_cents: 0,
            total_cents: 10000,
            saldo_cents: 10000,
            metodo: '',
            metodo_rotulo: 'PIX Instantâneo · Gateway WHMCS',
            gateway_tipo: 'gateway',
            url: @json(route('portal.faturas')),
            checkout_url: @json(route('portal.faturas')),
            pix_copia_cola: '',
            linha_digitavel: '',
            codigo_barras: '',
            nosso_numero: '',
            observacoes: '',
            cliente: {
                nome: '',
                empresa: '',
                documento: '',
                email: '',
                telefone: '',
                endereco: '',
                cidade: '',
                uf: '',
                cep: '',
            },
            itens: [
                {
                    id: 'novo-1',
                    descricao: 'Mensalidade Golden Rastreamento',
                    tipo: 'servico',
                    quantidade: 1,
                    valor_unitario_cents: 10000,
                    valor_cents: 10000,
                },
            ],
        };
        preencherInputsFaturaDados();
        agendarPreview();
    });

    document.getElementById('btnResetarDemoFatura')?.addEventListener('click', () => {
        faturaEditadaManual = false;
        faturaDados = clonar(faturaDemoPadrao);
        preencherInputsFaturaDados();
        agendarPreview();
    });

    inputWhmcsInvoice?.addEventListener('input', () => {
        if ((parseInt(inputWhmcsInvoice.value, 10) || 0) > 0) {
            faturaEditadaManual = false;
        }
        agendarPreview();
    });
    inputWhmcsInvoice?.addEventListener('change', () => {
        if ((parseInt(inputWhmcsInvoice.value, 10) || 0) > 0) {
            faturaEditadaManual = false;
        }
        agendarPreview();
    });

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
    renderizarItensFatura();
    recalcularTotaisFaturaDados();
})();
</script>
@endsection
