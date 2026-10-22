@extends('admin.layouts.app')
@section('title', 'Auditoria')
@section('page_title', 'Trilha de auditoria')
@section('page_description', 'Registro de ações administrativas: acessos, mudanças de funil, exportações e configurações.')

@section('content')
<div class="card shadow-sm mb-3 filters-card"><div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small">Ação</label>
            <select name="acao" class="form-select form-select-sm"><option value="">Todas</option>
            @foreach($acoes as $a=>$c)<option value="{{ $a }}" @selected(($filtros['acao']??'')==$a)>{{ $a }} ({{ $c }})</option>@endforeach</select>
        </div>
        <div class="col-md-3"><button class="btn btn-sm btn-golden"><i class="fas fa-filter me-1"></i> Filtrar</button>
        <a href="{{ route('admin.activities.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a></div>
    </form>
</div></div>

<div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th>Quando</th><th>Usuário</th><th>Ação</th><th>Descrição</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($atividades as $a)
                <tr>
                    <td class="small text-secondary">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                    <td class="small fw-semibold">{{ $a->user_name }}</td>
                    <td><span class="badge bg-body-secondary text-body">{{ $a->action }}</span></td>
                    <td class="small">{{ $a->description }}</td>
                    <td class="small text-secondary">{{ $a->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-5">Sem registros de auditoria.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card-footer bg-body-tertiary">{{ $atividades->links() }}</div>
</div>
@endsection
