@extends('portal.layout-auth')

@section('title', 'Recuperar acesso')

@section('showcase')
    <div style="margin-top:22px">
        <span class="auth-pill"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Recuperação de acesso</span>
        <h1>Recupere sua senha com segurança</h1>
        <p class="sub">Enviamos um link exclusivo e temporário para o e-mail cadastrado na sua conta, garantindo que apenas você possa redefinir o acesso.</p>
    </div>

    <ul class="auth-features">
        <li>
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <div><strong>Validade de 60 minutos</strong><span>O link expira automaticamente após uma hora.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div><strong>Verificação assinada</strong><span>Cada solicitação gera uma chave única de uso único.</span></div>
        </li>
    </ul>
@endsection

@section('content')
    <h2>Esqueci minha senha</h2>
    <p class="panel-sub">Informe o e-mail cadastrado no portal para receber as instruções de redefinição.</p>

    <form method="post" action="{{ route('portal.senha.esquecer.post') }}" novalidate>
        @csrf
        <div class="field">
            <label for="poEmail">E-mail da conta</label>
            <div class="input-wrap">
                <i class="fa-regular fa-envelope input-ico" aria-hidden="true"></i>
                <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="seu@email.com" required autofocus>
            </div>
            @error('email')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <button type="submit" class="auth-submit"><i class="fa-solid fa-paper-plane"></i> Enviar link de redefinição</button>
        <p class="alternar">Lembrou a senha? <a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
    </form>
@endsection
