@extends('layouts.site')

@section('title', 'Golden Rastreamento — Mais controle para o seu veículo')
@section('meta_description', 'Rastreamento e gestão veicular para frotas, motoristas e empresas. Kits com rastreador J16 4G/2G e chip M2M. Solicite sua proposta.')

@section('content')
{{-- HERO com fotografia da central de monitoramento --}}
<section class="hero hero-foto">
    <x-site.img imagem="home_hero" class="foto" :lazy="false" :prioridade="true" />
    <div class="wrap hero-grid">
        <div class="on-dark">
            <span class="eyebrow on-dark">Rastreamento e gestão veicular</span>
            <h1>Mais controle<br>para o seu veículo.<br><em>Mais resultados<br>para o seu negócio.</em></h1>
            <p>Soluções completas de rastreamento e gestão para frotas e motoristas profissionais. Tecnologia, presença em tempo real e informações para decisões mais seguras.</p>
            <div class="hero-actions">
                <a href="{{ route('site.planos') }}" class="btn btn-gold" data-track="view_plans" data-cta="hero">Quero uma proposta →</a>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-outline" data-track="whatsapp_click" data-cta="hero"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
            </div>
        </div>
    </div>
    <div class="trust">
        <div class="wrap trust-grid">
            <div class="trust-item"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Rastreadores homologados Anatel</div>
            <div class="trust-item"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i> Aplicativo web e mobile</div>
            <div class="trust-item"><i class="fa-solid fa-headset" aria-hidden="true"></i> Atendimento especializado</div>
            <div class="trust-item"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Planos para frotas e veículos</div>
        </div>
    </div>
</section>

{{-- POR QUE ESCOLHER --}}
<section class="sec-light">
    <div class="wrap split">
        <div>
            <span class="eyebrow">Por que escolher a Golden</span>
            <h2>Tecnologia que gera tranquilidade e produtividade.</h2>
            <p class="lead-sub">Acompanhe seus veículos em tempo real, reduza custos, organize sua operação e tome decisões com mais segurança.</p>
            <p><a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="home_beneficios">Falar com um especialista →</a></p>
        </div>
        <div class="ben-grid">
            @foreach($beneficios as $b)
            <article class="ben">
                <i class="fa-solid {{ $b['icone'] }}" aria-hidden="true"></i>
                <h4>{{ $b['titulo'] }}</h4>
                <p>{{ $b['texto'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- APLICATIVO E PLATAFORMA — fotografia em largura total, texto sobre o lado escuro --}}
<section class="sec-dark sec-foto sec-foto-esq">
    <x-site.img imagem="home_plataforma" class="foto" />
    <div class="wrap hero-grid">
        <div class="on-dark">
            <span class="eyebrow on-dark">Aplicativo e plataforma</span>
            <h2>Tudo o que você precisa, na palma da sua mão.</h2>
            <p>Acesse pelo celular ou computador, com uma plataforma completa e fácil de usar. Acompanhe o veículo, receba alertas, consulte relatórios e gerencie sua frota de onde estiver.</p>
            <div class="hero-actions">
                <a href="{{ route('site.solucoes') }}" class="btn btn-gold" data-track="internal_link" data-cta="home_plataforma">Conhecer a plataforma →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-outline" data-track="contact" data-cta="home_plataforma_demo">Solicitar demonstração</a>
            </div>
        </div>
    </div>
</section>

{{-- PARA QUEM É --}}
<section class="sec-alt">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow">Para quem é a Golden</span>
                <h2>Soluções para diferentes necessidades.</h2>
            </div>
            <p class="lead-sub">Atendemos desde motoristas que utilizam o próprio veículo até empresas com grandes frotas, sempre com soluções adequadas a cada tipo de operação.</p>
        </div>
        <div class="seg-grid">
            @foreach($segmentos as $s)
            <a href="{{ route('site.segmentos') }}" class="seg-foto" data-track="internal_link" data-cta="home_segmento">
                <x-site.img :imagem="$s['slot']" class="foto" />
                <i class="fa-solid {{ $s['icone'] }}" aria-hidden="true"></i>
                <h4>{{ $s['titulo'] }}</h4>
                <p>{{ $s['texto'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- KITS --}}
<section class="sec-light">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow">Nossos kits</span>
                <h2>Escolha a solução ideal para a sua frota.</h2>
            </div>
            <div>
                <p class="lead-sub">Equipamentos com chip M2M, prontos para uso na plataforma Golden. Instalação realizada por profissional contratado pelo cliente.</p>
                <p><a href="{{ route('site.planos') }}" class="btn btn-dark" data-track="view_plans" data-cta="home_kits">Ver todos os planos →</a></p>
            </div>
        </div>
        <div class="kits">
            @foreach($kits as $kit)
            <article class="kit {{ $kit['destaque'] ? 'feature' : '' }}">
                @if(!empty($kit['rotulo']))<span class="kit-tag">{{ $kit['rotulo'] }}</span>@endif
                <span class="eyebrow">{{ $kit['eyebrow'] }}</span>
                <div class="num">{{ $kit['numero'] }} <small>veículos</small></div>
                <ul>
                    @foreach($kit['itens'] as $item)
                    <li><i class="fa-solid fa-check" aria-hidden="true"></i> {{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('site.contato') }}?kit={{ urlencode($kit['titulo']) }}" class="btn {{ $kit['destaque'] ? 'btn-gold' : 'btn-dark' }}" data-kit="{{ $kit['titulo'] }}">{{ $kit['botao'] }} →</a>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- EQUIPAMENTO J16 — fotografia em largura total, texto sobre o lado escuro --}}
<section class="sec-dark sec-foto sec-foto-dir">
    <x-site.img imagem="home_equipamento" class="foto" />
    <div class="wrap hero-grid">
        <div class="on-dark col-dir">
            <span class="eyebrow on-dark">{{ $equipamento['eyebrow'] }}</span>
            <h2>{{ Str::before($equipamento['titulo'], $equipamento['destaque']) }}<span class="destaque">{{ $equipamento['destaque'] }}</span>.</h2>
            <p>{{ $equipamento['texto'] }}</p>
            <ul class="checks">
                @foreach($equipamento['checks'] as $check)
                <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $check }}</li>
                @endforeach
            </ul>
            <div class="hero-actions">
                @if($golden['loja_ativa'])
                <a href="{{ route('site.loja.index') }}" class="btn btn-gold" data-track="store_open" data-cta="home_equipamento"><i class="fa-solid fa-store" aria-hidden="true"></i> Comprar na loja</a>
                @endif
                <a href="{{ route('site.solucoes') }}" class="btn btn-outline" data-track="internal_link" data-cta="home_equipamento_detalhes">Conhecer o equipamento →</a>
            </div>
        </div>
    </div>
</section>

{{-- COMO FUNCIONA --}}
<section class="sec-alt">
    <div class="wrap split split-larga">
        <div class="split-txt">
            <span class="eyebrow">Como funciona</span>
            <h2>Da aquisição ao controle, de forma simples.</h2>
            <p><a href="{{ route('site.como-funciona') }}" class="btn btn-dark" data-track="internal_link" data-cta="home_passos">Ver o passo a passo completo →</a></p>
        </div>
        <div class="steps">
            @foreach($passos as $p)
            <div class="step">
                <div class="ic"><i class="fa-solid {{ $p['icone'] }}" aria-hidden="true"></i></div>
                <h4>{{ $p['titulo'] }}</h4>
                <p>{{ $p['texto'] }}</p>
            </div>
            @endforeach
        </div>
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
                                <div class="avatar">@if($d['avatar'])<img src="{{ $d['avatar'] }}" alt="Foto de {{ $d['nome'] }}" loading="lazy" width="46" height="46">@else{{ $d['iniciais'] }}@endif</div>
                                <div><b>{{ $d['nome'] }}</b>@if($d['papel'])<small>{{ $d['papel'] }}@if($d['cidade']) · {{ $d['cidade'] }}@endif</small>@endif</div>
                                @if(($d['fonte'] ?? 'site') === 'google')<span class="depo-origem" title="Avaliação pública no Google"><i class="fa-brands fa-google" aria-hidden="true"></i> Google</span>@endif
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

{{-- DÚVIDAS FREQUENTES --}}
<section class="sec-alt">
    <div class="wrap split">
        <div>
            <span class="eyebrow">Dúvidas frequentes</span>
            <h2>Perguntas que mais recebemos.</h2>
            <p class="lead-sub">Tire suas dúvidas sobre o funcionamento, instalação, planos e muito mais.</p>
            <p><a href="{{ route('site.faq') }}" class="btn btn-gold" data-track="internal_link" data-cta="home_faq">Ver todas as perguntas →</a></p>
            <p><a href="{{ route('site.videos') }}" class="btn btn-ghost" data-track="internal_link" data-cta="home_videos">Ver os vídeos tutoriais →</a></p>
        </div>
        <div class="faq">
            @foreach(array_slice($faq, 0, 5) as $f)
            <details><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA FINAL --}}
<section class="cta-final cta-foto sec-foto sec-foto-esq">
    <x-site.img imagem="home_cta" class="foto" />
    <div class="wrap">
        <div class="cta-final-inner">
            <span class="eyebrow on-dark">Pronto para ter mais controle?</span>
            <h2>Fale com um especialista e encontre <span class="destaque">a solução ideal</span> para você.</h2>
            <p>Receba um atendimento personalizado e descubra como a Golden pode ajudar você ou sua empresa a ir mais longe.</p>
            <div class="cta-actions">
                <a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="footer">Quero uma proposta →</a>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-outline" data-track="whatsapp_click" data-cta="footer"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
            </div>
        </div>
    </div>
    <div class="cta-marca">
        <x-site.img imagem="marca_logo" alt="" :largura="44" :altura="44" />
        <span><b>GOLDEN</b><small>Rastreamento</small></span>
    </div>
</section>
@endsection
