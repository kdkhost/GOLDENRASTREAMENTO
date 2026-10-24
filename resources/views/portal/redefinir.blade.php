@extends('portal.layout-auth')

@section('title', 'Definir senha nova')

@section('content')
<h1>Senha nova para {{ $cliente->nome }}</h1>
<p class="sub">Escolha a senha desta conta. O link que abriu esta tela expira em 60 minutos e só vale para o seu e-mail.</p>

{{-- A ação repete a URL com a assinatura: sem a query o link assinado não vale no POST. --}}
<form method="post" action="{{ request()->fullUrl() }}" novalidate>
    @csrf
    <div class="field">
        <label for="poSenha">Senha</label>
        <input id="poSenha" name="password" type="password" autocomplete="new-password" required minlength="8" autofocus>
        <span class="dica">Mínimo de 8 caracteres</span>
        @error('password')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poConfirma">Repetir a senha</label>
        <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
    </div>

    <button type="submit"><i class="fa-solid fa-lock"></i> Definir senha e entrar</button>
    <p class="alternar"><a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
</form>
@endsection
