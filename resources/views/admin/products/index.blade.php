@extends('admin.layouts.app')
@section('title', 'Produtos')
@section('page_title', 'Produtos e estoque')
@section('page_description', 'Catálogo da loja: rastreadores, chips M2M, acessórios e planos.')

@section('page_actions')
<a href="{{ route('admin.catalogo.produtos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo produto</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.catalogo.produtos.index')">
    <x-admin.filter name="q" label="Buscar" col="col-12 col-md-4" placeholder="Nome ou SKU..." />
    <x-admin.filter name="categoria" type="select" label="Categoria" col="col-6 col-md-3" :options="$categorias->pluck('nome', 'id')->all()" empty="Todas as categorias" />
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
                @if(!$p->controle_estoque)
                    <span class="badge bg-body-secondary text-secondary">livre</span>
                @elseif($p->estoque <= 0)
                    <span class="badge bg-danger">0</span>
                @elseif($p->estoque > $p->estoque_minimo)
                    <span class="badge-status st-vendido">{{ $p->estoque }}</span>
                @else
                    <span class="badge bg-warning text-dark">{{ $p->estoque }}</span>
                @endif
            </td>
            <td class="text-center"><span class="badge bg-body-secondary text-body">{{ $p->ativo ? 'ativo' : 'oculto' }}</span></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.catalogo.produtos.estoque.show', $p) }}" class="btn btn-sm btn-outline-primary" title="Movimentar estoque"><i class="fas fa-boxes-stacked"></i></a>
                    <a href="{{ route('admin.catalogo.produtos.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.catalogo.produtos.destroy', $p)" confirm="Excluir produto {{ $p->nome }}?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
