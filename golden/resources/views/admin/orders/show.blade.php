@extends('admin.layouts.app')
@section('title', 'Pedido ' . $pedido->numero)
@section('page_title', 'Pedido ' . $pedido->numero)
@section('page_description', $pedido->nome . ' · ' . $pedido->created_at->format('d/m/Y H:i'))

@section('page_actions')
<a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px">
            <div class="card-header bg-body fw-bold">Itens do pedido</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Produto</th><th class="text-center">Qtd</th><th class="text-center">Unitário</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($pedido->items as $it)
                        <tr>
                            <td>{{ $it->nome }}@if($it->product)<div class="small text-secondary">{{ $it->product->sku }}</div>@endif</td>
                            <td class="text-center">{{ $it->quantidade }}</td>
                            <td class="text-center small">{{ brl($it->preco_unit_cents) }}</td>
                            <td class="text-end small fw-semibold">{{ brl($it->subtotal_cents) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="border-top">
                        <tr><td colspan="3" class="text-end text-secondary small">Subtotal</td><td class="text-end small">{{ brl($pedido->subtotal_cents) }}</td></tr>
                        @if($pedido->frete_cents)<tr><td colspan="3" class="text-end text-secondary small">Frete</td><td class="text-end small">{{ brl($pedido->frete_cents) }}</td></tr>@endif
                        @if($pedido->desconto_cents)<tr><td colspan="3" class="text-end text-secondary small">Desconto</td><td class="text-end small">- {{ brl($pedido->desconto_cents) }}</td></tr>@endif
                        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end text-gold">{{ brl($pedido->total_cents) }}</td></tr>
                    </tfoot>
                </table>
            </div></div>
        </div>

        @if($pedido->notas)
        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold">Observações do cliente</h6><p class="small mb-0">{{ $pedido->notas }}</p>
        </div></div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-user text-warning me-1"></i> Dados de entrega</h6>
            <p class="small mb-1"><strong>{{ $pedido->nome }}</strong></p>
            <p class="small text-secondary mb-1">{{ $pedido->telefone }} @if($pedido->email)· {{ $pedido->email }}@endif</p>
            <p class="small text-secondary">{{ $pedido->endereco }} — {{ $pedido->cidade }}/{{ $pedido->uf }} @if($pedido->cep)· CEP {{ $pedido->cep }}@endif</p>

            @if($pedido->customer)
            <div class="border-top pt-2 mt-2 small"><i class="fas fa-users me-1"></i> Cliente no CRM: <a href="{{ route('admin.clientes.show',$pedido->customer) }}">{{ $pedido->customer->nome }}</a></div>
            @else
            <form method="POST" action="{{ route('admin.clientes.store') }}" class="border-top pt-2 mt-2">
                @csrf
                <input type="hidden" name="nome" value="{{ $pedido->nome }}">
                <input type="hidden" name="telefone" value="{{ $pedido->telefone }}">
                <input type="hidden" name="email" value="{{ $pedido->email }}">
                <input type="hidden" name="endereco" value="{{ $pedido->endereco }}">
                <input type="hidden" name="cidade" value="{{ $pedido->cidade }}">
                <input type="hidden" name="uf" value="{{ $pedido->uf }}">
                <input type="hidden" name="cep" value="{{ $pedido->cep }}">
                <input type="hidden" name="tipo" value="fisica">
                <input type="hidden" name="status" value="ativo">
                <input type="hidden" name="valor_anual" value="{{ $pedido->total_cents/100 }}">
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-user-plus me-1"></i> Criar cliente deste pedido</button>
            </form>
            @endif
        </div></div>

        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3">Gerenciar pedido</h6>
            <form method="POST" action="{{ route('admin.pedidos.status',$pedido) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm">@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected($pedido->status===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small">Pagamento</label><select name="pagamento" class="form-select form-select-sm">@foreach(\App\Models\Order::PAGAMENTOS as $k=>$v)<option value="{{ $k }}" @selected($pedido->pagamento===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label small">Notas internas</label><textarea name="notas" rows="3" class="form-control form-control-sm">{{ $pedido->notas }}</textarea></div>
                </div>
                <button class="btn btn-golden btn-sm mt-3"><i class="fas fa-save me-1"></i> Atualizar pedido</button>
            </form>
            @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('admin.pedidos.destroy',$pedido) }}" class="mt-2" onsubmit="return confirm('Excluir o pedido {{ $pedido->numero }}?')">@csrf@method('DELETE')<button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i> Excluir pedido</button></form>
            @endif
        </div></div>
    </div>
</div>
@endsection
