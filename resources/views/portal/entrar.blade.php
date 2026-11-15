@extends('portal.layout-auth')

@section('title', 'Entrar no portal')

@section('showcase')
    <div style="margin-top:22px">
        <span class="auth-pill"><i class="fa-solid fa-lock" aria-hidden="true"></i> Acesso seguro do cliente</span>
        <h1>Tudo o que é seu, num lugar só</h1>
        <p class="sub">Entre com o e-mail e a senha da sua conta para acompanhar pedidos, chamados, faturas e contratos com total segurança.</p>
    </div>

    <ul class="auth-features">
        <li>
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <div><strong>Pedidos em tempo real</strong><span>Acompanhe do pagamento confirmado até a entrega.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <div><strong>Atendimento centralizado</strong><span>Chamados diretos com a equipe e histórico completo.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
            <div><strong>Contratos e dados</strong><span>Consulte documentos assinados e mantenha seu cadastro atualizado.</span></div>
        </li>
    </ul>
@endsection

@section('content')
    <h2>Entrar no portal</h2>
    <p class="panel-sub">Informe suas credenciais para acessar sua área exclusiva.</p>

    <form method="post" action="{{ route('portal.login.post') }}" novalidate>
        @csrf

        <div class="field">
            <label for="poEmail">E-mail da conta</label>
            <div class="input-wrap">
                <i class="fa-regular fa-envelope input-ico" aria-hidden="true"></i>
                <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="seu@email.com" required autofocus>
            </div>
            @error('email')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poSenha">Senha</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                <input id="poSenha" name="password" type="password" autocomplete="current-password" placeholder="Sua senha" required>
                <button type="button" class="toggle-pass" data-toggle-pass="poSenha" aria-label="Mostrar ou ocultar senha">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="row">
            <label class="remember" for="poLembrar">
                <input id="poLembrar" type="checkbox" name="lembrar" value="1" @checked(old('lembrar'))>
                <span>Manter conectado</span>
            </label>
            <a href="{{ route('portal.senha.esquecer') }}">Esqueci minha senha</a>
        </div>

        <button type="submit" class="auth-submit"><i class="fa-solid fa-right-to-bracket"></i> Entrar no portal</button>
        <p class="alternar">Ainda sem conta? <a href="{{ route('portal.cadastro') }}">Criar meu acesso</a></p>
    </form>
@endsection
