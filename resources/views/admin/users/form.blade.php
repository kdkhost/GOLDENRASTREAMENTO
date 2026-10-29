@extends('admin.layouts.app')
@php
    $editando = $usuario->exists;
@endphp
@section('title', $editando ? 'Editar usuário' : 'Novo usuário')
@section('page_title', $editando ? 'Editar: '.$usuario->name : 'Novo usuário')
@section('page_description', 'O perfil define o que a pessoa enxerga no painel: vendedor atende leads, visualizador só consulta e administrador controla tudo.')

@section('page_actions')
<a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Acesso" icon="fa-user-gear">
            <form method="POST" action="{{ $editando ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="name" label="Nome" :value="$usuario->name" :required="true" maxlength="120" autocomplete="off" />
                    <x-admin.field name="email" type="email" label="E-mail de acesso" :value="$usuario->email" :required="true" col="col-12 col-md-6" maxlength="160" autocomplete="off" />
                    <x-admin.field name="telefone" label="Telefone" :value="$usuario->telefone" col="col-12 col-md-6" maxlength="32" :opcional="true" />
                    <x-admin.field name="role" type="select" label="Perfil" :value="$usuario->role ?: 'vendedor'" :options="$perfis" col="col-6 col-md-4" :required="true" :travado="$usuario->id === auth()->id()" />
                    @if($editando)
                        <x-admin.field name="status" type="select" label="Situação" :value="$usuario->status" :options="$situacoes" col="col-6 col-md-4" :required="true" :travado="$usuario->id === auth()->id()" hint="Você não pode desativar a si mesmo." />
                        <x-admin.field name="password" type="password" label="Nova senha" col="col-12 col-md-4" :opcional="true" minlength="8" autocomplete="new-password" hint="Deixe em branco para manter a senha atual." />
                    @else
                        <x-admin.field name="password" type="password" label="Senha inicial" :required="true" minlength="8" autocomplete="new-password" hint="Mínimo de 8 caracteres." />
                    @endif
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar usuário' : 'Criar usuário' }}</button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
