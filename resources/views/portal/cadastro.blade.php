@extends('layouts.site')

@section('title', 'Criar acesso ao portal — '.$golden['marca']['nome'])
@section('meta_description', 'Crie sua conta no portal Golden e acompanhe pedidos, chamados e contratos.')

@section('content')
<section class="entrada">
    <div class="wrap">
        <div class="entrada-grid">
            <div>
                <span class="eyebrow">Auto-cadastro</span>
                <h1>Crie seu acesso ao portal</h1>
                <p class="lead-sub">Informe o mesmo CPF/CNPJ e e-mail que você usou nos pedidos e no contrato. A gente reconhece
                    a sua ficha e traz tudo o que já é seu para dentro do portal — nada de redigitar histórico.</p>
                <ul class="entrada-lista">
                    <li><i class="fa-solid fa-link"></i> <span>Seus pedidos antigos aparecem automaticamente</span></li>
                    <li><i class="fa-solid fa-key"></i> <span>Você escolhe a senha; não enviamos senha por e-mail</span></li>
                    <li><i class="fa-solid fa-shield-halved"></i> <span>Um acesso por ficha: a mesma conta não abre duas portas</span></li>
                </ul>
            </div>

            <form method="post" action="{{ route('portal.cadastro.post') }}" class="form-card" novalidate>
                @csrf
                <h2>Minha conta</h2>

                @if(session('warning'))
                <p class="form-err block" role="status">{{ session('warning') }}</p>
                @endif

                {{-- Honeypot: fora da tela para o humano, cheio para o robô. --}}
                <div class="hp" aria-hidden="true">
                    <label for="poSite">Site (não preencha)</label>
                    <input id="poSite" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="poNome">Nome completo</label>
                    <input id="poNome" name="nome" type="text" value="{{ old('nome') }}" autocomplete="name" required maxlength="120">
                    @error('nome')<span class="form-err">{{ $message }}</span>@enderror
                </div>

                <div class="row-2">
                    <div class="field">
                        <label for="poEmail">E-mail</label>
                        <input id="poEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="190">
                        <span class="senha-dica">Será o seu login no portal</span>
                        @error('email')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="poTelefone">WhatsApp ou telefone</label>
                        <input id="poTelefone" name="telefone" type="tel" value="{{ old('telefone') }}" autocomplete="tel" required maxlength="32">
                        @error('telefone')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="poDocumento">CPF ou CNPJ</label>
                    <input id="poDocumento" name="documento" type="text" value="{{ old('documento') }}" required maxlength="24"
                           inputmode="numeric" placeholder="Somente números, com ou sem pontos">
                    <span class="senha-dica">É por ele que juntamos seus pedidos e contratos nesta conta</span>
                    @error('documento')<span class="form-err">{{ $message }}</span>@enderror
                </div>

                <div class="row-2">
                    <div class="field">
                        <label for="poSenha">Senha</label>
                        <input id="poSenha" name="password" type="password" autocomplete="new-password" required minlength="8">
                        <span class="senha-dica">Mínimo de 8 caracteres</span>
                        @error('password')<span class="form-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="poConfirma">Repetir a senha</label>
                        <input id="poConfirma" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
                    </div>
                </div>

                <label class="consent" for="poConsent">
                    <input id="poConsent" type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                    <span>Autorizo a Golden a usar meus dados para criar o acesso ao portal e manter o contato de suporte,
                        conforme a <a href="{{ route('site.privacidade') }}">Política de Privacidade</a> (LGPD).</span>
                </label>
                @error('consent')<span class="form-err">{{ $message }}</span>@enderror

                <button type="submit" class="btn btn-gold btn-block">Criar meu acesso</button>
                <p class="alterna">Já tem conta? <a href="{{ route('portal.login') }}">Entrar no portal</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
