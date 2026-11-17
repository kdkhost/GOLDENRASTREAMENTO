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
    <style>
        .metodo-tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px;background:rgba(127,127,127,.08);padding:4px;border-radius:12px;margin-bottom:20px;border:1px solid var(--login-line)}
        .metodo-btn{border:0;background:transparent;color:var(--login-muted);font-size:.84rem;font-weight:600;padding:9px 10px;border-radius:9px;cursor:pointer;transition:all .2s ease;display:flex;align-items:center;justify-content:center;gap:6px}
        .metodo-btn.ativo{background:var(--login-surface);color:var(--login-text);box-shadow:0 4px 12px rgba(0,0,0,.08)}
        [data-bs-theme="dark"] .metodo-btn.ativo{background:rgba(255,255,255,.12);color:#fff}
        .dica-2fa{padding:12px 14px;border-radius:10px;background:rgba(31,158,134,.08);border:1px solid rgba(31,158,134,.25);font-size:.82rem;line-height:1.45;color:var(--login-text);margin-top:14px;display:flex;gap:10px;align-items:flex-start}
        .dica-2fa i{color:#1f9e86;margin-top:2px;font-size:1rem}
    </style>
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
                <p class="sub">Não se preocupe. Você pode solicitar um link seguro por e-mail ou, caso não se lembre do e-mail, validar sua identidade pelo celular cadastrado e código do autenticador (2FA).</p>
            </div>
        </div>

        <ul class="auth-features">
            <li>
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div><strong>Validade de 60 minutos</strong><span>O link enviado expira automaticamente em 1 hora para sua segurança.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Validação instantânea por 2FA</strong><span>Se tiver verificação em 2 etapas, valide na hora com seu código de segurança.</span></div>
            </li>
            <li>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <div><strong>Proteção de credenciais</strong><span>Nenhum dado é exposto e suas informações permanecem criptografadas.</span></div>
            </li>
        </ul>
    </aside>

    <section class="auth-panel">
        <h2>Recuperar acesso</h2>
        <p class="panel-sub" id="subDescricao">Escolha como deseja recuperar sua conta corporativa.</p>

        @php $abaAtiva = session('aba_ativa', $errors->has('identificador') || $errors->has('codigo_2fa') ? 'celular' : 'email'); @endphp

        <div class="metodo-tabs">
            <button type="button" id="tabEmail" class="metodo-btn {{ $abaAtiva === 'celular' ? '' : 'ativo' }}">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i> Por E-mail
            </button>
            <button type="button" id="tabCelular" class="metodo-btn {{ $abaAtiva === 'celular' ? 'ativo' : '' }}">
                <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i> Celular / 2FA
            </button>
        </div>

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

        {{-- FORMULÁRIO 1: POR E-MAIL --}}
        <form method="POST" action="{{ route('password.email') }}" id="formEmail" style="{{ $abaAtiva === 'celular' ? 'display:none;' : '' }}">
            @csrf
            <div class="field">
                <label for="email">E-mail corporativo cadastrado</label>
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope input-ico" aria-hidden="true"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@goldenrastreamento.com.br" autocomplete="username">
                </div>
                @error('email')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-paper-plane"></i> Enviar link de recuperação</button>
            <p class="alternar" style="margin-top:14px;font-size:.84rem">
                Não lembra do seu e-mail? <a href="#" id="linkMudarParaCelular">Recuperar por celular ou código 2FA</a>
            </p>
            <p class="alternar">Lembrou da sua senha? <a href="{{ route('login') }}">Voltar para o login</a></p>
        </form>

        {{-- FORMULÁRIO 2: POR CELULAR / 2FA --}}
        <form method="POST" action="{{ route('password.phone') }}" id="formCelular" style="{{ $abaAtiva === 'celular' ? '' : 'display:none;' }}">
            @csrf
            <div class="field">
                <label for="identificador">Celular cadastrado na equipe</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-mobile-screen input-ico" aria-hidden="true"></i>
                    <input id="identificador" type="text" name="identificador" value="{{ old('identificador') }}" placeholder="(11) 98765-4321" autocomplete="tel">
                </div>
                <small style="color:var(--login-muted);font-size:.78rem;display:block;margin-top:4px">Informe seu celular com DDD cadastrado no perfil.</small>
                @error('identificador')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="codigo_2fa">Código de segurança do 2FA <span style="font-weight:400;color:var(--login-muted)">(se tiver ativado)</span></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-shield-halved input-ico" aria-hidden="true"></i>
                    <input id="codigo_2fa" type="text" name="codigo_2fa" value="{{ old('codigo_2fa') }}" placeholder="Ex: 123456 ou XXXXX-XXXXX" autocomplete="one-time-code">
                </div>
                <small style="color:var(--login-muted);font-size:.78rem;display:block;margin-top:4px">Código de 6 dígitos do app autenticador ou código de recuperação de backup.</small>
                @error('codigo_2fa')<span class="erro">{{ $message }}</span>@enderror
            </div>

            <div class="dica-2fa">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong>Recuperação instantânea com 2FA:</strong> Se você ativou a verificação em duas etapas, o código confirma sua posse na hora e você redefine a senha imediatamente, além de visualizar qual é seu e-mail de acesso.
                </div>
            </div>

            <button type="submit" class="auth-submit" style="margin-top:16px"><i class="fa-solid fa-shield-halved"></i> Validar e recuperar acesso</button>
            <p class="alternar" style="margin-top:14px;font-size:.84rem">
                Lembra do seu e-mail? <a href="#" id="linkMudarParaEmail">Receber link por e-mail</a>
            </p>
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

    var tabEmail = document.getElementById('tabEmail');
    var tabCelular = document.getElementById('tabCelular');
    var formEmail = document.getElementById('formEmail');
    var formCelular = document.getElementById('formCelular');
    var subDescricao = document.getElementById('subDescricao');
    var linkMudarParaCelular = document.getElementById('linkMudarParaCelular');
    var linkMudarParaEmail = document.getElementById('linkMudarParaEmail');

    function alternarAba(aba) {
        if (aba === 'celular') {
            tabCelular.classList.add('ativo');
            tabEmail.classList.remove('ativo');
            formCelular.style.display = 'block';
            formEmail.style.display = 'none';
            if (subDescricao) subDescricao.textContent = 'Informe o celular cadastrado e seu código de 2FA para validar o acesso.';
        } else {
            tabEmail.classList.add('ativo');
            tabCelular.classList.remove('ativo');
            formEmail.style.display = 'block';
            formCelular.style.display = 'none';
            if (subDescricao) subDescricao.textContent = 'Informe seu e-mail corporativo para receber as instruções de recuperação.';
        }
    }

    if (tabEmail && tabCelular) {
        tabEmail.addEventListener('click', function () { alternarAba('email'); });
        tabCelular.addEventListener('click', function () { alternarAba('celular'); });
    }
    if (linkMudarParaCelular) {
        linkMudarParaCelular.addEventListener('click', function (e) { e.preventDefault(); alternarAba('celular'); });
    }
    if (linkMudarParaEmail) {
        linkMudarParaEmail.addEventListener('click', function (e) { e.preventDefault(); alternarAba('email'); });
    }
})();
</script>
</body>
</html>
