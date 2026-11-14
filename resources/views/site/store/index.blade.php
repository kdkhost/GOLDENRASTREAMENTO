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
            @php
                $fotos = !empty($p['imagens']) ? $p['imagens'] : (!empty($p['imagem']) ? [$p['imagem']] : []);
                $totalFotos = count($fotos);
            @endphp
            <article class="card prod-card" data-card-prod>
                <div class="prod-thumb" data-prod-thumb>
                    @if($totalFotos > 0)
                        <div class="prod-slides" data-slides>
                            @foreach($fotos as $idx => $foto)
                                <img src="{{ produto_foto_url($foto) }}" alt="{{ $p['nome'] }}" class="prod-slide-img {{ $idx === 0 ? 'active' : '' }}" data-slide-index="{{ $idx }}" loading="lazy">
                            @endforeach
                        </div>
                        @if($totalFotos > 1)
                            <div class="prod-dots" data-dots>
                                @foreach($fotos as $idx => $foto)
                                    <button type="button" class="prod-dot {{ $idx === 0 ? 'active' : '' }}" data-dot="{{ $idx }}" aria-label="Foto {{ $idx + 1 }} de {{ $p['nome'] }}"></button>
                                @endforeach
                            </div>
                            <span class="prod-count-badge" title="Este produto possui {{ $totalFotos }} fotos"><i class="fa-solid fa-camera"></i> {{ $totalFotos }}</span>
                        @endif
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
.prod-thumb{position:relative;height:210px;border-radius:14px;background:linear-gradient(160deg,#1b2127,#0d1114);display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px;border:1px solid rgba(255,255,255,.06)}
.prod-slides{width:100%;height:100%;position:relative;display:flex;align-items:center;justify-content:center}
.prod-slide-img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:8px;opacity:0;transition:opacity .25s ease-in-out;pointer-events:none}
.prod-slide-img.active{opacity:1;pointer-events:auto}
.prod-thumb i{font-size:3rem;color:var(--gold);opacity:.8}
.prod-badge{position:absolute;top:10px;left:10px;background:var(--teal);color:#fff;font-size:.7rem;font-weight:700;padding:3px 8px;border-radius:20px;z-index:3}
.prod-badge.feat{left:auto;right:10px;background:var(--gold);color:#1a1a1a}
.prod-count-badge{position:absolute;top:10px;right:10px;background:rgba(0,0,0,.65);color:#fff;font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:14px;backdrop-filter:blur(4px);z-index:2;display:flex;align-items:center;gap:4px}
.prod-dots{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);display:flex;gap:5px;padding:3px 7px;background:rgba(0,0,0,.5);border-radius:12px;backdrop-filter:blur(4px);z-index:3}
.prod-dot{width:7px;height:7px;border-radius:50%;border:0;background:rgba(255,255,255,.45);padding:0;cursor:pointer;transition:background .2s,transform .2s}
.prod-dot.active{background:var(--gold);transform:scale(1.3)}
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-card-prod]').forEach(function (card) {
    var thumb = card.querySelector('[data-prod-thumb]');
    if (!thumb) return;
    var imgs = thumb.querySelectorAll('.prod-slide-img');
    var dots = thumb.querySelectorAll('.prod-dot');
    if (imgs.length <= 1) return;

    var cur = 0;
    function setIndex(idx) {
      if (idx < 0 || idx >= imgs.length) return;
      cur = idx;
      imgs.forEach(function (img, i) { img.classList.toggle('active', i === cur); });
      dots.forEach(function (dot, i) { dot.classList.toggle('active', i === cur); });
    }

    dots.forEach(function (dot) {
      dot.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setIndex(parseInt(this.dataset.dot, 10));
      });
      dot.addEventListener('mouseenter', function () {
        setIndex(parseInt(this.dataset.dot, 10));
      });
    });

    // Ao passar o mouse pelo card, alterna sutilmente entre as fotos
    thumb.addEventListener('mousemove', function (e) {
      var rect = thumb.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var ratio = x / rect.width;
      var targetIdx = Math.min(imgs.length - 1, Math.max(0, Math.floor(ratio * imgs.length)));
      if (targetIdx !== cur) {
        setIndex(targetIdx);
      }
    });

    card.addEventListener('mouseleave', function () {
      setIndex(0);
    });
  });
});
</script>
@endpush
