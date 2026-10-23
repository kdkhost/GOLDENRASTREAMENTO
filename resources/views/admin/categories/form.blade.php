@extends('admin.layouts.app')
@php $editando = $categoria->exists; @endphp
@section('title', $editando ? 'Editar categoria' : 'Nova categoria')
@section('page_title', $editando ? 'Editar: '.$categoria->nome : 'Nova categoria')
@section('page_description', 'Nome, descrição e ordem de exibição da categoria na loja.')

@section('page_actions')
<a href="{{ route('admin.catalogo.categorias.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Dados da categoria" icon="fa-tags">
            <form method="POST" action="{{ $editando ? route('admin.catalogo.categorias.update', $categoria) : route('admin.catalogo.categorias.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$categoria->nome" :required="true" maxlength="120" placeholder="Ex.: Rastreadores veiculares" />
                    <x-admin.field name="descricao" label="Descrição" :value="$categoria->descricao" maxlength="255" :opcional="true" hint="Texto curto que ajuda o cliente a escolher a categoria." />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$categoria->ordem ?? 0" col="col-6 col-md-3" min="0" />
                    <x-admin.switch name="ativa" label="Ativa (aparece na loja)" :checked="$categoria->ativa" col="col-6 d-flex align-items-end pb-2" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar categoria' : 'Criar categoria' }}</button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
