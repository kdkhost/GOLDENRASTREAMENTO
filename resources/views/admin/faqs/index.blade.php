@extends('admin.layouts.app')
@section('title', 'FAQs')
@section('page_title', 'Perguntas frequentes')
@section('page_description', 'Gerencie as dúvidas exibidas na página de FAQ e na home.')

@section('page_actions')
<a href="{{ route('admin.conteudo.faqs.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova pergunta</a>
@endsection

@section('content')
<x-admin.table :headers="['Pergunta', 'center:Status', 'center:Ordem', 'end:Ações']" empty="Nenhuma pergunta cadastrada.">
    @foreach($faqs as $f)
        <tr>
            <td><span class="fw-semibold">{{ $f->pergunta }}</span><div class="small text-secondary">{{ Str::limit($f->resposta, 90) }}</div></td>
            <td class="text-center"><span class="badge {{ $f->ativa ? 'bg-success' : 'bg-secondary' }}">{{ $f->ativa ? 'ativa' : 'oculta' }}</span></td>
            <td class="text-center small text-secondary">{{ $f->ordem }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.conteudo.faqs.edit', $f) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.conteudo.faqs.destroy', $f)" confirm="Remover esta pergunta?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
