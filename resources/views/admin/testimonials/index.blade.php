@extends('admin.layouts.app')
@section('title', 'Depoimentos')
@section('page_title', 'Depoimentos de clientes')
@section('page_description', 'Avaliações exibidas na home e nas páginas do site.')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus text-warning me-1"></i> Novo depoimento</h5>
            <form method="POST" action="{{ route('admin.conteudo.depoimentos.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Nome *</label><input name="nome" class="form-control form-control-sm" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Papel</label><input name="papel" class="form-control form-control-sm" placeholder="Motorista de app"></div>
                    <div class="col-6"><label class="form-label small">Cidade</label><input name="cidade" class="form-control form-control-sm"></div>
                </div>
                <div class="mt-2 mb-2"><label class="form-label small">Nota</label><select name="nota" class="form-select form-select-sm">@for($n=5;$n>=1;$n--)<option value="{{ $n }}">{{ $n }} estrela(s)</option>@endfor</select></div>
                <div class="mb-2"><label class="form-label small">Texto *</label><textarea name="texto" rows="3" class="form-control form-control-sm" required></textarea></div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="aprovado" value="1" checked><label class="form-check-label small">Aprovado (visível)</label></div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Adicionar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr class="text-secondary small"><th>Depoimento</th><th class="text-center">Nota</th><th class="text-center">Status</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($depoimentos as $d)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $d->nome }} <span class="text-secondary small">· {{ $d->papel ?: $d->cidade }}</span></div>
                            <div class="small text-secondary">"{{ Str::limit($d->texto, 90) }}"</div>
                        </td>
                        <td class="text-center text-warning small">{{ str_repeat('★', $d->nota) }}</td>
                        <td class="text-center"><span class="badge {{ $d->aprovado ? 'bg-success':'bg-secondary' }}">{{ $d->aprovado ? 'visível':'oculto' }}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dep{{ $d->id }}"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.conteudo.depoimentos.destroy',$d) }}" class="d-inline" onsubmit="return confirm('Remover depoimento?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                    <div class="modal fade" id="dep{{ $d->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.conteudo.depoimentos.update',$d) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Editar depoimento</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-2"><label class="form-label small">Nome</label><input name="nome" value="{{ $d->nome }}" class="form-control form-control-sm" required></div>
                            <div class="row g-2"><div class="col-6"><label class="form-label small">Papel</label><input name="papel" value="{{ $d->papel }}" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">Cidade</label><input name="cidade" value="{{ $d->cidade }}" class="form-control form-control-sm"></div></div>
                            <div class="row g-2 mt-1"><div class="col-6"><label class="form-label small">Nota</label><select name="nota" class="form-select form-select-sm">@for($n=5;$n>=1;$n--)<option value="{{ $n }}" @selected($d->nota===$n)>{{ $n }}</option>@endfor</select></div><div class="col-6"><label class="form-label small">Ordem</label><input type="number" name="ordem" value="{{ $d->ordem }}" class="form-control form-control-sm" min="0"></div></div>
                            <div class="mt-2"><label class="form-label small">Texto</label><textarea name="texto" rows="3" class="form-control form-control-sm" required>{{ $d->texto }}</textarea></div>
                            <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="aprovado" value="1" @checked($d->aprovado)><label class="form-check-label small">Aprovado</label></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-sm btn-golden"><i class="fas fa-save me-1"></i> Salvar</button></div>
                    </form></div></div>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-5">Nenhum depoimento.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div></div>
    </div>
</div>
@endsection
