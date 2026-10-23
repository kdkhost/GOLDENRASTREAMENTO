@extends('portal.layout')

@section('title', 'Meus pedidos — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Meus pedidos</h1>
        <p>{{ $pedidos->total() }} {{ $pedidos->total() === 1 ? 'pedido' : 'pedidos' }} nesta conta.</p>
    </div>
    @if(loja_ativa())
    <a href="{{ route('site.loja.index') }}" class="btn btn-gold btn-sm"><i class="fa-solid fa-basket-shopping"></i> Comprar mais</a>
    @endif
</div>

<div class="filtros">
    <a href="{{ route('portal.pedidos') }}" class="{{ request()->filled('status') ? '' : 'atual' }}">Todos</a>
    @foreach($statuses as $chave => $rotulo)
    <a href="{{ route('portal.pedidos', ['status' => $chave]) }}" class="{{ request('status') === $chave ? 'atual' : '' }}">{{ $rotulo }}</a>
    @endforeach
</div>

<div class="itens">
    @forelse($pedidos as $pedido)
    <a class="item" href="{{ route('portal.pedidos.show', $pedido) }}">
        <div class="item-nucleo">
            <b>{{ $pedido->numero }}</b>
            <small>{{ $pedido->created_at->format('d/m/Y') }} · {{ $pedido->items_count }} {{ $pedido->items_count === 1 ? 'item' : 'itens' }} · {{ $pedido->pagamentoLabel() }}</small>
        </div>
        <div class="item-lado">
            {{ brl($pedido->total_cents) }}
            <small><span class="selo tone-{{ tom_por_status('pedido', $pedido->status) }}">{{ $pedido->statusLabel() }}</span></small>
        </div>
    </a>
    @empty
    <div class="item vazio">
        {{ request()->filled('status') ? 'Nenhum pedido nesta situação.' : 'Você ainda não tem pedidos registrados.' }}
    </div>
    @endforelse
</div>

@include('portal.pager', ['paginas' => $pedidos])
@endsection
