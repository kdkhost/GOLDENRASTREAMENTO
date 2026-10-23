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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-site.css') }}?v={{ filemtime(public_path('css/golden-site.css')) }}">

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
        <nav class="nav-links" id="navLinks">
            <a href="{{ route('site.solucoes') }}" class="{{ request()->routeIs('site.solucoes') ? 'active' : '' }}">Soluções</a>
            <a href="{{ route('site.segmentos') }}" class="{{ request()->routeIs('site.segmentos') ? 'active' : '' }}">Para quem é</a>
            <a href="{{ route('site.planos') }}" class="{{ request()->routeIs('site.planos') ? 'active' : '' }}">Planos</a>
            <a href="{{ route('site.como-funciona') }}" class="{{ request()->routeIs('site.como-funciona') ? 'active' : '' }}">Como funciona</a>
            @if($golden['loja_ativa'])
            <a href="{{ route('site.loja.index') }}" class="{{ request()->routeIs('site.loja.*') ? 'active' : '' }}">Loja</a>
            @endif
            @foreach($golden['menu'] as $pg)
            <a href="{{ route('site.pagina', $pg->slug) }}" class="{{ request()->routeIs('site.pagina') && request()->route('slug') === $pg->slug ? 'active' : '' }}">{{ $pg->titulo }}</a>
            @endforeach
            <a href="{{ route('site.faq') }}" class="{{ request()->routeIs('site.faq') ? 'active' : '' }}">Dúvidas</a>
        </nav>
        <div class="nav-cta">
            <a href="{{ route('site.contato') }}" class="btn btn-gold btn-sm" data-track="contact" data-cta="header">Falar com especialista</a>
            <button class="nav-toggle" id="navToggle" aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</header>

<main>
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

<a class="wa-float" href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá, Golden! Quero saber mais sobre rastreamento.') }}" target="_blank" rel="noopener" aria-label="Falar no WhatsApp" data-track="whatsapp_click" data-cta="float"><i class="fa-brands fa-whatsapp"></i></a>

<div class="cookie" id="cookieBar" role="dialog" aria-live="polite">
    <p><strong>Privacidade.</strong> Usamos cookies essenciais e, com o seu consentimento, cookies de medição para entender o desempenho das campanhas, conforme a <a href="{{ route('site.privacidade') }}" style="text-decoration:underline">Política de Privacidade</a> (LGPD).</p>
    <div class="btns">
        <button class="btn btn-gold btn-sm" id="cookieAccept">Aceitar todos</button>
        <button class="btn btn-ghost btn-sm" id="cookieReject">Somente essenciais</button>
    </div>
</div>

<script>window.GOLDEN = { tracking: {{ $golden['tracking'] ? 'true' : 'false' }} };</script>
<script src="{{ asset('js/golden-site.js') }}?v={{ filemtime(public_path('js/golden-site.js')) }}"></script>
@stack('scripts')
</body>
</html>
