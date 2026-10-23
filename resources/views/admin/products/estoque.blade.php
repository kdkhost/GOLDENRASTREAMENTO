@extends('admin.layouts.app')
@section('title', 'Estoque — '.$produto->nome)
@section('page_title', 'Estoque: '.$produto->nome)
@section('page_description', ($produto->sku ?: 'Sem SKU').' · saldo atual '.($produto->controle_estoque ? $produto->estoque.' un.' : 'não controlado'))

@section('page_actions')
<a href="{{ route('admin.catalogo.produtos.edit', $produto) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i> Editar produto</a>
<a href="{{ route('admin.catalogo.produtos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="row g-3 mb-3">
            <div class="col-6"><x-admin.stat label="Saldo atual" :value="$produto->estoque" icon="fa-boxes-stacked" :tone="tom_por_status('estoque', $produto->estadoEstoque())" /></div>
            <div class="col-6"><x-admin.stat label="Mínimo" :value="$produto->estoque_minimo" icon="fa-arrow-down-1-9" tone="gold" /></div>
        </div>

        <x-admin.card title="Registrar movimentação" icon="fa-right-left" descricao="Cada entrada ou saída fica registrada com data e responsável.">
            @unless($produto->controle_estoque)
                <div class="alert alert-warning small py-2">Este produto está cadastrado <strong>sem controle de estoque</strong>. A movimentação ainda é registrada, mas o saldo não trava a venda na loja.</div>
            @endunless
            <form method="POST" action="{{ route('admin.catalogo.produtos.estoque', $produto) }}" class="row g-3">
                @csrf
                <x-admin.field name="tipo" type="select" label="Tipo" col="col-6" :options="['entrada' => 'Entrada', 'saida' => 'Saída']" :required="true" />
                <x-admin.field name="quantidade" type="number" label="Quantidade" col="col-6" :value="1" :required="true" min="1" />
                <x-admin.field name="motivo" label="Motivo" :opcional="true" placeholder="Ex.: compra da NF 4.312, venda avulsa, ajuste de inventário" maxlength="190" />
                <div class="col-12">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Registrar movimentação</button>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.table :headers="['Quando', 'Tipo', 'center:Qtde', 'center:Saldo', 'Responsável', 'Motivo']"
            empty="Nenhuma movimentação registrada." :paginator="$movimentos">
            @foreach($movimentos as $m)
                <tr>
                    <td class="small text-secondary">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td><x-admin.status :rotulo="$m->rotulo()" :tom="tom_por_status('movimento', $m->tipo)" /></td>
                    <td class="text-center small fw-semibold">{{ $m->quantidade >= 0 ? '+' : '' }}{{ $m->quantidade }}</td>
                    <td class="text-center small">{{ $m->saldo_after }}</td>
                    <td class="small">{{ $m->user?->name ?? 'Sistema' }}</td>
                    <td class="small text-secondary">{{ $m->motivo ?: '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>
</div>
@endsection
