@extends('admin.layouts.app')
@php
    $editando = $depoimento->exists;
    $notas = [5 => '5 estrelas', 4 => '4 estrelas', 3 => '3 estrelas', 2 => '2 estrelas', 1 => '1 estrela'];
@endphp
@section('title', $editando ? 'Editar depoimento' : 'Novo depoimento')
@section('page_title', $editando ? 'Editar depoimento de '.$depoimento->nome : 'Novo depoimento')
@section('page_description', 'Só aparece no site o depoimento marcado como aprovado.')

@section('page_actions')
<a href="{{ route('admin.conteudo.depoimentos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-9">
        <x-admin.card title="Depoimento" icon="fa-quote-left">
            <form method="POST" action="{{ $editando ? route('admin.conteudo.depoimentos.update', $depoimento) : route('admin.conteudo.depoimentos.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome do cliente" :value="$depoimento->nome" :required="true" col="col-12 col-md-6" maxlength="120" />
                    <x-admin.field name="papel" label="Papel / segmento" :value="$depoimento->papel" col="col-6 col-md-3" maxlength="120" :opcional="true" placeholder="Transportador autônomo" />
                    <x-admin.field name="cidade" label="Cidade" :value="$depoimento->cidade" col="col-6 col-md-3" maxlength="96" :opcional="true" />
                    <x-admin.field name="nota" type="select" label="Nota" :value="$depoimento->nota ?? 5" :options="$notas" col="col-6 col-md-3" :required="true" />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$depoimento->ordem ?? 0" col="col-6 col-md-3" min="0" />
                    <x-admin.field name="texto" type="textarea" label="Texto do depoimento" :value="$depoimento->texto" :required="true" :rows="5" maxlength="2000" hint="Entre 1 e 3 frases, do jeito que o cliente falou." />
                    <x-admin.switch name="aprovado" label="Aprovado (visível no site)" :checked="$depoimento->aprovado ?? true" col="col-12" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar depoimento' : 'Adicionar depoimento' }}</button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
