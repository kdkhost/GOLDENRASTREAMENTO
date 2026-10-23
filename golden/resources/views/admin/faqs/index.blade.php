@extends('admin.layouts.app')
@section('title', 'FAQs')
@section('page_title', 'Perguntas frequentes')
@section('page_description', 'Gerencie as dúvidas exibidas na página de FAQ e na home.')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus text-warning me-1"></i> Nova pergunta</h5>
            <form method="POST" action="{{ route('admin.conteudo.faqs.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Pergunta *</label><input name="pergunta" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Resposta *</label><textarea name="resposta" rows="3" class="form-control form-control-sm" required></textarea></div>
                <div class="mb-3"><label class="form-label small">Ordem</label><input type="number" name="ordem" min="0" value="0" class="form-control form-control-sm"></div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Adicionar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr class="text-secondary small"><th>Pergunta</th><th class="text-center">Status</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($faqs as $f)
                    <tr>
                        <td><span class="fw-semibold">{{ $f->pergunta }}</span><div class="small text-secondary">{{ Str::limit($f->resposta, 80) }}</div></td>
                        <td class="text-center"><span class="badge {{ $f->ativa ? 'bg-success':'bg-secondary' }}">{{ $f->ativa ? 'ativa':'oculta' }}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#faq{{ $f->id }}"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.conteudo.faqs.destroy',$f) }}" class="d-inline" onsubmit="return confirm('Remover pergunta?')">@csrf@method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                    <div class="modal fade" id="faq{{ $f->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.conteudo.faqs.update',$f) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Editar pergunta</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small">Pergunta</label><input name="pergunta" value="{{ $f->pergunta }}" class="form-control form-control-sm" required></div>
                            <div class="mb-2"><label class="form-label small">Resposta</label><textarea name="resposta" rows="3" class="form-control form-control-sm" required>{{ $f->resposta }}</textarea></div>
                            <div class="row g-2"><div class="col-6"><label class="form-label small">Ordem</label><input type="number" name="ordem" value="{{ $f->ordem }}" class="form-control form-control-sm" min="0"></div><div class="col-6"><label class="form-label small">Status</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="ativa" value="1" @checked($f->ativa)><label class="form-check-label small">Ativa</label></div></div></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-sm btn-golden"><i class="fas fa-save me-1"></i> Salvar</button></div>
                    </form></div></div>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-5">Nenhuma pergunta cadastrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div></div>
    </div>
</div>
@endsection
