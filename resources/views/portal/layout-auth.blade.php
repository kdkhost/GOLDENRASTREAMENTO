{{--
    Carcaça das telas de acesso do portal (entrar, criar conta, recuperar e redefinir senha):
    o mesmo formulário de entrada do painel administrativo (auth/login.blade.php) — a mesma
    folha golden-login.css, a mesma marca, os mesmos avisos de sessão e o tema gravado no mesmo
    endereço do localStorage. O cliente entra por aqui e acorda no painel que já conhece.
--}}
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Portal do cliente') — {{ $golden['marca']['nome'] }}</title>
    @include('partials.favicon')
    @include('admin.partials.theme-init')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-login.css') }}?v={{ filemtime(public_path('css/golden-login.css')) }}">
</head>
<body>
<button type="button" class="troca-tema" id="portalTema" aria-label="Alternar tema claro e escuro"><i class="fas fa-moon" id="portalTemaIcone"></i></button>

<div class="box @yield('box')">
    <div class="brand">
        <x-site.img imagem="marca_logo" alt="Golden" :largura="false" :altura="false" :lazy="false" />
        <span><b>GOLDEN</b><small>@yield('marca', 'Portal do cliente')</small></span>
    </div>

    @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
    @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert error">{{ session('warning') }}</div>@endif
    @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

    @yield('content')

    <div class="back"><a href="{{ route('site.home') }}">← Voltar ao site</a></div>
</div>

<script>
(function () {
    var botao = document.getElementById('portalTema'), icone = document.getElementById('portalTemaIcone');
    function pintar(tema) { icone.className = 'fas ' + (tema === 'dark' ? 'fa-sun' : 'fa-moon'); }
    botao.addEventListener('click', function () {
        var tema = (localStorage.getItem('golden_admin_theme') || 'light') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('golden_admin_theme', tema);
        document.documentElement.setAttribute('data-bs-theme', tema);
        pintar(tema);
    });
    pintar(localStorage.getItem('golden_admin_theme') || 'light');
})();
</script>
</body>
</html>
