@extends('layouts.site')

@section('title', 'Pedido ' . $pedido->numero . ' confirmado — Golden')

@section('content')
<section class="sec-light">
    <div class="wrap" style="max-width:720px">
        <div class="order-ok">
            <div class="order-check"><i class="fa-solid fa-check"></i></div>
            <h1 style="font-size:1.7rem;margin:.6rem 0">Pedido registrado!</h1>
            <p class="lead-sub">Obrigado, {{ $pedido->nome }}. Seu pedido <strong>{{ $pedido->numero }}</strong> foi recebido. Nossa equipe vai confirmar o pagamento e combinar a entrega/instalação pelo WhatsApp.</p>

            <div class="order-box">
                @foreach($pedido->items as $item)
                <div class="order-line"><span>{{ $item->quantidade }}× {{ $item->nome }}</span><b>{{ brl($item->subtotal_cents) }}</b></div>
                @endforeach
                @if($pedido->frete_cents > 0)
                <div class="order-line"><span>Frete</span><b>{{ brl($pedido->frete_cents) }}</b></div>
                @endif
                <div class="order-line total"><span>Total</span><b>{{ brl($pedido->total_cents) }}</b></div>
                <div class="order-line pay"><span>Pagamento</span><b>{{ $pedido->pagamentoLabel() }}</b></div>
                <div class="order-line pay"><span>Status</span><b>{{ $pedido->statusLabel() }}</b></div>
            </div>

            <div class="order-actions">
                <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Acabei de fazer o pedido ' . $pedido->numero . ' na loja Golden.') }}" target="_blank" rel="noopener" class="btn btn-gold"><i class="fa-brands fa-whatsapp"></i> Falar sobre meu pedido</a>
                <a href="{{ route('site.loja.index') }}" class="btn btn-ghost">Voltar para a loja</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<style>
.order-ok{text-align:center;padding:20px 0}
.order-check{width:72px;height:72px;border-radius:50%;background:var(--teal);color:#fff;display:grid;place-items:center;font-size:2rem;margin:0 auto 8px}
.order-box{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px 20px;margin:22px 0;text-align:left}
.order-line{display:flex;justify-content:space-between;padding:8px 0;font-size:.95rem;border-bottom:1px dashed rgba(0,0,0,.08)}
.order-line b{color:var(--ink)}
.order-line.total{border-bottom:0;padding-top:12px;font-size:1.15rem}
.order-line.total b{color:var(--gold)}
.order-line.pay{font-size:.85rem;color:var(--muted);border-bottom:0}
.order-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
</style>
@endpush
