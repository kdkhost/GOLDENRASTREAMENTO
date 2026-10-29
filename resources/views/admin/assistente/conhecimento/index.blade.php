@extends('admin.layouts.app')
@section('title', 'O que o assistente sabe')
@section('page_title', 'O que o assistente sabe')
@section('page_description', 'A base que a máquina lê antes de responder. Sem fonte aqui, ela chama a equipe em vez de inventar.')

@section('page_actions')
<form method="POST" action="{{ route('admin.assistente.conhecimento.semear') }}" class="d-inline" data-atualiza=".app-content">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Acrescenta o que falta e atualiza o catálogo, sem mexer no que você editou">
        <i class="fas fa-graduation-cap me-1"></i> Ensinar o básico da Golden
    </button>
</form>
<a href="{{ route('admin.assistente.conhecimento.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo aprendizado</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.assistente.conhecimento.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Título, conteúdo ou assunto..." />
    <x-admin.filter name="origem" type="select" label="Origem" col="col-6 col-md-3" :options="$origens" empty="Todas" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-4" :options="['ativa' => 'No ar', 'oculta' => 'Oculta']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Assunto', 'Origem', 'center:Situação', 'Última leitura', 'end:Ações']"
    empty="Nenhum aprendizado com esse filtro."
    :paginator="$itens">
    @foreach($itens as $item)
        <tr>
            <td>
                <span class="fw-semibold">{{ $item->titulo }}</span>
                @if($item->tags)<div class="small text-secondary">{{ $item->tags }}</div>@endif
            </td>
            <td>
                <span class="small">{{ $item->origemLabel() }}</span>
                @if($item->url)
                    <div class="small"><a href="{{ $item->url }}" target="_blank" rel="noopener" class="text-break">ver a fonte</a></div>
                @endif
            </td>
            <td class="text-center"><x-admin.status :rotulo="$item->ativo ? 'no ar' : 'oculta'" :tom="tom_por_flag($item->ativo)" /></td>
            <td class="small text-secondary">{{ $item->lido_em ? $item->lido_em->format('d/m/Y') : 'gravado no painel' }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.assistente.conhecimento.edit', $item) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.assistente.conhecimento.destroy', $item)" confirm="O assistente deixa de saber este assunto?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
