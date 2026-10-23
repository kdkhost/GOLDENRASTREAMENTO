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
    <meta property="og:image" content="{{ asset('img/logo-golden.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-to-top.css') }}?v={{ filemtime(public_path('css/golden-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-site.css') }}?v={{ filemtime(public_path('css/golden-site.css')) }}">
    @stack('styles')

    @if($golden['tracking'])
        @if($golden['ga_id'])
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $golden['ga_id'] }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $golden['ga_id'] }}');</script>
        @endif
        @if($golden['meta_pixel'])
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;t=b.getElementsByTagName(e)[0];s=b.createElement(e);s.async=!0;s.src=v;b.parentNode.insertBefore(s,t)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $golden['meta_pixel'] }}');fbq('track','PageView');</script>
        @endif
    @endif
</head>
<body>
<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="{{ route('site.home') }}">
            <img src="{{ asset('img/logo-golden.png') }}" alt="{{ $golden['marca']['nome'] }}">
            <span><b>GOLDEN</b><small>Rastreamento · Gestão</small></span>
        </a>
        <nav class="nav-links" aria-label="Menu principal">
            @include('site.partials.nav-links', ['escopo' => 'topo'])
        </nav>
        <div class="nav-cta">
            @if($golden['loja_ativa'])
            <a href="{{ route('site.loja.carrinho') }}" class="cart-link" aria-label="Abrir carrinho ({{ $golden['carrinho_itens'] }} {{ $golden['carrinho_itens'] === 1 ? 'item' : 'itens' }})" title="Carrinho">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="cart-count" data-cart-count @if($golden['carrinho_itens'] <= 0) hidden @endif>{{ $golden['carrinho_itens'] }}</span>
            </a>
            @endif
            <a href="{{ route('site.contato') }}" class="btn btn-gold btn-sm" data-track="contact" data-cta="header">Falar com especialista</a>
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
            <img src="{{ asset('img/logo-golden.png') }}" alt="">
            GOLDEN
        </span>
        <button type="button" class="drawer-close" data-nav-close aria-label="Fechar menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    @include('site.partials.nav-links', ['escopo' => 'gaveta'])
    <a href="{{ route('site.contato') }}" class="btn btn-gold btn-sm drawer-cta" data-track="contact" data-cta="menu">Falar com especialista</a>
    <div class="drawer-foot">
        <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" style="color:var(--gold)"></i> {{ $golden['telefone'] }}</a><br>
        <a href="mailto:{{ $golden['email'] }}"><i class="fa-solid fa-envelope" style="color:var(--gold)"></i> {{ $golden['email'] }}</a>
    </div>
</nav>
<button type="button" class="nav-backdrop" id="navBackdrop" tabindex="-1" aria-label="Fechar menu"></button>

<main>
@if (session('success') || session('error'))
    <div class="flash" role="status">
        @if (session('success'))<span class="ok"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</span>@endif
        @if (session('error'))<span class="err"><i class="fa-solid fa-triangle-exclamation"></i> {{ session('error') }}</span>@endif
    </div>
@endif
@yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <div class="foot-brand">
                    <img src="{{ asset('img/logo-golden.png') }}" alt="{{ $golden['marca']['nome'] }}">
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
                <a href="{{ route('site.faq') }}">Dúvidas frequentes</a>
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
        </div>
    </div>
</footer>

@include('site.partials.whatsapp-box')

@include('partials.to-top')

<div class="cookie" id="cookieBar" role="dialog" aria-live="polite">
    <p><strong>Privacidade.</strong> Usamos cookies essenciais e, com o seu consentimento, cookies de medição para entender o desempenho das campanhas, conforme a <a href="{{ route('site.privacidade') }}" style="text-decoration:underline">Política de Privacidade</a> (LGPD).</p>
    <div class="btns">
        <button class="btn btn-gold btn-sm" id="cookieAccept">Aceitar todos</button>
        <button class="btn btn-ghost btn-sm" id="cookieReject">Somente essenciais</button>
    </div>
</div>

<script>window.GOLDEN = { tracking: {{ $golden['tracking'] ? 'true' : 'false' }} };</script>
<script src="{{ asset('js/golden-site.js') }}?v={{ filemtime(public_path('js/golden-site.js')) }}"></script>
<script src="{{ asset('js/golden-to-top.js') }}?v={{ filemtime(public_path('js/golden-to-top.js')) }}"></script>
@stack('scripts')
</body>
</html>
