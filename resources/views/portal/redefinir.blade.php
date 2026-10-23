@extends('layouts.site')

@section('title', 'Definir senha nova — '.$golden['marca']['nome'])

@section('content')
<section class="entrada">
    <div class="wrap" style="max-width:640px">
        {{-- A ação repete a URL com a assinatura: sem a query o link assinado não vale no POST. --}}
        <form method="post" action="{{ request()->fullUrl() }}" class="form-card" novalidate>
            @csrf
            <span class="eyebrow">Recuperação de acesso</span>
            <h2>Senha nova para {{ $cliente->nome }}</h2>
            <p class="lead-sub" style="font-size:.95rem">Escolha a senha desta conta. O link que abriu esta tela expira em 60
                minutos e só vale para o seu e-mail.</p>

            <div class="row-2">
                <div class="field">
                    <label for="poSenha">Senha</label>
                    <input id="poSenha" name="password" type="password" autocomplete="new-password" required minlength="8" autofocus>
                    <span class="senha-dica">Mínimo de 8 caracteres</span>
                    @error('password')<span class="form-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="poConfirma">Repetir a senha</label>
                    <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn btn-gold btn-block">Definir senha e entrar</button>
            <p class="alterna"><a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
        </form>
    </div>
</section>
@endsection
