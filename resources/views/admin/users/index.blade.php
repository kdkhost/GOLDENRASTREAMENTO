@extends('admin.layouts.app')
@section('title', 'Usuários')
@section('page_title', 'Usuários e permissões')
@section('page_description', 'Quem tem acesso ao painel e o que cada perfil pode fazer.')

@section('page_actions')
<a href="{{ route('admin.usuarios.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-user-plus me-1"></i> Novo usuário</a>
@endsection

@section('content')
<x-admin.table :headers="['Usuário', 'Perfil', 'Status', 'center:Leads', 'end:Ações']" :paginator="$usuarios" empty="Nenhum usuário cadastrado.">
    @foreach($usuarios as $usr)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-letter bg-warning text-dark">{{ substr($usr->name, 0, 1) }}</span>
                    <div><span class="fw-semibold">{{ $usr->name }}</span><div class="small text-secondary">{{ $usr->email }}</div></div>
                </div>
            </td>
            <td><span class="badge bg-body-secondary text-body">{{ ucfirst($usr->role) }}</span></td>
            <td><span class="badge-status {{ $usr->status === 'ativo' ? 'st-vendido' : 'st-perdido' }}">{{ ucfirst($usr->status) }}</span></td>
            <td class="text-center small">{{ $usr->leads_count }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.usuarios.edit', $usr) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @if($usr->id !== auth()->id())
                        <x-admin.delete :action="route('admin.usuarios.destroy', $usr)" confirm="Excluir este usuário?" />
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
