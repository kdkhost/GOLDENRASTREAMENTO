@extends('layouts.site')

@section('title', $produto['nome'] . ' — Loja Golden')
@section('meta_description', $produto['resumo'] ?: $produto['nome'])

@section('content')
<section class="sec-light">
    <div class="wrap">
        <a href="{{ route('site.loja.index') }}" style="color:var(--muted);text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Voltar para a loja</a>
        <div class="hero-grid" style="margin-top:16px">
            <div>
                <div class="prod-media">
                    @php
                        $todasFotos = $produto['imagens'] ?? (!empty($produto['imagem']) ? [$produto['imagem']] : []);
                    @endphp
                    @if(count($todasFotos) > 1)
                        <span class="prod-count-badge"><i class="fa-solid fa-camera"></i> {{ count($todasFotos) }} fotos</span>
                    @endif
                    @if(!empty($todasFotos[0]))
                        <img id="prodMainImg" src="{{ asset('storage/' . $todasFotos[0]) }}" alt="{{ $produto['nome'] }}">
                    @else
                        <i class="fa-solid fa-satellite-dish"></i>
                    @endif
                </div>

                @if(count($todasFotos) > 1)
                <div class="prod-thumbs-strip" aria-label="Galeria de fotos do produto">
                    @foreach($todasFotos as $idx => $foto)
                    <button type="button" class="prod-thumb-btn {{ $idx === 0 ? 'active' : '' }}" data-src="{{ asset('storage/' . $foto) }}" aria-label="Visualizar foto {{ $idx + 1 }}">
                        <img src="{{ asset('storage/' . $foto) }}" alt="Foto {{ $idx + 1 }}">
                    </button>
                    @endforeach
                </div>
                @endif

                @if($produto['video'] ?? null)
                    <video class="prod-video" src="{{ asset('storage/' . $produto['video']) }}" controls preload="metadata" playsinline></video>
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
                <form method="POST" action="{{ route('site.loja.carrinho.adicionar') }}" class="buy-form" data-unit="{{ $produto['preco'] }}" data-farol="sacola" data-detalhe="{{ $produto['slug'] }}">
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
                        <button type="submit" name="ir" value="checkout" class="btn btn-gold" data-track="generate_lead" data-cta="buy" data-farol="checkout">Comprar agora →</button>
                    </div>
                </form>
                @else
                <div class="notice-warn" style="margin-top:18px"><i class="fa-solid fa-circle-info"></i> Produto esgotado. Fale no WhatsApp para encomendar a reposição.</div>
                <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Quero encomendar o ' . $produto['nome'] . ' da loja Golden.') }}" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:10px"><i class="fa-brands fa-whatsapp"></i> Encomendar</a>
                @endif

                <div class="prod-frete-card">
                    <div class="prod-frete-head">
                        <i class="fa-solid fa-truck-fast"></i>
                        <div>
                            <strong>Simular Frete e Prazo de Entrega</strong>
                            <small>Calcule SEDEX, PAC ou retirada direta na Golden</small>
                        </div>
                    </div>
                    <div class="prod-frete-input-row">
                        <input type="text" id="prodCep" placeholder="00000-000" maxlength="9" inputmode="numeric" aria-label="CEP para entrega">
                        <button type="button" id="btnCalcularFreteProd" class="btn btn-gold btn-sm">Calcular</button>
                    </div>
                    <div id="prodFreteStatus" class="prod-frete-status"></div>
                    <div id="prodFreteResultados" class="prod-frete-res" style="display:none"></div>
                </div>
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
.prod-media{position:relative;height:380px;border-radius:18px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
.prod-media img{width:100%;height:100%;object-fit:contain;padding:16px;transition:opacity .2s ease-in-out}
.prod-media i{font-size:5rem;color:var(--gold);opacity:.85}
.prod-count-badge{position:absolute;top:12px;right:12px;background:rgba(0,0,0,.7);color:#fff;font-size:.74rem;font-weight:600;padding:3px 10px;border-radius:20px;backdrop-filter:blur(4px);z-index:2;display:flex;align-items:center;gap:6px}

.prod-thumbs-strip{display:flex;gap:10px;margin-top:12px;overflow-x:auto;padding-bottom:6px}
.prod-thumb-btn{width:70px;height:70px;border-radius:12px;background:linear-gradient(160deg,#1b2127,#0d1114);border:2px solid rgba(255,255,255,.12);padding:4px;cursor:pointer;flex-shrink:0;transition:border-color .2s,transform .2s;overflow:hidden;display:grid;place-items:center}
.prod-thumb-btn.active{border-color:var(--gold);box-shadow:0 0 0 2px rgba(224,163,62,.3);transform:scale(1.04)}
.prod-thumb-btn img{width:100%;height:100%;object-fit:contain}

.prod-video{display:block;width:100%;margin-top:12px;border-radius:16px;background:#0d1114;border:1px solid rgba(255,255,255,.08)}
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

.prod-frete-card{margin-top:22px;padding:16px 18px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.03)}
.prod-frete-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.prod-frete-head i{font-size:1.4rem;color:var(--gold)}
.prod-frete-head strong{display:block;font-size:.95rem;color:var(--ink)}
.prod-frete-head small{display:block;font-size:.8rem;color:var(--muted)}
.prod-frete-input-row{display:flex;gap:8px;max-width:320px}
.prod-frete-input-row input{flex:1;padding:8px 12px;border:1px solid rgba(0,0,0,.15);border-radius:8px;font-size:.92rem}
.prod-frete-status{font-size:.8rem;color:var(--muted);margin-top:6px;min-height:16px}
.prod-frete-res{margin-top:10px;border-top:1px dashed rgba(0,0,0,.1);padding-top:10px;display:flex;flex-direction:column;gap:8px}
.prod-frete-item{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;border-radius:8px;background:#f8fafc;font-size:.86rem;border:1px solid rgba(0,0,0,.05)}
.prod-frete-item b{color:var(--gold);font-size:.95rem}
.prod-frete-item small{display:block;color:var(--muted);font-size:.76rem;margin-top:2px}

.prod-thumb{height:180px;border-radius:12px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden;margin-bottom:10px}
.prod-thumb img{width:100%;height:100%;object-fit:contain;padding:10px}
.prod-thumb i{font-size:2.8rem;color:var(--gold);opacity:.8}

.steps-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.steps-mini div{display:flex;flex-direction:column;gap:4px}
.steps-mini i{color:var(--gold);font-size:1.2rem}
.steps-mini b{font-size:.98rem}
.steps-mini span{font-size:.85rem;color:var(--muted)}
@media(max-width:720px){.steps-mini{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  // 1. Quantidade e Subtotal
  var form=document.querySelector('.buy-form');
  var unit=form ? (parseInt(form.dataset.unit,10)||0) : 0;
  var inp=form ? form.querySelector('input[name="quantidade"]') : null;
  var total=form ? form.querySelector('.line-total') : null;
  var teto=inp ? (parseInt(inp.max,10)||999) : 999;
  function fmt(c){return 'R$ '+(c/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function recalc(){
    if(!inp||!total)return;
    var q=Math.min(teto,Math.max(1,parseInt(inp.value,10)||1));
    total.textContent=fmt(unit*q);
  }
  if(inp){
    inp.addEventListener('input',recalc);
    if(form.querySelector('.qminus')) form.querySelector('.qminus').addEventListener('click',function(){inp.value=Math.max(1,(parseInt(inp.value,10)||1)-1);recalc();});
    if(form.querySelector('.qplus')) form.querySelector('.qplus').addEventListener('click',function(){inp.value=Math.min(teto,(parseInt(inp.value,10)||1)+1);recalc();});
  }

  // 2. Galeria de fotos: clique/hover nas miniaturas
  var mainImg=document.getElementById('prodMainImg');
  var thumbs=document.querySelectorAll('.prod-thumb-btn');
  if(mainImg && thumbs.length>0){
    thumbs.forEach(function(btn){
      function ativar(){
        thumbs.forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        mainImg.style.opacity='0.4';
        setTimeout(function(){
          mainImg.src=btn.dataset.src;
          mainImg.style.opacity='1';
        },100);
      }
      btn.addEventListener('click',ativar);
      btn.addEventListener('mouseenter',ativar);
    });
  }

  // 3. Calculador de Frete na Página do Produto
  var cepInp=document.getElementById('prodCep');
  var btnFrete=document.getElementById('btnCalcularFreteProd');
  var statusBox=document.getElementById('prodFreteStatus');
  var resBox=document.getElementById('prodFreteResultados');

  if(cepInp && btnFrete && resBox){
    cepInp.addEventListener('input',function(){
      var v=cepInp.value.replace(/\D/g,'').slice(0,8);
      cepInp.value=v.replace(/^(\d{5})(\d)/,'$1-$2');
    });

    function simularFrete(){
      var raw=cepInp.value.replace(/\D/g,'');
      if(raw.length!==8){
        statusBox.innerHTML='<span style="color:#dc2626"><i class="fa-solid fa-triangle-exclamation"></i> Informe um CEP com 8 dígitos.</span>';
        resBox.style.display='none';
        return;
      }

      btnFrete.disabled=true;
      btnFrete.textContent='Calculando...';
      statusBox.innerHTML='<span style="color:var(--muted)"><i class="fa-solid fa-spinner fa-spin"></i> Consultando Correios e prazos...</span>';
      resBox.style.display='none';
      resBox.innerHTML='';

      // Consulta ViaCEP em paralelo para feedback da cidade/UF
      fetch('https://viacep.com.br/ws/'+raw+'/json/')
        .then(function(r){return r.json();})
        .then(function(loc){
          var locTxt = loc && !loc.erro && loc.localidade ? (' para '+loc.localidade+'/'+loc.uf) : '';
          var qty = inp ? (parseInt(inp.value, 10)||1) : 1;

          fetch("{{ route('site.loja.frete.calcular') }}", {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              cep: raw,
              produto_id: {{ (int) $produto['id'] }},
              quantidade: qty
            })
          })
          .then(function(r){return r.json();})
          .then(function(res){
            btnFrete.disabled=false;
            btnFrete.textContent='Calcular';

            if(!res.sucesso || !res.opcoes || res.opcoes.length===0){
              statusBox.innerHTML='<span style="color:#dc2626">Não foi possível calcular o frete para este CEP.</span>';
              return;
            }

            statusBox.innerHTML='<span style="color:var(--teal)"><i class="fa-solid fa-circle-check"></i> Opções disponíveis'+locTxt+':</span>';
            resBox.style.display='flex';
            resBox.innerHTML='';

            res.opcoes.forEach(function(op){
              var item=document.createElement('div');
              item.className='prod-frete-item';
              var prazo=op.prazo_dias ? (' · '+op.prazo_dias+' '+(op.prazo_dias>1?'dias úteis':'dia útil')) : '';
              var icone = op.tipo==='mercado_livre' ? 'fa-handshake' : (op.tipo==='retirada' ? 'fa-store' : 'fa-truck');

              item.innerHTML='<div><strong><i class="fa-solid '+icone+'" style="color:var(--gold);margin-right:4px"></i> '+op.nome+'</strong>'+
                '<small>'+(op.descricao || '')+prazo+'</small></div>'+
                '<b>'+(op.valor_cents === 0 ? 'GRÁTIS' : op.valor_formatado)+'</b>';
              resBox.appendChild(item);
            });
          })
          .catch(function(){
            btnFrete.disabled=false;
            btnFrete.textContent='Calcular';
            statusBox.innerHTML='<span style="color:#dc2626">Erro ao conectar com o serviço de frete.</span>';
          });
        })
        .catch(function(){
          btnFrete.disabled=false;
          btnFrete.textContent='Calcular';
          statusBox.innerHTML='<span style="color:#dc2626">Erro ao consultar CEP.</span>';
        });
    }

    btnFrete.addEventListener('click',simularFrete);
    cepInp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();simularFrete();}});
  }
});
</script>
@endpush
