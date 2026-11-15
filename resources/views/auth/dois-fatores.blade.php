<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verificação em duas etapas — {{ config('golden.marca.nome', 'Golden Rastreamento') }}</title>
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
                <span class="auth-pill"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i> Segurança reforçada</span>
                <h1>Verificação em duas etapas</h1>
                <p class="sub">A senha de <strong>{{ $nome }}</strong> foi validada. Confirme o código temporário do seu aplicativo autenticador para liberar o acesso.</p>
            </div>
        </div>

        <ul class="auth-features">
            <li>
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <div><strong>Código de recuperação</strong><span>Sem o celular? Utilize um dos códigos de recuperação no formato <code>ABCDE-12345</code>.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Tentativas restantes: {{ $restam }}</strong><span>Proteção automática contra acessos não autorizados.</span></div>
            </li>
        </ul>
    </aside>

    <section class="auth-panel">
        <h2>Confirmar código</h2>
        <p class="panel-sub">Digite os 6 dígitos exibidos no seu aplicativo autenticador.</p>

        <form method="POST" action="{{ route('login.dois-fatores.post') }}">
            @csrf
            <div class="field">
                <label for="codigo">Código do aplicativo</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-shield-halved input-ico" aria-hidden="true"></i>
                    <input id="codigo" type="text" name="codigo" autocomplete="one-time-code" maxlength="32" required autofocus placeholder="000000">
                </div>
                @error('codigo')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="auth-submit"><i class="fas fa-shield-halved"></i> Entrar no painel</button>
        </form>

        <form method="POST" action="{{ route('login.dois-fatores.cancelar') }}">
            @csrf
            <button type="submit" class="sutil">Cancelar e voltar ao login</button>
        </form>
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
})();
</script>
@include('partials.avisos')
</body>
</html>
