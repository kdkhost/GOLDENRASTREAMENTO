<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Acessar o painel — {{ config('golden.marca.nome', 'Golden Rastreamento') }}</title>
    @include('partials.favicon')
    @include('admin.partials.theme-init')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    @include('partials.icones')
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-login.css') }}?v={{ filemtime(public_path('css/golden-login.css')) }}">
</head>
<body>
<button type="button" class="troca-tema" id="adminTema" aria-label="Alternar tema claro e escuro">
    <i class="fas fa-moon" id="adminTemaIcone"></i>
</button>

<main class="auth-shell">
    <aside class="auth-showcase">
        <div>
            <div class="auth-brand">
                <x-site.img imagem="marca_logo" alt="{{ config('golden.marca.nome', 'Golden Rastreamento') }}" :largura="false" :altura="false" :lazy="false" />
                <span>
                    <b>GOLDEN</b>
                    <small>Painel Administrativo</small>
                </span>
            </div>

            <div style="margin-top:22px">
                <span class="auth-pill"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Acesso restrito à equipe</span>
                <h1>Gestão inteligente em um só painel</h1>
                <p class="sub">Acompanhe leads, pedidos da loja, rastreamentos, atendimento e indicadores de desempenho em tempo real.</p>
            </div>
        </div>

        <ul class="auth-features">
            <li>
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                <div><strong>Leads e conversões</strong><span>Monitoramento completo de campanhas e funil comercial.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                <div><strong>Pedidos e logística</strong><span>Controle de envios, rastreio e atendimento ao cliente.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <div><strong>Autenticação protegida</strong><span>Sessão criptografada com suporte a verificação em duas etapas.</span></div>
            </li>
        </ul>
    </aside>

    <section class="auth-panel">
        <h2>Acessar o painel</h2>
        <p class="panel-sub">Entre com seu e-mail e senha corporativa para continuar.</p>

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="field">
                <label for="email">E-mail</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope input-ico" aria-hidden="true"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@goldenrastreamento.com.br" required autofocus autocomplete="username">
                </div>
                @error('email')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="password">Senha</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                    <input id="password" type="password" name="password" placeholder="Sua senha" required autocomplete="current-password">
                    <button type="button" class="toggle-pass" data-toggle-pass="password" aria-label="Mostrar ou ocultar senha">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <div class="row">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Manter conectado</span>
                </label>
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-right-to-bracket"></i> Entrar no painel</button>
        </form>

        @if (app()->environment('local'))
            <div class="demo"><strong>Ambiente de demonstração</strong><br>admin@golden.com.br · senha: golden123</div>
        @endif
    </section>

    <footer class="auth-footer">
        <a href="{{ route('site.home') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao site
        </a>
        <span class="auth-version">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            {{ config('golden.marca.nome', 'Golden Rastreamento') }} · Versão {{ config('golden.versao', '2.65.0') }}
        </span>
    </footer>
</main>

<script>
(function () {
    var botao = document.getElementById('adminTema'), icone = document.getElementById('adminTemaIcone');
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
