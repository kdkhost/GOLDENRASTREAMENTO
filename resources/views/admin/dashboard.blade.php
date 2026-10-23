@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Visão geral de leads')
@section('page_description', 'Contatos captados, posição no funil e conversão das campanhas de tráfego pago.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm"><i class="fas fa-file-csv me-1"></i> Exportar CSV</a>
@endsection

@section('content')
@php
    $admin = auth()->user()->isAdmin();
    $funilLider = max($total, 1);
@endphp

{{-- Leads --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Leads totais" :value="$total" icon="fa-users" tone="teal" :href="route('admin.leads.index')" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Novos / aguardando" :value="$novos" icon="fa-envelope-circle-check" tone="gold" :href="route('admin.leads.index', ['status' => 'novo'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Taxa de conversão" :value="$conversao.'%'" icon="fa-trophy" tone="violet" :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Receita fechada" :value="brl($receita)" icon="fa-sack-dollar" tone="blue" pequeno :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
</div>

@if($admin)
    {{-- Módulos integrados --}}
    <div class="row g-3 mb-4">
        <div class="col-12"><h6 class="fw-bold text-secondary text-uppercase small mb-0"><i class="fas fa-cubes text-gold me-1"></i> Módulos integrados</h6></div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Clientes (CRM)" :value="$modulos['clientes']" :hint="$modulos['clientes_ativos'].' ativos na base'" icon="fa-user-check" tone="teal" pequeno :href="route('admin.clientes.index')" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Produtos ativos" :value="$modulos['produtos']" :hint="$modulos['estoque_baixo'] > 0 ? $modulos['estoque_baixo'].' com estoque baixo' : 'estoque em dia'" icon="fa-boxes-stacked" tone="gold" pequeno :href="route('admin.catalogo.produtos.index', $modulos['estoque_baixo'] > 0 ? ['estoque' => 'baixo'] : [])" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat :label="'Vendas na loja ('.$modulos['pedidos'].' pedidos)'" :value="brl($modulos['faturamento_loja'])" icon="fa-receipt" tone="violet" pequeno :href="route('admin.pedidos.index')" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Páginas publicadas" :value="$modulos['paginas']" :hint="$modulos['depoimentos'].' depoimentos aprovados'" icon="fa-file-lines" tone="blue" pequeno :href="route('admin.conteudo.paginas.index')" />
        </div>
        <div class="col-12">
            <div class="alert {{ $modulos['loja_ativa'] ? 'alert-success' : 'alert-secondary' }} d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 mb-0">
                <span class="small mb-0">
                    <i class="fas fa-store me-1"></i> Loja virtual <strong>{{ $modulos['loja_ativa'] ? 'ativa' : 'desativada' }}</strong> — vendas diretas de rastreadores, chips e acessórios.
                    @if($modulos['pedidos_abertos'] > 0) · {{ $modulos['pedidos_abertos'] }} pedido(s) aguardando.@endif
                </span>
                <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-outline-dark">Gerenciar</a>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <x-admin.card title="Funil de conversão" icon="fa-filter">
            <div class="funnel mt-2">
                @foreach(['novo' => $novos, 'contatado' => $contatado, 'proposta' => $proposta, 'vendido' => $vendid, 'perdido' => $perdido] as $status => $quantidade)
                    @php $largura = round($quantidade / $funilLider * 100); @endphp
                    <div class="funnel-row">
                        <div class="funnel-label">{{ \App\Models\Lead::label($status) }}</div>
                        <a href="{{ route('admin.leads.index', ['status' => $status]) }}" class="text-decoration-none">
                            <div class="funnel-bar fb-{{ $status }}" style="width:{{ max($largura, 8) }}%">{{ $quantidade }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.card title="Leads nos últimos 30 dias" icon="fa-chart-line">
            <canvas id="chartLeads" height="150" role="img" aria-label="Gráfico de leads por dia nos últimos 30 dias"></canvas>
        </x-admin.card>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.table :headers="['Campanha', 'Canal', 'center:Leads', 'center:Vendidos', 'center:Conversão']" empty="Nenhuma campanha com leads ainda.">
            @foreach($campanhas as $c)
                <tr>
                    <td>
                        <a href="{{ route('admin.leads.index', ['campanha' => $c->id]) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a>
                    </td>
                    <td><span class="badge bg-body-secondary text-body">{{ $c->canalLabel() }}</span></td>
                    <td class="text-center">{{ $c->leads_count }}</td>
                    <td class="text-center">{{ $c->leads_vendidos }}</td>
                    <td class="text-center"><span class="badge-status {{ $c->taxo_conversao > 0 ? 'st-vendido' : 'st-novo' }}">{{ $c->taxo_conversao }}%</span></td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Leads recentes" icon="fa-clock">
            @forelse($recentes as $l)
                <a href="{{ route('admin.leads.show', $l) }}" class="d-flex justify-content-between align-items-center gap-2 text-decoration-none py-2 border-bottom">
                    <span class="overflow-hidden">
                        <span class="fw-semibold">{{ $l->name }}</span>
                        <span class="d-block small text-secondary">{{ $l->campaign?->name ?? 'Sem campanha' }} · {{ $l->created_at->diffForHumans() }}</span>
                    </span>
                    <span class="badge-status st-{{ $l->status }}">{{ \App\Models\Lead::label($l->status) }}</span>
                </a>
            @empty
                <p class="small text-secondary mb-0">Nenhum lead recebido ainda.</p>
            @endforelse
        </x-admin.card>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartLeads'), {
    type: 'line',
    data: {
        labels: @js($labels),
        datasets: [{ label: 'Leads', data: @js($serie), borderColor: '#c9862a', backgroundColor: 'rgba(224,163,62,.12)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } }
    }
});
</script>
@endpush
