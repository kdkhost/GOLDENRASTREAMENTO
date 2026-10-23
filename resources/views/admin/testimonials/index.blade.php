@extends('admin.layouts.app')
@section('title', 'Depoimentos')
@section('page_title', 'Depoimentos de clientes')
@section('page_description', 'Avaliações exibidas no carrossel da home e nas páginas do site.')

@section('page_actions')
<a href="{{ route('admin.conteudo.depoimentos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo depoimento</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.conteudo.depoimentos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-6" placeholder="Nome, papel, cidade ou texto..." />
    <x-admin.filter name="nota" type="select" label="Nota" col="col-6 col-md-3" :options="['5' => '5 estrelas', '4' => '4 estrelas', '3' => '3 estrelas', '2' => '2 estrelas', '1' => '1 estrela']" empty="Todas" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-3" :options="['aprovado' => 'Visível', 'pendente' => 'Oculto']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Depoimento', 'center:Nota', 'center:Status', 'end:Ações']"
    empty="Nenhum depoimento com esse filtro."
    :paginator="$depoimentos">
    @foreach($depoimentos as $d)
        <tr>
            <td>
                <div class="fw-semibold">{{ $d->nome }} <span class="text-secondary small">· {{ $d->papel ?: $d->cidade }}</span></div>
                <div class="small text-secondary">“{{ Str::limit($d->texto, 90) }}”</div>
            </td>
            <td class="text-center text-warning small">{{ str_repeat('★', $d->nota) }}</td>
            <td class="text-center"><x-admin.status :rotulo="$d->aprovado ? 'visível' : 'oculto'" :tom="tom_por_flag($d->aprovado)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.conteudo.depoimentos.edit', $d) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.conteudo.depoimentos.destroy', $d)" confirm="Remover este depoimento?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
