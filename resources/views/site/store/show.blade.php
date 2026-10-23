@extends('layouts.site')

@section('title', $produto['nome'] . ' — Loja Golden')
@section('meta_description', $produto['resumo'] ?: $produto['nome'])

@section('content')
<section class="sec-light">
    <div class="wrap">
        <a href="{{ route('site.loja.index') }}" style="color:var(--muted);text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Voltar para a loja</a>
        <div class="hero-grid" style="margin-top:16px">
            <div class="prod-media">
                @if($produto['imagem'])
                    <img src="{{ asset('storage/' . $produto['imagem']) }}" alt="{{ $produto['nome'] }}">
                @else
                    <i class="fa-solid fa-satellite-dish"></i>
                @endif
            </div>
            <div>
                @if($produto['categoria'])<span class="eyebrow">{{ $produto['categoria'] }}</span>@endif
                <h1 style="font-size:1.9rem;margin:.2rem 0">{{ $produto['nome'] }}</h1>
                @if($produto['sku'])<p style="color:var(--muted);font-size:.85rem">SKU/Código: {{ $produto['sku'] }}</p>@endif

                <div class="prod-price" style="margin:14px 0">
                    @if($produto['promocao'])<s>{{ brl($produto['preco_cents']) }}</s>@endif
                    @if($produto['preco'] > 0)
                        <b style="font-size:2rem">{{ brl($produto['preco']) }}</b>
                    @elseif($produto['mensalidade'])
                        <b style="font-size:2rem">{{ brl($produto['mensalidade']) }}/mês</b>
                    @else
                        <b style="font-size:2rem">Consulte</b>
                    @endif
                    @if($produto['mensalidade'] && $produto['preco'] > 0)<small style="display:block">Monitoramento: {{ brl($produto['mensalidade']) }}/mês</small>@endif
                </div>

                <p style="color:var(--muted)">{{ $produto['resumo'] }}</p>
                @if($produto['descricao'])
                <div class="prod-desc">{!! nl2br(e($produto['descricao'])) !!}</div>
                @endif

                <div class="prod-stock" style="font-size:.9rem;margin-top:14px">
                    @if(!$produto['controle_estoque'])
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> Pronta entrega, volume sob consulta</span>
                    @elseif($produto['estoque'] > 0)
                        <span class="ok"><i class="fa-solid fa-circle-check"></i> {{ $produto['estoque'] }} unidade(s) em estoque</span>
                    @else
                        <span class="out"><i class="fa-solid fa-circle-xmark"></i> Sem estoque no momento</span>
                    @endif
                </div>

                <div class="prod-meta">
                    <span><i class="fa-solid fa-shield-halved" style="color:var(--gold)"></i> Homologado Anatel</span>
                    <span><i class="fa-solid fa-credit-card" style="color:var(--gold)"></i> Pix, cartão ou boleto</span>
                    <span><i class="fa-solid fa-truck-fast" style="color:var(--gold)"></i> Entrega ou retirada</span>
                    <span><i class="fa-solid fa-sim-card" style="color:var(--gold)"></i> Chips: {{ implode(', ', $golden['parceiros']) }}</span>
                </div>

                @if($produto['disponivel'])
                <form method="POST" action="{{ route('site.loja.carrinho.adicionar') }}" class="buy-form" data-unit="{{ $produto['preco'] }}">
                    @csrf
                    <input type="hidden" name="produto" value="{{ $produto['id'] }}">
                    <div class="quantity-row">
                        <span>Quantidade</span>
                        <div class="qty">
                            <button type="button" class="qminus" aria-label="Diminuir">−</button>
                            <input type="number" name="quantidade" min="1" max="{{ $produto['controle_estoque'] ? max(1, $produto['estoque']) : 999 }}" value="1" required>
                            <button type="button" class="qplus" aria-label="Aumentar">+</button>
                        </div>
                        <strong class="line-total">{{ brl($produto['preco']) }}</strong>
                    </div>
                    <div class="buy-actions">
                        <button type="submit" name="ir" value="carrinho" class="btn btn-outline">Adicionar ao carrinho</button>
                        <button type="submit" name="ir" value="checkout" class="btn btn-gold" data-track="generate_lead" data-cta="buy">Comprar agora →</button>
                    </div>
                </form>
                @else
                <div class="notice-warn" style="margin-top:18px"><i class="fa-solid fa-circle-info"></i> Produto esgotado. Fale no WhatsApp para encomendar a reposição.</div>
                <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Quero encomendar o ' . $produto['nome'] . ' da loja Golden.') }}" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:10px"><i class="fa-brands fa-whatsapp"></i> Encomendar</a>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="sec-alt">
    <div class="wrap steps-mini">
        <div><i class="fa-solid fa-basket-shopping"></i><b>Monte a sacola</b><span>Adicione itens e ajuste a quantidade antes de finalizar.</span></div>
        <div><i class="fa-solid fa-file-invoice-dollar"></i><b>Informe a entrega</b><span>Endereço com CEP ou retirada na loja, com CPF/CNPJ.</span></div>
        <div><i class="fa-solid fa-circle-check"></i><b>Acompanhe</b><span>Status, timeline e link privado do seu pedido.</span></div>
    </div>
</section>

@if($relacionados->isNotEmpty())
<section class="sec-light">
    <div class="wrap">
        <h3 style="font-size:1.2rem;margin-bottom:14px">Você também pode querer</h3>
        <div class="grid-3">
            @foreach($relacionados as $r)
            <a href="{{ route('site.loja.produto', $r['slug']) }}" class="card prod-card" style="text-decoration:none;color:inherit">
                <div class="prod-thumb">@if($r['imagem'])<img src="{{ asset('storage/' . $r['imagem']) }}" alt="{{ $r['nome'] }}">@else<i class="fa-solid fa-satellite-dish"></i>@endif</div>
                <h3 style="font-size:1rem">{{ $r['nome'] }}</h3>
                <div class="prod-price"><b>{{ brl($r['preco']) }}</b></div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection

@push('scripts')
<style>
.prod-media{height:320px;border-radius:18px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
.prod-media img{width:100%;height:100%;object-fit:cover}
.prod-media i{font-size:5rem;color:var(--gold);opacity:.85}
.prod-desc{margin-top:12px;color:var(--muted);font-size:.95rem;line-height:1.6}
.prod-meta{display:flex;flex-wrap:wrap;gap:10px 18px;margin-top:16px;font-size:.85rem;color:var(--muted)}
.prod-price s{color:var(--muted);margin-right:8px}
.prod-price b{color:var(--gold);font-size:1.8rem}
.prod-price small{color:var(--muted);font-size:.82rem}
.prod-stock .ok{color:var(--teal)}
.prod-stock .out{color:#d06a5f}
.notice-warn{background:rgba(224,163,62,.1);border:1px solid rgba(224,163,62,.3);border-radius:12px;padding:12px 14px;font-size:.9rem;color:var(--gold)}
.buy-form{margin-top:18px}
.quantity-row{display:flex;align-items:center;gap:14px}
.quantity-row span{color:var(--muted);font-size:.9rem}
.qty{display:flex;align-items:center;border:1px solid rgba(0,0,0,.15);border-radius:10px;overflow:hidden}
.qty button{width:38px;height:38px;border:0;background:transparent;font-size:1.2rem;cursor:pointer;color:var(--ink)}
.qty input{width:56px;text-align:center;border:0;font-size:1rem}
.line-total{margin-left:auto;color:var(--gold);font-size:1.15rem}
.buy-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
.steps-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.steps-mini div{display:flex;flex-direction:column;gap:4px}
.steps-mini i{color:var(--gold);font-size:1.2rem}
.steps-mini b{font-size:.98rem}
.steps-mini span{font-size:.85rem;color:var(--muted)}
@media(max-width:720px){.steps-mini{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('.buy-form');if(!form)return;
  var unit=parseInt(form.dataset.unit,10)||0;
  var inp=form.querySelector('input[name="quantidade"]');
  var total=form.querySelector('.line-total');
  var teto=parseInt(inp.max,10)||999;
  function fmt(c){return 'R$ '+(c/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function recalc(){var q=Math.min(teto,Math.max(1,parseInt(inp.value,10)||1));total.textContent=fmt(unit*q);}
  inp.addEventListener('input',recalc);
  form.querySelector('.qminus').addEventListener('click',function(){inp.value=Math.max(1,(parseInt(inp.value,10)||1)-1);recalc();});
  form.querySelector('.qplus').addEventListener('click',function(){inp.value=Math.min(teto,(parseInt(inp.value,10)||1)+1);recalc();});
});
</script>
@endpush
