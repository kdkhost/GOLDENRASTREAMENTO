@extends('layouts.site')

@section('title', 'Finalizar pedido — Loja Golden')
@section('meta_description', 'Informe seus dados, escolha a entrega e a forma de pagamento para concluir a compra na loja Golden.')

@section('content')
@php
    $entregaAtual = old('entrega', 'entrega');
    $mostrarEndereco = $entregaAtual !== 'retira';
@endphp
<section class="sec-light">
    <div class="wrap checkout">
        <form class="checkout-form" method="POST" action="{{ route('site.loja.pedido.registrar') }}" id="checkoutForm" data-frete="{{ $frete }}" data-frete-retira="0" novalidate>
            @csrf
            <h1 class="checkout-titulo">Finalizar pedido</h1>
            <p class="lead-sub">Confirme seus dados abaixo. Depois de registrar você recebe o link do pedido por e-mail e pode acompanhar cada etapa.</p>

            <fieldset>
                <legend>Quem compra</legend>
                <div class="row-2">
                    <div class="field">
                        <label for="coNome">Nome completo</label>
                        <input id="coNome" name="nome" type="text" maxlength="160" required value="{{ old('nome') }}" autocomplete="name" placeholder="Como podemos chamar você?">
                        @error('nome')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="coTelefone">WhatsApp com DDD</label>
                        <input id="coTelefone" name="telefone" type="tel" inputmode="tel" maxlength="32" required value="{{ old('telefone') }}" autocomplete="tel" placeholder="(00) 00000-0000">
                        @error('telefone')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="coEmail">E-mail <span class="fine">(recebe a confirmação e o link do pedido)</span></label>
                    <input id="coEmail" name="email" type="email" maxlength="160" required value="{{ old('email') }}" autocomplete="email" placeholder="voce@email.com">
                    @error('email')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                <div class="row-2">
                    <div class="field">
                        <label class="field-title">Tipo de pessoa</label>
                        <div class="radio-row">
                            <label class="radio" for="coTipoFisica">
                                <input id="coTipoFisica" type="radio" name="tipo_pessoa" value="fisica" @checked(old('tipo_pessoa', 'fisica') === 'fisica') required>
                                <span>Pessoa física (CPF)</span>
                            </label>
                            <label class="radio" for="coTipoJuridica">
                                <input id="coTipoJuridica" type="radio" name="tipo_pessoa" value="juridica" @checked(old('tipo_pessoa') === 'juridica')>
                                <span>Pessoa jurídica (CNPJ)</span>
                            </label>
                        </div>
                        @error('tipo_pessoa')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="coDocumento">CPF / CNPJ <span class="fine">(só números)</span></label>
                        <input id="coDocumento" name="documento" type="text" inputmode="numeric" maxlength="20" required value="{{ old('documento') }}" placeholder="000.000.000-00">
                        @error('documento')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Entrega</legend>
                <div class="field">
                    <label class="field-title">Como você quer receber</label>
                    <div class="radio-row">
                        @foreach($entregas as $valor => $rotulo)
                        <label class="radio card-radio" for="coEntrega-{{ $valor }}">
                            <input id="coEntrega-{{ $valor }}" type="radio" name="entrega" value="{{ $valor }}" data-entrega="{{ $valor }}" @checked($entregaAtual === $valor) required>
                            <span>{{ $rotulo }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('entrega')<span class="form-err">{{ $message }}</span>@enderror
                </div>

                <div class="endereco" @if(! $mostrarEndereco) hidden @endif>
                    <div class="row-2">
                        <div class="field">
                            <label for="coCep">CEP</label>
                            <input id="coCep" name="cep" type="text" inputmode="numeric" maxlength="9" value="{{ old('cep') }}" autocomplete="postal-code" placeholder="00000-000">
                            @error('cep')<span class="form-err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label class="field-title">&nbsp;</label>
                            <p class="cep-hint" id="cepHint"><i class="fa-solid fa-location-crosshairs"></i> Digite o CEP e preenchemos o endereço.</p>
                        </div>
                    </div>
                    <div class="field">
                        <label for="coEndereco">Rua / avenida e número</label>
                        <input id="coEndereco" name="endereco" type="text" maxlength="160" value="{{ old('endereco') }}" autocomplete="street-address" placeholder="Av. Brasil, 1200">
                        @error('endereco')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="row-2">
                        <div class="field">
                            <label for="coBairro">Bairro</label>
                            <input id="coBairro" name="bairro" type="text" maxlength="96" value="{{ old('bairro') }}">
                            @error('bairro')<span class="form-err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="coComplemento">Complemento <span class="fine">(opcional)</span></label>
                            <input id="coComplemento" name="complemento" type="text" maxlength="120" value="{{ old('complemento') }}" placeholder="Galpão, sala, referência">
                            @error('complemento')<span class="form-err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="row-2">
                        <div class="field">
                            <label for="coCidade">Cidade</label>
                            <input id="coCidade" name="cidade" type="text" maxlength="96" value="{{ old('cidade') }}" autocomplete="address-level2">
                            @error('cidade')<span class="form-err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="coUf">UF</label>
                            <input id="coUf" name="uf" type="text" maxlength="2" value="{{ old('uf') }}" placeholder="MG">
                            @error('uf')<span class="form-err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <p class="retira-note" @if($mostrarEndereco) hidden @endif><i class="fa-solid fa-store"></i> Retirada em {{ $golden['endereco'] }} — {{ $golden['cidade_uf'] }}. Combinamos o horário pelo WhatsApp.</p>
            </fieldset>

            <fieldset>
                <legend>Pagamento</legend>
                <div class="field">
                    <label class="field-title">Forma de pagamento</label>
                    <div class="radio-row">
                        @foreach($pagamentos as $valor => $rotulo)
                        <label class="radio card-radio" for="coPagamento-{{ $valor }}" data-pag-entrega="{{ $valor === 'retirada' ? 'retira' : 'qualquer' }}">
                            <input id="coPagamento-{{ $valor }}" type="radio" name="pagamento" value="{{ $valor }}" @checked(old('pagamento', 'pix') === $valor)>
                            <span>{{ $rotulo }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('pagamento')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                @if($pixChave)
                <p class="pix-key" id="pixKey"><i class="fa-brands fa-pix"></i> Chave Pix: <b>{{ $pixChave }}</b> — enviamos o QR Code pelo WhatsApp após confirmar o pedido.</p>
                @endif
            </fieldset>

            <fieldset>
                <legend>Observações</legend>
                <div class="field">
                    <label for="coNotas">Alguma informação para a equipe? <span class="fine">(opcional)</span></label>
                    <textarea id="coNotas" name="notas" rows="3" maxlength="2000" placeholder="Ex.: 3 caminhões VW, instalação na matriz">{{ old('notas') }}</textarea>
                    @error('notas')<span class="form-err">{{ $message }}</span>@enderror
                </div>
            </fieldset>

            {{-- Honeypot anti-spam --}}
            <div class="hp" aria-hidden="true">
                <label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <label class="consent">
                <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                <span>Autorizo a Golden a usar meus dados para processar este pedido e o contato comercial, conforme a <a href="{{ route('site.privacidade') }}">Política de Privacidade</a> (LGPD).</span>
            </label>
            @error('consent')<span class="form-err">{{ $message }}</span>@enderror
            @error('carrinho')<p class="form-err block">{{ $message }}</p>@enderror

            <button type="submit" class="btn btn-gold btn-block" data-track="generate_lead" data-cta="checkout">Registrar pedido →</button>
            <p class="fine checkout-aviso"><i class="fa-solid fa-lock"></i> Nada é cobrado agora: confirmamos o pagamento e a entrega antes de emitir qualquer cobrança.</p>
        </form>

        <aside class="checkout-summary">
            <h2>Sua compra</h2>
            <ul>
                @foreach($linhas as $l)
                <li><span>{{ $l['quantidade'] }}× {{ $l['nome'] }}</span><b>{{ brl($l['subtotal']) }}</b></li>
                @endforeach
            </ul>
            <div class="sum-line"><span>Subtotal</span><b>{{ brl($subtotal) }}</b></div>
            <div class="sum-line"><span>Frete</span><b id="sumFrete">{{ $frete > 0 ? brl($frete) : 'Grátis' }}</b></div>
            @if($freteGratisEm > 0)
            <p class="sum-hint"><i class="fa-solid fa-gift"></i> Faltam {{ brl($freteGratisEm) }} para o frete grátis.</p>
            @endif
            <div class="sum-line total"><span>Total</span><b id="sumTotal">{{ brl($subtotal + $frete) }}</b></div>
            <a href="{{ route('site.loja.carrinho') }}" class="sum-edit">Editar carrinho</a>
        </aside>
    </div>
</section>
@endsection

@push('scripts')
<style>
.checkout{display:grid;grid-template-columns:1.4fr .8fr;gap:34px;align-items:start}
.checkout-form{max-width:720px}
.checkout-titulo{font-size:1.7rem;margin:0 0 4px}
fieldset{border:1px solid var(--line);border-radius:var(--radius);padding:16px 18px 18px;margin:0 0 18px;background:#fff}
legend{font-family:"Poppins",sans-serif;font-size:.95rem;font-weight:700;padding:0 8px;color:var(--gold)}
.radio-row{display:flex;gap:10px;flex-wrap:wrap}
.radio{display:flex;align-items:center;gap:8px;font-size:.9rem;cursor:pointer;font-weight:500}
.card-radio{border:1.5px solid var(--line);border-radius:12px;padding:10px 14px;flex:1;min-width:200px;transition:border-color .18s ease,background .18s ease}
.card-radio:has(input:checked){border-color:var(--gold);background:rgba(224,163,62,.08)}
.card-radio:has(input:focus-visible){outline:2px solid var(--gold);outline-offset:2px}
.endereco{margin-top:2px}
.retira-note{margin:0;font-size:.88rem;color:var(--slate)}
.pix-key{margin:0;font-size:.86rem;color:var(--slate)}
.pix-key b{color:var(--ink)}
.cep-hint{font-size:.8rem;color:var(--slate);margin:0;padding-top:2.1em}
.checkout-summary{background:#12161a;color:#fff;border-radius:18px;padding:20px 22px;position:sticky;top:96px}
.checkout-summary h2{font-size:1.05rem;margin:0 0 12px;color:var(--gold)}
.checkout-summary ul{list-style:none;margin:0 0 12px;padding:0}
.checkout-summary li{display:flex;justify-content:space-between;gap:12px;font-size:.88rem;padding:6px 0;color:#c4ccd4}
.checkout-summary li b{color:#fff}
.sum-line{display:flex;justify-content:space-between;padding:8px 0;font-size:.92rem;border-top:1px dashed rgba(255,255,255,.16)}
.sum-line.total{font-size:1.15rem;border-top:1px solid rgba(255,255,255,.3);margin-top:6px;padding-top:12px}
.sum-line.total b{color:var(--gold)}
.sum-hint{font-size:.8rem;color:#c4ccd4;margin:8px 0 0}
.sum-hint i{color:var(--gold)}
.retira-note i{color:var(--gold)}
.pix-key i{color:var(--teal)}
.checkout-aviso{text-align:center;margin-top:12px}
.checkout-aviso i{color:var(--teal)}
.sum-edit{display:inline-block;margin-top:12px;font-size:.82rem;color:var(--gold)}
@media(max-width:900px){.checkout{grid-template-columns:1fr}.checkout-summary{position:static}}
@media(max-width:560px){.checkout .row-2{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.getElementById('checkoutForm');if(!form)return;
  var freteBase=parseInt(form.dataset.frete,10)||0;
  var subtotal=({{ (int) $subtotal }});
  var freteEl=document.getElementById('sumFrete'),totalEl=document.getElementById('sumTotal');
  var endereco=form.querySelector('.endereco'),retira=form.querySelector('.retira-note');
  function brl(c){return 'R$ '+(c/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}

  function aplicarEntrega(valor){
    var retirou=valor==='retira';
    if(endereco)endereco.hidden=retirou;
    if(retira)retira.hidden=!retirou;
    var frete=retirou?0:freteBase;
    if(freteEl)freteEl.textContent=frete>0?brl(frete):'Grátis';
    if(totalEl)totalEl.textContent=brl(subtotal+frete);
    form.querySelectorAll('.card-radio[data-pag-entrega]').forEach(function(l){
      var v=l.dataset.pagEntrega;
      l.style.display=(v==='qualquer'||v===valor)?'':'none';
      if(v!=='qualquer'&&v!==valor){var i=l.querySelector('input');if(i)i.checked=false;}
    });
  }

  form.querySelectorAll('input[name="entrega"]').forEach(function(i){i.addEventListener('change',function(){aplicarEntrega(this.value);});});
  aplicarEntrega((form.querySelector('input[name="entrega"]:checked')||{value:'entrega'}).value);

  // ViaCEP: preenchimento do endereço a partir do CEP (requisição externa, não ao banco).
  var cep=document.getElementById('coCep');
  if(cep){
    cep.addEventListener('blur',function(){
      var d=(cep.value||'').replace(/\D/g,'');
      if(d.length!==8)return;
      fetch('https://viacep.com.br/ws/'+d+'/json/').then(function(r){return r.json();}).then(function(j){
        if(j.erro)return;
        var set=function(n,v){var e=form.querySelector('[name="'+n+'"]');if(e&&!e.value&&v)e.value=v;};
        set('endereco',j.logradouro?j.logradouro+', ':'');
        set('bairro',j.bairro||'');
        set('cidade',j.localidade||'');
        set('uf',j.uf||'');
        var hint=document.getElementById('cepHint');
        if(hint)hint.innerHTML='<i class="fa-solid fa-circle-check"></i> '+(j.localidade||'')+'/'+(j.uf||'');
      }).catch(function(){});
    });
  }

  // Máscaras leves: telefone, CEP e documento, sem biblioteca.
  var tel=document.getElementById('coTelefone');
  if(tel)tel.addEventListener('input',function(){
    var d=tel.value.replace(/\D/g,'').slice(0,11);
    tel.value=d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
  });
  var doc=document.getElementById('coDocumento');
  var tipo=form.querySelectorAll('input[name="tipo_pessoa"]');
  function mascaraDoc(){
    var d=(doc.value||'').replace(/\D/g,'');
    var juridica=Array.prototype.some.call(tipo,function(i){return i.checked&&i.value==='juridica';});
    doc.value=juridica
      ? d.slice(0,14).replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2')
      : d.slice(0,11).replace(/^(\d{3})(\d)/,'$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/,'.$1-$2');
  }
  if(doc){doc.addEventListener('input',mascaraDoc);tipo.forEach(function(i){i.addEventListener('change',mascaraDoc);});}
  if(cep)cep.addEventListener('input',function(){
    var d=cep.value.replace(/\D/g,'').slice(0,8);
    cep.value=d.replace(/^(\d{5})(\d)/,'$1-$2');
  });
});
</script>
@endpush
