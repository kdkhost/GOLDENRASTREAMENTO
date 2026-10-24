@extends('portal.layout-auth')

@section('title', 'Recuperar acesso')

@section('content')
<h1>Esqueci minha senha</h1>
<p class="sub">Informe o e-mail da sua conta. Se ele tiver acesso ao portal, enviamos um link válido por 60 minutos para
    você escolher uma senha nova.</p>

<form method="post" action="{{ route('portal.senha.esquecer.post') }}" novalidate>
    @csrf
    <div class="field">
        <label for="poEmail">E-mail da conta</label>
        <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <button type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar o link de redefinição</button>
    <p class="alternar"><a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
</form>
@endsection
