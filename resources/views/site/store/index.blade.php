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
            <article class="card prod-card">
                <div class="prod-thumb">
                    @if($p['imagem'])
                        <img src="{{ asset('storage/' . $p['imagem']) }}" alt="{{ $p['nome'] }}">
                    @else
                        <i class="fa-solid fa-satellite-dish"></i>
                    @endif
                    @if($p['promocao'])<span class="prod-badge">Promoção</span>@endif
                    @if($p['destaque'])<span class="prod-badge feat">Destaque</span>@endif
                </div>
                <h3 style="font-size:1.1rem;margin:.4rem 0">{{ $p['nome'] }}</h3>
                @if($p['resumo'])<p style="font-size:.9rem;color:var(--muted);min-height:2.4em">{{ $p['resumo'] }}</p>@endif
                <div class="prod-price">
                    @if($p['promocao'])<s>{{ brl($p['preco_cents']) }}</s>@endif
                    @if($p['preco'] > 0)
                        <b>{{ brl($p['preco']) }}</b>
                        @if($p['mensalidade'])<small>+ {{ brl($p['mensalidade']) }}/mês</small>@endif
                    @elseif($p['mensalidade'])
                        <b>{{ brl($p['mensalidade']) }}/mês</b>
                    @else
                        <b>Consulte</b>
                    @endif
                </div>
                <div class="prod-stock">
                    @if(!$p['controle_estoque'])
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> Pronta entrega sob consulta</span>
                    @elseif($p['estoque'] > 0)
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> {{ $p['estoque'] }} em estoque</span>
                    @else
                        <span class="out"><i class="fa-solid fa-circle-xmark"></i> Sem estoque</span>
                    @endif
                </div>
                <div class="prod-actions">
                    <a href="{{ route('site.loja.produto', $p['slug']) }}" class="btn btn-ghost">Detalhes</a>
                    @if($p['disponivel'])
                    <form method="POST" action="{{ route('site.loja.carrinho.adicionar') }}" data-farol="sacola" data-detalhe="{{ $p['slug'] }}">
                        @csrf
                        <input type="hidden" name="produto" value="{{ $p['id'] }}">
                        <input type="hidden" name="quantidade" value="1">
                        <button type="submit" class="btn btn-gold">Adicionar</button>
                    </form>
                    @else
                    <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Quero encomendar o ' . $p['nome'] . ' da loja Golden.') }}" target="_blank" rel="noopener" class="btn btn-ghost"><i class="fa-brands fa-whatsapp"></i></a>
                    @endif
                </div>
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
.prod-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:12px}
/* Cada botão tem texto inteiro e não encolhe: na tela de celular os dois lado a lado
   passavam da largura do cartão, e o documento ganhava rolagem horizontal. A linha quebra
   e cada botão passa a ocupar o cartão inteiro. */
.prod-actions form{flex:1 1 120px;min-width:0}
.prod-actions .btn{width:100%}
</style>
@endpush
