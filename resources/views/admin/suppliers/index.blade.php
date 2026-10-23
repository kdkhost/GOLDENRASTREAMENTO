@extends('admin.layouts.app')
@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')
@section('page_description', 'Parceiros de equipamentos e chips M2M (Vivo, Claro, Algar, TIM).')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus text-warning me-1"></i> Novo fornecedor</h5>
            <form method="POST" action="{{ route('admin.catalogo.fornecedores.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Nome *</label><input name="nome" class="form-control form-control-sm" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">CNPJ</label><input name="cnpj" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">Contato</label><input name="contato" class="form-control form-control-sm"></div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6"><label class="form-label small">Telefone</label><input name="telefone" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control form-control-sm"></div>
                </div>
                <div class="mt-2 mb-3"><label class="form-label small">Observações</label><textarea name="observacoes" rows="2" class="form-control form-control-sm"></textarea></div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Cadastrar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr class="text-secondary small"><th>Fornecedor</th><th>Contato</th><th class="text-center">Status</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($fornecedores as $f)
                    <tr>
                        <td><span class="fw-semibold">{{ $f->nome }}</span><div class="small text-secondary">{{ $f->cnpj }}</div></td>
                        <td class="small">{{ $f->contato }}<div class="text-secondary">{{ $f->telefone }} {{ $f->email }}</div></td>
                        <td class="text-center"><span class="badge {{ $f->ativo ? 'bg-success':'bg-secondary' }}">{{ $f->ativo ? 'ativo':'inativo' }}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#for{{ $f->id }}"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.catalogo.fornecedores.destroy',$f) }}" class="d-inline" onsubmit="return confirm('Remover fornecedor?')">@csrf@method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                    <div class="modal fade" id="for{{ $f->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.catalogo.fornecedores.update',$f) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Editar fornecedor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small">Nome</label><input name="nome" value="{{ $f->nome }}" class="form-control form-control-sm" required></div>
                            <div class="row g-2"><div class="col-6"><label class="form-label small">CNPJ</label><input name="cnpj" value="{{ $f->cnpj }}" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">Contato</label><input name="contato" value="{{ $f->contato }}" class="form-control form-control-sm"></div></div>
                            <div class="row g-2 mt-1"><div class="col-6"><label class="form-label small">Telefone</label><input name="telefone" value="{{ $f->telefone }}" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">E-mail</label><input name="email" value="{{ $f->email }}" class="form-control form-control-sm"></div></div>
                            <div class="mt-2"><label class="form-label small">Observações</label><textarea name="observacoes" rows="2" class="form-control form-control-sm">{{ $f->observacoes }}</textarea></div>
                            <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="ativa" value="1" @checked($f->ativo)><label class="form-check-label small">Ativo</label></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-sm btn-golden"><i class="fas fa-save me-1"></i> Salvar</button></div>
                    </form></div></div>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-5">Nenhum fornecedor cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div></div>
    </div>
</div>
@endsection
