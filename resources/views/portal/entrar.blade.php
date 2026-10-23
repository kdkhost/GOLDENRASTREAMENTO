@extends('layouts.site')

@section('title', 'Entrar no portal — '.$golden['marca']['nome'])
@section('meta_description', 'Acompanhe pedidos, chamados e contratos dos seus rastreadores Golden.')

@section('content')
<section class="entrada">
    <div class="wrap">
        <div class="entrada-grid">
            <div>
                <span class="eyebrow">Portal do cliente</span>
                <h1>Tudo o que é seu, num lugar só</h1>
                <p class="lead-sub">Entre com o e-mail e a senha da sua conta. Quem ainda não tem conta cria em um minuto — e os
                    pedidos, chamados e contratos que já existem no nosso cadastro aparecem sozinhos.</p>
                <ul class="entrada-lista">
                    <li><i class="fa-solid fa-box-open"></i> <span>Status de cada pedido, do pagamento à entrega</span></li>
                    <li><i class="fa-solid fa-headset"></i> <span>Chamados com a equipe, com histórico completo</span></li>
                    <li><i class="fa-solid fa-file-signature"></i> <span>Contrato assinado para ler quando precisar</span></li>
                    <li><i class="fa-solid fa-user-gear"></i> <span>Seus dados, que você mesmo mantém atualizados</span></li>
                </ul>
            </div>

            <form method="post" action="{{ route('portal.login.post') }}" class="form-card" novalidate>
                @csrf
                <h2>Entrar</h2>

                @if(session('warning'))
                <p class="form-err block" role="status">{{ session('warning') }}</p>
                @endif

                <div class="field">
                    <label for="poEmail">E-mail da conta</label>
                    <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                    @error('email')<span class="form-err">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label for="poSenha">Senha</label>
                    <input id="poSenha" name="password" type="password" autocomplete="current-password" required>
                    @error('password')<span class="form-err">{{ $message }}</span>@enderror
                    <p class="senha-dica"><a href="{{ route('portal.senha.esquecer') }}">Esqueci minha senha</a></p>
                </div>

                <label class="consent" for="poLembrar">
                    <input id="poLembrar" type="checkbox" name="lembrar" value="1" @checked(old('lembrar'))>
                    <span>Manter-me conectado neste dispositivo</span>
                </label>

                <button type="submit" class="btn btn-gold btn-block">Entrar no portal</button>
                <p class="alterna">Ainda sem conta? <a href="{{ route('portal.cadastro') }}">Criar meu acesso</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
