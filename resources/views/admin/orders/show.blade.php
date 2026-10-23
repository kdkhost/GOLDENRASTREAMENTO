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
                    <dt>Desconto</dt><dd>- {{ brl($pedido->desconto_cents) }}</dd>
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
            <form method="POST" action="{{ route('admin.pedidos.documentos', $pedido) }}">
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

            <label class="form-label" for="publicLink">Link público do pedido <span class="form-hint">(é por aqui que o cliente acompanha)</span></label>
            <div class="input-group input-group-sm">
                <input id="publicLink" type="text" class="form-control font-monospace" readonly value="{{ $pedido->registroUrl() }}">
                <button class="btn btn-outline-secondary" type="button" data-copiar="#publicLink"><i class="fas fa-copy" aria-hidden="true"></i> Copiar</button>
            </div>
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
            <form method="POST" action="{{ route('admin.pedidos.status', $pedido) }}" class="row g-3">
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
document.querySelectorAll('[data-copiar]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var campo = document.querySelector(btn.dataset.copiar);
        campo.select();
        navigator.clipboard?.writeText(campo.value);
        btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Copiado';
    });
});
</script>
@endpush
