@extends('layouts.site')

@section('title', 'Recuperar acesso — '.$golden['marca']['nome'])
@section('meta_description', 'Receba um link para criar uma senha nova no portal Golden.')

@section('content')
<section class="entrada">
    <div class="wrap" style="max-width:640px">
        <form method="post" action="{{ route('portal.senha.esquecer.post') }}" class="form-card" novalidate>
            @csrf
            <span class="eyebrow">Recuperação de acesso</span>
            <h2>Esqueci minha senha</h2>
            <p class="lead-sub" style="font-size:.95rem">Informe o e-mail da sua conta. Se ele tiver acesso ao portal, enviamos um
                link válido por 60 minutos para você escolher uma senha nova.</p>

            <div class="field">
                <label for="poEmail">E-mail da conta</label>
                <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email')<span class="form-err">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="btn btn-gold btn-block">Enviar o link de redefinição</button>
            <p class="alterna"><a href="{{ route('portal.login') }}">Voltar para o acesso</a></p>
        </form>
    </div>
</section>
@endsection
