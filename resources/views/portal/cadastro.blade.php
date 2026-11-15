@extends('portal.layout-auth')

@section('title', 'Criar acesso ao portal')

@section('showcase')
    <div style="margin-top:22px">
        <span class="auth-pill"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Cadastro rápido</span>
        <h1>Crie seu acesso ao portal</h1>
        <p class="sub">Informe o mesmo CPF/CNPJ e e-mail utilizados em seus pedidos e contratos para vincular seu histórico automaticamente.</p>
    </div>

    <ul class="auth-features">
        <li>
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            <div><strong>Vínculo automático</strong><span>Seus pedidos e contratos existentes aparecem na hora.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            <div><strong>Senha definida por você</strong><span>Proteção individual com criptografia de acesso.</span></div>
        </li>
        <li>
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div><strong>Conta única e protegida</strong><span>Um acesso exclusivo por CPF ou CNPJ cadastrado.</span></div>
        </li>
    </ul>
@endsection

@section('content')
    <h2>Criar minha conta</h2>
    <p class="panel-sub">Preencha seus dados para liberar o acesso imediato.</p>

    <form method="post" action="{{ route('portal.cadastro.post') }}" novalidate>
        @csrf

        <div class="hp" aria-hidden="true">
            <label for="poSite">Site</label>
            <input id="poSite" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <div class="field">
            <label for="poNome">Nome completo</label>
            <input id="poNome" name="nome" type="text" value="{{ old('nome') }}" autocomplete="name" placeholder="Seu nome completo" required maxlength="120">
            @error('nome')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poEmail">E-mail</label>
            <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="seu@email.com" required maxlength="190">
            <span class="dica">Será o seu login no portal</span>
            @error('email')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poTelefone">WhatsApp ou telefone</label>
            <input id="poTelefone" name="telefone" type="tel" value="{{ old('telefone') }}" autocomplete="tel" placeholder="(00) 00000-0000" required maxlength="32">
            @error('telefone')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poDocumento">CPF ou CNPJ</label>
            <input id="poDocumento" name="documento" type="text" value="{{ old('documento') }}" required maxlength="24"
                   inputmode="numeric" placeholder="Somente números, com ou sem pontos">
            <span class="dica">Usado para localizar seus pedidos e contratos</span>
            @error('documento')<span class="erro">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label for="poSenha">Senha</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock input-ico" aria-hidden="true"></i>
                <input id="poSenha" name="password" type="password" autocomplete="new-password" placeholder="Mínimo de 8 caracteres" required minlength="8">
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
                <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repita a senha" required minlength="8">
                <button type="button" class="toggle-pass" data-toggle-pass="poConfirma" aria-label="Mostrar ou ocultar confirmação de senha">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <label class="consent" for="poConsent">
            <input id="poConsent" type="checkbox" name="consent" value="1" @checked(old('consent')) required>
            <span>Autorizo a Golden a usar meus dados para criar o acesso ao portal e manter o contato de suporte,
                conforme a <a href="{{ route('site.privacidade') }}">Política de Privacidade</a> (LGPD).</span>
        </label>
        @error('consent')<span class="erro">{{ $message }}</span>@enderror

        <button type="submit" class="auth-submit"><i class="fa-solid fa-user-plus"></i> Criar meu acesso</button>
        <p class="alternar">Já tem conta? <a href="{{ route('portal.login') }}">Entrar no portal</a></p>
    </form>
@endsection
