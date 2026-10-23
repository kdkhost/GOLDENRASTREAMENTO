@extends('admin.layouts.app')
@section('title', 'Pedidos')
@section('page_title', 'Pedidos e vendas')
@section('page_description', 'Vendas da loja virtual e pedidos avulsos. Baixa manual de estoque na confirmação.')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Total de pedidos</div><div class="fs-4 fw-bold">{{ \App\Models\Order::count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Faturamento (não cancelados)</div><div class="fs-4 fw-bold text-gold">{{ brl($faturamento) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Pendentes</div><div class="fs-4 fw-bold">{{ \App\Models\Order::where('status','pendente')->count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Entregues</div><div class="fs-4 fw-bold">{{ \App\Models\Order::where('status','entregue')->count() }}</div></div></div></div>
</div>

<div class="card shadow-sm" style="border-radius:16px">
    <div class="card-header bg-body"><form method="GET" class="d-flex gap-2 flex-wrap">
        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nº, nome ou telefone...">
        <select name="status" class="form-select form-select-sm" style="max-width:170px">
            <option value="">Todos os status</option>
            @foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th>Pedido</th><th>Cliente</th><th>Itens</th><th class="text-center">Pagamento</th><th class="text-center">Total</th><th class="text-center">Status</th><th class="text-center">Data</th></tr></thead>
            <tbody>
            @forelse($pedidos as $o)
                <tr>
                    <td><a href="{{ route('admin.pedidos.show',$o) }}" class="fw-semibold text-decoration-none text-body">{{ $o->numero }}</a></td>
                    <td class="small">{{ $o->nome }}<div class="text-secondary">{{ $o->telefone }}</div></td>
                    <td class="small">{{ $o->items->count() }} item(ns)</td>
                    <td class="text-center small">{{ $o->pagamentoLabel() }}</td>
                    <td class="text-center small fw-semibold">{{ brl($o->total_cents) }}</td>
                    <td class="text-center"><span class="badge-status {{ in_array($o->status,['pago','enviado','entregue'])?'st-vendido':($o->status==='cancelado'?'st-perdido':'st-proposta') }}">{{ $o->statusLabel() }}</span></td>
                    <td class="text-center small text-secondary">{{ $o->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Nenhum pedido. Assim que a loja receber compras elas aparecem aqui.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="card-footer bg-body">{{ $pedidos->links() }}</div>
</div>
@endsection
