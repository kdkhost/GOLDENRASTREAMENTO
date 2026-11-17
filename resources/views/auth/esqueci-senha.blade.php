<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Recuperar senha — {{ config('golden.marca.nome', 'Golden Rastreamento') }}</title>
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
                <span class="auth-pill"><i class="fa-solid fa-key" aria-hidden="true"></i> Recuperação de acesso</span>
                <h1>Esqueceu sua senha do painel?</h1>
                <p class="sub">Não se preocupe. Digite o seu e-mail corporativo cadastrado para receber um link de redefinição com chave criptográfica exclusiva.</p>
            </div>
        </div>

        <ul class="auth-features">
            <li>
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div><strong>Validade de 60 minutos</strong><span>O link enviado expira automaticamente em 1 hora para sua segurança.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Assinatura digital</strong><span>Cada link é exclusivo, de uso único e protegido contra falsificação.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <div><strong>Proteção de credenciais</strong><span>Nenhum dado é exposto e suas informações permanecem criptografadas.</span></div>
            </li>
        </ul>
    </aside>

    <section class="auth-panel">
        <h2>Recuperar senha</h2>
        <p class="panel-sub">Informe seu e-mail para receber as instruções e o link de acesso.</p>

        @if (session('status'))
            <div style="padding:14px 16px;border-radius:12px;background:rgba(212,168,67,0.12);border:1px solid rgba(212,168,67,0.3);color:var(--login-text);font-size:.88rem;line-height:1.5;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px">
                <i class="fa-solid fa-circle-check" style="color:var(--brand-gold);margin-top:3px;font-size:1.05rem"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div style="padding:14px 16px;border-radius:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--login-text);font-size:.88rem;line-height:1.5;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px">
                <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;margin-top:3px;font-size:1.05rem"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">E-mail corporativo</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope input-ico" aria-hidden="true"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@goldenrastreamento.com.br" required autofocus autocomplete="username">
                </div>
                @error('email')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-paper-plane"></i> Enviar link de recuperação</button>
            <p class="alternar">Lembrou da sua senha? <a href="{{ route('login') }}">Voltar para o login</a></p>
        </form>
    </section>

    <footer class="auth-footer">
        <a href="{{ route('site.home') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao site
        </a>
        <span class="auth-version">{{ config('golden.marca.nome', 'Golden Rastreamento') }}</span>
    </footer>
</main>

<script>
(function () {
    var botao = document.getElementById('adminTema'), icone = document.getElementById('adminTemaIcone');
    function pintar(tema) { if (icone) icone.className = 'fas ' + (tema === 'dark' ? 'fa-sun' : 'fa-moon'); }
    if (botao && icone) {
        botao.addEventListener('click', function () {
            var tema = (localStorage.getItem('golden_admin_theme') || 'light') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('golden_admin_theme', tema);
            document.documentElement.setAttribute('data-bs-theme', tema);
            pintar(tema);
        });
        pintar(localStorage.getItem('golden_admin_theme') || 'light');
    }
})();
</script>
</body>
</html>
