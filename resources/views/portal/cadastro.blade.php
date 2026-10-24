@extends('portal.layout-auth')

@section('title', 'Criar acesso ao portal')
@section('box', 'box-largo')

@section('content')
<div class="apresenta">
    <h1>Crie seu acesso ao portal</h1>
    <p class="sub">Informe o mesmo CPF/CNPJ e e-mail que você usou nos pedidos e no contrato. A gente reconhece a sua ficha
        e traz tudo o que já é seu para dentro do portal — nada de redigitar histórico.</p>
    <ul class="lista">
        <li><i class="fa-solid fa-link" aria-hidden="true"></i> <span>Seus pedidos antigos aparecem automaticamente</span></li>
        <li><i class="fa-solid fa-key" aria-hidden="true"></i> <span>Você escolhe a senha; não enviamos senha por e-mail</span></li>
        <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <span>Um acesso por ficha: a mesma conta não abre duas portas</span></li>
    </ul>
</div>

<form class="form" method="post" action="{{ route('portal.cadastro.post') }}" novalidate>
    @csrf
    <h2>Minha conta</h2>

    {{-- Honeypot: fora da tela para o humano, cheio para o robô. --}}
    <div class="hp" aria-hidden="true">
        <label for="poSite">Site (não preencha)</label>
        <input id="poSite" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <div class="field">
        <label for="poNome">Nome completo</label>
        <input id="poNome" name="nome" type="text" value="{{ old('nome') }}" autocomplete="name" required maxlength="120">
        @error('nome')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poEmail">E-mail</label>
        <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="190">
        <span class="dica">Será o seu login no portal</span>
        @error('email')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poTelefone">WhatsApp ou telefone</label>
        <input id="poTelefone" name="telefone" type="tel" value="{{ old('telefone') }}" autocomplete="tel" required maxlength="32">
        @error('telefone')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poDocumento">CPF ou CNPJ</label>
        <input id="poDocumento" name="documento" type="text" value="{{ old('documento') }}" required maxlength="24"
               inputmode="numeric" placeholder="Somente números, com ou sem pontos">
        <span class="dica">É por ele que juntamos seus pedidos e contratos nesta conta</span>
        @error('documento')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poSenha">Senha</label>
        <input id="poSenha" name="password" type="password" autocomplete="new-password" required minlength="8">
        <span class="dica">Mínimo de 8 caracteres</span>
        @error('password')<span class="erro">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="poConfirma">Repetir a senha</label>
        <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
    </div>

    <label class="consent" for="poConsent">
        <input id="poConsent" type="checkbox" name="consent" value="1" @checked(old('consent')) required>
        <span>Autorizo a Golden a usar meus dados para criar o acesso ao portal e manter o contato de suporte,
            conforme a <a href="{{ route('site.privacidade') }}">Política de Privacidade</a> (LGPD).</span>
    </label>
    @error('consent')<span class="erro">{{ $message }}</span>@enderror

    <button type="submit"><i class="fa-solid fa-user-plus"></i> Criar meu acesso</button>
    <p class="alternar">Já tem conta? <a href="{{ route('portal.login') }}">Entrar no portal</a></p>
</form>
@endsection
