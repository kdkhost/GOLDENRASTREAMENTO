@extends('admin.layouts.app')
@php $editando = $campanha->exists; @endphp
@section('title', $editando ? 'Editar campanha' : 'Nova campanha')
@section('page_title', $editando ? 'Editar: '.$campanha->name : 'Nova campanha')
@section('page_description', 'As tags UTMs abaixo são o que o painel usa para atribuir cada lead à campanha certa.')

@section('page_actions')
<a href="{{ route('admin.campanhas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Dados da campanha" icon="fa-bullhorn">
            <form method="POST" action="{{ $editando ? route('admin.campanhas.update', $campanha) : route('admin.campanhas.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="name" label="Nome da campanha" :value="$campanha->name" :required="true" maxlength="190" placeholder="Ex.: Meta Ads — Motoristas de app" />
                    <x-admin.field name="channel" type="select" label="Canal" :value="$campanha->channel ?: 'pago'" :options="\App\Models\Campaign::CANAIS" col="col-6 col-md-4" :required="true" />
                    <x-admin.switch name="ativa" label="Ativa" :checked="$campanha->ativa" col="col-6 d-flex align-items-end pb-2" />
                    <x-admin.field name="source" label="utm_source" :value="$campanha->source" col="col-6 col-md-4" maxlength="120" placeholder="facebook" :opcional="true" />
                    <x-admin.field name="medium" label="utm_medium" :value="$campanha->medium" col="col-6 col-md-4" maxlength="120" placeholder="paid-social" :opcional="true" />
                    <x-admin.field name="content" label="utm_content / criativo" :value="$campanha->content" maxlength="190" :opcional="true" hint="Identifica o criativo quando a mesma campanha roda com vários anúncios." />
                </div>
                <button class="btn btn-golden mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar campanha' : 'Criar campanha' }}</button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Como o lead chega aqui" icon="fa-circle-info">
            <p class="small text-secondary">Quando alguém envia o formulário do site, o painel grava as UTMs da visita. Se o <code>utm_campaign</code> bater com o nome ou com o <code>utm_content</code> cadastrado aqui, o lead é vinculado a esta campanha automaticamente.</p>
            <p class="small text-secondary mb-0">Sem vínculo, o lead entra como <em>sem campanha</em> e continua editável na ficha dele.</p>
        </x-admin.card>
    </div>
</div>
@endsection
