@extends('portal.layout')

@section('title', 'Meus dados — Portal do cliente')
@section('page_title', 'Meus dados')
@section('page_description', 'O que a equipe tem na sua ficha — e o que você mantém atualizado por aqui.')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.card title="Cadastro" icon="fa-id-card" descricao="Usado nos seus pedidos, contratos e no aviso dos chamados.">
            <form method="POST" action="{{ route('portal.dados.salvar') }}" class="row g-3" novalidate>
                @csrf
                <x-admin.field name="nome" label="Nome / razão social" :value="$cliente->nome" :required="true" maxlength="120" />

                <x-admin.field name="telefone" label="Telefone / WhatsApp" type="tel" col="col-12 col-md-6" :value="$cliente->telefone" :required="true" maxlength="32" />
                <x-admin.field name="email" label="E-mail de contato" type="email" col="col-12 col-md-6" :value="$cliente->email"
                    :opcional="true" :hint="'Muda só o contato; o login continua sendo '.$cliente->email_portal.'.'" />

                <x-admin.field name="endereco" label="Endereço" :value="$cliente->endereco" maxlength="160" :opcional="true" />

                <x-admin.field name="cidade" label="Cidade" :value="$cliente->cidade" col="col-8 col-md-6" maxlength="96" :opcional="true" />
                <x-admin.field name="uf" label="UF" :value="$cliente->uf" col="col-4 col-md-2" maxlength="2" :opcional="true" />
                <x-admin.field name="cep" label="CEP" :value="$cliente->cep" col="col-12 col-md-4" maxlength="12" inputmode="numeric" :opcional="true" />

                <div class="col-12">
                    <button type="submit" class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar cadastro</button>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Documento e conta" icon="fa-address-card">
            <dl class="defs mb-0">
                <dt>CPF / CNPJ</dt><dd>{{ $cliente->documento ?: 'Não informado' }}</dd>
                <dt>E-mail de acesso</dt><dd>{{ $cliente->email_portal }}</dd>
                <dt>Cliente desde</dt><dd>{{ $cliente->created_at->format('m/Y') }}</dd>
                <dt>Último acesso</dt><dd>{{ $cliente->ultimo_acesso_em ? $cliente->ultimo_acesso_em->format('d/m/Y \à\s H:i') : 'Ainda não registrado' }}</dd>
                <dt>Total investido</dt><dd class="text-gold">{{ brl($total_gasto_cents) }}</dd>
            </dl>
            <p class="form-hint mt-3 mb-0">Mudar CPF/CNPJ ou o e-mail de acesso é com a equipe, na <a href="{{ route('site.contato') }}">página de contato</a> — é por eles que pedidos e contratos ficam ligados à sua conta.</p>
        </x-admin.card>

        <x-admin.card title="Senha do portal" icon="fa-key" class="mt-3">
            <form method="POST" action="{{ route('portal.senha.salvar') }}" class="row g-3" novalidate>
                @csrf
                <x-admin.field name="senha_atual" type="password" label="Senha atual" :required="true" autocomplete="current-password" />
                <x-admin.field name="password" type="password" label="Senha nova" col="col-12 col-md-6" :required="true" minlength="8"
                    autocomplete="new-password" hint="Mínimo de 8 caracteres." />
                <x-admin.field name="password_confirmation" type="password" label="Repetir a nova" col="col-12 col-md-6" :required="true" minlength="8" autocomplete="new-password" />
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-rotate me-1"></i> Trocar a senha</button>
                </div>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
