@extends('admin.layouts.app')
@section('title', 'Depoimentos')
@section('page_title', 'Depoimentos de clientes')
@section('page_description', 'Avaliações exibidas no carrossel da home e nas páginas do site.')

@section('page_actions')
<a href="{{ route('admin.conteudo.depoimentos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo depoimento</a>
@endsection

@section('content')
<x-admin.table :headers="['Depoimento', 'center:Nota', 'center:Status', 'end:Ações']" empty="Nenhum depoimento cadastrado.">
    @foreach($depoimentos as $d)
        <tr>
            <td>
                <div class="fw-semibold">{{ $d->nome }} <span class="text-secondary small">· {{ $d->papel ?: $d->cidade }}</span></div>
                <div class="small text-secondary">“{{ Str::limit($d->texto, 90) }}”</div>
            </td>
            <td class="text-center text-warning small">{{ str_repeat('★', $d->nota) }}</td>
            <td class="text-center"><span class="badge {{ $d->aprovado ? 'tone-green' : 'tone-neutral' }}">{{ $d->aprovado ? 'visível' : 'oculto' }}</span></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.conteudo.depoimentos.edit', $d) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.conteudo.depoimentos.destroy', $d)" confirm="Remover este depoimento?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
