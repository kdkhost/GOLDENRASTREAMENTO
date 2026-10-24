@extends('portal.layout-auth')

@section('title', 'Entrar no portal')
@section('box', 'box-largo')

@section('content')
<div class="apresenta">
    <h1>Tudo o que é seu, num lugar só</h1>
    <p class="sub">Entre com o e-mail e a senha da sua conta. Quem ainda não tem conta cria em um minuto — e os pedidos,
        chamados e contratos que já existem no nosso cadastro aparecem sozinhos.</p>
    <ul class="lista">
        <li><i class="fa-solid fa-box-open" aria-hidden="true"></i> <span>Status de cada pedido, do pagamento à entrega</span></li>
        <li><i class="fa-solid fa-headset" aria-hidden="true"></i> <span>Chamados com a equipe, com histórico completo</span></li>
        <li><i class="fa-solid fa-file-signature" aria-hidden="true"></i> <span>Contrato assinado para ler quando precisar</span></li>
        <li><i class="fa-solid fa-user-gear" aria-hidden="true"></i> <span>Seus dados, que você mesmo mantém atualizados</span></li>
    </ul>
</div>

<form class="form" method="post" action="{{ route('portal.login.post') }}" novalidate>
    @csrf
    <h2>Entrar</h2>

    <div class="field">
        <label for="poEmail">E-mail da conta</label>
        <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poSenha">Senha</label>
        <input id="poSenha" name="password" type="password" autocomplete="current-password" required>
        @error('password')<span class="erro">{{ $message }}</span>@enderror
        <p class="dica"><a href="{{ route('portal.senha.esquecer') }}">Esqueci minha senha</a></p>
    </div>

    <label class="consent" for="poLembrar">
        <input id="poLembrar" type="checkbox" name="lembrar" value="1" @checked(old('lembrar'))>
        <span>Manter-me conectado neste dispositivo</span>
    </label>

    <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Entrar no portal</button>
    <p class="alternar">Ainda sem conta? <a href="{{ route('portal.cadastro') }}">Criar meu acesso</a></p>
</form>
@endsection
