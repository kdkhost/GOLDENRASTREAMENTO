@extends('admin.layouts.app')
@section('title', 'Leads')
@section('page_title', 'Leads')
@section('page_description', 'Contatos captados pelo site, com origem de campanha e posição no funil.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm"><i class="fas fa-file-csv me-1"></i> Exportar</a>
@endsection

@section('content')
{{-- Filtros --}}
<div class="card filters-card shadow-sm mb-3"><div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Buscar</label>
            <input name="busca" class="form-control form-control-sm" placeholder="Nome, telefone, e-mail" value="{{ $filtros['busca'] ?? '' }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach(\App\Models\Lead::STATUSES as $s)<option value="{{ $s }}" @selected(($filtros['status']??'')==$s)>{{ \App\Models\Lead::label($s) }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Campanha</label>
            <select name="campanha" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach($campanhas as $c)<option value="{{ $c->id }}" @selected(($filtros['campanha']??'')==$c->id)>{{ Str::limit($c->name,22) }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">De</label>
            <input type="date" name="inicio" class="form-control form-control-sm" value="{{ $filtros['inicio'] ?? '' }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Até</label>
            <input type="date" name="fim" class="form-control form-control-sm" value="{{ $filtros['fim'] ?? '' }}">
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-golden"><i class="fas fa-filter me-1"></i> Filtrar</button>
            <a href="{{ route('admin.leads.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
            <div class="ms-auto d-flex gap-2 align-items-center">
                @foreach(\App\Models\Lead::STATUSES as $s)<span class="badge-status st-{{ $s }}">{{ \App\Models\Lead::label($s) }}: {{ $contagem[$s] ?? 0 }}</span>@endforeach
            </div>
        </div>
    </form>
</div></div>

{{-- Tabela --}}
<div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover table-leads mb-0">
            <thead><tr class="text-secondary small"><th>Lead</th><th>Contato</th><th>Kit / Frota</th><th>Campanha / Origem</th><th>Status</th><th>Recebido</th><th></th></tr></thead>
            <tbody>
            @forelse($leads as $l)
                <tr>
                    <td>
                        <a href="{{ route('admin.leads.show',$l) }}" class="fw-semibold text-decoration-none text-body">{{ $l->name }}</a>
                        <div class="small text-secondary">{{ \App\Models\Lead::STATUS_LABELS[$l->profile] ?? ($l->profile ?: '—') }}</div>
                    </td>
                    <td class="small">{{ $l->phone }}@if($l->email)<br><span class="text-secondary">{{ $l->email }}</span>@endif</td>
                    <td class="small">{{ $l->kit ?: '—' }}<br><span class="text-secondary">{{ $l->fleet_size ? $l->fleet_size.' veíc.' : '' }}</span></td>
                    <td class="small">
                        {{ $l->campaign?->name ?? 'Sem campanha' }}
                        <div class="text-secondary">{{ $l->utm_source ?: ($l->gclid ? 'google' : ($l->fbclid ? 'facebook' : 'direto')) }}</div>
                    </td>
                    <td><span class="badge-status st-{{ $l->status }}">{{ \App\Models\Lead::label($l->status) }}</span></td>
                    <td class="small text-secondary">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end"><a href="{{ route('admin.leads.show',$l) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Nenhum lead encontrado com esses filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card-footer bg-body-tertiary">{{ $leads->links() }}</div>
</div>
@endsection
