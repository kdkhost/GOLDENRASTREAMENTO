@extends('portal.layout')

@section('title', 'Meus pedidos — Portal do cliente')
@section('page_title', 'Meus pedidos')
@section('page_description', $pedidos->total().' '.($pedidos->total() === 1 ? 'pedido' : 'pedidos').' nesta conta'.(request()->filled('status') ? ' com o filtro aplicado.' : '.'))

@section('page_actions')
@if(loja_ativa())
<a href="{{ route('site.loja.index') }}" class="btn btn-sm btn-golden"><i class="fas fa-basket-shopping me-1"></i> Comprar mais</a>
@endif
@endsection

@section('content')
<x-admin.filters :reset="route('portal.pedidos')">
    <x-admin.filter name="status" type="select" label="Situação" col="col-12 col-md-4" :options="$statuses" empty="Todos os pedidos" />
</x-admin.filters>

<x-admin.table :headers="['Pedido', 'center:Data', 'center:Itens', 'Pagamento', 'center:Situação', 'end:Total']"
    :paginator="$pedidos"
    empty="{{ request()->filled('status') ? 'Nenhum pedido nesta situação.' : 'Você ainda não tem pedidos registrados.' }}">
    @foreach($pedidos as $pedido)
    <tr>
        <td>
            <a href="{{ route('portal.pedidos.show', $pedido) }}" class="fw-semibold text-decoration-none">{{ $pedido->numero }}</a>
            <div class="text-secondary small">{{ $pedido->created_at->format('d/m/Y') }} · {{ $pedido->items_count }} {{ $pedido->items_count === 1 ? 'item' : 'itens' }}</div>
        </td>
        <td class="text-center small text-secondary">{{ $pedido->created_at->format('d/m/Y') }}</td>
        <td class="text-center small">{{ $pedido->items_count }}</td>
        <td class="small">{{ $pedido->pagamentoLabel() }}</td>
        <td class="text-center"><x-admin.status :rotulo="$pedido->statusLabel()" :tom="tom_por_status('pedido', $pedido->status)" /></td>
        <td class="text-end fw-semibold">{{ brl($pedido->total_cents) }}</td>
    </tr>
    @endforeach
</x-admin.table>
@endsection
