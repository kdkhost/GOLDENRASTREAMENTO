@extends('layouts.site')

@section('title', $produto->nome . ' — Loja Golden')
@section('meta_description', $produto->resumo ?: $produto->nome)

@section('content')
@php $qtdAtual = max(1, old('produtos.0.quantidade', 1)); $totalAtual = $produto->preco() * $qtdAtual; @endphp
<section class="sec-light">
    <div class="wrap">
        <a href="{{ route('site.loja.index') }}" style="color:var(--muted);text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Voltar para a loja</a>
        <div class="hero-grid" style="margin-top:16px">
            <div class="prod-media">
                @if($produto->imagem)
                    <img src="{{ asset('storage/' . $produto->imagem) }}" alt="{{ $produto->nome }}">
                @else
                    <i class="fa-solid fa-satellite-dish"></i>
                @endif
            </div>
            <div>
                @if($produto->category)<span class="eyebrow">{{ $produto->category->nome }}</span>@endif
                <h1 style="font-size:1.9rem;margin:.2rem 0">{{ $produto->nome }}</h1>
                @if($produto->sku)<p style="color:var(--muted);font-size:.85rem">SKU/Código: {{ $produto->sku }}</p>@endif

                <div class="prod-price" style="margin:14px 0">
                    @if($produto->temPromocao())<s>{{ brl($produto->preco_cents) }}</s>@endif
                    <b style="font-size:2rem">{{ brl($produto->preco()) }}</b>
                    @if($produto->mensalidade_cents)<small style="display:block">Monitoramento: {{ brl($produto->mensalidade_cents) }}/mês</small>@endif
                </div>

                <p style="color:var(--muted)">{{ $produto->resumo }}</p>
                @if($produto->descricao)
                <div class="prod-desc">{!! nl2br(e($produto->descricao)) !!}</div>
                @endif

                <div class="prod-meta">
                    <span><i class="fa-solid fa-shield-halved" style="color:var(--gold)"></i> Homologado Anatel</span>
                    <span><i class="fa-solid fa-credit-card" style="color:var(--gold)"></i> {{ implode(' ou ', $golden['pagamentos']) }}</span>
                    <span><i class="fa-solid fa-sim-card" style="color:var(--gold)"></i> Chips: {{ implode(', ', $golden['parceiros']) }}</span>
                </div>

                @if($produto->controle_estoque && $produto->estoque <= 0)
                <div class="notice-warn" style="margin-top:18px"><i class="fa-solid fa-circle-info"></i> Produto esgotado. Fale no WhatsApp para encomendar a reposição.</div>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:10px"><i class="fa-brands fa-whatsapp"></i> Encomendar</a>
                @endif
            </div>
        </div>
    </div>
</section>

@if($produto->disponivel())
<section class="sec-alt" id="comprar">
    <div class="wrap lead-panel">
        <div>
            <span class="eyebrow">Finalizar compra</span>
            <h2 style="font-size:1.6rem">Receba o {{ $produto->nome }}.</h2>
            <p class="lead-sub">Preencha seus dados e escolha a forma de pagamento. Confirmamos o pedido pelo WhatsApp e combinamos instalação/envio.</p>
            <div style="margin-top:16px">
                <i class="fa-solid fa-location-dot" style="color:var(--gold)"></i>
                <span style="font-size:.9rem">{{ $golden['endereco'] }} — {{ $golden['cidade_uf'] }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('site.loja.checkout') }}" class="buy-form" data-total-unit="{{ $produto->preco() }}">
            @csrf
            <input type="hidden" name="produtos[0][product_id]" value="{{ $produto->id }}">
            <div class="field-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="grid-2">
                <label>Nome completo<input name="nome" required value="{{ old('nome') }}"></label>
                <label>WhatsApp / Telefone<input name="telefone" required value="{{ old('telefone') }}" placeholder="(00) 00000-0000"></label>
            </div>
            <label>E-mail (opcional)<input type="email" name="email" value="{{ old('email') }}"></label>
            <div class="quantity-row">
                <span>Quantidade</span>
                <div class="qty">
                    <button type="button" class="qminus" aria-label="Diminuir">−</button>
                    <input type="number" name="produtos[0][quantidade]" min="1" max="999" value="{{ $qtdAtual }}" required>
                    <button type="button" class="qplus" aria-label="Aumentar">+</button>
                </div>
                <strong class="line-total">{{ brl($totalAtual) }}</strong>
            </div>
            <div class="grid-2">
                <label>CEP<input name="cep" value="{{ old('cep') }}" placeholder="00000-000"></label>
                <label>Cidade<input name="cidade" required value="{{ old('cidade') }}"></label>
            </div>
            <div class="grid-2">
                <label>UF<input name="uf" maxlength="2" required value="{{ old('uf') }}" placeholder="MG"></label>
                <label>&nbsp;</label>
            </div>
            <label>Endereço (rua, número, bairro)<input name="endereco" required value="{{ old('endereco') }}"></label>
            <label>Pagamento
                <select name="pagamento" required>
                    <option value="cartao" @selected(old('pagamento') === 'cartao')>Cartão (na entrega/link)</option>
                    <option value="boleto" @selected(old('pagamento') === 'boleto')>Boleto bancário</option>
                    <option value="pix" @selected(old('pagamento') === 'pix')>Pix</option>
                </select>
            </label>
            <label>Observações<textarea name="notas" rows="2" placeholder="Ex.: tipo de veículo, placa, preferência de instalação">{{ old('notas') }}</textarea></label>
            @error('produtos')<p class="form-err">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-gold" style="width:100%;margin-top:8px" data-track="generate_lead" data-cta="buy">Enviar pedido →</button>
            <p class="fine" style="margin-top:10px"><i class="fa-solid fa-lock" style="color:var(--teal)"></i> Seus dados são usados apenas para processar o pedido e o contato comercial.</p>
        </form>
    </div>
</section>
@endif

@if($relacionados->isNotEmpty())
<section class="sec-light">
    <div class="wrap">
        <h3 style="font-size:1.2rem;margin-bottom:14px">Você também pode querer</h3>
        <div class="grid-3">
            @foreach($relacionados as $r)
            <a href="{{ route('site.loja.produto', $r->slug) }}" class="card prod-card" style="text-decoration:none;color:inherit">
                <div class="prod-thumb">@if($r->imagem)<img src="{{ asset('storage/' . $r->imagem) }}" alt="{{ $r->nome }}">@else<i class="fa-solid fa-satellite-dish"></i>@endif</div>
                <h3 style="font-size:1rem">{{ $r->nome }}</h3>
                <div class="prod-price"><b>{{ brl($r->preco()) }}</b></div>
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
.notice-warn{background:rgba(224,163,62,.1);border:1px solid rgba(224,163,62,.3);border-radius:12px;padding:12px 14px;font-size:.9rem;color:var(--gold)}
.buy-form .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.quantity-row{display:flex;align-items:center;gap:14px;margin:14px 0}
.quantity-row span{color:var(--muted);font-size:.9rem}
.qty{display:flex;align-items:center;border:1px solid rgba(0,0,0,.15);border-radius:10px;overflow:hidden}
.qty button{width:38px;height:38px;border:0;background:transparent;font-size:1.2rem;cursor:pointer;color:var(--ink)}
.qty input{width:56px;text-align:center;border:0;font-size:1rem}
.line-total{margin-left:auto;color:var(--gold);font-size:1.15rem}
.field-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.form-err{color:#d06a5f;font-size:.85rem;margin:.4rem 0}
@media(max-width:720px){.buy-form .grid-2{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('.buy-form');if(!form)return;
  var unit=parseInt(form.dataset.totalUnit,10)||0;
  var inp=form.querySelector('input[name="produtos[0][quantidade]"]');
  var total=form.querySelector('.line-total');
  function fmt(c){return 'R$ '+(c/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function recalc(){var q=Math.max(1,parseInt(inp.value,10)||1);total.textContent=fmt(unit*q);}
  inp.addEventListener('input',recalc);
  form.querySelector('.qminus').addEventListener('click',function(){inp.value=Math.max(1,(parseInt(inp.value,10)||1)-1);recalc();});
  form.querySelector('.qplus').addEventListener('click',function(){inp.value=Math.min(999,(parseInt(inp.value,10)||1)+1);recalc();});
});
</script>
@endpush
