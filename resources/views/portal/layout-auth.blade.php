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
    @include('partials.icones')
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-login.css') }}?v={{ filemtime(public_path('css/golden-login.css')) }}">
</head>
<body>
<button type="button" class="troca-tema" id="portalTema" aria-label="Alternar tema claro e escuro">
    <i class="fas fa-moon" id="portalTemaIcone"></i>
</button>

<main class="auth-shell">
    <aside class="auth-showcase">
        <div>
            <div class="auth-brand">
                <x-site.img imagem="marca_logo" alt="{{ $golden['marca']['nome'] }}" :largura="false" :altura="false" :lazy="false" />
                <span>
                    <b>GOLDEN</b>
                    <small>@yield('marca', 'Portal do cliente')</small>
                </span>
            </div>

            @yield('showcase')
        </div>
    </aside>

    <section class="auth-panel">
        @yield('content')
    </section>

    <footer class="auth-footer">
        <a href="{{ route('site.home') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao site
        </a>
        <span class="auth-version">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            {{ $golden['marca']['nome'] }} · Versão {{ config('golden.versao', '2.65.0') }}
        </span>
    </footer>
</main>

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

    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-pass'));
            if (!input) return;
            var visivel = input.type === 'text';
            input.type = visivel ? 'password' : 'text';
            var ico = btn.querySelector('i');
            if (ico) ico.className = 'fa-regular ' + (visivel ? 'fa-eye' : 'fa-eye-slash');
        });
    });
})();
</script>
@include('partials.avisos')
</body>
</html>
