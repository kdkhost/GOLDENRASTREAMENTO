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
                        {{ $it->nome }}
                        @if($it->product)
                            <div class="small text-secondary">
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
            <dl class="defs mb-0">
                <dt>Subtotal</dt><dd>{{ brl($pedido->subtotal_cents) }}</dd>
                @if($pedido->frete_cents)
                    <dt>Frete</dt><dd>{{ $pedido->frete_cents > 0 ? brl($pedido->frete_cents) : 'Grátis' }}</dd>
                @endif
                @if($pedido->desconto_cents)
                    <dt>Desconto @if($pedido->cupom_codigo)<span class="badge bg-success-subtle text-success ms-1">{{ $pedido->cupom_codigo }}</span>@endif</dt><dd>- {{ brl($pedido->desconto_cents) }}</dd>
                @elseif($pedido->cupom_codigo)
                    <dt>Cupom</dt><dd><span class="badge bg-success-subtle text-success">{{ $pedido->cupom_codigo }} (Frete grátis)</span></dd>
                @endif
                <dt>Total</dt><dd class="text-gold">{{ brl($pedido->total_cents) }}</dd>
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

            @if($pedido->possuiRastreio())
                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary text-uppercase"><i class="fas fa-satellite me-1"></i> Consulta em Tempo Real</span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="btnConsultarRastreio">
                            <i class="fas fa-rotate me-1" id="iconConsultar"></i> Consultar agora
                        </button>
                    </div>

                    <div id="resultadoRastreio" class="small p-2 rounded bg-light border">
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
