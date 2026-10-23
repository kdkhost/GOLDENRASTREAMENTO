@extends('layouts.site')

@section('title', 'Pedido ' . $pedido->numero . ' — Golden')
@section('meta_description', 'Acompanhe a situação do pedido ' . $pedido->numero . ' da loja Golden.')

@section('content')
<section class="sec-light">
    <div class="wrap order-status">
        <div class="order-head">
            <div class="order-check"><i class="fa-solid fa-check"></i></div>
            <div>
                <span class="eyebrow">Pedido {{ $pedido->numero }}</span>
                <h1 style="font-size:1.7rem;margin:.2rem 0">Obrigado, {{ $pedido->nome }}!</h1>
                <p class="lead-sub">Recebemos sua compra. Confirmamos o pagamento por {{ $pedido->pagamentoLabel() }} e combinamos {{ $pedido->entrega === 'retira' ? 'a retirada' : 'a entrega' }} — tudo por aqui, sem login.</p>
            </div>
            <span class="order-badge st-{{ $pedido->status }}">{{ $pedido->statusLabel() }}</span>
        </div>

        <div class="order-grid">
            <div>
                <h2 class="order-h">Linha do tempo</h2>
                <ol class="timeline">
                    @foreach($pedido->jornada() as $etapa)
                    <li class="{{ $etapa['quando'] ? 'done' : '' }} {{ $etapa['atual'] ? 'now' : '' }}">
                        <span class="dot"></span>
                        <b>{{ $etapa['titulo'] }}</b>
                        <small>{{ $etapa['quando'] ? $etapa['quando']->format('d/m/Y \a\s H:i') : 'Aguardando' }}</small>
                    </li>
                    @endforeach
                </ol>

                @if($pedido->histories->whereNotNull('observacao')->isNotEmpty())
                <h2 class="order-h">Novidades do pedido</h2>
                <ul class="order-notes">
                    @foreach($pedido->histories->filter(fn ($h) => $h->observacao)->sortByDesc('created_at') as $h)
                    <li><span>{{ $h->created_at->format('d/m/Y H:i') }}</span>{{ $h->observacao }}</li>
                    @endforeach
                </ul>
                @endif

                <h2 class="order-h">Entrega e pagamento</h2>
                <table class="order-info">
                    <tr><th>Modalidade</th><td>{{ $pedido->entregaLabel() }}</td></tr>
                    @if($pedido->entrega !== 'retira')
                    <tr><th>Endereço</th><td>{{ $pedido->enderecoFormatado() }}</td></tr>
                    @endif
                    <tr><th>Pagamento</th><td>{{ $pedido->pagamentoLabel() }}@if($pedido->pagamento === 'pix' && $pixChave)<br><small>Chave Pix: <b>{{ $pixChave }}</b></small>@endif</td></tr>
                    <tr><th>Comprador</th><td>{{ $pedido->documentoFormatado() }} ({{ $pedido->tipo_pessoa === 'juridica' ? 'pessoa jurídica' : 'pessoa física' }})</td></tr>
                    @if($pedido->notas)
                    <tr><th>Observações</th><td>{{ $pedido->notas }}</td></tr>
                    @endif
                </table>
            </div>

            <aside class="order-side">
                <h2 class="order-h">Resumo</h2>
                <div class="order-box">
                    @foreach($pedido->items as $item)
                    <div class="order-line"><span>{{ $item->quantidade }}× {{ $item->nome }}</span><b>{{ brl($item->subtotal_cents) }}</b></div>
                    @endforeach
                    <div class="order-line"><span>Subtotal</span><b>{{ brl($pedido->subtotal_cents) }}</b></div>
                    <div class="order-line"><span>Frete</span><b>{{ $pedido->frete_cents > 0 ? brl($pedido->frete_cents) : 'Grátis' }}</b></div>
                    @if($pedido->desconto_cents > 0)
                    <div class="order-line"><span>Desconto</span><b>– {{ brl($pedido->desconto_cents) }}</b></div>
                    @endif
                    <div class="order-line total"><span>Total</span><b>{{ brl($pedido->total_cents) }}</b></div>
                </div>

                <label class="order-link">Guarde este link para rever o pedido
                    <div class="copy-row">
                        <input type="text" readonly value="{{ url()->current() }}" id="orderLink">
                        <button type="button" class="btn btn-ghost btn-sm" id="copyLink"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </label>

                <div class="order-actions">
                    <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Sou cliente da loja Golden, pedido ' . $pedido->numero . '.') }}" target="_blank" rel="noopener" class="btn btn-gold"><i class="fa-brands fa-whatsapp"></i> Falar sobre meu pedido</a>
                    <a href="{{ route('site.loja.index') }}" class="btn btn-ghost">Voltar para a loja</a>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<style>
.order-head{display:flex;gap:18px;align-items:center;flex-wrap:wrap}
.order-check{width:56px;height:56px;border-radius:50%;background:var(--teal);color:#fff;display:grid;place-items:center;font-size:1.5rem;flex:none}
.order-head>div{flex:1;min-width:220px}
.order-badge{background:rgba(224,163,62,.14);color:var(--gold);font-size:.8rem;font-weight:700;padding:6px 12px;border-radius:20px;white-space:nowrap}
.order-badge.st-entregue{background:rgba(31,158,134,.14);color:var(--teal)}
.order-badge.st-cancelado{background:rgba(208,106,95,.14);color:#d06a5f}
.order-grid{display:grid;grid-template-columns:1.25fr .9fr;gap:32px;margin-top:26px;align-items:start}
.order-h{font-size:1rem;margin:0 0 12px;font-family:"Poppins",sans-serif}
.timeline{list-style:none;margin:0 0 26px;padding:0;position:relative}
.timeline::before{content:'';position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:rgba(0,0,0,.1)}
.timeline li{position:relative;padding:0 0 16px 26px}
.timeline .dot{position:absolute;left:0;top:4px;width:16px;height:16px;border-radius:50%;background:#fff;border:2px solid rgba(0,0,0,.18)}
.timeline .done .dot{border-color:var(--teal);background:var(--teal)}
.timeline .now .dot{border-color:var(--gold);background:var(--gold);box-shadow:0 0 0 4px rgba(224,163,62,.18)}
.timeline b{display:block;font-size:.95rem}
.timeline small{color:var(--muted);font-size:.8rem}
.order-notes{list-style:none;margin:0 0 26px;padding:0}
.order-notes li{display:flex;gap:12px;font-size:.9rem;padding:8px 0;border-bottom:1px dashed rgba(0,0,0,.1)}
.order-notes span{color:var(--muted);font-size:.78rem;white-space:nowrap;padding-top:2px}
.order-info{width:100%;border-collapse:collapse;font-size:.92rem;margin-bottom:10px}
.order-info th{text-align:left;color:var(--muted);font-weight:500;padding:8px 12px 8px 0;white-space:nowrap;vertical-align:top}
.order-info td{padding:8px 0;border-bottom:1px dashed rgba(0,0,0,.08)}
.order-side{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:18px;padding:18px 20px}
.order-box{margin-bottom:16px}
.order-line{display:flex;justify-content:space-between;gap:12px;padding:7px 0;font-size:.92rem;border-bottom:1px dashed rgba(0,0,0,.08)}
.order-line.total{border-bottom:0;padding-top:12px;font-size:1.1rem}
.order-line.total b{color:var(--gold)}
.order-link{display:block;font-size:.8rem;color:var(--muted);margin-bottom:14px}
.copy-row{display:flex;gap:8px;margin-top:6px}
.copy-row input{flex:1;min-width:0;border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:8px 10px;font-size:.78rem;color:var(--ink);background:#fafafa}
.order-actions{display:flex;flex-direction:column;gap:10px}
@media(max-width:900px){.order-grid{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var btn=document.getElementById('copyLink'),inp=document.getElementById('orderLink');
  if(!btn||!inp)return;
  btn.addEventListener('click',function(){
    inp.select();
    var done=function(){btn.innerHTML='<i class="fa-solid fa-check"></i>';setTimeout(function(){btn.innerHTML='<i class="fa-regular fa-copy"></i>';},1600);};
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(inp.value).then(done,done);}
    else{try{document.execCommand('copy');}catch(e){}done();}
  });
});
</script>
@endpush
