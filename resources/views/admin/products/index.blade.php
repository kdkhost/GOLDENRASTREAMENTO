@extends('admin.layouts.app')
@section('title', 'Produtos')
@section('page_title', 'Produtos e estoque')
@section('page_description', 'Catálogo da loja: rastreadores, chips M2M, acessórios e planos.')

@section('page_actions')
<a href="{{ route('admin.catalogo.produtos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo produto</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Produtos cadastrados" :value="$kpis['total']" icon="fa-boxes-stacked" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="No ar na loja" :value="$kpis['no_ar']" icon="fa-play" tone="green" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Abaixo do mínimo" :value="$kpis['baixo']" icon="fa-triangle-exclamation" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Zerados" :value="$kpis['zerado']" icon="fa-circle-xmark" tone="red" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.catalogo.produtos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nome ou SKU..." />
    <x-admin.filter name="categoria" type="select" label="Categoria" col="col-6 col-md-3" :options="$categorias" empty="Todas as categorias" />
    <x-admin.filter name="estoque" type="select" label="Estoque" col="col-6 col-md-3" :options="['baixo' => 'Abaixo do mínimo']" empty="Todos os produtos" />
</x-admin.filters>

<x-admin.table :headers="['Produto', 'Categoria', 'end:Preço', 'center:Estoque', 'center:Loja', 'end:Ações']"
    empty="Nenhum produto com esse filtro."
    :paginator="$produtos">
    @foreach($produtos as $p)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="table-thumb">
                        @if($p->imagem)<img src="{{ asset('storage/'.$p->imagem) }}" alt="" loading="lazy">@else<i class="fas fa-box" aria-hidden="true"></i>@endif
                    </span>
                    <span>
                        <span class="fw-semibold">{{ $p->nome }}</span>
                        <div class="small text-secondary">{{ $p->sku ?: 'sem SKU' }}</div>
                    </span>
                </div>
            </td>
            <td class="small">{{ $p->category?->nome ?: '—' }}</td>
            <td class="text-end small">{{ brl($p->preco()) }}@if($p->mensalidade_cents)<div class="text-secondary">+{{ brl($p->mensalidade_cents) }}/mês</div>@endif</td>
            <td class="text-center">
                @php $estado = $p->estadoEstoque(); @endphp
                <x-admin.status :rotulo="$estado === 'livre' ? 'livre' : $p->estoque" :tom="tom_por_status('estoque', $estado)" />
            </td>
            <td class="text-center"><x-admin.status :rotulo="$p->ativo ? 'ativo' : 'oculto'" :tom="tom_por_flag($p->ativo)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.catalogo.produtos.estoque.show', $p) }}" class="btn btn-sm btn-outline-primary" title="Movimentar estoque"><i class="fas fa-boxes-stacked"></i></a>
                    <a href="{{ route('admin.catalogo.produtos.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.catalogo.produtos.destroy', $p)" confirm="Excluir produto {{ $p->nome }}?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
