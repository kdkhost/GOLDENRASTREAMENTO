@extends('admin.layouts.app')
@php $editando = $faq->exists; @endphp
@section('title', $editando ? 'Editar pergunta' : 'Nova pergunta')
@section('page_title', $editando ? 'Editar pergunta' : 'Nova pergunta')
@section('page_description', 'A pergunta aparece na página de FAQ e na home.')

@section('page_actions')
<a href="{{ route('admin.conteudo.faqs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-9">
        <x-admin.card title="Pergunta e resposta" icon="fa-circle-question">
            <form method="POST" action="{{ $editando ? route('admin.conteudo.faqs.update', $faq) : route('admin.conteudo.faqs.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="pergunta" label="Pergunta" :value="$faq->pergunta" :required="true" maxlength="255" placeholder="Ex.: O rastreador funciona em qualquer veículo?" />
                    <x-admin.field name="resposta" type="textarea" label="Resposta" :value="$faq->resposta" :required="true" :rows="6" maxlength="5000" />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$faq->ordem ?? 0" col="col-6 col-md-3" min="0" />
                    <x-admin.switch name="ativa" label="Ativa (visível no site)" :checked="$faq->ativa ?? true" col="col-6 d-flex align-items-end pb-2" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar pergunta' : 'Adicionar pergunta' }}</button>
            </form>
        </x-admin.card>
    </div>
</div>
@endsection
