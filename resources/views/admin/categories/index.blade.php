@extends('admin.layouts.app')
@section('title', 'Categorias')
@section('page_title', 'Categorias do catálogo')
@section('page_description', 'Agrupe os produtos da loja (rastreadores, chips, acessórios, planos).')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus text-warning me-1"></i> Nova categoria</h5>
            <form method="POST" action="{{ route('admin.catalogo.categorias.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Nome *</label><input name="nome" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Descrição</label><input name="descricao" class="form-control form-control-sm"></div>
                <div class="mb-3"><label class="form-label small">Ordem</label><input type="number" name="ordem" min="0" value="0" class="form-control form-control-sm"></div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Criar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr class="text-secondary small"><th>Categoria</th><th class="text-center">Produtos</th><th class="text-center">Status</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($categorias as $c)
                    <tr>
                        <td><span class="fw-semibold">{{ $c->nome }}</span><div class="small text-secondary mt-1">{{ $c->descricao }} · ordem {{ $c->ordem }}</div></td>
                        <td class="text-center">{{ $c->products_count }}</td>
                        <td class="text-center"><span class="badge {{ $c->ativa ? 'bg-success':'bg-secondary' }}">{{ $c->ativa ? 'ativa':'inativa' }}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cat{{ $c->id }}"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.catalogo.categorias.destroy',$c) }}" class="d-inline" onsubmit="return confirm('Excluir categoria? Produtos ficam sem categoria.')">@csrf@method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                    <div class="modal fade" id="cat{{ $c->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.catalogo.categorias.update',$c) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Editar categoria</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small">Nome</label><input name="nome" value="{{ $c->nome }}" class="form-control form-control-sm" required></div>
                            <div class="mb-2"><label class="form-label small">Descrição</label><input name="descricao" value="{{ $c->descricao }}" class="form-control form-control-sm"></div>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label small">Ordem</label><input type="number" name="ordem" value="{{ $c->ordem }}" class="form-control form-control-sm" min="0"></div>
                                <div class="col-6"><label class="form-label small">Status</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="ativa" value="1" @checked($c->ativa)><label class="form-check-label small">Ativa</label></div></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-sm btn-golden"><i class="fas fa-save me-1"></i> Salvar</button></div>
                    </form></div></div>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-5">Nenhuma categoria cadastrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div></div>
    </div>
</div>
@endsection
