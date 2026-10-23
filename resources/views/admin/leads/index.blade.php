@extends('admin.layouts.app')
@section('title', 'Leads')
@section('page_title', 'Leads')
@section('page_description', 'Contatos captados pelo site, com origem de campanha e posição no funil.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm"><i class="fas fa-file-csv me-1"></i> Exportar CSV</a>
@endsection

@section('content')
<x-admin.filters :reset="route('admin.leads.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-3" placeholder="Nome, telefone ou e-mail..." />
    <x-admin.filter name="status" type="select" label="Status" col="col-6 col-md-2" :options="\App\Models\Lead::STATUS_LABELS" empty="Todos" />
    <x-admin.filter name="campanha" type="select" label="Campanha" col="col-6 col-md-2" :options="$campanhas->mapWithKeys(fn ($nome, $id) => [$id => Str::limit($nome, 22)])" empty="Todas" />
    <x-admin.filter name="responsavel" type="select" label="Responsável" col="col-6 col-md-2" :options="$responsaveis" empty="Qualquer" />
    <x-admin.filter name="inicio" type="date" label="De" col="col-6 col-md-1" />
    <x-admin.filter name="fim" type="date" label="Até" col="col-6 col-md-1" />
    <x-slot:extra>
        <div class="ms-auto d-flex flex-wrap gap-1">
            @foreach(\App\Models\Lead::STATUS_LABELS as $s => $rotulo)
                <x-admin.status :rotulo="$rotulo.': '.($contagem[$s] ?? 0)" :tom="tom_por_status('funil', $s)" />
            @endforeach
        </div>
    </x-slot:extra>
</x-admin.filters>

<x-admin.table class="table-leads" :headers="['Lead', 'Contato', 'Kit / Frota', 'Campanha / Origem', 'Status', 'Responsável', 'Recebido', 'end:Ações']"
    empty="Nenhum lead encontrado com esses filtros."
    :paginator="$leads">
    @foreach($leads as $l)
        <tr>
            <td>
                <a href="{{ route('admin.leads.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->name }}</a>
                <div class="small text-secondary">{{ \App\Models\Lead::STATUS_LABELS[$l->profile] ?? ($l->profile ?: '—') }}</div>
            </td>
            <td class="small">{{ $l->phone }}@if($l->email)<div class="text-secondary">{{ $l->email }}</div>@endif</td>
            <td class="small">{{ $l->kit ?: '—' }}<div class="text-secondary">{{ $l->fleet_size ? $l->fleet_size.' veículo(s)' : '' }}</div></td>
            <td class="small">
                {{ $l->campaign?->name ?? 'Sem campanha' }}
                <div class="text-secondary">{{ $l->utm_source ?: ($l->gclid ? 'google' : ($l->fbclid ? 'facebook' : 'direto')) }}</div>
            </td>
            <td><x-admin.status :rotulo="\App\Models\Lead::label($l->status)" :tom="tom_por_status('funil', $l->status)" /></td>
            <td class="small">{{ $l->owner?->name ?? '—' }}</td>
            <td class="small text-secondary">{{ $l->created_at->format('d/m/Y H:i') }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.leads.show', $l) }}" class="btn btn-sm btn-outline-secondary" title="Abrir ficha"><i class="fas fa-eye"></i></a>
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
