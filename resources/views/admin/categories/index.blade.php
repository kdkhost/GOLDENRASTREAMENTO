@extends('admin.layouts.app')
@section('title', 'Categorias')
@section('page_title', 'Categorias do catálogo')
@section('page_description', 'Agrupe os produtos da loja (rastreadores, chips, acessórios, planos).')

@section('page_actions')
<a href="{{ route('admin.catalogo.categorias.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova categoria</a>
@endsection

@section('content')
<x-admin.table :headers="['Categoria', 'center:Produtos', 'center:Status', 'end:Ações']" empty="Nenhuma categoria cadastrada.">
    @foreach($categorias as $c)
        <tr>
            <td>
                <span class="fw-semibold">{{ $c->nome }}</span>
                <div class="small text-secondary mt-1">{{ $c->descricao ?: '—' }} · ordem {{ $c->ordem }}</div>
            </td>
            <td class="text-center">{{ $c->products_count }}</td>
            <td class="text-center"><span class="badge {{ $c->ativa ? 'tone-green' : 'tone-neutral' }}">{{ $c->ativa ? 'ativa' : 'inativa' }}</span></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.catalogo.categorias.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.catalogo.categorias.destroy', $c)" confirm="Excluir categoria? Os produtos ficam sem categoria." />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
