@extends('portal.layout')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Olá, {{ str($cliente->nome)->explode(' ')->first() }}</h1>
        <p>Aqui estão seus pedidos, chamados e contratos com a Golden.</p>
    </div>
    <a href="{{ route('portal.chamados.criar') }}" class="btn btn-gold btn-sm"><i class="fa-solid fa-plus"></i> Abrir chamado</a>
</div>

<div class="tiles">
    <div class="tile">
        <i class="fa-solid fa-box-open"></i>
        <div><span>Pedidos</span><b>{{ $totais['pedidos'] }}</b></div>
    </div>
    <div class="tile">
        <i class="fa-solid fa-receipt"></i>
        <div><span>Investido</span><b>{{ brl($investido_cents) }}</b></div>
    </div>
    <div class="tile">
        <i class="fa-solid fa-headset"></i>
        <div><span>Chamados abertos</span><b>{{ $abertos }}</b></div>
    </div>
    <div class="tile">
        <i class="fa-solid fa-file-signature"></i>
        <div><span>Contratos</span><b>{{ $totais['contratos'] }}</b></div>
    </div>
</div>

<div class="bloco">
    <div class="bloco-cab">
        <h2>Últimos pedidos</h2>
        <a href="{{ route('portal.pedidos') }}">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="itens">
        @forelse($pedidos as $pedido)
        <a class="item" href="{{ route('portal.pedidos.show', $pedido) }}">
            <div class="item-nucleo">
                <b>{{ $pedido->numero }}</b>
                <small>{{ $pedido->items_count }} {{ $pedido->items_count === 1 ? 'item' : 'itens' }} · {{ $pedido->created_at->format('d/m/Y') }}</small>
            </div>
            <div class="item-lado">
                {{ brl($pedido->total_cents) }}
                <small><span class="selo tone-{{ tom_por_status('pedido', $pedido->status) }}">{{ $pedido->statusLabel() }}</span></small>
            </div>
        </a>
        @empty
        <div class="item vazio">
            Nenhum pedido ainda. <a href="{{ route('site.loja.index') }}" style="color:var(--gold-2);font-weight:700">Ver a loja</a>
            ou <a href="{{ route('site.contato') }}" style="color:var(--gold-2);font-weight:700">falar com um especialista</a>.
        </div>
        @endforelse
    </div>
</div>

<div class="row-2">
    <div class="bloco">
        <div class="bloco-cab">
            <h2>Chamados</h2>
            <a href="{{ route('portal.chamados') }}">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="itens">
            @forelse($chamados as $chamado)
            <a class="item" href="{{ route('portal.chamados.show', $chamado) }}">
                <div class="item-nucleo">
                    <b>{{ $chamado->assunto }}</b>
                    <small>{{ $chamado->numero }} · {{ $chamado->created_at->format('d/m/Y') }}</small>
                </div>
                <div class="item-lado"><span class="selo tone-{{ tom_por_status('chamado', $chamado->status) }}">{{ $chamado->statusLabel() }}</span></div>
            </a>
            @empty
            <div class="item vazio">Nada aberto por aqui. Precisando de algo? <b>Abra um chamado</b> — a equipe responde pelo portal.</div>
            @endforelse
        </div>
    </div>

    <div class="bloco">
        <div class="bloco-cab">
            <h2>Contratos</h2>
            <a href="{{ route('portal.contratos') }}">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="itens">
            @forelse($contratos as $contrato)
            <a class="item" href="{{ route('portal.contratos') }}">
                <div class="item-nucleo">
                    <b>{{ $contrato->titulo }}</b>
                    <small>{{ $contrato->numero }} · {{ $contrato->tipoLabel() }}</small>
                </div>
                <div class="item-lado"><span class="selo tone-{{ tom_por_status('contrato', $contrato->status) }}">{{ $contrato->statusLabel() }}</span></div>
            </a>
            @empty
            <div class="item vazio">Sua ficha ainda não tem contrato publicado no portal.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
