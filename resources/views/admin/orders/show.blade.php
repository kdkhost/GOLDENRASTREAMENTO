@extends('admin.layouts.app')
@section('title', 'Pedido '.$pedido->numero)
@section('page_title', 'Pedido '.$pedido->numero)
@section('page_description', $pedido->nome.' · '.$pedido->created_at->format('d/m/Y H:i'))

@section('page_actions')
@if($pedido->customer)
    <a href="{{ route('admin.clientes.show', $pedido->customer) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-user-check me-1"></i> Ver cliente no CRM</a>
@else
    <a href="{{ route('admin.clientes.create', ['nome' => $pedido->nome, 'telefone' => $pedido->telefone, 'email' => $pedido->email, 'documento' => $pedido->documento, 'endereco' => $pedido->endereco, 'cidade' => $pedido->cidade, 'uf' => $pedido->uf, 'cep' => $pedido->cep, 'tipo' => $pedido->tipo_pessoa ?: 'fisica', 'status' => 'ativo', 'valor_anual' => $pedido->total_cents / 100, 'pedido' => $pedido->numero]) }}"
        class="btn btn-sm btn-golden"><i class="fas fa-user-plus me-1"></i> Criar cliente deste pedido</a>
@endif
<a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.table class="mb-3" :headers="['Produto', 'center:Qtd', 'end:Unitário', 'end:Subtotal']" empty="Pedido sem itens.">
            @foreach($pedido->items as $it)
                <tr>
                    <td>
                        <div class="fw-bold">{{ $it->nome }}</div>
                        @if($it->chip_operadora)
                            @php
                                $infoChip = \App\Models\Product::OPERADORAS_CHIP[$it->chip_operadora] ?? null;
                            @endphp
                            <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:.76rem">
                                    <i class="fas fa-sim-card text-warning me-1"></i> Chip: <strong>{{ $infoChip['nome'] ?? ucfirst($it->chip_operadora) }}</strong>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success" style="font-size:.76rem">
                                    <i class="fas fa-check-circle text-success me-1"></i> Benefício: Chip incluso no pedido
                                </span>
                            </div>
                        @endif
                        @if(($it->mensalidade_cents ?? 0) > 0)
                            <div class="small mt-1 {{ $it->cobrar_mensalidade_hoje ? 'text-teal fw-semibold' : 'text-primary' }}">
                                <i class="fas fa-repeat me-1"></i> Mensalidade: {{ brl($it->mensalidade_cents) }}/mês
                                <span class="badge {{ $it->cobrar_mensalidade_hoje ? 'bg-info-subtle text-info-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }} ms-1">
                                    {{ $it->cobrar_mensalidade_hoje ? '1ª Mensalidade paga hoje' : 'Pós-paga (faturar após 30 dias)' }}
                                </span>
                            </div>
                        @endif
                        @if($it->product)
                            <div class="small text-secondary mt-1">
                                {{ $it->product->sku ?: 'sem SKU' }}
                                · <a href="{{ route('admin.catalogo.produtos.edit', $it->product) }}" class="text-decoration-none">editar produto</a>
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $it->quantidade }}</td>
                    <td class="text-end small">{{ brl($it->preco_unit_cents) }}</td>
                    <td class="text-end small fw-semibold">{{ brl($it->subtotal_cents) }}</td>
                </tr>
            @endforeach
        </x-admin.table>

        <x-admin.card title="Totais" icon="fa-calculator">
            @php
                $mensalidadePagaHoje = $pedido->items->filter(fn($i) => (bool) $i->cobrar_mensalidade_hoje)->sum(fn($i) => ($i->mensalidade_cents ?? 0) * $i->quantidade);
            @endphp
            <dl class="defs mb-0">
                <dt>Aparelhos / Adesão</dt><dd>{{ brl($pedido->subtotal_cents) }}</dd>
                @if($mensalidadePagaHoje > 0)
                    <dt>1ª Mensalidade (paga hoje)</dt><dd class="text-teal">{{ brl($mensalidadePagaHoje) }}</dd>
                @elseif(($pedido->mensalidade_total_cents ?? 0) > 0)
                    <dt>1ª Mensalidade (pós-paga)</dt><dd class="text-primary">Faturar após 30 dias</dd>
                @endif
                @if($pedido->frete_cents)
                    <dt>Frete</dt><dd>{{ $pedido->frete_cents > 0 ? brl($pedido->frete_cents) : 'Grátis' }}</dd>
                @endif
                @if($pedido->desconto_cents)
                    <dt>Desconto @if($pedido->cupom_codigo)<span class="badge bg-success-subtle text-success ms-1">{{ $pedido->cupom_codigo }}</span>@endif</dt><dd>- {{ brl($pedido->desconto_cents) }}</dd>
                @elseif($pedido->cupom_codigo)
                    <dt>Cupom</dt><dd><span class="badge bg-success-subtle text-success">{{ $pedido->cupom_codigo }} (Frete grátis)</span></dd>
                @endif
                <dt>Total pago hoje</dt><dd class="text-gold fw-bold">{{ brl($pedido->total_cents) }}</dd>
                @if(($pedido->mensalidade_total_cents ?? 0) > 0)
                    <dt>Recorrência mensal</dt><dd class="text-teal fw-semibold">{{ brl($pedido->mensalidade_total_cents) }}/mês ({{ $mensalidadePagaHoje > 0 ? 'a partir do 2º mês' : 'a partir de 30 dias' }})</dd>
                @endif
            </dl>
            @if($pedido->notas)
                <div class="border-top mt-3 pt-2">
                    <h6 class="fw-bold small mb-1">Observações do cliente</h6>
                    <p class="small mb-0">{{ $pedido->notas }}</p>
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Documentos do pedido" icon="fa-paperclip" class="mt-3"
            descricao="Ficam no painel; o cliente acompanha o pedido pela linha do tempo.">
            <form method="POST" action="{{ route('admin.pedidos.documentos', $pedido) }}" data-atualiza=".app-content">
                @csrf
                @include('partials.upload', [
                    'campo' => 'pedido_documento',
                    'name' => 'documentos',
                    'valor' => old('documentos', $pedido->documentos ?? []),
                    'multiplo' => true,
                ])
                <button class="btn btn-golden btn-sm mt-2"><i class="fas fa-save me-1"></i> Salvar documentos</button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Comprador e entrega" icon="fa-user">
            <dl class="defs mb-3">
                <dt>Nome</dt><dd>{{ $pedido->nome }}</dd>
                <dt>Contato</dt><dd>{{ $pedido->telefone }}@if($pedido->email)<div>{{ $pedido->email }}</div>@endif</dd>
                @if($pedido->documento)
                    <dt>Documento</dt><dd>{{ $pedido->documentoFormatado() }} <span class="form-hint">({{ $pedido->tipo_pessoa === 'juridica' ? 'pessoa jurídica' : 'pessoa física' }})</span></dd>
                @endif
                <dt>Entrega</dt><dd>{{ $pedido->entregaLabel() }}</dd>
                @if($pedido->entrega !== 'retira')
                    <dt>Endereço</dt><dd>{{ $pedido->enderecoFormatado() }}</dd>
                @endif
                @if($pedido->consent_given)
                    <dt>LGPD</dt><dd class="text-success">Consentimento registrado em {{ $pedido->created_at->format('d/m/Y H:i') }}</dd>
                @endif
            </dl>

            <x-admin.link-copiar :valor="$pedido->registroUrl()" label="Link público do pedido" hint="é por aqui que o cliente acompanha" />
        </x-admin.card>

        <x-admin.card title="Logística, Despacho e Rastreamento" icon="fa-truck-fast" class="mt-3"
            descricao="Defina a transportadora, insira o código de rastreio e notifique o cliente por e-mail.">
            <x-slot:actions>
                @if($pedido->possuiRastreio())
                    <span class="badge bg-success"><i class="fas fa-barcode me-1"></i> Rastreio ativo</span>
                @else
                    <span class="badge bg-secondary">Aguardando despacho</span>
                @endif
            </x-slot:actions>

            <form method="POST" action="{{ route('admin.pedidos.rastreio', $pedido) }}" class="row g-3">
                @csrf
                <x-admin.field name="transportadora" type="select" label="Transportadora / Modalidade" col="col-12"
                    :value="$pedido->transportadora ?? 'correios_sedex'" :options="\App\Models\Order::TRANSPORTADORAS" />

                <div class="col-12">
                    <label for="codigo_rastreio" class="form-label">Código de Rastreamento</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" name="codigo_rastreio" id="codigo_rastreio" class="form-control text-uppercase"
                            maxlength="60" value="{{ old('codigo_rastreio', $pedido->codigo_rastreio) }}"
                            placeholder="Ex.: AA123456789BR ou MLB123456789">
                    </div>
                    <div class="form-text">Aceita códigos dos Correios (SEDEX/PAC), Mercado Envios ou transportadora parceira.</div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="marcar_enviado" value="1" id="marcar_enviado"
                            @checked(old('marcar_enviado', $pedido->status !== 'enviado' && $pedido->status !== 'entregue'))>
                        <label class="form-check-label small" for="marcar_enviado">
                            Marcar status do pedido como <strong>"Enviado"</strong> na linha do tempo
                        </label>
                    </div>
                    @if($pedido->email)
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="notificar_cliente" value="1" id="notificar_cliente"
                                @checked(old('notificar_cliente', ! $pedido->rastreio_notificado_em))>
                            <label class="form-check-label small" for="notificar_cliente">
                                Notificar cliente por e-mail com link de rastreamento (<code>{{ $pedido->email }}</code>)
                            </label>
                        </div>
                    @endif
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Salvar despacho</button>
                    @if($pedido->possuiRastreio())
                        <a href="{{ $pedido->rastreioUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-up-right-from-square me-1"></i> Rastreio no site
                        </a>
                    @endif
                    @if($pedido->entrega !== 'retira')
                        <button type="submit" form="formSolicitarColeta" class="btn btn-outline-warning btn-sm" title="Solicitar coleta da encomenda na sede da Golden e notificar o administrador">
                            <i class="fas fa-truck-ramp-box me-1"></i> Solicitar coleta na empresa
                        </button>
                    @endif
                </div>
            </form>

            @if($pedido->entrega !== 'retira')
                <form id="formSolicitarColeta" method="POST" action="{{ route('admin.pedidos.rastreio.coleta', $pedido) }}" class="d-none">
                    @csrf
                </form>
            @endif

            {{-- Registro de cada parada / movimentação da transportadora --}}
            <div class="border-top mt-3 pt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-secondary text-uppercase">
                        <i class="fas fa-route me-1"></i> Registrar Parada / Movimentação
                    </span>
                    <span class="badge text-bg-light border">
                        {{ count(is_array($pedido->rastreio_historico) ? $pedido->rastreio_historico : []) }} parada(s)
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-1 mb-2">
                    @php
                        $destinoSugestao = $pedido->cidade ? "{$pedido->cidade}/{$pedido->uf}" : 'Cidade do destinatário';
                        $origemSugestao = \App\Models\Setting::get('empresa_cidade', 'Três Pontas/MG');
                        $atalhosParada = [
                            ['rotulo' => 'Coletado/Postado', 'status' => 'Objeto postado / coletado pela transportadora', 'unidade' => $pedido->transportadoraLabel(), 'local' => $origemSugestao, 'detalhe' => 'Encomenda conferida, lacrada e coletada com sucesso na origem.', 'atualizar' => 'enviado'],
                            ['rotulo' => 'Em Trânsito / Hub', 'status' => 'Objeto em trânsito para centro de distribuição', 'unidade' => 'Hub Logístico / Unidade de Tratamento', 'local' => 'Em rota interestadual', 'detalhe' => 'Pacote encaminhado com segurança pela malha da transportadora.', 'atualizar' => 'nenhum'],
                            ['rotulo' => 'Unidade da Cidade', 'status' => 'Objeto recebido na unidade de distribuição local', 'unidade' => 'Unidade de Entrega Local', 'local' => $destinoSugestao, 'detalhe' => 'Encomenda chegou à cidade de destino e está em triagem para entrega.', 'atualizar' => 'nenhum'],
                            ['rotulo' => 'Saiu p/ Entrega', 'status' => 'Objeto saiu para entrega ao destinatário', 'unidade' => 'Entregador / Motorista em Rota', 'local' => $destinoSugestao, 'detalhe' => 'O pedido está a caminho do endereço informado. Acompanhe o recebimento.', 'atualizar' => 'nenhum'],
                            ['rotulo' => 'Entregue', 'status' => 'Objeto entregue ao destinatário', 'unidade' => $pedido->transportadoraLabel(), 'local' => $destinoSugestao, 'detalhe' => 'Entrega confirmada com sucesso ao recebedor no endereço de destino.', 'atualizar' => 'entregue'],
                        ];
                    @endphp
                    @foreach($atalhosParada as $at)
                        <button type="button"
                            class="btn btn-outline-secondary btn-sm py-0 px-2 btn-atalho-parada"
                            style="font-size: 0.72rem;"
                            data-status="{{ $at['status'] }}"
                            data-unidade="{{ $at['unidade'] }}"
                            data-local="{{ $at['local'] }}"
                            data-detalhe="{{ $at['detalhe'] }}"
                            data-atualizar="{{ $at['atualizar'] }}">
                            + {{ $at['rotulo'] }}
                        </button>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('admin.pedidos.rastreio.movimentacao', $pedido) }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                        <input type="text" name="status" id="paradaStatus" class="form-control form-control-sm" required maxlength="140"
                            placeholder="Status da parada (ex.: Objeto recebido na unidade de tratamento)">
                    </div>
                    <div class="col-6">
                        <input type="text" name="unidade" id="paradaUnidade" class="form-control form-control-sm" maxlength="140"
                            value="{{ $pedido->transportadoraLabel() }}" placeholder="Unidade / Hub / Transportadora">
                    </div>
                    <div class="col-6">
                        <input type="text" name="local" id="paradaLocal" class="form-control form-control-sm" required maxlength="140"
                            value="{{ $destinoSugestao }}" placeholder="Cidade / UF da parada">
                    </div>
                    <div class="col-12">
                        <textarea name="detalhe" id="paradaDetalhe" rows="2" class="form-control form-control-sm" maxlength="500"
                            placeholder="Detalhes da parada exibidos ao cliente (ex.: Conferido e liberado para entrega)..."></textarea>
                    </div>
                    <div class="col-4">
                        <input type="text" name="data" class="form-control form-control-sm" maxlength="20" value="{{ now()->format('d/m/Y') }}" placeholder="dd/mm/aaaa">
                    </div>
                    <div class="col-3">
                        <input type="text" name="hora" class="form-control form-control-sm" maxlength="10" value="{{ now()->format('H:i') }}" placeholder="HH:mm">
                    </div>
                    <div class="col-5">
                        <select name="atualizar_status_pedido" id="paradaAtualizarStatus" class="form-select form-select-sm">
                            <option value="nenhum">Manter status atual</option>
                            <option value="separando">Status → Em separação</option>
                            <option value="enviado">Status → Enviado</option>
                            <option value="entregue">Status → Entregue</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-golden btn-sm w-100">
                            <i class="fas fa-location-dot me-1"></i> Gravar parada no rastreamento
                        </button>
                    </div>
                </form>

                @php $paradasSalvas = is_array($pedido->rastreio_historico) ? $pedido->rastreio_historico : []; @endphp
                @if(!empty($paradasSalvas))
                    <div class="mt-3">
                        <div class="small fw-semibold text-secondary mb-1">Paradas registradas no rastreamento:</div>
                        <div class="list-group list-group-flush border rounded overflow-hidden">
                            @foreach($paradasSalvas as $idx => $parada)
                                <div class="list-group-item py-2 px-2 d-flex justify-content-between align-items-start gap-2 bg-body-tertiary">
                                    <div class="small lh-sm">
                                        <div class="fw-semibold">{{ $parada['status'] ?? 'Movimentação' }}</div>
                                        <div class="text-secondary" style="font-size: 0.76rem;">
                                            @if(!empty($parada['unidade']))
                                                <span class="me-1"><i class="fas fa-building"></i> {{ $parada['unidade'] }}</span> ·
                                            @endif
                                            <i class="fas fa-location-dot"></i> {{ $parada['local'] ?? 'Em trânsito' }}
                                            · {{ $parada['data'] ?? '' }} {{ $parada['hora'] ?? '' }}
                                        </div>
                                        @if(!empty($parada['detalhe']))
                                            <div class="text-muted mt-1" style="font-size: 0.76rem;">{!! golden_html($parada['detalhe']) !!}</div>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.pedidos.rastreio.movimentacao.remover', [$pedido, $idx]) }}"
                                          data-confirmar="Remover esta parada do rastreamento?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Remover parada">
                                            <i class="fas fa-trash" style="font-size: 0.7rem;"></i>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if($pedido->possuiRastreio())
                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary text-uppercase"><i class="fas fa-satellite me-1"></i> Consulta em Tempo Real</span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="btnConsultarRastreio">
                            <i class="fas fa-rotate me-1" id="iconConsultar"></i> Consultar agora
                        </button>
                    </div>

                    <div id="resultadoRastreio" class="small p-2 rounded bg-body-tertiary border">
                        <div class="d-flex align-items-center justify-content-between">
                            <span><strong>Código:</strong> <code>{{ $pedido->codigo_rastreio }}</code></span>
                            <span class="badge bg-secondary">{{ $pedido->transportadoraLabel() }}</span>
                        </div>
                        <div id="detalhesRastreioConteudo" class="mt-2 text-muted">
                            Clique em "Consultar agora" para buscar o status atualizado nos Correios.
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <small class="text-secondary">
                            @if($pedido->rastreio_notificado_em)
                                <i class="fas fa-envelope-circle-check text-success me-1"></i> E-mail enviado em {{ $pedido->rastreio_notificado_em->format('d/m/Y H:i') }}
                            @else
                                <i class="fas fa-envelope text-muted me-1"></i> Cliente ainda não notificado por e-mail
                            @endif
                        </small>
                        @if($pedido->email)
                            <form method="POST" action="{{ route('admin.pedidos.rastreio.notificar', $pedido) }}">
                                @csrf
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2">
                                    <i class="fas fa-paper-plane me-1"></i> {{ $pedido->rastreio_notificado_em ? 'Reenviar e-mail' : 'Enviar e-mail' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Linha do tempo do cliente" icon="fa-timeline" class="mt-3">
            <ul class="timeline">
                @foreach($pedido->histories->sortByDesc('created_at') as $h)
                    <li class="{{ $loop->first ? 'atual' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">{{ $h->statusLabel() }}</span>
                            <small class="text-secondary text-nowrap">{{ $h->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                        @if($h->observacao)<div class="small">{{ $h->observacao }}</div>@endif
                        <div class="small text-secondary">{{ $h->user?->name ?? 'Automático' }}</div>
                    </li>
                @endforeach
            </ul>
            @if($pedido->histories->isEmpty())
                <p class="small text-secondary mb-0">Nenhum marco registrado.</p>
            @endif
        </x-admin.card>

        <x-admin.card title="Gerenciar pedido" icon="fa-sliders" class="mt-3">
            <form method="POST" action="{{ route('admin.pedidos.status', $pedido) }}" class="row g-3" data-atualiza=".app-content-header,.app-content">
                @csrf
                <x-admin.field name="status" type="select" label="Status" col="col-6" :value="$pedido->status" :options="$statuses" />
                <x-admin.field name="pagamento" type="select" label="Pagamento" col="col-6" :value="$pedido->pagamento" :options="\App\Models\Order::PAGAMENTOS" />
                <x-admin.field name="observacao" label="Recado para a linha do tempo" col="col-12" :opcional="true" maxlength="255" placeholder="Ex.: Pagamento confirmado, enviamos hoje." />
                <x-admin.field name="notas" type="textarea" label="Notas internas" col="col-12" :value="$pedido->notas" rows="3" :opcional="true" />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Atualizar pedido</button>
                </div>
            </form>
            @can('admin')
                <div class="border-top mt-3 pt-3">
                    <x-admin.delete :action="route('admin.pedidos.destroy', $pedido)" label="Excluir pedido" confirm="Excluir o pedido {{ $pedido->numero }}?" />
                </div>
            @endcan
        </x-admin.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-atalho-parada').forEach(function (btnAtalho) {
        btnAtalho.addEventListener('click', function () {
            const st = document.getElementById('paradaStatus');
            const un = document.getElementById('paradaUnidade');
            const lc = document.getElementById('paradaLocal');
            const dt = document.getElementById('paradaDetalhe');
            const sel = document.getElementById('paradaAtualizarStatus');
            if (st) st.value = btnAtalho.dataset.status || '';
            if (un) un.value = btnAtalho.dataset.unidade || '';
            if (lc) lc.value = btnAtalho.dataset.local || '';
            if (dt) dt.value = btnAtalho.dataset.detalhe || '';
            if (sel && btnAtalho.dataset.atualizar) sel.value = btnAtalho.dataset.atualizar;
        });
    });

    const btnConsultar = document.getElementById('btnConsultarRastreio');
    const containerConteudo = document.getElementById('detalhesRastreioConteudo');
    const iconConsultar = document.getElementById('iconConsultar');

    if (!btnConsultar || !containerConteudo) return;

    btnConsultar.addEventListener('click', function () {
        btnConsultar.disabled = true;
        if (iconConsultar) iconConsultar.classList.add('fa-spin');
        containerConteudo.innerHTML = '<span class="text-secondary"><i class="fas fa-spinner fa-spin me-1"></i> Consultando Correios em tempo real...</span>';

        fetch("{{ route('admin.pedidos.rastreio.consultar', $pedido) }}", {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            btnConsultar.disabled = false;
            if (iconConsultar) iconConsultar.classList.remove('fa-spin');

            if (!data.sucesso || !data.rastreio) {
                containerConteudo.innerHTML = '<span class="text-danger"><i class="fas fa-circle-exclamation me-1"></i> ' + (data.mensagem || 'Não foi possível consultar o rastreio no momento.') + '</span>';
                return;
            }

            const r = data.rastreio;
            let html = '<div class="mt-2 pt-2 border-top">';
            html += '<div class="d-flex justify-content-between align-items-center mb-1">';
            html += '<span class="badge bg-primary">' + (r.status || 'Em trânsito') + '</span>';
            if (r.ultimo_evento) {
                html += '<span class="text-secondary small">' + (r.ultimo_evento.data || '') + ' ' + (r.ultimo_evento.hora || '') + '</span>';
            }
            html += '</div>';

            if (r.eventos && r.eventos.length > 0) {
                html += '<ul class="list-unstyled mb-0 mt-2">';
                r.eventos.slice(0, 4).forEach(function (ev, idx) {
                    html += '<li class="pb-1 mb-1 ' + (idx < r.eventos.length - 1 ? 'border-bottom' : '') + '">';
                    html += '<div class="fw-semibold">' + (ev.descricao || ev.status || '') + '</div>';
                    html += '<div class="text-secondary d-flex justify-content-between">';
                    html += '<span><i class="fas fa-location-dot me-1"></i>' + (ev.local || 'Origem') + '</span>';
                    html += '<span>' + (ev.data || '') + ' ' + (ev.hora || '') + '</span>';
                    html += '</div>';
                    if (ev.detalhe) {
                        html += '<div class="text-muted fst-italic">' + ev.detalhe + '</div>';
                    }
                    html += '</li>';
                });
                html += '</ul>';
            } else {
                html += '<div class="text-secondary mt-1">Objeto postado, aguardando próximas atualizações do centro de distribuição.</div>';
            }

            html += '</div>';
            containerConteudo.innerHTML = html;
        })
        .catch(err => {
            btnConsultar.disabled = false;
            if (iconConsultar) iconConsultar.classList.remove('fa-spin');
            containerConteudo.innerHTML = '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i> Erro ao conectar ao serviço de rastreio.</span>';
        });
    });
});
</script>
@endpush
