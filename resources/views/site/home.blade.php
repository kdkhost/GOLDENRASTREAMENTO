@extends('layouts.site')

@section('title', 'Golden Rastreamento — Mais controle para o seu veículo')
@section('meta_description', 'Rastreamento e gestão veicular para frotas, motoristas e empresas. Kits com rastreador J16 4G/2G e chip M2M. Solicite sua proposta.')

@section('content')
{{-- HERO --}}
<section class="hero">
    <div class="wrap hero-grid">
        <div class="on-dark">
            <span class="eyebrow on-dark">Rastreamento e gestão veicular</span>
            <h1>Mais controle para o seu veículo.<br><em>Mais resultados para o seu negócio.</em></h1>
            <p>Soluções completas de rastreamento e gestão para frotas e motoristas profissionais. Tecnologia, presença em tempo real e informações para decisões mais seguras.</p>
            <div class="hero-actions">
                <a href="{{ route('site.planos') }}" class="btn btn-gold" data-track="view_plans" data-cta="hero">Quero uma proposta →</a>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-outline" data-track="whatsapp_click" data-cta="hero"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
            </div>
            <div class="hero-checks">
                <span><i class="fa-solid fa-shield-halved"></i> Rastreador homologado Anatel</span>
                <span><i class="fa-solid fa-sim-card"></i> Chip M2M incluso</span>
                <span><i class="fa-solid fa-mobile-screen"></i> Web e aplicativo</span>
            </div>
        </div>
        <div class="hero-visual">
            <div class="mock">
                <div class="mock-top"><span>●</span> ● ● &nbsp; Golden Gestão — prévia ilustrativa</div>
                <div class="mock-body">
                    <aside class="mock-side">
                        <b>GOLDEN</b>
                        <hr><span>Visão geral</span><span>Veículos</span><span>Mapa</span><span>Cercas</span><span>Relatórios</span>
                    </aside>
                    <div class="mock-map">
                        <div class="mock-route"></div>
                        <span class="mock-pin p1"><i class="fa-solid fa-location-dot"></i></span>
                        <span class="mock-pin p2"><i class="fa-solid fa-truck"></i></span>
                        <div class="mock-metrics">
                            <span>Veículos <strong>10</strong></span>
                            <span>Em rota <strong>6</strong></span>
                            <span>Parados <strong>4</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAIXA DE CONFIANÇA --}}
<div class="trust">
    <div class="wrap trust-grid">
        <div class="trust-item"><i class="fa-solid fa-shield-halved"></i> Homologado Anatel</div>
        <div class="trust-item"><i class="fa-solid fa-tower-cell"></i> Rede 4G / 2G</div>
        <div class="trust-item"><i class="fa-solid fa-headset"></i> Atendimento especializado</div>
        <div class="trust-item"><i class="fa-solid fa-layer-group"></i> Planos para toda frota</div>
    </div>
</div>

{{-- PLATAFORMA --}}
<section class="sec-alt">
    <div class="wrap hero-grid">
        <div>
            <span class="eyebrow">Aplicativo e plataforma</span>
            <h2>Tudo o que você precisa, na palma da sua mão.</h2>
            <p class="lead-sub">Acesse pelo celular ou computador, com uma plataforma completa e fácil de usar. Acompanhe o veículo, receba alertas, consulte relatórios e gerencie sua frota de onde estiver.</p>
            <ul style="list-style:none;padding:0;margin:18px 0 26px;display:grid;gap:12px">
                @foreach(['Mapa e histórico de trajetos','Alertas de cerca e eventos','Manutenções e despesas','Aplicativo e acesso web'] as $li)
                <li><i class="fa-solid fa-circle-check" style="color:var(--teal)"></i> {{ $li }}</li>
                @endforeach
            </ul>
            <a href="{{ route('site.solucoes') }}" class="btn btn-dark">Conhecer a plataforma →</a>
        </div>
        <div class="hero-visual" style="border-radius:22px">
            <div class="mock">
                <div class="mock-top"><span>●</span> ● ● &nbsp; App Golden</div>
                <div class="mock-body" style="grid-template-columns:1fr">
                    <div class="mock-map">
                        <div class="mock-route"></div>
                        <span class="mock-pin p1"><i class="fa-solid fa-location-dot"></i></span>
                        <span class="mock-pin p2"><i class="fa-solid fa-truck"></i></span>
                        <div class="mock-metrics"><span>Em rota <strong>6</strong></span><span>Cercas <strong>3</strong></span><span>Alertas <strong>2</strong></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ATALHOS PARA AS PÁGINAS DEDICADAS --}}
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Explore o site</span>
        <h2>Tudo o que a Golden faz, página por página.</h2>
        <p class="lead-sub">Cada assunto tem sua própria página, com o detalhe completo.</p>
        <div class="atalhos">
            @php
                $atalhos = [
                    ['rota' => 'site.solucoes', 'icone' => 'fa-layer-group', 'titulo' => 'Plataforma e funcionalidades', 'texto' => 'Mapa, cercas, alertas, relatórios e o app em detalhe.'],
                    ['rota' => 'site.planos', 'icone' => 'fa-tags', 'titulo' => 'Planos e kits', 'texto' => 'Kits com rastreador e chip M2M por tamanho de frota.'],
                    ['rota' => 'site.segmentos', 'icone' => 'fa-truck', 'titulo' => 'Para quem é', 'texto' => 'Motorista autônomo, carro de passeio, frota e pesados.'],
                    ['rota' => 'site.como-funciona', 'icone' => 'fa-route', 'titulo' => 'Como funciona', 'texto' => 'Da escolha do kit ao acompanhamento diário.'],
                    ['rota' => 'site.faq', 'icone' => 'fa-circle-question', 'titulo' => 'Dúvidas frequentes', 'texto' => 'Instalação, sinal, mensalidade, bloqueio e LGPD.'],
                    ['rota' => 'site.contato', 'icone' => 'fa-headset', 'titulo' => 'Falar com especialista', 'texto' => 'Formulário de proposta e canais de atendimento.'],
                ];
            @endphp
            @foreach($atalhos as $a)
            <a href="{{ route($a['rota']) }}" class="atalho" data-track="internal_link" data-cta="atalho_{{ $a['rota'] }}">
                <span class="atalho-ic"><i class="fa-solid {{ $a['icone'] }}" aria-hidden="true"></i></span>
                <span class="atalho-txt"><b>{{ $a['titulo'] }}</b><small>{{ $a['texto'] }}</small></span>
                <i class="fa-solid fa-arrow-right atalho-go" aria-hidden="true"></i>
            </a>
            @endforeach
        </div>
        @if($golden['loja_ativa'])
        <p class="center" style="margin-top:22px">
            <a href="{{ route('site.loja.index') }}" class="btn btn-ghost" data-track="store_open" data-cta="atalhos"><i class="fa-solid fa-store"></i> Comprar rastreador e chip M2M na loja →</a>
        </p>
        @endif
    </div>
</section>

{{-- DEPOIMENTOS --}}
@if($depoimentos !== [])
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}?v={{ filemtime(public_path('vendor/swiper/swiper-bundle.min.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}?v={{ filemtime(public_path('vendor/swiper/swiper-bundle.min.js')) }}"></script>
@endpush
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Depoimentos</span>
        <h2>Quem confia, recomenda.</h2>
        <p class="lead-sub">Avaliações reais de clientes Golden Rastreamento. Segure e arraste para ver mais.</p>
        <div class="depo">
            <div class="swiper js-depoimentos">
                <div class="swiper-wrapper">
                    @foreach($depoimentos as $d)
                    <div class="swiper-slide">
                        <div class="depo-card">
                            <div class="stars">{{ str_repeat('★', max(1, min(5, (int) $d['nota']))) }}</div>
                            <p>"{{ $d['texto'] }}"</p>
                            <div class="depo-who">
                                <div class="avatar">{{ mb_strtoupper(mb_substr($d['nome'], 0, 1)) }}</div>
                                <div><b>{{ $d['nome'] }}</b>@if($d['papel'])<small>{{ $d['papel'] }}@if($d['cidade']) · {{ $d['cidade'] }}@endif</small>@endif</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="depo-ctl">
                <button type="button" class="depo-nav depo-prev" aria-label="Depoimento anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <div class="swiper-pagination depo-dots"></div>
                <button type="button" class="depo-nav depo-next" aria-label="Próximo depoimento"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
</section>
@endif

{{-- CTA FINAL --}}
<section style="padding-top:0">
    <div class="wrap">
        <div class="cta-final">
            <span class="eyebrow on-dark">Pronto para ter mais controle?</span>
            <h2>Fale com um especialista e receba sua proposta.</h2>
            <div class="cta-actions">
                <a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="footer">Quero uma proposta →</a>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-outline" data-track="whatsapp_click" data-cta="footer"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<style>
.mock{background:linear-gradient(160deg,#1b2127,#0d1114);border-radius:22px;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
.mock-top{padding:12px 16px;font-size:.78rem;color:#8a97a4;border-bottom:1px solid rgba(255,255,255,.06)}
.mock-top span{color:var(--gold)}
.mock-body{display:grid;grid-template-columns:120px 1fr;min-height:280px}
.mock-side{padding:16px 12px;border-right:1px solid rgba(255,255,255,.06);color:#9fb0bd;font-size:.82rem}
.mock-side b{color:var(--gold);letter-spacing:.1em}
.mock-side hr{border:0;border-top:1px solid rgba(255,255,255,.08);margin:10px 0}
.mock-side span{display:block;padding:6px 0}
.mock-map{position:relative;background:radial-gradient(400px 200px at 60% 40%,rgba(31,158,134,.18),transparent 60%),#12161a;overflow:hidden}
.mock-route{position:absolute;inset:0;background:linear-gradient(115deg,transparent 46%,rgba(224,163,62,.5) 47%,rgba(224,163,62,.5) 48%,transparent 49%),linear-gradient(200deg,transparent 55%,rgba(31,158,134,.5) 56%,rgba(31,158,134,.5) 57%,transparent 58%)}
.mock-pin{position:absolute;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:.85rem;box-shadow:0 6px 16px rgba(0,0,0,.4)}
.mock-pin.p1{background:var(--gold);top:30%;left:30%}
.mock-pin.p2{background:var(--teal);top:55%;left:62%}
.mock-metrics{position:absolute;left:14px;right:14px;bottom:14px;display:flex;gap:10px;flex-wrap:wrap}
.mock-metrics span{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:8px 12px;font-size:.75rem;color:#9fb0bd}
.mock-metrics strong{color:#fff;margin-left:6px}
@media(max-width:960px){.mock-body{grid-template-columns:90px 1fr}}
</style>
@endpush
