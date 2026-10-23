@extends('layouts.site')

@section('title', 'Seu carrinho — Loja Golden')
@section('meta_description', 'Revise os equipamentos e chips antes de finalizar o pedido na loja Golden.')

@section('content')
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Carrinho</span>
        <h1 style="font-size:1.9rem;margin:.2rem 0">{{ count($linhas) }} {{ count($linhas) === 1 ? 'item' : 'itens' }} na sua sacola.</h1>
        <p class="lead-sub">Ajuste as quantidades quando quiser — nada é cobrado antes de você escolher a entrega e o pagamento.</p>

        @if($linhas === [])
        <div class="cart-empty">
            <i class="fa-solid fa-basket-shopping"></i>
            <p>Seu carrinho está vazio.</p>
            <a href="{{ route('site.loja.index') }}" class="btn btn-gold">Ver a loja</a>
        </div>
        @else
        <form method="POST" action="{{ route('site.loja.carrinho.atualizar') }}" class="cart-form">
            @csrf
            <ul class="cart-list">
                @foreach($linhas as $l)
                <li class="cart-line">
                    <a class="cart-thumb" href="{{ route('site.loja.produto', $l['slug']) }}">
                        @if($l['imagem'])<img src="{{ asset('storage/' . $l['imagem']) }}" alt="{{ $l['nome'] }}">@else<i class="fa-solid fa-satellite-dish"></i>@endif
                    </a>
                    <div class="cart-info">
                        <a href="{{ route('site.loja.produto', $l['slug']) }}"><b>{{ $l['nome'] }}</b></a>
                        <small>{{ $l['categoria'] }}@if($l['sku']) · {{ $l['sku'] }}@endif</small>
                        <span class="cart-unit">{{ brl($l['preco']) }} cada</span>
                    </div>
                    <div class="qty">
                        <button type="submit" name="quantidades[{{ $l['id'] }}]" value="{{ $l['quantidade'] - 1 }}" aria-label="Diminuir quantidade de {{ $l['nome'] }}">−</button>
                        <input type="number" name="quantidades[{{ $l['id'] }}]" min="0" max="{{ $l['controle_estoque'] ? $l['estoque'] : 999 }}" value="{{ $l['quantidade'] }}" aria-label="Quantidade de {{ $l['nome'] }}">
                        <button type="submit" name="quantidades[{{ $l['id'] }}]" value="{{ $l['quantidade'] + 1 }}" aria-label="Aumentar quantidade de {{ $l['nome'] }}">+</button>
                    </div>
                    <b class="cart-total">{{ brl($l['subtotal']) }}</b>
                    <button class="cart-del" type="submit" name="quantidades[{{ $l['id'] }}]" value="0" aria-label="Remover {{ $l['nome'] }}"><i class="fa-solid fa-trash-can"></i></button>
                </li>
                @endforeach
            </ul>

            <div class="cart-foot">
                <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate"></i> Atualizar quantidades</button>
                <a href="{{ route('site.loja.index') }}" class="btn btn-ghost btn-sm">Continuar comprando</a>
            </div>
        </form>

        <aside class="cart-summary">
            <h2>Resumo</h2>
            <div class="sum-line"><span>Subtotal ({{ array_sum(array_column($linhas, 'quantidade')) }} itens)</span><b>{{ brl($subtotal) }}</b></div>
            <div class="sum-line"><span>Frete</span><b>{{ $frete > 0 ? brl($frete) : 'Grátis' }}</b></div>
            @if($freteGratisEm > 0)
            <p class="sum-hint"><i class="fa-solid fa-gift" style="color:var(--gold)"></i> Faltam {{ brl($freteGratisEm) }} para o frete grátis.</p>
            @endif
            <a href="{{ route('site.loja.checkout') }}" class="btn btn-gold" data-farol="checkout" style="width:100%;margin-top:14px">Finalizar pedido →</a>
            <p class="fine">Pagamento na etapa seguinte: Pix, cartão ou boleto. Retirada na loja quando disponível.</p>
        </aside>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<style>
.cart-list{list-style:none;margin:22px 0 0;padding:0}
.cart-line{display:grid;grid-template-columns:72px 1fr auto auto auto;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid rgba(0,0,0,.08)}
.cart-thumb{width:72px;height:72px;border-radius:12px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden}
.cart-thumb img{width:100%;height:100%;object-fit:cover}
.cart-thumb i{color:var(--gold);font-size:1.5rem}
.cart-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.cart-info a{color:var(--ink);text-decoration:none;font-size:1rem}
.cart-info small{color:var(--muted);font-size:.78rem}
.cart-unit{font-size:.82rem;color:var(--muted)}
.qty{display:flex;align-items:center;border:1px solid rgba(0,0,0,.15);border-radius:10px;overflow:hidden}
.qty button{width:34px;height:36px;border:0;background:transparent;font-size:1.1rem;cursor:pointer;color:var(--ink)}
.qty input{width:52px;text-align:center;border:0;font-size:.95rem}
.cart-total{color:var(--gold);font-size:1.05rem;white-space:nowrap}
.cart-del{border:0;background:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:6px}
.cart-del:hover{color:#d06a5f}
.cart-foot{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.cart-summary{margin-top:26px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px 20px;max-width:420px;margin-left:auto}
.cart-summary h2{font-size:1.1rem;margin:0 0 10px}
.sum-line{display:flex;justify-content:space-between;padding:7px 0;font-size:.95rem;border-bottom:1px dashed rgba(0,0,0,.08)}
.sum-line b{color:var(--ink)}
.sum-hint{font-size:.82rem;color:var(--muted);margin:10px 0 0}
.cart-empty{text-align:center;padding:44px 0;color:var(--muted)}
.cart-empty i{font-size:2.6rem;color:var(--gold);opacity:.6;display:block;margin-bottom:10px}
@media(max-width:720px){
  .cart-line{grid-template-columns:56px 1fr auto;gap:10px}
  .cart-thumb{width:56px;height:56px}
  .cart-total{grid-column:2;text-align:right}
  .cart-del{grid-column:3;grid-row:2}
}
</style>
@endpush
