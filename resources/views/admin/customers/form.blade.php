@extends('admin.layouts.app')
@php
    $editando = $cliente->exists;
    $tiposPessoa = ['fisica' => 'Pessoa física', 'juridica' => 'Pessoa jurídica'];
@endphp
@section('title', $editando ? 'Editar cliente' : 'Novo cliente')
@section('page_title', $editando ? 'Editar: '.$cliente->nome : 'Novo cliente')
@section('page_description', 'Ficha cadastral que alimenta o CRM — contatos, endereço, segmento e responsável.')

@section('page_actions')
@if($editando)
    <a href="{{ route('admin.clientes.show', $cliente) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar à ficha</a>
@else
    <a href="{{ route('admin.clientes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Identificação" icon="fa-address-card">
            <form method="POST" action="{{ $editando ? route('admin.clientes.update', $cliente) : route('admin.clientes.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$cliente->nome" col="col-12 col-md-8" :required="true" maxlength="190" autocomplete="organization" />
                    <x-admin.field name="tipo" type="select" label="Tipo" :value="$cliente->tipo ?: 'fisica'" :options="$tiposPessoa" col="col-6 col-md-4" :required="true" />
                    <x-admin.field name="documento" label="CPF / CNPJ" :value="$cliente->documento" col="col-6 col-md-4" maxlength="24" :opcional="true" hint="Somente números, com ou sem máscara." />
                    <x-admin.field name="nascimento" type="date" label="Nascimento" :value="$cliente->nascimento?->format('Y-m-d')" col="col-6 col-md-4" :opcional="true" hint="Usada pelo assistente para conferir a identidade antes de enviar qualquer coisa." />
                    <x-admin.field name="telefone" label="WhatsApp" :value="$cliente->telefone" col="col-6 col-md-4" maxlength="32" :opcional="true" />
                    <x-admin.field name="email" type="email" label="E-mail" :value="$cliente->email" col="col-6 col-md-4" maxlength="160" :opcional="true" autocomplete="off" />
                    <x-admin.field name="placas" label="Placas rastreadas" :value="$cliente->placas" col="col-12 col-md-8" maxlength="190" :opcional="true" placeholder="ABC1D23, DEF4E56" hint="Separadas por vírgula. O assistente pergunta uma delas antes de mandar a segunda via da fatura." />

                    <x-admin.field name="endereco" label="Endereço" :value="$cliente->endereco" maxlength="255" :opcional="true" />
                    <x-admin.field name="cidade" label="Cidade" :value="$cliente->cidade" col="col-6 col-md-5" maxlength="96" :opcional="true" />
                    <x-admin.field name="uf" label="UF" :value="$cliente->uf" col="col-3 col-md-2" maxlength="2" :opcional="true" />
                    <x-admin.field name="cep" label="CEP" :value="$cliente->cep" col="col-3 col-md-2" maxlength="12" :opcional="true" />

                    <x-admin.field name="segmento" type="select" label="Segmento" :value="$cliente->segmento" :options="config('golden.perfis')" empty="—" col="col-6 col-md-4" :opcional="true" />
                    <x-admin.field name="frota_tamanho" type="number" label="Tamanho da frota" :value="$cliente->frota_tamanho" col="col-3 col-md-4" min="0" :opcional="true" />
                    <x-admin.field name="status" type="select" label="Status no funil" :value="$cliente->status ?: 'prospecto'" :options="\App\Models\Customer::STATUS" col="col-3 col-md-4" :required="true" />

                    <x-admin.field name="valor_anual" type="number" label="Valor anual (R$)" :value="$cliente->valor_anual_cents ? $cliente->valor_anual_cents / 100 : null" col="col-6 col-md-4" step="0.01" min="0" :opcional="true" />
                    <x-admin.field name="owner_id" type="select" label="Responsável" :value="$cliente->owner_id" :options="$responsaveis" empty="Sem responsável" col="col-6 col-md-4" :opcional="true" />

                    <x-admin.field name="observacoes" type="textarea" label="Observações" :value="$cliente->observacoes" rows="3" :opcional="true" maxlength="5000" />
                </div>
                <button class="btn btn-golden mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar cliente' : 'Cadastrar cliente' }}</button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        @unless($editando)
            @if(request()->filled('pedido'))
                <x-admin.card title="Origem" icon="fa-receipt">
                    <p class="small text-secondary mb-0">Você veio do pedido <strong>{{ request('pedido') }}</strong>. Os dados de compra já estão preenchidos acima — revise e salve.</p>
                </x-admin.card>
            @endif
        @endunless
        <x-admin.card title="Status do CRM" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Prospecto</dt><dd>Em negociação, ainda sem contrato fechado.</dd>
                <dt>Ativo</dt><dd>Cliente com contrato ou compra vigente — entra na base pagante.</dd>
                <dt>Inativo</dt><dd>Já foi cliente; vale acompanhar para reativação.</dd>
            </dl>
        </x-admin.card>
    </div>
</div>
@endsection
