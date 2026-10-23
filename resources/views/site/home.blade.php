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
                <a href="#planos" class="btn btn-gold" data-track="view_plans" data-cta="hero">Quero uma proposta →</a>
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

{{-- BENEFÍCIOS --}}
<section class="sec-light" id="beneficios">
    <div class="wrap">
        <span class="eyebrow">Por que escolher a Golden</span>
        <h2>Tecnologia que gera tranquilidade<br>e produtividade.</h2>
        <p class="lead-sub">Acompanhe seus veículos em tempo real, reduza custos, organize a operação e tome decisões com mais segurança.</p>
        <div class="grid-3">
            @foreach($beneficios as $b)
            <article class="card">
                <div class="ic"><i class="fa-solid {{ $b['icone'] }}"></i></div>
                <h3>{{ $b['titulo'] }}</h3>
                <p>{{ $b['texto'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

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

{{-- SEGMENTOS --}}
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Para quem é a Golden</span>
        <h2>Soluções para diferentes necessidades.</h2>
        <p class="lead-sub">Atendemos desde motoristas que utilizam o próprio veículo até empresas com grandes frotas.</p>
        <div class="seg-grid">
            @foreach($segmentos as $s)
            <div class="seg">
                <i class="fa-solid {{ $s['icone'] }}"></i>
                <h4>{{ $s['titulo'] }}</h4>
                <p>{{ $s['texto'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- KITS --}}
<section class="sec-alt" id="planos">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Kits com chip M2M</span>
            <h2>Escolha a solução ideal para a sua frota.</h2>
            <p class="lead-sub">Equipamentos prontos para uso na plataforma Golden. Instalação realizada por profissional contratado pelo cliente.</p>
        </div>
        <div class="kits">
            @foreach($kits as $kit)
            <article class="kit {{ $kit['destaque'] ? 'feature' : '' }}">
                @if(!empty($kit['rotulo']))<span class="kit-tag">{{ $kit['rotulo'] }}</span>@endif
                <span class="eyebrow">{{ $kit['eyebrow'] }}</span>
                <div class="num">{{ $kit['titulo'] }} <small>veículos</small></div>
                <ul>
                    @foreach($kit['itens'] as $item)
                    <li><i class="fa-solid fa-check"></i> {{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('site.contato') }}?kit={{ urlencode($kit['titulo']) }}" class="btn {{ $kit['destaque'] ? 'btn-gold' : 'btn-dark' }}" data-kit="{{ $kit['titulo'] }}" data-fleet="{{ $kit['id'] == 'kit-5' ? 5 : ($kit['id'] == 'kit-10' ? 10 : '10+') }}">{{ $kit['botao'] }} →</a>
            </article>
            @endforeach
        </div>
        <p class="fine center" style="margin-top:26px">A Golden não cobra instalação: o cliente contrata e remunera diretamente o instalador de sua confiança. Também atendemos unidade avulsa — fale com um especialista.</p>
    </div>
</section>

{{-- COMO FUNCIONA --}}
<section class="sec-dark">
    <div class="wrap">
        <div class="center on-dark">
            <span class="eyebrow on-dark">Como funciona</span>
            <h2>Da aquisição ao controle, de forma simples.</h2>
        </div>
        <div class="steps">
            @foreach($passos as $p)
            <div class="step">
                <div class="n">{{ $p['n'] }}</div>
                <div class="ic"><i class="fa-solid {{ $p['icone'] }}"></i></div>
                <h4>{{ $p['titulo'] }}</h4>
                <p>{{ $p['texto'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- DEPOIMENTOS --}}
@if($depoimentos->isNotEmpty())
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Depoimentos</span>
        <h2>Quem confia, recomenda.</h2>
        <p class="lead-sub">Avaliações reais de clientes Golden Rastreamento.</p>
        <div class="depo">
            @foreach($depoimentos as $d)
            <div class="depo-card">
                <div class="stars">{{ str_repeat('★', max(1, min(5, (int) $d->nota))) }}</div>
                <p>"{{ $d->texto }}"</p>
                <div class="depo-who">
                    <div class="avatar">{{ strtoupper(substr($d->nome, 0, 1)) }}</div>
                    <div><b>{{ $d->nome }}</b>@if($d->papel)<small>{{ $d->papel }}@if($d->cidade) · {{ $d->cidade }}@endif</small>@endif</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- FAQ --}}
<section class="sec-alt">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Dúvidas frequentes</span>
            <h2>Perguntas que mais recebemos.</h2>
        </div>
        <div class="faq">
            @foreach(array_slice(\App\Models\Faq::paraSite(), 0, 5) as $f)
            <details>
                <summary>{{ $f['q'] }}</summary>
                <p>{{ $f['a'] }}</p>
            </details>
            @endforeach
        </div>
        <p class="center" style="margin-top:22px"><a href="{{ route('site.faq') }}" class="btn btn-ghost btn-sm">Ver todas as perguntas →</a></p>
    </div>
</section>

{{-- CONTATO / LEAD --}}
<section class="sec-light" id="contato">
    <div class="wrap lead-panel">
        <div>
            <span class="eyebrow">Fale com a Golden</span>
            <h2>Vamos encontrar a solução ideal para seus veículos?</h2>
            <p class="lead-sub">Solicite uma proposta para sua frota ou fale com nossa equipe. Retornamos rapidamente com as condições dos kits e da mensalidade.</p>
            <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap">
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-gold" data-track="whatsapp_click" data-cta="contact"><i class="fa-brands fa-whatsapp"></i> Conversar agora</a>
            </div>
            <p class="fine" style="margin-top:16px"><i class="fa-solid fa-lock" style="color:var(--teal)"></i> Seus dados ficam seguros e são usados apenas para o seu atendimento.</p>
        </div>
        @include('site.partials.lead-form')
    </div>
</section>

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
