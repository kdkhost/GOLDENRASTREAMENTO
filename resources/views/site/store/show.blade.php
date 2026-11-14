@extends('layouts.site')

@section('title', $produto['nome'] . ' — Loja Golden')
@section('meta_description', $produto['resumo'] ?: $produto['nome'])

@section('content')
@php
    $todasFotos = $produto['imagens'] ?? (!empty($produto['imagem']) ? [$produto['imagem']] : []);
    $descontoPct = (!empty($produto['promocao']) && !empty($produto['preco_cents']) && $produto['preco'] > 0 && $produto['preco_cents'] > $produto['preco'])
        ? (int) round((($produto['preco_cents'] - $produto['preco']) / $produto['preco_cents']) * 100)
        : 0;
@endphp

<section class="pdp-hero">
    <div class="wrap">
        <nav class="pdp-breadcrumb" aria-label="Navegação">
            <a href="{{ route('site.loja.index') }}"><i class="fa-solid fa-store"></i> Loja Golden</a>
            @if(!empty($produto['categoria']))
                <span aria-hidden="true">/</span>
                <a href="{{ route('site.loja.index', ['categoria' => $produto['categoria_slug'] ?? '']) }}">{{ $produto['categoria'] }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span class="current">{{ $produto['nome'] }}</span>
        </nav>

        <div class="pdp-grid">
            {{-- Coluna Esquerda: Galeria Sticky --}}
            <div class="pdp-gallery-col">
                <div class="pdp-media">
                    @if(!empty($produto['promocao']))
                        <span class="pdp-badge-offer">
                            <i class="fa-solid fa-tag"></i>
                            {{ $descontoPct > 0 ? "-{$descontoPct}% OFF" : 'Oferta Especial' }}
                        </span>
                    @elseif(!empty($produto['destaque']))
                        <span class="pdp-badge-feat"><i class="fa-solid fa-star"></i> Mais vendido</span>
                    @endif

                    @if(count($todasFotos) > 1)
                        <span class="pdp-count-badge"><i class="fa-solid fa-camera"></i> {{ count($todasFotos) }} fotos</span>
                    @endif

                    @if(!empty($todasFotos[0]))
                        <img id="prodMainImg" src="{{ produto_foto_url($todasFotos[0]) }}" alt="{{ $produto['nome'] }}">
                    @else
                        <div class="pdp-placeholder-box">
                            <span class="badge-cat">{{ $produto['categoria'] ?? 'Golden Rastreamento' }}</span>
                            <i class="fa-solid fa-satellite-dish"></i>
                            <strong>{{ $produto['nome'] }}</strong>
                            <small>Tecnologia e Gestão Veicular</small>
                        </div>
                    @endif
                </div>

                @if(count($todasFotos) > 1)
                    <div class="prod-thumbs-strip" aria-label="Galeria de fotos do produto">
                        @foreach($todasFotos as $idx => $foto)
                            <button type="button" class="prod-thumb-btn {{ $idx === 0 ? 'active' : '' }}" data-src="{{ produto_foto_url($foto) }}" aria-label="Visualizar foto {{ $idx + 1 }}">
                                <img src="{{ produto_foto_url($foto) }}" alt="Foto {{ $idx + 1 }}">
                            </button>
                        @endforeach
                    </div>
                @endif

                @if($produto['video'] ?? null)
                    <div class="pdp-video-wrap">
                        <div class="pdp-video-label"><i class="fa-solid fa-circle-play"></i> Demonstração do produto</div>
                        <video class="prod-video" src="{{ asset('storage/' . $produto['video']) }}" controls preload="metadata" playsinline></video>
                    </div>
                @endif
            </div>

            {{-- Coluna Direita: Oferta & Conversão --}}
            <div class="pdp-buy-col">
                <div class="pdp-top-meta">
                    @if(!empty($produto['categoria']))
                        <span class="pdp-cat-pill">{{ $produto['categoria'] }}</span>
                    @endif

                    <div class="pdp-stock-pill">
                        @if(empty($produto['controle_estoque']))
                            <span class="ok"><i class="fa-solid fa-circle-check"></i> Pronta entrega</span>
                        @elseif($produto['estoque'] > 0)
                            <span class="ok"><i class="fa-solid fa-circle-check"></i> {{ $produto['estoque'] }} unidade(s) em estoque</span>
                        @else
                            <span class="out"><i class="fa-solid fa-circle-xmark"></i> Sem estoque</span>
                        @endif
                    </div>

                    @if(!empty($produto['sku']))
                        <span class="pdp-sku">Cód: {{ $produto['sku'] }}</span>
                    @endif
                </div>

                <h1 class="pdp-title">{{ $produto['nome'] }}</h1>

                @if(!empty($produto['resumo']))
                    <p class="pdp-lead">{{ $produto['resumo'] }}</p>
                @endif

                {{-- Bloco de Preço --}}
                <div class="pdp-price-card">
                    @if(!empty($produto['promocao']))
                        <div class="pdp-price-old">
                            <span>De <s>{{ brl($produto['preco_cents']) }}</s></span>
                            @if($descontoPct > 0)
                                <span class="pdp-save-tag">Economize {{ $descontoPct }}%</span>
                            @endif
                        </div>
                    @endif

                    <div class="pdp-price-main">
                        @if($produto['preco'] > 0)
                            <b>{{ brl($produto['preco']) }}</b>
                        @elseif(!empty($produto['mensalidade']))
                            <b>{{ brl($produto['mensalidade']) }}<span>/mês</span></b>
                        @else
                            <b>Sob consulta</b>
                        @endif
                    </div>

                    @if(!empty($produto['mensalidade']) && $produto['preco'] > 0)
                        <div class="pdp-monthly-note">
                            <i class="fa-solid fa-tower-broadcast"></i>
                            Monitoramento opcional/plano: <strong>{{ brl($produto['mensalidade']) }}/mês</strong>
                        </div>
                    @endif

                    <div class="pdp-pay-methods">
                        <span><i class="fa-brands fa-pix"></i> Pix com confirmação imediata</span>
                        <span><i class="fa-regular fa-credit-card"></i> Cartão ou Boleto</span>
                    </div>
                </div>

                {{-- Caixa de Compra --}}
                @if(!empty($produto['disponivel']))
                    @php
                        $precoBase = $produto['preco'] > 0 ? $produto['preco'] : ($produto['mensalidade'] ?? 0);
                        $ehMensal = ($produto['preco'] == 0 && ($produto['mensalidade'] ?? 0) > 0);
                    @endphp
                    <form method="POST" action="{{ route('site.loja.carrinho.adicionar') }}" class="pdp-buy-card buy-form" data-unit="{{ $precoBase }}" data-monthly="{{ $ehMensal ? '1' : '0' }}" data-farol="sacola" data-detalhe="{{ $produto['slug'] }}">
                        @csrf
                        <input type="hidden" name="produto" value="{{ $produto['id'] }}">

                        <div class="quantity-row">
                            <span class="qty-label">Quantidade</span>
                            <div class="qty">
                                <button type="button" class="qminus" aria-label="Diminuir">−</button>
                                <input type="number" name="quantidade" min="1" max="{{ $produto['controle_estoque'] ? max(1, $produto['estoque']) : 999 }}" value="1" required>
                                <button type="button" class="qplus" aria-label="Aumentar">+</button>
                            </div>
                            <div class="qty-total-wrap">
                                <small>Subtotal</small>
                                <strong class="line-total">{{ brl($precoBase) }}{{ $ehMensal ? '/mês' : '' }}</strong>
                            </div>
                        </div>

                        <div class="buy-actions">
                            <button type="submit" name="ir" value="checkout" class="pdp-btn-primary" data-track="generate_lead" data-cta="buy" data-farol="checkout">
                                <i class="fa-solid fa-bolt"></i> Comprar agora →
                            </button>
                            <button type="submit" name="ir" value="carrinho" class="pdp-btn-secondary">
                                <i class="fa-solid fa-cart-plus"></i> Adicionar ao carrinho
                            </button>
                        </div>

                        <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Tenho interesse no produto ' . $produto['nome'] . ' e gostaria de tirar uma dúvida antes de comprar.') }}" target="_blank" rel="noopener" class="pdp-wa-assist">
                            <i class="fa-brands fa-whatsapp"></i> Dúvidas? Fale com um especialista agora
                        </a>
                    </form>
                @else
                    <div class="pdp-unavailable-card">
                        <div class="notice-warn"><i class="fa-solid fa-circle-info"></i> Produto temporariamente esgotado. Fale no WhatsApp para reservar a reposição.</div>
                        <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Quero encomendar o ' . $produto['nome'] . ' da loja Golden.') }}" target="_blank" rel="noopener" class="pdp-btn-primary" style="margin-top:12px">
                            <i class="fa-brands fa-whatsapp"></i> Encomendar pelo WhatsApp
                        </a>
                    </div>
                @endif

                {{-- Selos de Confiança --}}
                <div class="pdp-trust-grid">
                    <div class="pdp-trust-tile">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Homologado Anatel</strong>
                            <small>Equipamento certificado</small>
                        </div>
                    </div>
                    <div class="pdp-trust-tile">
                        <i class="fa-solid fa-sim-card"></i>
                        <div>
                            <strong>Multi-operadora</strong>
                            <small>{{ implode(', ', $golden['parceiros']) }}</small>
                        </div>
                    </div>
                    <div class="pdp-trust-tile">
                        <i class="fa-solid fa-lock"></i>
                        <div>
                            <strong>Compra 100% Segura</strong>
                            <small>Dados criptografados</small>
                        </div>
                    </div>
                    <div class="pdp-trust-tile">
                        <i class="fa-solid fa-truck-fast"></i>
                        <div>
                            <strong>Entrega ou Retirada</strong>
                            <small>Envio rápido para todo o Brasil</small>
                        </div>
                    </div>
                </div>

                {{-- Simulador de Frete --}}
                <div class="prod-frete-card">
                    <div class="prod-frete-head">
                        <div class="prod-frete-icon"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <strong>Simular Frete e Prazo de Entrega</strong>
                            <small>Calcule SEDEX, PAC ou retirada direta na Golden</small>
                        </div>
                    </div>
                    <div class="prod-frete-input-row">
                        <input type="text" id="prodCep" placeholder="00000-000" maxlength="9" inputmode="numeric" aria-label="CEP para entrega">
                        <button type="button" id="btnCalcularFreteProd" class="pdp-btn-frete">Calcular</button>
                    </div>
                    <div id="prodFreteStatus" class="prod-frete-status"></div>
                    <div id="prodFreteResultados" class="prod-frete-res" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Seção Persuasiva: Descrição Completa + Benefícios Golden --}}
<section class="sec-alt">
    <div class="wrap">
        <div class="pdp-details-grid {{ empty($produto['descricao']) ? 'pdp-details-single' : '' }}">
            @if(!empty($produto['descricao']))
                <div class="pdp-detail-card">
                    <span class="eyebrow">Especificações & Detalhes</span>
                    <h2>Tudo sobre {{ $produto['nome'] }}</h2>
                    <div class="prod-desc">{!! nl2br(e($produto['descricao'])) !!}</div>
                </div>
            @endif

            <div class="pdp-why-card">
                <span class="eyebrow">Diferenciais Golden</span>
                <h2>Por que escolher a Golden Rastreamento?</h2>
                <ul class="pdp-why-list">
                    <li>
                        <i class="fa-solid fa-location-crosshairs"></i>
                        <div>
                            <strong>Monitoramento 24h em tempo real</strong>
                            <span>Acompanhe posição, velocidade e histórico de rotas pelo celular ou computador.</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-bell"></i>
                        <div>
                            <strong>Alertas instantâneos e cerca elétrica</strong>
                            <span>Receba notificações de ignição, movimentação indevida e entrada/saída de áreas.</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-mobile-screen-button"></i>
                        <div>
                            <strong>Aplicativo Android, iOS e Plataforma Web</strong>
                            <span>Interface rápida e intuitiva para gerenciar um único veículo ou toda a sua frota.</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-headset"></i>
                        <div>
                            <strong>Suporte técnico especializado</strong>
                            <span>Atendimento humanizado antes e depois da compra para ativação e configuração.</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pdp-steps">
            <div class="pdp-step-card">
                <span class="pdp-step-num">1</span>
                <i class="fa-solid fa-basket-shopping"></i>
                <b>Escolha e finalize em minutos</b>
                <span>Adicione à sacola ou clique em Comprar agora para ir direto ao checkout.</span>
            </div>
            <div class="pdp-step-card">
                <span class="pdp-step-num">2</span>
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <b>Frete ou retirada transparente</b>
                <span>Informe seu CEP para envio via Correios ou retire diretamente na Golden.</span>
            </div>
            <div class="pdp-step-card">
                <span class="pdp-step-num">3</span>
                <i class="fa-solid fa-circle-check"></i>
                <b>Acompanhe cada etapa</b>
                <span>Receba código de rastreio e link exclusivo com a linha do tempo do pedido.</span>
            </div>
        </div>
    </div>
</section>

@if($relacionados->isNotEmpty())
<section class="sec-light">
    <div class="wrap">
        <div class="pdp-rel-head">
            <div>
                <span class="eyebrow">Complete seu kit</span>
                <h2 style="font-size:1.5rem;margin:.2rem 0 0">Você também pode querer</h2>
            </div>
            <a href="{{ route('site.loja.index') }}" class="btn btn-ghost btn-sm">Ver toda a loja →</a>
        </div>
        <div class="grid-3">
            @foreach($relacionados as $r)
                @include('site.store._product-card', ['p' => $r])
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection

@push('scripts')
@include('site.store._product-card-scripts')
<style>
.pdp-hero{background:var(--paper);padding:34px 0 64px}
.pdp-breadcrumb{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.86rem;color:var(--muted);margin-bottom:24px}
.pdp-breadcrumb a{color:var(--muted);text-decoration:none;transition:color .15s}
.pdp-breadcrumb a:hover{color:var(--ink)}
.pdp-breadcrumb .current{color:var(--ink);font-weight:600}

.pdp-grid{display:grid;grid-template-columns:minmax(0,1.04fr) minmax(0,.96fr);gap:44px;align-items:start}
.pdp-gallery-col{position:sticky;top:96px}
.pdp-media{position:relative;aspect-ratio:4/3;min-height:360px;border-radius:26px;background:#f3f4f6;display:grid;place-items:center;overflow:hidden;border:1px solid rgba(15,23,42,.06);box-shadow:0 16px 38px rgba(15,23,42,.08)}
.pdp-media img{width:100%;height:100%;object-fit:cover;transition:opacity .2s ease-in-out,transform .4s ease}
.pdp-media:hover img{transform:scale(1.02)}
.pdp-badge-offer,.pdp-badge-feat{position:absolute;top:16px;left:16px;z-index:3;display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:9999px;font-size:.76rem;font-weight:700;box-shadow:0 6px 16px rgba(0,0,0,.12)}
.pdp-badge-offer{background:var(--teal);color:#fff}
.pdp-badge-feat{background:var(--gold);color:#141619}
.pdp-count-badge{position:absolute;bottom:14px;right:14px;background:rgba(17,24,39,.72);color:#fff;font-size:.74rem;font-weight:600;padding:4px 11px;border-radius:9999px;backdrop-filter:blur(4px);z-index:2;display:flex;align-items:center;gap:6px}

.pdp-placeholder-box{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:28px;background:linear-gradient(160deg,#1b2127,#0d1114);color:#fff;gap:10px}
.pdp-placeholder-box i{font-size:4rem;color:var(--gold);opacity:.9}
.pdp-placeholder-box strong{font-size:1.15rem;color:#fff;max-width:260px}
.pdp-placeholder-box small{color:#9ca3af;font-size:.84rem}
.pdp-placeholder-box .badge-cat{font-size:.72rem;background:rgba(224,163,62,.16);color:var(--gold);border:1px solid rgba(224,163,62,.35);padding:4px 12px;border-radius:9999px;font-weight:600;text-transform:uppercase}

.prod-thumbs-strip{display:flex;gap:10px;margin-top:14px;overflow-x:auto;padding-bottom:4px}
.prod-thumb-btn{width:76px;height:76px;border-radius:16px;background:#f3f4f6;border:2px solid transparent;padding:0;cursor:pointer;flex-shrink:0;transition:border-color .2s,transform .2s,box-shadow .2s;overflow:hidden}
.prod-thumb-btn.active{border-color:var(--gold);box-shadow:0 4px 12px rgba(224,163,62,.28);transform:translateY(-2px)}
.prod-thumb-btn img{width:100%;height:100%;object-fit:cover;display:block}

.pdp-video-wrap{margin-top:16px;background:#fff;border-radius:20px;padding:12px;border:1px solid rgba(15,23,42,.06);box-shadow:0 8px 24px rgba(15,23,42,.05)}
.pdp-video-label{font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.pdp-video-label i{color:var(--gold-2)}
.prod-video{display:block;width:100%;border-radius:14px;background:#0d1114}

.pdp-top-meta{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.pdp-cat-pill{background:rgba(224,163,62,.14);color:#92610c;font-size:.75rem;font-weight:700;padding:4px 12px;border-radius:9999px;text-transform:uppercase;letter-spacing:.03em}
.pdp-stock-pill{font-size:.82rem;font-weight:600}
.pdp-stock-pill .ok{color:var(--teal);background:rgba(31,158,134,.1);padding:4px 11px;border-radius:9999px;display:inline-flex;align-items:center;gap:5px}
.pdp-stock-pill .out{color:#d06a5f;background:rgba(208,106,95,.12);padding:4px 11px;border-radius:9999px;display:inline-flex;align-items:center;gap:5px}
.pdp-sku{font-size:.78rem;color:var(--muted);margin-left:auto}

.pdp-title{font-size:clamp(1.65rem,2.6vw,2.2rem);line-height:1.18;color:#111827;margin:0 0 10px;font-weight:800}
.pdp-lead{font-size:1rem;line-height:1.55;color:#4b5563;margin:0 0 18px}

.pdp-price-card{background:#fff;border:1px solid rgba(15,23,42,.07);border-radius:22px;padding:18px 22px;box-shadow:0 8px 24px rgba(15,23,42,.04);margin-bottom:16px}
.pdp-price-old{display:flex;align-items:center;gap:10px;font-size:.88rem;color:#9ca3af;margin-bottom:4px}
.pdp-save-tag{background:rgba(31,158,134,.12);color:var(--teal);font-size:.74rem;font-weight:700;padding:2px 9px;border-radius:9999px}
.pdp-price-main b{font-size:2.25rem;font-weight:800;color:#111827;letter-spacing:-.02em;line-height:1.1}
.pdp-price-main b span{font-size:1.1rem;font-weight:600;color:var(--muted)}
.pdp-monthly-note{margin-top:6px;font-size:.86rem;color:#374151;display:flex;align-items:center;gap:6px}
.pdp-monthly-note i{color:var(--gold-2)}
.pdp-pay-methods{margin-top:12px;padding-top:12px;border-top:1px dashed rgba(15,23,42,.1);display:flex;flex-wrap:wrap;gap:8px 18px;font-size:.8rem;color:#4b5563}
.pdp-pay-methods i{color:var(--teal);margin-right:4px}

.pdp-buy-card{background:#fff;border:1px solid rgba(15,23,42,.07);border-radius:22px;padding:20px 22px;box-shadow:0 12px 30px rgba(15,23,42,.06)}
.quantity-row{display:flex;align-items:center;gap:14px;padding-bottom:16px;border-bottom:1px solid rgba(15,23,42,.06)}
.qty-label{color:#374151;font-size:.9rem;font-weight:600}
.qty{display:flex;align-items:center;background:#f8fafc;border:1px solid rgba(15,23,42,.12);border-radius:9999px;overflow:hidden}
.qty button{width:38px;height:38px;border:0;background:transparent;font-size:1.15rem;cursor:pointer;color:#111827;transition:background .15s}
.qty button:hover{background:rgba(15,23,42,.06)}
.qty input{width:46px;text-align:center;border:0;background:transparent;font-size:.98rem;font-weight:700;color:#111827}
.qty-total-wrap{margin-left:auto;text-align:right;line-height:1.15}
.qty-total-wrap small{display:block;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.line-total{color:#111827;font-size:1.2rem;font-weight:800}

.buy-actions{display:flex;flex-direction:column;gap:10px;margin-top:16px}
.pdp-btn-primary{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#e0a33e,#c88a24);color:#141619;font-size:1rem;font-weight:700;padding:14px 24px;border:0;border-radius:9999px;cursor:pointer;text-decoration:none;box-shadow:0 10px 24px rgba(224,163,62,.3);transition:transform .18s ease,box-shadow .18s ease}
.pdp-btn-primary:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(224,163,62,.4);color:#141619}
.pdp-btn-secondary{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#141619;color:#fff;font-size:.94rem;font-weight:600;padding:12px 24px;border:0;border-radius:9999px;cursor:pointer;transition:background .18s ease,transform .18s ease}
.pdp-btn-secondary:hover{background:#272b30;transform:translateY(-1px)}
.pdp-wa-assist{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:14px;font-size:.84rem;font-weight:600;color:var(--teal);text-decoration:none}
.pdp-wa-assist:hover{text-decoration:underline}

.pdp-unavailable-card{margin-top:16px}
.notice-warn{background:rgba(224,163,62,.12);border:1px solid rgba(224,163,62,.35);border-radius:14px;padding:13px 16px;font-size:.9rem;color:#92610c}

.pdp-trust-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
.pdp-trust-tile{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:16px;padding:12px 14px;display:flex;align-items:center;gap:11px}
.pdp-trust-tile i{width:36px;height:36px;flex-shrink:0;border-radius:10px;background:rgba(224,163,62,.14);color:var(--gold-2);display:grid;place-items:center;font-size:1rem}
.pdp-trust-tile strong{display:block;font-size:.82rem;color:#111827;line-height:1.2}
.pdp-trust-tile small{display:block;font-size:.74rem;color:var(--muted);margin-top:2px}

.prod-frete-card{margin-top:16px;padding:18px 20px;background:#fff;border:1px solid rgba(15,23,42,.07);border-radius:20px;box-shadow:0 6px 20px rgba(15,23,42,.04)}
.prod-frete-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.prod-frete-icon{width:38px;height:38px;border-radius:11px;background:rgba(31,158,134,.12);color:var(--teal);display:grid;place-items:center;font-size:1.1rem;flex-shrink:0}
.prod-frete-head strong{display:block;font-size:.94rem;color:#111827}
.prod-frete-head small{display:block;font-size:.79rem;color:var(--muted)}
.prod-frete-input-row{display:flex;gap:8px}
.prod-frete-input-row input{flex:1;min-width:0;padding:10px 14px;border:1px solid rgba(15,23,42,.14);border-radius:9999px;font-size:.92rem;background:#f8fafc}
.pdp-btn-frete{background:#141619;color:#fff;border:0;border-radius:9999px;padding:10px 20px;font-size:.86rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .18s}
.pdp-btn-frete:hover{background:#272b30}
.prod-frete-status{font-size:.8rem;color:var(--muted);margin-top:8px;min-height:16px}
.prod-frete-res{margin-top:10px;border-top:1px dashed rgba(15,23,42,.1);padding-top:10px;display:flex;flex-direction:column;gap:8px}
.prod-frete-item{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:12px;background:#f8fafc;font-size:.86rem;border:1px solid rgba(15,23,42,.05)}
.prod-frete-item b{color:#111827;font-size:.95rem}
.prod-frete-item small{display:block;color:var(--muted);font-size:.76rem;margin-top:2px}

.pdp-details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:26px;align-items:stretch}
.pdp-details-grid.pdp-details-single{grid-template-columns:minmax(0,1fr)}
.pdp-detail-card,.pdp-why-card{background:#fff;border-radius:24px;padding:30px;border:1px solid rgba(15,23,42,.06);box-shadow:0 10px 26px rgba(15,23,42,.05)}
.pdp-detail-card h2,.pdp-why-card h2{font-size:1.35rem;margin:.35rem 0 14px;color:#111827}
.prod-desc{color:#4b5563;font-size:.96rem;line-height:1.7}
.pdp-why-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:16px}
.pdp-why-list li{display:flex;align-items:flex-start;gap:14px}
.pdp-why-list li i{width:38px;height:38px;flex-shrink:0;border-radius:11px;background:rgba(224,163,62,.14);color:var(--gold-2);display:grid;place-items:center;font-size:1rem;margin-top:2px}
.pdp-why-list li strong{display:block;font-size:.95rem;color:#111827;margin-bottom:2px}
.pdp-why-list li span{display:block;font-size:.86rem;color:#6b7280;line-height:1.45}

.pdp-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin-top:28px}
.pdp-step-card{position:relative;background:#fff;border-radius:20px;padding:22px;border:1px solid rgba(15,23,42,.05);display:flex;flex-direction:column;gap:6px}
.pdp-step-num{position:absolute;top:16px;right:18px;font-size:1.4rem;font-weight:800;color:rgba(15,23,42,.08);line-height:1}
.pdp-step-card i{color:var(--gold-2);font-size:1.25rem;margin-bottom:4px}
.pdp-step-card b{font-size:.98rem;color:#111827}
.pdp-step-card span{font-size:.85rem;color:#6b7280;line-height:1.45}

.pdp-rel-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}

@media(max-width:960px){
  .pdp-grid{grid-template-columns:minmax(0,1fr);gap:28px}
  .pdp-gallery-col{position:static}
  .pdp-media{min-height:280px}
  .pdp-details-grid,.pdp-steps{grid-template-columns:minmax(0,1fr)}
}
@media(max-width:520px){
  .pdp-trust-grid{grid-template-columns:minmax(0,1fr)}
  .pdp-price-main b{font-size:1.9rem}
}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  // 1. Quantidade e Subtotal
  var form=document.querySelector('.buy-form');
  var unit=form ? (parseInt(form.dataset.unit,10)||0) : 0;
  var isMonthly=form ? (form.dataset.monthly === '1') : false;
  var inp=form ? form.querySelector('input[name="quantidade"]') : null;
  var total=form ? form.querySelector('.line-total') : null;
  var teto=inp ? (parseInt(inp.max,10)||999) : 999;
  function fmt(c){return 'R$ '+(c/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function recalc(){
    if(!inp||!total)return;
    var q=Math.min(teto,Math.max(1,parseInt(inp.value,10)||1));
    total.textContent=fmt(unit*q) + (isMonthly ? '/mês' : '');
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

        statusBox.innerHTML='<span style="color:var(--teal)"><i class="fa-solid fa-circle-check"></i> Opções disponíveis para o CEP '+res.cep+':</span>';
        resBox.style.display='flex';
        resBox.innerHTML='';

        res.opcoes.forEach(function(op){
          var item=document.createElement('div');
          item.className='prod-frete-item';
          var prazo=op.prazo_dias ? (' · '+op.prazo_dias+' '+(op.prazo_dias>1?'dias úteis':'dia útil')) : (op.prazo_texto ? ' · '+op.prazo_texto : '');
          var icone = op.tipo==='mercado_livre' ? 'fa-handshake' : (op.tipo==='retirada' ? 'fa-store' : (op.tipo==='digital' ? 'fa-bolt' : 'fa-truck'));

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
    }

    btnFrete.addEventListener('click',simularFrete);
    cepInp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();simularFrete();}});
  }
});
</script>
@endpush
