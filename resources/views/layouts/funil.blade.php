<!doctype html>
{{--
    Carcaça da página de funil: campanha de divulgação que não entra no menu nem na busca.
    Sem navegação para o resto do site — quem chegou por um link de anúncio tem um só
    caminho para seguir, que é a chamada da página. A paleta vem das mesmas folhas do site
    (golden-theme + golden-site); golden-funil.css só acrescenta as peles e o layout do funil.
--}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pagina->tituloSite() }}</title>
    <meta name="description" content="{{ $pagina->resumoSite() }}">
    @if($pagina->meta_keywords)
    <meta name="keywords" content="{{ $pagina->meta_keywords }}">
    @endif
    <meta name="robots" content="{{ $pagina->metaRobots() }}">
    <meta name="theme-color" content="#0d1114">
    <link rel="canonical" href="{{ $pagina->canonical ?: $pagina->url() }}">
    <link rel="alternate" hreflang="{{ app()->getLocale() }}" href="{{ $pagina->url() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $golden['marca']['nome'] }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="{{ $pagina->tituloSite() }}">
    <meta property="og:description" content="{{ $pagina->resumoSite() }}">
    <meta property="og:url" content="{{ $pagina->url() }}">
    @php $cartaz = $pagina->imageUrl() ?: \App\Models\FunnelBlock::thumbDaPagina($pagina); @endphp
    @if($cartaz)
    <meta property="og:image" content="{{ $cartaz }}">
    <meta property="og:image:alt" content="{{ $pagina->og_imagem_alt ?: $pagina->tituloSite() }}">
    <meta name="twitter:image" content="{{ $cartaz }}">
    @endif
    <meta name="twitter:card" content="{{ $cartaz ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pagina->tituloSite() }}">
    <meta name="twitter:description" content="{{ $pagina->resumoSite() }}">

    @include('partials.favicon')
    @yield('cabecalho')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    @include('partials.icones')
    <link rel="stylesheet" href="{{ asset('css/golden-to-top.css') }}?v={{ filemtime(public_path('css/golden-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-site.css') }}?v={{ filemtime(public_path('css/golden-site.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-funil.css') }}?v={{ filemtime(public_path('css/golden-funil.css')) }}">
    {{-- A bolha do assistente tem folha e script próprios, e só pesam aqui se o robô for
         aparecer: a mesma pergunta decide o estilo, o botão incluído embaixo e o script. --}}
    @php $bolha = \App\Models\Assistente::atendeVisitante('funil'); @endphp
    @if($bolha)
    <link rel="stylesheet" href="{{ asset('css/golden-assistente.css') }}?v={{ filemtime(public_path('css/golden-assistente.css')) }}">
    @endif
    {{-- O bloco que desliza puxa o Swiper; os outros blocos deixam a página leve. --}}
    @stack('styles')

    {{-- Os pixels são da página, não do site inteiro: cada campanha measure só o que
         aconteceu nela. A barra de consentimento está na tela e o rodapé leva à política. --}}
    @if($pagina->pixel_ga4)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $pagina->pixel_ga4 }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pagina->pixel_ga4 }}');</script>
    @endif
    @if($pagina->pixel_google_ads)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $pagina->pixel_google_ads }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pagina->pixel_google_ads }}');</script>
    @endif
    @if($pagina->pixel_meta)
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;t=b.getElementsByTagName(e)[0];s=b.createElement(e);s.async=!0;s.src=v;b.parentNode.insertBefore(s,t)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pagina->pixel_meta }}');fbq('track','PageView');</script>
    @endif
    @if($pagina->pixel_tiktok)
    <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};ttq.load('{{ $pagina->pixel_tiktok }}');ttq.page();</script>
    @endif
    {!! $pagina->codigo_head !!}
</head>
<body class="funil pele-{{ $pagina->pele }}">
<header class="f-topo">
    <div class="wrap">
        <a class="brand" href="{{ $pagina->url() }}">
            <x-site.img imagem="marca_logo" :largura="false" :altura="false" :lazy="false" />
            <span><b>GOLDEN</b><small>Rastreamento · Gestão</small></span>
        </a>
        <div class="f-topo-contatos">
            <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" data-track="whatsapp_click" data-cta="topo_funil">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Falar agora
            </a>
            <a href="tel:{{ $golden['whatsapp'] }}">
                <i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $golden['telefone'] }}
            </a>
        </div>
    </div>
</header>

<main id="conteudo">
@yield('content')
</main>

<footer class="f-rodape">
    <div class="wrap">
        <p><b>{{ $golden['marca']['nome'] }}</b> — {{ $golden['marca']['slogan'] }}. {{ $golden['endereco'] }}, {{ $golden['cidade_uf'] }}.</p>
        @if($pagina->nota_legal)<p class="fine">{{ $pagina->nota_legal }}</p>@endif
        <p class="fine">A Golden não presta vigilância humana, pronta resposta nem seguro. <a href="{{ route('site.privacidade') }}">Política de Privacidade</a> · {{ $golden['email'] }}</p>
        <div class="f-rodape-baixo">@include('partials.desenvolvedor')</div>
    </div>
</footer>

@include('site.partials.whatsapp-box')
@include('site.partials.assistente-bolha', ['canal' => 'funil', 'bolha' => $bolha])
@include('partials.to-top')
@include('site.partials.cookie-bar')
@include('partials.avisos', ['confirmacoes' => false])
{{-- Sem o modal de vídeo da galeria: aqui o player toca na própria faixa (bloco de vídeo),
     então não há nada que abra em cima da página. --}}

@php
    // O que o golden-site.js precisa saber para medir a página: o endereço do farol e as
    // marcas de conversão da campanha. Array fechado antes do @json — dentro de um
    // <script> ele não sobrevive à compilação do Blade.
    $medicao = [
        'tracking' => true,
        'farol' => route('site.rastreio.evento'),
        'funil' => [
            'preco_cents' => $pagina->preco_cents,
            'conversao_google_ads' => $pagina->conversao_google_ads,
            'pixel_meta' => $pagina->pixel_meta,
            'pixel_tiktok' => $pagina->pixel_tiktok,
        ],
    ];
@endphp
<script>window.GOLDEN = @json($medicao);</script>
<script src="{{ asset('js/golden-site.js') }}?v={{ filemtime(public_path('js/golden-site.js')) }}"></script>
<script src="{{ asset('js/golden-to-top.js') }}?v={{ filemtime(public_path('js/golden-to-top.js')) }}"></script>
@if($bolha)
<script src="{{ asset('js/golden-assistente.js') }}?v={{ filemtime(public_path('js/golden-assistente.js')) }}"></script>
@endif
{{-- O player embutido e os carrosséis dos blocos carregam o que só esta página usa. --}}
@stack('scripts')
{!! $pagina->codigo_body !!}
</body>
</html>
