@extends('admin.layouts.app')
@section('title', 'Usuários')
@section('page_title', 'Usuários e permissões')
@section('page_description', 'Perfis com acesso ao painel de leads.')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-user-plus text-warning me-1"></i> Novo usuário</h5>
            <form method="POST" action="{{ route('admin.usuarios.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Nome</label><input name="name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Telefone</label><input name="telefone" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label small">Perfil</label>
                    <select name="role" class="form-select form-select-sm"><option value="vendedor">Vendedor</option><option value="visualizador">Visualizador</option><option value="admin">Administrador</option></select>
                </div>
                <div class="mb-3"><label class="form-label small">Senha</label><input type="password" name="password" class="form-control form-control-sm" required minlength="8"></div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Criar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Usuário</th><th>Perfil</th><th>Status</th><th>Leads</th><th></th></tr></thead>
                    <tbody>
                    @foreach($usuarios as $usr)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px">{{ substr($usr->name,0,1) }}</div><div><span class="fw-semibold">{{ $usr->name }}</span><div class="small text-secondary">{{ $usr->email }}</div></div></div></td>
                            <td><span class="badge bg-body-secondary text-body">{{ ucfirst($usr->role) }}</span></td>
                            <td><span class="badge-status {{ $usr->status==='ativo'?'st-vendido':'st-perdido' }}">{{ ucfirst($usr->status) }}</span></td>
                            <td class="text-center small">{{ $usr->leads_count }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit{{ $usr->id }}"><i class="fas fa-pen"></i></button>
                                @if($usr->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.usuarios.destroy',$usr) }}" class="d-inline" onsubmit="return confirm('Excluir usuário?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-body-tertiary">{{ $usuarios->links() }}</div>
        </div>
    </div>
</div>

{{-- Modais de edição --}}
@foreach($usuarios as $usr)
<div class="modal fade" id="edit{{ $usr->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('admin.usuarios.update',$usr) }}">
        @csrf
        <div class="modal-header"><h5 class="modal-title">Editar {{ $usr->name }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label small">Nome</label><input name="name" class="form-control form-control-sm" value="{{ $usr->name }}" required></div>
            <div class="mb-2"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control form-control-sm" value="{{ $usr->email }}" required></div>
            <div class="mb-2"><label class="form-label small">Telefone</label><input name="telefone" class="form-control form-control-sm" value="{{ $usr->telefone }}"></div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label small">Perfil</label>
                    <select name="role" class="form-select form-select-sm"><option value="vendedor" @selected($usr->role==='vendedor')>Vendedor</option><option value="visualizador" @selected($usr->role==='visualizador')>Visualizador</option><option value="admin" @selected($usr->role==='admin')>Administrador</option></select>
                </div>
                <div class="col-6"><label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm"><option value="ativo" @selected($usr->status==='ativo')>Ativo</option><option value="inativo" @selected($usr->status==='inativo')>Inativo</option></select>
                </div>
            </div>
            <div class="mt-2"><label class="form-label small">Nova senha <span class="text-secondary">(opcional)</span></label><input type="password" name="password" class="form-control form-control-sm" minlength="8"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-golden btn-sm">Salvar</button></div>
    </form>
</div></div></div>
@endforeach
@endsection
