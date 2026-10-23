@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Visão geral')
@section('page_description', 'Leads captados, posição no funil e o estado dos módulos integrados do painel.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm"><i class="fas fa-file-csv me-1" aria-hidden="true"></i> Exportar CSV</a>
<a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1" aria-hidden="true"></i> Abrir a fila de leads</a>
@endsection

@section('content')
@php
    $admin = auth()->user()->can('admin');
    $funil = ['novo' => $novos, 'contatado' => $contatado, 'proposta' => $proposta, 'vendido' => $vendido, 'perdido' => $perdido];
    $funilLider = max($total, 1);
@endphp

{{-- Leads: os quatro números que definem o período. --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Leads totais" :value="$total" icon="fa-users" tone="teal" :href="route('admin.leads.index')" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Leads novos" :value="$novos" hint="aguardando contato" icon="fa-envelope-circle-check" tone="gold" :href="route('admin.leads.index', ['status' => 'novo'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Taxa de conversão" :value="$conversao.'%'" icon="fa-trophy" tone="violet" :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Receita fechada" :value="brl($receita)" icon="fa-sack-dollar" tone="blue" pequeno :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
</div>

@if($admin)
    {{-- Módulos integrados: CRM, ERP, CMS e loja em uma linha. --}}
    <div class="row g-3 mb-4">
        <div class="col-12"><h6 class="zone-title"><i class="fas fa-cubes text-tone-gold" aria-hidden="true"></i> Módulos integrados</h6></div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Clientes (CRM)" :value="$modulos['clientes']" :hint="$modulos['clientes_ativos'].' ativos na base'" icon="fa-user-check" tone="teal" pequeno :href="route('admin.clientes.index')" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Produtos ativos" :value="$modulos['produtos']" :hint="$modulos['estoque_baixo'] > 0 ? $modulos['estoque_baixo'].' com estoque baixo' : 'estoque em dia'" icon="fa-boxes-stacked" tone="gold" pequeno :href="route('admin.catalogo.produtos.index', $modulos['estoque_baixo'] > 0 ? ['estoque' => 'baixo'] : [])" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Vendas na loja" :value="brl($modulos['faturamento_loja'])" :hint="$modulos['pedidos'].' pedido(s) registrado(s)'" icon="fa-receipt" tone="violet" pequeno :href="route('admin.pedidos.index')" />
        </div>
        <div class="col-6 col-lg-3">
            <x-admin.stat label="Páginas publicadas" :value="$modulos['paginas']" :hint="$modulos['depoimentos'].' depoimentos aprovados'" icon="fa-file-lines" tone="blue" pequeno :href="route('admin.conteudo.paginas.index')" />
        </div>
        <div class="col-12">
            <div class="callout {{ $modulos['loja_ativa'] ? 'ok' : 'warn' }}">
                <span>
                    <i class="fas fa-store me-1" aria-hidden="true"></i> Loja virtual <strong>{{ $modulos['loja_ativa'] ? 'ativa' : 'desativada' }}</strong> — vendas diretas de rastreadores, chips e acessórios.
                    @if($modulos['pedidos_abertos'] > 0) {{ $modulos['pedidos_abertos'] }} pedido(s) aguardando.@endif
                </span>
                <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-golden">Gerenciar loja</a>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <x-admin.card title="Leads nos últimos 30 dias" icon="fa-chart-line" descricao="Contatos recebidos por dia, a partir da data de entrada do lead.">
            <div class="chart-box"><canvas id="chartLeads" role="img" aria-label="Gráfico de leads por dia nos últimos 30 dias"></canvas></div>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="Para fazer agora" icon="fa-list-check">
            <div class="task-list">
                <a href="{{ route('admin.leads.index', ['status' => 'novo']) }}" class="task">
                    <span class="icon tone-gold"><i class="fas fa-envelope-circle-check" aria-hidden="true"></i></span>
                    <span class="caption">Leads aguardando o primeiro contato<small>Fila de novos da semana</small></span>
                    <span class="count text-tone-gold">{{ $novos }}</span>
                </a>
                <a href="{{ route('admin.pedidos.index') }}" class="task">
                    <span class="icon tone-blue"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                    <span class="caption">Pedidos em aberto<small>Loja: aguardando pagamento ou envio</small></span>
                    <span class="count text-tone-blue">{{ $modulos['pedidos_abertos'] }}</span>
                </a>
                @if($admin)
                <a href="{{ route('admin.catalogo.produtos.index', ['estoque' => 'baixo']) }}" class="task">
                    <span class="icon tone-red"><i class="fas fa-boxes-stacked" aria-hidden="true"></i></span>
                    <span class="caption">Estoque abaixo do mínimo<small>Repor antes de vender</small></span>
                    <span class="count">{{ $modulos['estoque_baixo'] }}</span>
                </a>
                @endif
            </div>
        </x-admin.card>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Funil de conversão" icon="fa-filter" descricao="Cada barra abre a fila do status.">
            <div class="funnel mt-2">
                @foreach($funil as $status => $quantidade)
                    <div class="funnel-row">
                        <div class="funnel-label">{{ \App\Models\Lead::label($status) }}</div>
                        <a href="{{ route('admin.leads.index', ['status' => $status]) }}" class="flex-grow-1 text-decoration-none">
                            <div class="funnel-bar fb-{{ tom_por_status('funil', $status) }}" style="--w:{{ round(max($quantidade / $funilLider, .08) * 100) }}%">{{ $quantidade }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.table :headers="['Campanha', 'Canal', 'center:Leads', 'center:Vendidos', 'center:Conversão']" empty="Nenhuma campanha com leads ainda." title="Campanhas" icon="fa-bullhorn" descricao="As oito que mais trouxeram leads.">
            <x-slot:actions><a href="{{ route('admin.campanhas.index') }}" class="btn btn-sm btn-outline-secondary">Gerenciar</a></x-slot:actions>
            @foreach($campanhas as $c)
                <tr>
                    <td><a href="{{ route('admin.leads.index', ['campanha' => $c->id]) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a></td>
                    <td><x-admin.status :rotulo="$c->canalLabel()" tom="neutral" /></td>
                    <td class="text-center">{{ $c->leads_count }}</td>
                    <td class="text-center">{{ $c->leads_vendidos }}</td>
                    <td class="text-center"><x-admin.status :rotulo="$c->conversao().'%'" :tom="tom_por_flag($c->conversao() > 0)" /></td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-12">
        <x-admin.table :headers="['Lead', 'Contato', 'Campanha', 'center:Situação', 'Recebido']" empty="Nenhum lead recebido ainda." title="Leads recentes" icon="fa-clock" descricao="Os oito últimos contatos recebidos.">
            <x-slot:actions><a href="{{ route('admin.leads.index') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a></x-slot:actions>
            @foreach($recentes as $l)
                <tr>
                    <td><a href="{{ route('admin.leads.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->name }}</a></td>
                    <td class="small text-secondary">{{ $l->phone ?: $l->email }}</td>
                    <td class="small text-secondary">{{ $l->campaign?->name ?? 'Sem campanha' }}</td>
                    <td class="text-center"><x-admin.status :rotulo="\App\Models\Lead::label($l->status)" :tom="tom_por_status('funil', $l->status)" /></td>
                    <td class="small text-secondary">{{ $l->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
$(function () {
    var raiz = getComputedStyle(document.documentElement);
    var tom = function (nome) { return raiz.getPropertyValue(nome).trim(); };

    var grafico = new Chart(document.getElementById('chartLeads'), {
        type: 'line',
        data: {
            labels: @js($labels),
            datasets: [{ label: 'Leads', data: @js($serie), fill: true, tension: .35, pointRadius: 0, borderWidth: 2 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }
            }
        }
    });

    // As cores do gráfico vêm dos tokens do tema, então claro e escuro ficam coerentes.
    function pintar() {
        var dados = grafico.data.datasets[0];
        dados.borderColor = tom('--chart-line');
        dados.backgroundColor = tom('--chart-area');
        dados.pointBackgroundColor = tom('--chart-line');
        ['x', 'y'].forEach(function (eixo) {
            grafico.options.scales[eixo].ticks.color = tom('--chart-tick');
            grafico.options.scales[eixo].borderColor = tom('--chart-grid');
            grafico.options.scales[eixo].grid.color = tom('--chart-grid');
        });
        grafico.update();
    }
    pintar();
    $(document).on('golden:theme', pintar);
});
</script>
@endpush
