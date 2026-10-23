@extends('admin.layouts.app')
@section('title', 'Usuários')
@section('page_title', 'Usuários e permissões')
@section('page_description', 'Quem tem acesso ao painel e o que cada perfil pode fazer.')

@section('page_actions')
<a href="{{ route('admin.usuarios.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-user-plus me-1"></i> Novo usuário</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.usuarios.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-6" placeholder="Nome ou e-mail..." />
    <x-admin.filter name="perfil" type="select" label="Perfil" col="col-6 col-md-3" :options="$perfis" empty="Todos" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-3" :options="$situacoes" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Usuário', 'Perfil', 'Status', 'center:Leads', 'end:Ações']" :paginator="$usuarios" empty="Nenhum usuário com esse filtro.">
    @foreach($usuarios as $usr)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-letter">{{ substr($usr->name, 0, 1) }}</span>
                    <div><span class="fw-semibold">{{ $usr->name }}</span><div class="small text-secondary">{{ $usr->email }}</div></div>
                </div>
            </td>
            <td><span class="badge tone-neutral">{{ $perfis[$usr->role] ?? ucfirst($usr->role) }}</span></td>
            <td><x-admin.status :rotulo="ucfirst($usr->status)" :tom="tom_por_status('usuario', $usr->status)" /></td>
            <td class="text-center small">{{ $usr->leads_count }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.usuarios.edit', $usr) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        @if($usr->id !== auth()->id())
                            <x-admin.delete :action="route('admin.usuarios.destroy', $usr)" confirm="Excluir este usuário?" />
                        @endif
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
