@extends('admin.layouts.app')
@section('title', 'Páginas')
@section('page_title', 'Páginas do site (CMS)')
@section('page_description', 'Páginas institucionais publicadas em /p/slug. As marcadas para o menu aparecem na navegação do site.')

@section('page_actions')
<a href="{{ route('admin.conteudo.paginas.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova página</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.conteudo.paginas.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-6" placeholder="Título ou slug..." />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-3" :options="['publicada' => 'Publicada', 'rascunho' => 'Rascunho']" empty="Todas" />
    <x-admin.filter name="menu" type="select" label="Menu" col="col-6 col-md-3" :options="['no_menu' => 'No menu', 'fora_do_menu' => 'Fora do menu']" empty="Todos" />
</x-admin.filters>

<x-admin.table :headers="['Página', 'Endereço', 'center:Menu', 'center:Status', 'center:Atualizada', 'end:Ações']"
    empty="Nenhuma página com esse filtro."
    :paginator="$paginas">
    @foreach($paginas as $pg)
        <tr>
            <td>
                <span class="fw-semibold">{{ $pg->titulo }}</span>
                @if($pg->intro)<div class="small text-secondary">{{ Str::limit($pg->intro, 70) }}</div>@endif
            </td>
            <td><code class="small">/p/{{ $pg->slug }}</code></td>
            <td class="text-center"><x-admin.status :rotulo="$pg->exibir_menu ? 'no menu' : 'fora do menu'" :tom="tom_por_flag($pg->exibir_menu)" /></td>
            <td class="text-center"><x-admin.status :rotulo="$pg->publicada ? 'publicada' : 'rascunho'" :tom="tom_por_flag($pg->publicada)" /></td>
            <td class="text-center small text-secondary">{{ $pg->updated_at->format('d/m/Y') }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('site.pagina', $pg->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Ver no site"><i class="fas fa-up-right-from-square"></i></a>
                    <a href="{{ route('admin.conteudo.paginas.edit', $pg) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.conteudo.paginas.destroy', $pg)" confirm="Excluir a página {{ $pg->titulo }}?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
