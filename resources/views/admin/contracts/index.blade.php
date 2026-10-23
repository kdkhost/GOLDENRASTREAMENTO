@extends('admin.layouts.app')
@section('title', 'Contratos')
@section('page_title', 'Contratos do cliente')
@section('page_description', 'O PDF assinado publicado aqui é o que o cliente baixa no portal. Rascunho não aparece para ele.')

@section('page_actions')
<a href="{{ route('admin.contratos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo contrato</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Contratos" :value="$kpis['total']" icon="fa-file-signature" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Em vigor" :value="$kpis['vigentes']" icon="fa-circle-check" tone="teal" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Vencendo em 30 dias" :value="$kpis['vencendo']" icon="fa-clock" tone="amber" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Sem arquivo" :value="$kpis['sem_arquivo']" icon="fa-file-circle-exclamation" tone="red" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.contratos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nº do contrato, título ou cliente..." />
    <x-admin.filter name="status" type="select" label="Situação" col="col-6 col-md-4" :options="$statuses" empty="Todas as situações" />
    <x-admin.filter name="tipo" type="select" label="Tipo" col="col-6 col-md-4" :options="$tipos" empty="Todos os tipos" />
</x-admin.filters>

<x-admin.table :headers="['Contrato', 'Cliente', 'center:Tipo', 'end:Mensalidade', 'center:Situação', 'center:Vigência', 'end:Arquivo', 'end:Ações']"
    empty="Nenhum contrato com esse filtro. Cadastre o termo de adesão e anexe o PDF assinado."
    :paginator="$contratos">
    @foreach($contratos as $c)
        <tr>
            <td>
                <a href="{{ route('admin.contratos.edit', $c) }}" class="fw-semibold text-decoration-none">{{ $c->titulo }}</a>
                <div class="text-secondary small">{{ $c->numero }}</div>
            </td>
            <td class="small"><a href="{{ route('admin.clientes.show', $c->customer) }}" class="text-decoration-none">{{ $c->customer?->nome ?? 'Ficha removida' }}</a></td>
            <td class="text-center small">{{ $c->tipoLabel() }}</td>
            <td class="text-end small">{{ $c->valor_mensal_cents ? brl($c->valor_mensal_cents) : '—' }}</td>
            <td class="text-center"><x-admin.status :rotulo="$c->statusLabel()" :tom="tom_por_status('contrato', $c->status)" /></td>
            <td class="text-center small text-secondary">
                @if($c->celebrado_em)<div>{{ $c->celebrado_em->format('d/m/Y') }}</div>@endif
                @if($c->vigencia_ate)
                    <div class="{{ $c->vencendoEm() ? 'fw-semibold text-gold' : '' }}">até {{ $c->vigencia_ate->format('d/m/Y') }}</div>
                @endif
            </td>
            <td class="text-end">
                @if($c->temArquivo())
                    <a href="{{ asset('storage/'.$c->arquivo) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Abrir o PDF"><i class="fas fa-file-pdf"></i></a>
                @else
                    <x-admin.status rotulo="sem arquivo" tom="neutral" />
                @endif
            </td>
            <td class="text-end">
                <div class="table-actions">
                    <a href="{{ route('admin.contratos.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <x-admin.delete :action="route('admin.contratos.destroy', $c)" confirm="Excluir o contrato {{ $c->numero }} e o PDF anexado?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
