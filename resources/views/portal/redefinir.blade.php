@extends('portal.layout-auth')

@section('title', 'Definir senha nova')

@section('showcase')
    <div style="margin-top:22px">
        <span class="auth-pill"><i class="fa-solid fa-key" aria-hidden="true"></i> Nova senha</span>
        <h1>Escolha uma senha forte</h1>
        <p class="sub">Você está redefinindo o acesso da conta de <strong>{{ $cliente->nome }}</strong>. Após salvar, você será conectado automaticamente.</p>
    </div>

    <ul class="auth-features">
        <li>
            <i class="fa-solid fa-check-double" aria-hidden="true"></i>
            <div><strong>Mínimo de 8 caracteres</strong><span>Combine letras, números e símbolos para maior proteção.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <div><strong>Link temporário</strong><span>Este endereço é pessoal e expira em 60 minutos.</span></div>
        </li>
    </ul>
@endsection

@section('content')
    <h2>Definir senha nova</h2>
    <p class="panel-sub">Digite e confirme sua nova senha de acesso ao portal.</p>

    <form method="post" action="{{ request()->fullUrl() }}" novalidate>
        @csrf
        <div class="field">
            <label for="poSenha">Nova senha</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                <input id="poSenha" name="password" type="password" autocomplete="new-password" placeholder="Mínimo de 8 caracteres" required minlength="8" autofocus>
                <button type="button" class="toggle-pass" data-toggle-pass="poSenha" aria-label="Mostrar ou ocultar senha">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poConfirma">Repetir a senha</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repita a nova senha" required minlength="8">
                <button type="button" class="toggle-pass" data-toggle-pass="poConfirma" aria-label="Mostrar ou ocultar confirmação de senha">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-submit"><i class="fa-solid fa-lock"></i> Definir senha e entrar</button>
        <p class="alternar"><a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
    </form>
@endsection
