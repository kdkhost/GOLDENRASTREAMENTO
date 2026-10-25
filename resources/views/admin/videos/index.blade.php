@extends('admin.layouts.app')
@section('title', 'Vídeos tutoriais')
@section('page_title', 'Vídeos tutoriais')
@section('page_description', 'Os tutoriais que o cliente assiste na galeria do site, em /videos.')

@section('page_actions')
<a href="{{ route('admin.conteudo.videos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo vídeo</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.conteudo.videos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Título ou descrição..." />
    <x-admin.filter name="categoria" type="select" label="Categoria" col="col-6 col-md-3" :options="$categorias" empty="Todas" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-4" :options="['ativa' => 'No ar', 'oculta' => 'Oculta']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Tutorial', 'Categoria', 'center:Ordem', 'center:Status', 'end:Ações']"
    empty="Nenhum vídeo com esse filtro."
    :paginator="$videos">
    @foreach($videos as $v)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="table-thumb">
                        @if($v->codigo)<img src="{{ \App\Models\Video::thumb($v->codigo) }}" alt="" loading="lazy">@else<i class="fas fa-play" aria-hidden="true"></i>@endif
                    </span>
                    <span>
                        <span class="fw-semibold">{{ $v->titulo }}</span>
                        <div class="small text-secondary">{{ $v->codigo }}</div>
                    </span>
                </div>
            </td>
            <td><x-admin.status :rotulo="$categorias[$v->categoria] ?? $v->categoria" :tom="tom_por_status('video', $v->categoria)" /></td>
            <td class="text-center small text-secondary">{{ $v->ordem }}</td>
            <td class="text-center"><x-admin.status :rotulo="$v->ativa ? 'no ar' : 'oculta'" :tom="tom_por_flag($v->ativa)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ $v->codigo ? 'https://www.youtube.com/watch?v=' . $v->codigo : '#' }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Abrir no YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="{{ route('admin.conteudo.videos.edit', $v) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.conteudo.videos.destroy', $v)" confirm="Remover este vídeo da galeria?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
