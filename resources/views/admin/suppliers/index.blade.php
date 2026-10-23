@extends('admin.layouts.app')
@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')
@section('page_description', 'Parceiros de equipamentos e chips M2M (Vivo, Claro, Algar, TIM).')

@section('page_actions')
<a href="{{ route('admin.catalogo.fornecedores.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo fornecedor</a>
@endsection

@section('content')
<x-admin.table :headers="['Fornecedor', 'Contato', 'center:Status', 'end:Ações']" empty="Nenhum fornecedor cadastrado.">
    @foreach($fornecedores as $f)
        <tr>
            <td><span class="fw-semibold">{{ $f->nome }}</span><div class="small text-secondary">{{ $f->cnpj ?: '—' }}</div></td>
            <td class="small">{{ $f->contato ?: '—' }}<div class="text-secondary">{{ $f->telefone }} {{ $f->email }}</div></td>
            <td class="text-center"><span class="badge {{ $f->ativo ? 'bg-success' : 'bg-secondary' }}">{{ $f->ativo ? 'ativo' : 'inativo' }}</span></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.catalogo.fornecedores.edit', $f) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.catalogo.fornecedores.destroy', $f)" confirm="Remover este fornecedor?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
