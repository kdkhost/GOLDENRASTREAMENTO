@extends('layouts.site')

@section('title', 'Loja Golden — Rastreadores, chips M2M e acessórios')
@section('meta_description', 'Compre rastreadores J16 4G/2G, chips M2M (Vivo, Claro, Algar, TIM) e acessórios para sua frota. Pagamento por cartão ou boleto.')

@section('content')
<section class="sec-light" style="padding-bottom:20px">
    <div class="wrap">
        <span class="eyebrow">Loja Golden</span>
        <h1 style="font-size:2rem;margin:.2rem 0">Equipamentos e chips para o seu veículo.</h1>
        <p class="lead-sub">Rastreadores J16 4G/2G, chips M2M dos parceiros {{ implode(', ', $golden['parceiros']) }} e acessórios. Pagamento por {{ implode(' ou ', $golden['pagamentos']) }}. Atendimento e envio a combinar.</p>

        @if($categorias->isNotEmpty())
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
            <a href="{{ route('site.loja.index') }}" class="btn btn-sm {{ !$categoriaAtual ? 'btn-gold' : 'btn-ghost' }}">Todos</a>
            @foreach($categorias as $c)
                @if($c->products_count > 0)
                <a href="{{ route('site.loja.index', ['categoria' => $c->slug]) }}" class="btn btn-sm {{ $categoriaAtual === $c->slug ? 'btn-gold' : 'btn-ghost' }}">{{ $c->nome }}</a>
                @endif
            @endforeach
        </div>
        @endif
    </div>
</section>

<section class="sec-alt" style="padding-top:20px">
    <div class="wrap">
        @if($produtos->isEmpty())
        <p class="center" style="padding:40px 0;color:var(--muted)">Nenhum produto disponível no momento. Fale com um especialista para encomendar.</p>
        @else
        <div class="grid-3">
            @foreach($produtos as $p)
            <article class="card prod-card">
                <div class="prod-thumb">
                    @if($p->imagem)
                        <img src="{{ asset('storage/' . $p->imagem) }}" alt="{{ $p->nome }}">
                    @else
                        <i class="fa-solid fa-satellite-dish"></i>
                    @endif
                    @if($p->temPromocao())<span class="prod-badge">Promoção</span>@endif
                    @if($p->destaque)<span class="prod-badge feat">Destaque</span>@endif
                </div>
                <h3 style="font-size:1.1rem;margin:.4rem 0">{{ $p->nome }}</h3>
                @if($p->resumo)<p style="font-size:.9rem;color:var(--muted);min-height:2.4em">{{ $p->resumo }}</p>@endif
                <div class="prod-price">
                    @if($p->temPromocao())<s>{{ brl($p->preco_cents) }}</s>@endif
                    <b>{{ brl($p->preco()) }}</b>
                    @if($p->mensalidade_cents)<small>+ {{ brl($p->mensalidade_cents) }}/mês</small>@endif
                </div>
                <div class="prod-stock">
                    @if(!$p->controle_estoque)
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> Sob consulta</span>
                    @elseif($p->estoque > 0)
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> {{ $p->estoque }} em estoque</span>
                    @else
                        <span class="out"><i class="fa-solid fa-circle-xmark"></i> Sem estoque</span>
                    @endif
                </div>
                <a href="{{ route('site.loja.produto', $p->slug) }}" class="btn {{ $p->disponivel() ? 'btn-gold' : 'btn-ghost' }}" style="width:100%;margin-top:12px">{{ $p->disponivel() ? 'Comprar' : 'Ver detalhes' }} →</a>
            </article>
            @endforeach
        </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<style>
.prod-card{display:flex;flex-direction:column}
.prod-thumb{position:relative;height:160px;border-radius:14px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden;margin-bottom:12px}
.prod-thumb img{width:100%;height:100%;object-fit:cover}
.prod-thumb i{font-size:3rem;color:var(--gold);opacity:.8}
.prod-badge{position:absolute;top:10px;left:10px;background:var(--teal);color:#fff;font-size:.7rem;font-weight:700;padding:3px 8px;border-radius:20px}
.prod-badge.feat{left:auto;right:10px;background:var(--gold);color:#1a1a1a}
.prod-price{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-top:6px}
.prod-price b{font-size:1.3rem;color:var(--gold)}
.prod-price s{color:var(--muted);font-size:.9rem}
.prod-price small{width:100%;color:var(--muted);font-size:.78rem}
.prod-stock{font-size:.82rem;margin-top:4px}
.prod-stock .ok{color:var(--teal)}
.prod-stock .out{color:#d06a5f}
</style>
@endpush
