@extends('admin.layouts.app')
@section('title', 'Categorias')
@section('page_title', 'Categorias do catálogo')
@section('page_description', 'Agrupe os produtos da loja (rastreadores, chips, acessórios, planos).')

@section('page_actions')
<a href="{{ route('admin.catalogo.categorias.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova categoria</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.catalogo.categorias.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-8" placeholder="Nome ou descrição..." />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-12 col-md-4" :options="['ativa' => 'Ativa', 'inativa' => 'Inativa']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Categoria', 'center:Produtos', 'center:Status', 'end:Ações']"
    empty="Nenhuma categoria com esse filtro."
    :paginator="$categorias">
    @foreach($categorias as $c)
        <tr>
            <td>
                <span class="fw-semibold">{{ $c->nome }}</span>
                <div class="small text-secondary mt-1">{{ $c->descricao ?: '—' }} · ordem {{ $c->ordem }}</div>
            </td>
            <td class="text-center">{{ $c->products_count }}</td>
            <td class="text-center"><x-admin.status :rotulo="$c->ativa ? 'ativa' : 'inativa'" :tom="tom_por_flag($c->ativa)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.catalogo.categorias.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.catalogo.categorias.destroy', $c)" confirm="Excluir categoria? Os produtos ficam sem categoria." />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
