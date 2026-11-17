<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Definir nova senha — {{ config('golden.marca.nome', 'Golden Rastreamento') }}</title>
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
                <span class="auth-pill"><i class="fa-solid fa-lock" aria-hidden="true"></i> Nova senha</span>
                <h1>Cadastre sua nova senha</h1>
                <p class="sub">Você está definindo uma nova credencial de acesso para a conta de <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
            </div>
        </div>

        <ul class="auth-features">
            <li>
                <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                <div><strong>Mínimo de 8 caracteres</strong><span>Combine letras, números e símbolos para uma proteção robusta.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Criptografia no banco</strong><span>Sua senha é armazenada com hash irreversível seguro.</span></div>
            </li>
        </ul>
    </aside>

    <section class="auth-panel">
        <h2>Definir nova senha</h2>
        <p class="panel-sub">Escolha uma nova senha forte para acessar o painel administrativo.</p>

        @if (session('info_email'))
            <div style="padding:14px 16px;border-radius:12px;background:rgba(31,158,134,0.14);border:1px solid rgba(31,158,134,0.35);color:var(--login-text);font-size:.88rem;line-height:1.5;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px">
                <i class="fa-solid fa-shield-halved" style="color:#1f9e86;margin-top:3px;font-size:1.1rem"></i>
                <div>
                    <strong style="display:block;color:#1f9e86;margin-bottom:2px">Identidade confirmada pelo 2FA!</strong>
                    <span>{{ session('info_email') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div style="padding:14px 16px;border-radius:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--login-text);font-size:.88rem;line-height:1.5;margin-bottom:20px">
                <strong style="color:#ef4444;display:block;margin-bottom:4px"><i class="fa-solid fa-triangle-exclamation me-1"></i> Não foi possível salvar:</strong>
                <ul style="margin:0;padding-left:18px">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf

            <div class="field">
                <label for="password">Nova senha</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                    <input id="password" type="password" name="password" placeholder="Mínimo de 8 caracteres" required minlength="8" autofocus autocomplete="new-password">
                    <button type="button" class="toggle-pass" data-toggle-pass="password" aria-label="Mostrar ou ocultar senha">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmar nova senha</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repita a nova senha" required minlength="8" autocomplete="new-password">
                    <button type="button" class="toggle-pass" data-toggle-pass="password_confirmation" aria-label="Mostrar ou ocultar confirmação de senha">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-check"></i> Salvar nova senha e entrar</button>
            <p class="alternar"><a href="{{ route('login') }}">Voltar para o login</a></p>
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

    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-toggle-pass');
            var input = document.getElementById(id);
            var ico = this.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                if (ico) ico.className = 'fa-regular fa-eye-slash';
            } else {
                input.type = 'password';
                if (ico) ico.className = 'fa-regular fa-eye';
            }
        });
    });
})();
</script>
</body>
</html>
