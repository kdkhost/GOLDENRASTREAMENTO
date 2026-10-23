@extends('admin.layouts.app')
@section('title', 'Pedidos')
@section('page_title', 'Pedidos e vendas')
@section('page_description', 'Vendas da loja virtual e pedidos avulsos. A baixa de estoque acontece na confirmação do pagamento.')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Total de pedidos" :value="$kpis['total']" icon="fa-receipt" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Faturamento (exceto pendentes)" :value="brl($faturamento)" icon="fa-sack-dollar" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Pendentes" :value="$kpis['pendentes']" icon="fa-hourglass-half" tone="red" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Entregues" :value="$kpis['entregues']" icon="fa-truck-fast" tone="teal" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.pedidos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Nº do pedido, nome ou telefone..." />
    <x-admin.filter name="status" type="select" label="Status" col="col-6 col-md-3" :options="$statuses" empty="Todos os status" />
</x-admin.filters>

<x-admin.table :headers="['Pedido', 'Cliente', 'center:Itens', 'Pagamento', 'end:Total', 'center:Status', 'center:Data']"
    empty="Nenhum pedido com esse filtro. Assim que a loja receber compras elas aparecem aqui."
    :paginator="$pedidos">
    @foreach($pedidos as $o)
        <tr>
            <td><a href="{{ route('admin.pedidos.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->numero }}</a></td>
            <td class="small">{{ $o->nome }}<div class="text-secondary">{{ $o->telefone }}</div></td>
            <td class="text-center small">{{ $o->items_count }}</td>
            <td class="small">{{ $o->pagamentoLabel() }}</td>
            <td class="text-end small fw-semibold">{{ brl($o->total_cents) }}</td>
            <td class="text-center"><x-admin.status :rotulo="$o->statusLabel()" :tom="tom_por_status('pedido', $o->status)" /></td>
            <td class="text-center small text-secondary">{{ $o->created_at->format('d/m/Y') }}</td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
