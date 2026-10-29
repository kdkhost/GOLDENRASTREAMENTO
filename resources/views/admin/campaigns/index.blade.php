@extends('admin.layouts.app')
@section('title', 'Campanhas')
@section('page_title', 'Campanhas de tráfego pago')
@section('page_description', 'Origem dos leads e taxa de conversão por campanha.')

@section('page_actions')
<a href="{{ route('admin.campanhas.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova campanha</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Campanhas" :value="$kpis['total']" icon="fa-bullhorn" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="No ar" :value="$kpis['ativas']" icon="fa-play" tone="green" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Com leads" :value="$kpis['com_leads']" icon="fa-user-plus" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Receita de vendidos" :value="brl($kpis['receita'])" icon="fa-sack-dollar" tone="teal" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.campanhas.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Nome, source, medium ou conteúdo..." />
    <x-admin.filter name="canal" type="select" label="Canal" col="col-6 col-md-3" :options="\App\Models\Campaign::canais()" empty="Todos os canais" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-4" :options="['ativa' => 'No ar', 'pausada' => 'Pausada']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Campanha', 'Canal', 'center:Leads', 'center:Novos', 'center:Vendidos', 'center:Conversão', 'end:Receita', 'end:Ações']"
    empty="Nenhuma campanha com esse filtro."
    :paginator="$campanhas">
    @foreach($campanhas as $c)
        <tr>
            <td>
                <a href="{{ route('admin.leads.index', ['campanha' => $c->id]) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a>
                <div class="small text-secondary mt-1">
                    <code>{{ $c->source ?: '—' }}</code> · <code>{{ $c->medium ?: '—' }}</code>
                    @unless($c->ativa)<x-admin.status rotulo="pausada" :tom="tom_por_flag($c->ativa)" class="ms-2" />@endunless
                </div>
            </td>
            <td><x-admin.status :rotulo="$c->canalLabel()" :tom="tom_por_status('canal', $c->channel)" /></td>
            <td class="text-center">{{ $c->leads_count }}</td>
            <td class="text-center">{{ $c->leads_novos }}</td>
            <td class="text-center">{{ $c->leads_vendidos }}</td>
            <td class="text-center">
                @php $taxo = $c->conversao(); @endphp
                <x-admin.status :rotulo="$taxo.'%'" :tom="tom_por_flag($taxo > 0)" />
            </td>
            <td class="text-end small">{{ $c->leads_receita ? brl($c->leads_receita) : '—' }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.campanhas.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <form method="POST" action="{{ route('admin.campanhas.toggle', $c) }}" class="d-inline" data-atualiza=".app-content">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" title="{{ $c->ativa ? 'Pausar' : 'Ativar' }}"><i class="fas fa-{{ $c->ativa ? 'pause' : 'play' }}"></i></button>
                    </form>
                    @can('admin')
                        <x-admin.delete :action="route('admin.campanhas.destroy', $c)" confirm="Excluir campanha? Os leads permanecem, apenas perdem o vínculo." />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
