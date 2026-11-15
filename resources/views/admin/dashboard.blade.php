@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Visão geral')
@section('page_description', 'Indicadores comerciais, pendências da equipe, pedidos da loja e desempenho das campanhas.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm">
    <i class="fas fa-file-csv me-1" aria-hidden="true"></i> Exportar CSV
</a>
<a href="{{ route('admin.pedidos.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-truck-fast me-1" aria-hidden="true"></i> Pedidos
</a>
<a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-globe me-1" aria-hidden="true"></i> Ver o site
</a>
@endsection

@section('content')
@php
    $admin = auth()->user()?->can('admin') ?? false;
    $funil = ['novo' => $novos, 'contatado' => $contatado, 'proposta' => $proposta, 'vendido' => $vendido, 'perdido' => $perdido];
    $tonsFunil = ['novo' => 'gold', 'contatado' => 'amber', 'proposta' => 'violet', 'vendido' => 'green', 'perdido' => 'red'];
    $funilLider = max($total, 1);

    $manutencaoAtiva = (bool) ($modulos['manutencao_ativa'] ?? false);
    $lojaAtiva = (bool) ($modulos['loja_ativa'] ?? true);
    $wafAtivo = (bool) ($modulos['waf_ativo'] ?? true);
    $pedidosTotal = (int) ($modulos['pedidos'] ?? 0);
    $pedidosAbertos = (int) ($modulos['pedidos_abertos'] ?? 0);
    $pedidosEnviados = (int) ($modulos['pedidos_enviados'] ?? 0);
    $chamadosAbertos = (int) ($modulos['chamados_abertos'] ?? 0);
    $conversasAguardando = (int) ($modulos['conversas_aguardando'] ?? 0);
    $estoqueBaixo = (int) ($modulos['estoque_baixo'] ?? 0);
    $pendenciasTotal = $novos + $pedidosAbertos + $chamadosAbertos + $conversasAguardando + ($admin ? $estoqueBaixo : 0);

    $tomPedido = static function (?string $status): string {
        $tom = tom_por_status('pedido', $status);

        return $tom === 'blue' ? 'teal' : $tom;
    };
@endphp

@if($admin)
    {{-- Faixa operacional instantânea: disponibilidade do site, loja e blindagem WAF. --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="small fw-bold text-secondary text-uppercase">
                <i class="fas fa-signal me-1 text-tone-gold" aria-hidden="true"></i> Estado operacional
            </span>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.manutencao.index') }}"
                    class="text-decoration-none d-inline-flex align-items-center gap-2 px-2 py-1 rounded {{ $manutencaoAtiva ? 'tone-red' : 'tone-green' }}">
                    <i class="fas {{ $manutencaoAtiva ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ $manutencaoAtiva ? 'Manutenção Ativa (503)' : 'Site Online' }}</span>
                </a>
                <a href="{{ route('admin.settings.index') }}#loja"
                    class="text-decoration-none d-inline-flex align-items-center gap-2 px-2 py-1 rounded {{ $lojaAtiva ? 'tone-teal' : 'tone-neutral' }}">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ $lojaAtiva ? 'Loja Virtual Ativa' : 'Loja Desativada' }}</span>
                </a>
                <a href="{{ route('admin.seguranca-militar.index') }}"
                    class="text-decoration-none d-inline-flex align-items-center gap-2 px-2 py-1 rounded {{ $wafAtivo ? 'tone-gold' : 'tone-red' }}">
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ $wafAtivo ? 'Escudo Militar WAF Ativo' : 'WAF Inativo' }}</span>
                </a>
            </div>
        </div>
    </div>
@endif

{{-- Indicadores principais do período comercial. --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Leads totais" :value="$total" hint="Base do funil comercial" icon="fa-users" tone="gold"
            :href="route('admin.leads.index')" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Leads novos" :value="$novos" hint="Aguardando contato" icon="fa-envelope-circle-check" tone="amber"
            :href="route('admin.leads.index', ['status' => 'novo'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Taxa de conversão" :value="$conversao.'%'" hint="Vendas sobre o total" icon="fa-bullseye" tone="teal"
            :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Receita fechada" :value="brl($receita)" hint="Leads vendidos" icon="fa-sack-dollar" tone="green" pequeno
            :href="route('admin.leads.index', ['status' => 'vendido'])" />
    </div>
</div>

{{-- Curva diária + fila prioritária de ação imediata. --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <x-admin.card title="Leads nos últimos 30 dias" icon="fa-chart-line" ajuda="dashboard_grafico_leads"
            descricao="Contatos recebidos por dia, a partir da data de entrada do lead." class="h-100">
            <x-slot:actions>
                <x-admin.status :rotulo="array_sum($serie).' no período'" tom="gold" />
            </x-slot:actions>
            <div class="chart-box"><canvas id="chartLeads" role="img" aria-label="Gráfico de leads por dia nos últimos 30 dias"></canvas></div>
        </x-admin.card>
    </div>

    <div class="col-12 col-xl-4">
        <x-admin.card title="Para fazer agora" icon="fa-list-check" ajuda="dashboard_para_fazer"
            descricao="Pendências de atendimento, suporte e expedição." class="h-100">
            <x-slot:actions>
                <x-admin.status :rotulo="$pendenciasTotal > 0 ? $pendenciasTotal.' em aberto' : 'tudo em dia'" :tom="$pendenciasTotal > 0 ? 'amber' : 'green'" />
            </x-slot:actions>
            <div class="task-list">
                <a href="{{ route('admin.leads.index', ['status' => 'novo']) }}" class="task">
                    <span class="icon tone-gold"><i class="fas fa-envelope-circle-check" aria-hidden="true"></i></span>
                    <span class="caption">Leads novos aguardando contato<small>Fila de entrada comercial</small></span>
                    <span class="count text-tone-gold">{{ $novos }}</span>
                </a>
                <a href="{{ route('admin.pedidos.index') }}" class="task">
                    <span class="icon tone-amber"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                    <span class="caption">Pedidos em aberto<small>Loja: aguardando pagamento ou envio</small></span>
                    <span class="count text-tone-amber">{{ $pedidosAbertos }}</span>
                </a>
                <a href="{{ route('admin.chamados.index') }}" class="task">
                    <span class="icon tone-teal"><i class="fas fa-headset" aria-hidden="true"></i></span>
                    <span class="caption">Chamados abertos<small>Solicitações no portal do cliente</small></span>
                    <span class="count text-tone-teal">{{ $chamadosAbertos }}</span>
                </a>
                <a href="{{ route('admin.assistente.conversas.index') }}" class="task">
                    <span class="icon tone-violet"><i class="fas fa-comments" aria-hidden="true"></i></span>
                    <span class="caption">Conversas aguardando equipe<small>Transferidas pelo assistente do site</small></span>
                    <span class="count text-tone-violet">{{ $conversasAguardando }}</span>
                </a>
                @if($admin)
                    <a href="{{ route('admin.catalogo.produtos.index', ['estoque' => 'baixo']) }}" class="task">
                        <span class="icon {{ $estoqueBaixo > 0 ? 'tone-red' : 'tone-neutral' }}"><i class="fas fa-boxes-stacked" aria-hidden="true"></i></span>
                        <span class="caption">Estoque abaixo do mínimo<small>Repor antes de vender</small></span>
                        <span class="count {{ $estoqueBaixo > 0 ? 'text-tone-red' : '' }}">{{ $estoqueBaixo }}</span>
                    </a>
                @else
                    <div class="task">
                        <span class="icon {{ $estoqueBaixo > 0 ? 'tone-red' : 'tone-neutral' }}"><i class="fas fa-boxes-stacked" aria-hidden="true"></i></span>
                        <span class="caption">Estoque abaixo do mínimo<small>Alertas de reposição do catálogo</small></span>
                        <span class="count {{ $estoqueBaixo > 0 ? 'text-tone-red' : '' }}">{{ $estoqueBaixo }}</span>
                    </div>
                @endif
            </div>
        </x-admin.card>
    </div>
</div>

{{-- Visão operacional segmentada em abas na paleta Golden. --}}
<div class="abas" data-abas="dashboard">
    <div class="abas-barra">
        <ul class="nav abas-nav" role="tablist" aria-label="Painéis do dashboard">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" role="tab" id="aba-comercial-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-comercial" data-aba="comercial"
                    aria-controls="aba-comercial" aria-selected="true">
                    <i class="fas fa-filter" aria-hidden="true"></i><span>Comercial &amp; Funil</span>
                    <span class="abas-contagem">{{ $recentes->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-pedidos-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-pedidos" data-aba="pedidos"
                    aria-controls="aba-pedidos" aria-selected="false">
                    <i class="fas fa-truck-fast" aria-hidden="true"></i><span>Pedidos &amp; Despachos</span>
                    <span class="abas-contagem">{{ $pedidosRecentes->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-campanhas-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-campanhas" data-aba="campanhas"
                    aria-controls="aba-campanhas" aria-selected="false">
                    <i class="fas fa-bullhorn" aria-hidden="true"></i><span>Campanhas &amp; Módulos</span>
                    <span class="abas-contagem">{{ $campanhas->count() }}</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- Aba 1: Comercial & Funil --}}
        <div class="tab-pane fade show active" id="aba-comercial" data-aba-painel="comercial" role="tabpanel" aria-labelledby="aba-comercial-botao" tabindex="0">
            <div class="row g-3">
                <div class="col-12 col-xl-5">
                    <x-admin.card title="Funil de conversão" icon="fa-filter" ajuda="dashboard_funil"
                        descricao="Cada barra abre a fila do status." class="h-100">
                        <div class="funnel mt-2">
                            @foreach($funil as $status => $quantidade)
                                <div class="funnel-row">
                                    <div class="funnel-label">{{ \App\Models\Lead::label($status) }}</div>
                                    <a href="{{ route('admin.leads.index', ['status' => $status]) }}" class="flex-grow-1 text-decoration-none">
                                        <div class="funnel-bar fb-{{ $tonsFunil[$status] ?? 'neutral' }}"
                                            style="--w:{{ round(max($quantidade / $funilLider, .08) * 100) }}%">{{ $quantidade }}</div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>

                <div class="col-12 col-xl-7">
                    <x-admin.table :headers="['Lead', 'Contato', 'Campanha', 'center:Situação', 'Recebido']"
                        empty="Nenhum lead recebido ainda." title="Leads recentes" icon="fa-clock"
                        ajuda="dashboard_leads_recentes" descricao="Os oito últimos contatos recebidos." class="h-100">
                        <x-slot:actions>
                            <a href="{{ route('admin.leads.index') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
                        </x-slot:actions>
                        @foreach($recentes as $l)
                            <tr>
                                <td><a href="{{ route('admin.leads.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->name }}</a></td>
                                <td class="small text-secondary">{{ $l->phone ?: $l->email }}</td>
                                <td class="small text-secondary">{{ $l->campaign?->name ?? 'Sem campanha' }}</td>
                                <td class="text-center">
                                    <x-admin.status :rotulo="\App\Models\Lead::label($l->status)" :tom="$tonsFunil[$l->status] ?? 'neutral'" />
                                </td>
                                <td class="small text-secondary">{{ $l->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </x-admin.table>
                </div>
            </div>
        </div>

        {{-- Aba 2: Pedidos & Despachos --}}
        <div class="tab-pane fade" id="aba-pedidos" data-aba-painel="pedidos" role="tabpanel" aria-labelledby="aba-pedidos-botao" tabindex="0">
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <x-admin.stat label="Total de pedidos" :value="$pedidosTotal" icon="fa-receipt" tone="gold" pequeno
                        :href="route('admin.pedidos.index')" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-admin.stat label="Em aberto" :value="$pedidosAbertos" icon="fa-hourglass-half" tone="amber" pequeno
                        :href="route('admin.pedidos.index')" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-admin.stat label="Enviados / trânsito" :value="$pedidosEnviados" icon="fa-truck-fast" tone="teal" pequeno
                        :href="route('admin.pedidos.index', ['status' => 'enviado'])" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-admin.stat label="Faturamento da loja" :value="brl($modulos['faturamento_loja'] ?? 0)" icon="fa-sack-dollar" tone="green" pequeno
                        :href="route('admin.pedidos.index')" />
                </div>
            </div>

            <x-admin.table :headers="['Pedido', 'Cliente', 'end:Valor', 'Rastreio / Envio', 'center:Status', 'end:Data']"
                empty="Nenhum pedido registrado na loja ainda." title="Pedidos e despachos recentes" icon="fa-truck-fast"
                ajuda="dashboard_pedidos_recentes" descricao="Os oito últimos pedidos da loja virtual e código de rastreamento.">
                <x-slot:actions>
                    <a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-secondary">Todos os pedidos</a>
                </x-slot:actions>
                @foreach($pedidosRecentes as $o)
                    <tr>
                        <td><a href="{{ route('admin.pedidos.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->numero }}</a></td>
                        <td class="small">{{ $o->nome }}<div class="text-secondary">{{ $o->telefone }}</div></td>
                        <td class="text-end small fw-semibold">{{ brl($o->total_cents) }}</td>
                        <td class="small">
                            @if($o->codigo_rastreio)
                                <a href="{{ route('site.rastreio.show', $o->codigo_rastreio) }}" target="_blank" rel="noopener" class="text-decoration-none">
                                    <x-admin.status :rotulo="$o->codigo_rastreio" tom="teal" />
                                </a>
                                <div class="text-secondary mt-1">{{ \App\Models\Order::TRANSPORTADORAS[$o->transportadora] ?? ($o->transportadora ?: 'Transportadora') }}</div>
                            @else
                                <span class="text-secondary">Aguardando despacho</span>
                            @endif
                        </td>
                        <td class="text-center"><x-admin.status :rotulo="$o->statusLabel()" :tom="$tomPedido($o->status)" /></td>
                        <td class="text-end small text-secondary">{{ $o->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </div>

        {{-- Aba 3: Campanhas & Módulos --}}
        <div class="tab-pane fade" id="aba-campanhas" data-aba-painel="campanhas" role="tabpanel" aria-labelledby="aba-campanhas-botao" tabindex="0">
            <div class="row g-3">
                <div class="col-12 col-xl-7">
                    <x-admin.table :headers="['Campanha', 'Canal', 'center:Leads', 'center:Vendidos', 'center:Conversão']"
                        empty="Nenhuma campanha com leads ainda." title="Campanhas" icon="fa-bullhorn"
                        ajuda="dashboard_campanhas" descricao="As oito que mais trouxeram leads." class="h-100">
                        <x-slot:actions>
                            <a href="{{ route('admin.campanhas.index') }}" class="btn btn-sm btn-outline-secondary">Gerenciar</a>
                        </x-slot:actions>
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

                <div class="col-12 col-xl-5">
                    <x-admin.card title="Módulos integrados" icon="fa-cubes" ajuda="dashboard_modulos"
                        descricao="Resumo de CRM, catálogo, suporte, atendimento e conteúdo." class="h-100">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <x-admin.stat label="Clientes (CRM)" :value="$modulos['clientes'] ?? 0"
                                    :hint="($modulos['clientes_ativos'] ?? 0).' ativos'" icon="fa-user-check" tone="gold" pequeno
                                    :href="route('admin.clientes.index')" />
                            </div>
                            <div class="col-6">
                                <x-admin.stat label="Chamados abertos" :value="$chamadosAbertos"
                                    hint="Portal do cliente" icon="fa-headset" :tone="$chamadosAbertos > 0 ? 'amber' : 'green'" pequeno
                                    :href="route('admin.chamados.index')" />
                            </div>
                            <div class="col-6">
                                <x-admin.stat label="Conversas IA" :value="$conversasAguardando"
                                    hint="Aguardando equipe" icon="fa-comments" :tone="$conversasAguardando > 0 ? 'violet' : 'neutral'" pequeno
                                    :href="route('admin.assistente.conversas.index')" />
                            </div>
                            <div class="col-6">
                                <x-admin.stat label="Pedidos ativos" :value="$pedidosAbertos"
                                    :hint="$pedidosEnviados.' em trânsito'" icon="fa-truck-fast" tone="teal" pequeno
                                    :href="route('admin.pedidos.index')" />
                            </div>
                            @if($admin)
                                <div class="col-6">
                                    <x-admin.stat label="Produtos ativos" :value="$modulos['produtos'] ?? 0"
                                        :hint="$estoqueBaixo > 0 ? $estoqueBaixo.' em falta' : 'Estoque em dia'" icon="fa-boxes-stacked"
                                        :tone="$estoqueBaixo > 0 ? 'red' : 'teal'" pequeno
                                        :href="route('admin.catalogo.produtos.index', $estoqueBaixo > 0 ? ['estoque' => 'baixo'] : [])" />
                                </div>
                                <div class="col-6">
                                    <x-admin.stat label="Páginas no ar" :value="$modulos['paginas'] ?? 0"
                                        :hint="($modulos['depoimentos'] ?? 0).' depoimentos'" icon="fa-file-lines" tone="green" pequeno
                                        :href="route('admin.conteudo.paginas.index')" />
                                </div>
                            @endif
                        </div>

                        @if($admin)
                            <div class="callout {{ $lojaAtiva ? 'ok' : 'warn' }}">
                                <span>
                                    <i class="fas fa-store me-1" aria-hidden="true"></i>
                                    Loja virtual <strong>{{ $lojaAtiva ? 'ativa' : 'desativada' }}</strong>
                                    @if($pedidosAbertos > 0) · {{ $pedidosAbertos }} pedido(s) aguardando.@endif
                                </span>
                                <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-golden">Gerenciar loja</a>
                            </div>
                        @endif
                    </x-admin.card>
                </div>
            </div>
        </div>
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
