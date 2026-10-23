@extends('admin.layouts.app')
@php $editando = $fornecedor->exists; @endphp
@section('title', $editando ? 'Editar fornecedor' : 'Novo fornecedor')
@section('page_title', $editando ? 'Editar: '.$fornecedor->nome : 'Novo fornecedor')
@section('page_description', 'Quem fornece os equipamentos e os chips usados nas instalações.')

@section('page_actions')
<a href="{{ route('admin.catalogo.fornecedores.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Dados do fornecedor" icon="fa-truck">
            <form method="POST" action="{{ $editando ? route('admin.catalogo.fornecedores.update', $fornecedor) : route('admin.catalogo.fornecedores.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$fornecedor->nome" :required="true" maxlength="190" />
                    <x-admin.field name="cnpj" label="CNPJ" :value="$fornecedor->cnpj" col="col-6 col-md-4" maxlength="24" :opcional="true" placeholder="00.000.000/0000-00" />
                    <x-admin.field name="contato" label="Pessoa de contato" :value="$fornecedor->contato" col="col-6 col-md-4" maxlength="120" :opcional="true" />
                    <x-admin.field name="telefone" label="Telefone" :value="$fornecedor->telefone" col="col-6 col-md-4" maxlength="32" :opcional="true" placeholder="(00) 0000-0000" />
                    <x-admin.field name="email" type="email" label="E-mail" :value="$fornecedor->email" maxlength="160" :opcional="true" />
                    <x-admin.field name="observacoes" type="textarea" label="Observações" :value="$fornecedor->observacoes" :rows="3" :opcional="true" hint="Prazo de entrega, forma de pagamento, garantia." />
                    <x-admin.switch name="ativo" label="Ativo" :checked="$fornecedor->ativo" col="col-12" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar fornecedor' : 'Cadastrar fornecedor' }}</button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
