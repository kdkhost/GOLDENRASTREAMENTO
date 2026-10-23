{{--
    Layout da área logada do portal. Tem barra própria (não a navegação comercial do site):
    quem entra aqui quer ver pedido, chamado e contrato, não o funil de vendas. O CSS é o
    mesmo do site, com uma folha só para as telas do cliente.
--}}
@php
    $cliente = \Illuminate\Support\Facades\Auth::guard('cliente')->user();
    $abas = [
        'Início' => ['rota' => 'portal.inicio', 'icone' => 'fa-house', 'prefixo' => 'portal.inicio'],
        'Pedidos' => ['rota' => 'portal.pedidos', 'icone' => 'fa-box-open', 'prefixo' => 'portal.pedidos'],
        'Chamados' => ['rota' => 'portal.chamados', 'icone' => 'fa-headset', 'prefixo' => 'portal.chamados'],
        'Contratos' => ['rota' => 'portal.contratos', 'icone' => 'fa-file-signature', 'prefixo' => 'portal.contratos'],
        'Meus dados' => ['rota' => 'portal.dados', 'icone' => 'fa-user-gear', 'prefixo' => 'portal.dados'],
    ];
    $inicial = mb_strtoupper(mb_substr(trim($cliente->nome ?? 'G'), 0, 1));
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Portal do cliente — '.$golden['marca']['nome'])</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-site.css') }}?v={{ filemtime(public_path('css/golden-site.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-portal.css') }}?v={{ filemtime(public_path('css/golden-portal.css')) }}">
    @stack('styles')
</head>
<body>
<header class="portal-bar">
    <div class="wrap nav">
        <a class="brand" href="{{ route('portal.inicio') }}">
            <x-site.img imagem="marca_logo" :largura="false" :altura="false" :lazy="false" />
            <span><b>GOLDEN</b><small>Portal do cliente</small></span>
        </a>
        <div class="portal-quem">
            <span class="portal-inicial" aria-hidden="true">{{ $inicial }}</span>
            <span>
                <b>{{ $cliente->nome }}</b>
                <small>{{ $cliente->email_portal }}</small>
            </span>
        </div>
        <form method="post" action="{{ route('portal.sair') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</button>
        </form>
    </div>
</header>

<nav class="portal-nav" aria-label="Área do cliente">
    <div class="wrap">
        <ul>
            @foreach($abas as $rotulo => $aba)
            <li>
                <a href="{{ route($aba['rota']) }}" @if(request()->routeIs($aba['prefixo'].'*')) class="atual" aria-current="page" @endif>
                    <i class="fa-solid {{ $aba['icone'] }}"></i> {{ $rotulo }}
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</nav>

<main class="portal-main">
    <div class="wrap">
        @foreach(['success' => 'fa-circle-check', 'error' => 'fa-triangle-exclamation', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'] as $tipo => $icone)
            @if(session($tipo))
            <div class="flash" role="status">
                <span class="{{ $tipo === 'success' ? 'ok' : 'err' }}">
                    <i class="fa-solid {{ $icone }}"></i> {{ session($tipo) }}
                </span>
            </div>
            @endif
        @endforeach

        @if($errors->any())
        <div class="flash" role="alert">
            <span class="err"><i class="fa-solid fa-triangle-exclamation"></i> {{ $errors->first() }}</span>
        </div>
        @endif

        @yield('content')
    </div>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="foot-bottom">
            <span>© {{ date('Y') }} {{ $golden['marca']['nome'] }}. Todos os direitos reservados.</span>
            <span><a href="{{ route('site.contato') }}" style="text-decoration:underline">Falar com a equipe</a> · <a href="{{ route('site.privacidade') }}" style="text-decoration:underline">Privacidade (LGPD)</a></span>
        </div>
    </div>
</footer>

@stack('scripts')
</body>
</html>
