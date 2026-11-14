@extends('admin.layouts.app')
@php $editando = $cupom->exists; @endphp
@section('title', $editando ? 'Editar cupom' : 'Novo cupom')
@section('page_title', $editando ? 'Editar cupom: '.$cupom->codigo : 'Novo cupom de desconto')
@section('page_description', 'Defina o código, regra de desconto, validade e limites de utilização na loja.')

@section('page_actions')
<a href="{{ route('admin.catalogo.cupons.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.catalogo.cupons.update', $cupom) : route('admin.catalogo.cupons.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Regra do Cupom" icon="fa-ticket">
                <div class="row g-3">
                    <x-admin.field name="codigo" label="Código do cupom" :value="old('codigo', $cupom->codigo)" col="col-12 col-md-5" :required="true" maxlength="40" placeholder="Ex.: GOLDEN10" hint="Letras maiúsculas, números, hífen ou _." />
                    <x-admin.field name="tipo" type="select" label="Tipo de desconto" :value="old('tipo', $cupom->tipo ?? 'percentual')" :options="\App\Models\Coupon::TIPOS" col="col-12 col-md-4" :required="true" />
                    <x-admin.field name="valor" type="number" label="Valor (% ou R$)" :value="old('valor', $cupom->tipo === 'fixo' ? ($cupom->valor ? $cupom->valor / 100 : null) : $cupom->valor)" col="col-12 col-md-3" step="0.01" min="0" hint="Ex.: 10 para 10% ou 50 para R$ 50." />
                    <x-admin.field name="descricao" label="Descrição interna / campanha" :value="old('descricao', $cupom->descricao)" col="col-12" maxlength="190" :opcional="true" placeholder="Ex.: Campanha primeira compra / Google Ads" />
                </div>
            </x-admin.card>

            <x-admin.card title="Condições e Limites" icon="fa-sliders" class="mt-3">
                <div class="row g-3">
                    <x-admin.field name="valor_minimo" type="number" label="Pedido mínimo (R$)" :value="old('valor_minimo', $cupom->valor_minimo_cents ? $cupom->valor_minimo_cents / 100 : null)" col="col-12 col-md-4" step="0.01" min="0" :opcional="true" />
                    <x-admin.field name="desconto_maximo" type="number" label="Desconto máximo (R$)" :value="old('desconto_maximo', $cupom->desconto_maximo_cents ? $cupom->desconto_maximo_cents / 100 : null)" col="col-12 col-md-4" step="0.01" min="0" :opcional="true" hint="Apenas para cupons em %." />
                    <x-admin.field name="limite_usos" type="number" label="Limite total de usos" :value="old('limite_usos', $cupom->limite_usos)" col="col-12 col-md-4" min="1" :opcional="true" placeholder="Ilimitado" />
                    <x-admin.field name="limite_por_cliente" type="number" label="Limite por cliente" :value="old('limite_por_cliente', $cupom->limite_por_cliente)" col="col-12 col-md-4" min="1" :opcional="true" placeholder="Ilimitado" hint="Conta por e-mail/CPF/CNPJ." />
                    <x-admin.field name="valido_de" type="datetime-local" label="Válido a partir de" :value="old('valido_de', $cupom->valido_de?->format('Y-m-d\TH:i'))" col="col-12 col-md-4" :opcional="true" />
                    <x-admin.field name="valido_ate" type="datetime-local" label="Válido até" :value="old('valido_ate', $cupom->valido_ate?->format('Y-m-d\TH:i'))" col="col-12 col-md-4" :opcional="true" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Publicação" icon="fa-toggle-on">
                <x-admin.switch name="ativo" label="Cupom ativo na loja" :checked="old('ativo', $cupom->ativo ?? true)" hint="Quando desligado, o código é recusado no carrinho e checkout." />
                @if($editando)
                    <div class="border-top mt-3 pt-3 small text-secondary">
                        <div><strong>Usos registrados:</strong> {{ $cupom->usos }}</div>
                        @if($cupom->created_at)
                            <div><strong>Criado em:</strong> {{ $cupom->created_at->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>
                @endif
            </x-admin.card>

            <button class="btn btn-golden w-100 mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar cupom' : 'Criar cupom' }}</button>
        </div>
    </div>
</form>
@endsection
