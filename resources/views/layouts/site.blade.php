<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $golden['marca']['nome'] . ' — ' . $golden['marca']['slogan'])</title>
    <meta name="description" content="@yield('meta_description', 'Rastreamento e gestão veicular para frotas, motoristas e empresas. Kits com rastreador J16 4G/2G e chip M2M.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', $golden['marca']['nome'])">
    <meta property="og:description" content="@yield('meta_description', 'Rastreamento e gestão veicular.')">
    <meta property="og:type" content="website">
    <meta property="og:image" content="@yield('og_image', site_img('site_compartilhar'))">
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    @include('partials.icones')
    <link rel="stylesheet" href="{{ asset('css/golden-to-top.css') }}?v={{ filemtime(public_path('css/golden-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-site.css') }}?v={{ filemtime(public_path('css/golden-site.css')) }}">
    {{-- O assistente é um dos atendentes da caixa do WhatsApp: folha e script só pesam quando
         existe caixa (ou quando ele é o único atendente) e ele atende neste canal. --}}
    @php $bolha = $golden['assistente_na_caixa'] && \App\Models\Assistente::atendeVisitante('site'); @endphp
    @if($bolha)
    <link rel="stylesheet" href="{{ asset('css/golden-assistente.css') }}?v={{ filemtime(public_path('css/golden-assistente.css')) }}">
    @endif
    @stack('styles')

    @if($golden['tracking'])
        @php
            $gtagId = $golden['ga_id'] ?: $golden['google_ads_tag'];
        @endphp
        @if($gtagId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtagId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            @if($golden['ga_id'])
            gtag('config', '{{ $golden['ga_id'] }}');
            @endif
            @if($golden['google_ads_tag'])
            gtag('config', '{{ $golden['google_ads_tag'] }}');
            @endif
        </script>
        @endif
        @if($golden['gtm_id'])
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $golden['gtm_id'] }}');</script>
        <!-- End Google Tag Manager -->
        @endif
        @if($golden['meta_pixel'])
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;t=b.getElementsByTagName(e)[0];s=b.createElement(e);s.async=!0;s.src=v;b.parentNode.insertBefore(s,t)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $golden['meta_pixel'] }}');fbq('track','PageView');</script>
        @endif
    @endif
</head>
<body>
@if($golden['tracking'] && !empty($golden['gtm_id']))
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $golden['gtm_id'] }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif
<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="{{ route('site.home') }}">
            <x-site.img imagem="marca_logo" :largura="false" :altura="false" :lazy="false" />
            <span><b>GOLDEN</b><small>Rastreamento · Gestão</small></span>
        </a>
        <nav class="nav-links" aria-label="Menu principal">
            @include('site.partials.nav-links', ['escopo' => 'topo'])
        </nav>
        <div class="nav-cta">
            @include('site.partials.acesso-conta')
            @if($golden['loja_ativa'])
            <a href="{{ route('site.loja.carrinho') }}" class="cart-link" aria-label="Abrir carrinho ({{ $golden['carrinho_itens'] }} {{ $golden['carrinho_itens'] === 1 ? 'item' : 'itens' }})" title="Carrinho">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="cart-count" data-cart-count @if($golden['carrinho_itens'] <= 0) hidden @endif>{{ $golden['carrinho_itens'] }}</span>
            </a>
            @endif
            <button type="button" class="btn btn-gold btn-sm nav-especialista" data-acesso-plataformas aria-haspopup="dialog" aria-controls="acessoPlataformas">{{ $golden['acessos']['botao'] }}</button>
            <button class="nav-toggle" id="navToggle" type="button" aria-label="Abrir menu" aria-controls="navDrawer" aria-expanded="false">
                <i class="fa-solid fa-bars icon-open"></i>
                <i class="fa-solid fa-xmark icon-close"></i>
            </button>
        </div>
    </div>
</header>

{{-- Gaveta lateral (aparece só em telas menores) --}}
<nav class="nav-drawer" id="navDrawer" aria-label="Menu principal">
    <div class="drawer-head">
        <span>
            <x-site.img imagem="marca_logo" alt="" :largura="false" :altura="false" />
            GOLDEN
        </span>
        <button type="button" class="drawer-close" data-nav-close aria-label="Fechar menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    @include('site.partials.nav-links', ['escopo' => 'gaveta'])
    <button type="button" class="btn btn-gold btn-sm drawer-cta" data-acesso-plataformas aria-haspopup="dialog" aria-controls="acessoPlataformas">{{ $golden['acessos']['botao'] }}</button>
    <div class="drawer-foot">
        <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" style="color:var(--gold)"></i> {{ $golden['telefone'] }}</a><br>
        <a href="mailto:{{ $golden['email'] }}"><i class="fa-solid fa-envelope" style="color:var(--gold)"></i> {{ $golden['email'] }}</a>
    </div>
</nav>
<button type="button" class="nav-backdrop" id="navBackdrop" tabindex="-1" aria-label="Fechar menu"></button>

<main>
@yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <div class="foot-brand">
                    <x-site.img imagem="marca_logo" :largura="false" :altura="false" />
                    <b>GOLDEN</b>
                </div>
                <p style="max-width:34ch">{{ $golden['marca']['slogan'] }}. Tecnologia de autorrastreamento e gestão para quem faz o negócio acontecer.</p>
                <p style="font-size:.85rem"><i class="fa-solid fa-location-dot" style="color:var(--gold)"></i> {{ $golden['endereco'] }} — {{ $golden['cidade_uf'] }} · CEP {{ $golden['cep'] }}</p>
                <p style="font-size:.85rem"><i class="fa-solid fa-phone" style="color:var(--gold)"></i> {{ $golden['telefone'] }}</p>
            </div>
            <div>
                <h5>Navegação</h5>
                <a href="{{ route('site.solucoes') }}">Soluções</a>
                <a href="{{ route('site.segmentos') }}">Para quem é</a>
                <a href="{{ route('site.planos') }}">Planos e kits</a>
                <a href="{{ route('site.como-funciona') }}">Como funciona</a>
                @if($golden['loja_ativa'])
                <a href="{{ route('site.loja.index') }}">Loja (rastreadores e chips)</a>
                @endif
            </div>
            <div>
                <h5>Suporte</h5>
                <a href="{{ $golden['portal_url'] }}">Área do cliente</a>
                <a href="{{ route('site.faq') }}">Dúvidas frequentes</a>
                <a href="{{ route('site.videos') }}">Vídeos tutoriais</a>
                <a href="{{ route('site.contato') }}">Fale conosco</a>
                <a href="{{ route('site.privacidade') }}">Privacidade (LGPD)</a>
                <p style="font-size:.8rem;margin-top:.6rem;color:var(--muted)">Chips M2M: {{ implode(', ', $golden['parceiros']) }}<br>Pagamento: {{ implode(' ou ', $golden['pagamentos']) }}</p>
            </div>
            <div>
                <h5>Contato</h5>
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp comercial</a>
                <a href="mailto:{{ $golden['email'] }}"><i class="fa-solid fa-envelope"></i> {{ $golden['email'] }}</a>
            </div>
        </div>
        <div class="foot-bottom">
            <span>© {{ date('Y') }} {{ $golden['marca']['nome'] }}. Todos os direitos reservados.</span>
            <span>A Golden não presta vigilância humana, pronta resposta nem seguro.</span>
            @include('partials.desenvolvedor')
        </div>
    </div>
</footer>

@include('site.partials.whatsapp-box')

@include('site.partials.acesso-plataformas')

@include('site.partials.assistente-bolha', ['canal' => 'site', 'bolha' => $bolha])

@include('partials.to-top')

@include('site.partials.cookie-bar')

@include('partials.avisos', ['confirmacoes' => false])
@php
    $medicaoSite = [
        'tracking' => (bool) $golden['tracking'],
        'farol' => route('site.rastreio.evento'),
        'google_ads_tag' => $golden['google_ads_tag'] ?? null,
        'google_ads_conversion' => $golden['google_ads_conversion'] ?? null,
    ];
@endphp
<script>window.GOLDEN = @json($medicaoSite);</script>
@if(session('lead_ok') && $golden['tracking'] && !empty($golden['google_ads_conversion']))
    @php
        $sendToLead = $golden['google_ads_conversion'];
        if (!str_contains($sendToLead, '/') && !empty($golden['google_ads_tag'])) {
            $sendToLead = $golden['google_ads_tag'] . '/' . $sendToLead;
        }
    @endphp
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            if (typeof gtag === 'function') {
                gtag('event', 'conversion', {
                    'send_to': '{{ $sendToLead }}',
                    'value': 1.0,
                    'currency': 'BRL'
                });
            }
        });
    </script>
@endif
<script src="{{ asset('js/golden-site.js') }}?v={{ filemtime(public_path('js/golden-site.js')) }}"></script>
<script src="{{ asset('js/golden-to-top.js') }}?v={{ filemtime(public_path('js/golden-to-top.js')) }}"></script>
@if($bolha)
<script src="{{ asset('js/golden-assistente.js') }}?v={{ filemtime(public_path('js/golden-assistente.js')) }}"></script>
@endif
@stack('scripts')
</body>
</html>
