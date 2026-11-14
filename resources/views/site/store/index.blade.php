@extends('layouts.site')

@section('title', 'Loja Golden — Rastreadores, chips M2M e acessórios')
@section('meta_description', 'Compre rastreadores J16 4G/2G, chips M2M (Vivo, Claro, Algar, TIM) e acessórios para sua frota. Pix, cartão ou boleto, com entrega ou retirada.')

@section('content')
<section class="sec-light" style="padding-bottom:20px">
    <div class="wrap">
        <span class="eyebrow">Loja Golden</span>
        <h1 style="font-size:2rem;margin:.2rem 0">Equipamentos e chips para o seu veículo.</h1>
        <p class="lead-sub">Rastreadores J16 4G/2G, chips M2M dos parceiros {{ implode(', ', $golden['parceiros']) }} e acessórios. Pague com Pix, cartão ou boleto e acompanhe o pedido on-line.</p>

        @if($categorias->isNotEmpty())
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
            <a href="{{ route('site.loja.index') }}" class="btn btn-sm {{ !$categoriaAtual ? 'btn-gold' : 'btn-ghost' }}">Todos</a>
            @foreach($categorias as $c)
                <a href="{{ route('site.loja.index', ['categoria' => $c['slug']]) }}" class="btn btn-sm {{ $categoriaAtual === $c['slug'] ? 'btn-gold' : 'btn-ghost' }}">{{ $c['nome'] }} ({{ $c['total'] }})</a>
            @endforeach
        </div>
        @endif
    </div>
</section>

<section class="sec-alt" style="padding-top:20px">
    <div class="wrap">
        @if($produtos->isEmpty())
        <p class="center" style="padding:40px 0;color:var(--muted)">Nenhum produto disponível nesta categoria. Fale com um especialista para encomendar.</p>
        @else
        <div class="grid-3">
            @foreach($produtos as $p)
                @include('site.store._product-card', ['p' => $p])
            @endforeach
        </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
@include('site.store._product-card-scripts')
@endpush
