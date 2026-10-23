@extends('admin.layouts.app')
@php $editando = $contrato->exists; @endphp
@section('title', $editando ? 'Contrato '.$contrato->numero : 'Novo contrato')
@section('page_title', $editando ? 'Editar: '.$contrato->titulo : 'Novo contrato')
@section('page_description', 'O arquivo anexado fica disponível para o cliente baixar no portal dele.')

@section('page_actions')
@if($editando && $contrato->customer)
    <a href="{{ route('admin.clientes.show', $contrato->customer) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-address-card me-1"></i> Ficha do cliente</a>
@endif
<a href="{{ route('admin.contratos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.contratos.update', $contrato) : route('admin.contratos.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.card title="Documento" icon="fa-file-signature">
                <div class="row g-3">
                    <x-admin.field name="customer_id" type="select" label="Cliente" :value="$contrato->customer_id"
                        :options="$clientes->pluck('nome', 'id')->all()" empty="Escolha o cliente" :required="true"
                        hint="É a ficha que vê este contrato no portal." />
                    <x-admin.field name="titulo" label="Título" :value="$contrato->titulo" :required="true" maxlength="180"
                        col="col-12" placeholder="Ex.: Contrato de adesão — 3 veículos" />
                    <x-admin.field name="tipo" type="select" label="Tipo" col="col-6" :value="$contrato->tipo" :options="$tipos" />
                    <x-admin.field name="status" type="select" label="Situação" col="col-6" :value="$contrato->status" :options="$statuses"
                        hint="Rascunho não aparece para o cliente." />
                    <x-admin.field name="celebrado_em" type="date" label="Assinado em" col="col-6"
                        :value="$contrato->celebrado_em?->format('Y-m-d')" :opcional="true" />
                    <x-admin.field name="vigencia_ate" type="date" label="Vigência até" col="col-6"
                        :value="$contrato->vigencia_ate?->format('Y-m-d')" :opcional="true" />
                    <x-admin.field name="valor_mensal" type="number" label="Mensalidade (R$)" col="col-6" step="0.01" min="0"
                        :value="$contrato->valor_mensal_cents ? $contrato->valor_mensal_cents / 100 : null" :opcional="true" />
                    <x-admin.field name="observacoes" type="textarea" label="Observações" col="col-12" rows="3"
                        :value="$contrato->observacoes" :opcional="true" maxlength="5000"
                        placeholder="O que vale neste contrato: prazo, carência, kits inclusos..." />
                </div>
                <div class="border-top mt-3 pt-3">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Salvar contrato</button>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <x-admin.card title="PDF assinado" icon="fa-file-arrow-up"
                descricao="Cliente baixa este arquivo no portal. Sem ele, o contrato é só lista.">
                @include('partials.upload', [
                    'campo' => 'contrato_documento',
                    'name' => 'arquivo',
                    'valor' => old('arquivo', $contrato->arquivo),
                ])

                @if($editando && $contrato->temArquivo())
                    <p class="form-text mt-2">
                        <a href="{{ asset('storage/'.$contrato->arquivo) }}" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square me-1"></i> Conferir o que está publicado
                        </a>
                    </p>
                @endif
            </x-admin.card>

            @if($editando)
                <x-admin.card title="No portal do cliente" icon="fa-user-shield" class="mt-3">
                    <dl class="defs mb-0">
                        <dt>Número</dt><dd>{{ $contrato->numero }}</dd>
                        <dt>Cliente vê?</dt><dd>{{ $contrato->status === 'rascunho' ? 'Não — está em rascunho' : 'Sim' }}</dd>
                        <dt>Arquivo</dt><dd>{{ $contrato->temArquivo() ? 'publicado' : 'nenhum anexado' }}</dd>
                        <dt>Cadastrado em</dt><dd>{{ $contrato->created_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                </x-admin.card>
            @endif
        </div>
    </div>
</form>
@endsection
