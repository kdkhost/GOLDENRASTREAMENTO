@extends('admin.layouts.app')
@php
    $liderCanal = max((int) ($porCanal->max('visitas') ?? 0), 1);
    $liderRede = max((int) ($porRede->max('visitas') ?? 0), 1);
    $liderDispositivo = max((int) ($dispositivos->max('visitas') ?? 0), 1);
    $pago = (int) $porCanal->sum('pagas');
@endphp
@section('title', 'Contador da campanha')
@section('page_title', $pagina->nome)
@section('page_description', 'Visitas, cliques e cadastros de '.$pagina->url().' — o que o link produziu.')

@section('page_actions')
    <form method="POST" action="{{ route('admin.funil.toggle', $pagina) }}" class="d-inline"
        data-confirmar="{{ $pagina->status === 'publicada' ? 'Tirar esta campanha do ar? Quem tem o link vê 404 até ela voltar.' : 'Colocar esta campanha no ar agora?' }}">
        @csrf
        <button class="btn btn-sm {{ $pagina->status === 'publicada' ? 'btn-outline-secondary' : 'btn-golden' }}">
            <i class="fas fa-{{ $pagina->status === 'publicada' ? 'pause' : 'play' }} me-1" aria-hidden="true"></i>
            {{ $pagina->status === 'publicada' ? 'Tirar do ar' : 'Colocar no ar' }}
        </button>
    </form>
    @if($pagina->status === 'publicada')
        <a href="{{ $pagina->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i> Abrir a campanha
        </a>
    @endif
    <a href="{{ route('admin.funil.edit', $pagina) }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-pen me-1" aria-hidden="true"></i> Editar a página
    </a>
    <a href="{{ route('admin.funil.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar
    </a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Visitas no período" :value="number_format($kpis['visitas'], 0, ',', '.')" icon="fa-person-walking" tone="blue" pequeno
            :hint="$kpis['unicos'].' pessoa(s) única(s) · '.$kpis['desde_sempre'].' desde a primeira visita'" />
    </div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Telas vistas" :value="number_format($kpis['views'], 0, ',', '.')" icon="fa-eye" tone="teal" pequeno hint="Rolagens e trocas de tela medidas." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Cliques medidos" :value="number_format($kpis['cliques'], 0, ',', '.')" icon="fa-hand-pointer" tone="violet" pequeno hint="Botões, telefone e WhatsApp." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Cadastros" :value="number_format($kpis['leads'], 0, ',', '.')" icon="fa-user-plus" tone="gold" pequeno :href="route('admin.leads.index', ['pagina' => $pagina->id])" /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Visita → cadastro" :value="$kpis['taxa'].'%'" icon="fa-percent" :tone="tom_por_flag($kpis['taxa'] > 0)" pequeno hint="Mesmo período, mesma régua." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Vendas fechadas" :value="number_format($kpis['vendidos'], 0, ',', '.')" icon="fa-trophy" :tone="tom_por_flag($kpis['vendidos'] > 0)" pequeno hint="Leads desta página marcados como vendidos." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Tráfego pago" :value="number_format($pago, 0, ',', '.')" icon="fa-bullhorn" tone="red" pequeno hint="Visitas que chegaram com clique de anúncio." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Robôs à parte" :value="number_format($kpis['robos'], 0, ',', '.')" icon="fa-robot" tone="neutral" pequeno hint="Fora de todos os números acima." /></div>
</div>

<x-admin.filters :reset="route('admin.funil.show', $pagina)">
    <x-admin.filter name="inicio" type="date" label="De" col="col-6 col-md-3" :value="$periodo[0]" />
    <x-admin.filter name="fim" type="date" label="Até" col="col-6 col-md-3" :value="$periodo[1]" />
    <x-slot:extra>
        <span class="form-hint align-self-center">
            <i class="fas fa-link me-1" aria-hidden="true"></i>
            <code class="text-break">{{ url($pagina->caminho()) }}</code>
        </span>
    </x-slot:extra>
</x-admin.filters>

<div class="row g-3">
    <div class="col-xl-8">
        <x-admin.card title="Dia a dia da campanha" icon="fa-chart-line"
            descricao="Visitas e cadastros do mesmo dia — o intervalo entre as duas linhas é o tempo que a página leva para convencer.">
            <div class="chart-box"><canvas id="chartFunil" role="img" aria-label="Visitas e cadastros por dia nesta página de campanha"></canvas></div>
        </x-admin.card>
    </div>
    <div class="col-xl-4">
        <x-admin.card title="O que a página tem" icon="fa-layer-group" :descricao="$blocos->count().' '.($blocos->count() === 1 ? 'bloco publicado' : 'blocos publicados').' nesta ordem.'">
            <ul class="list-unstyled small mb-0">
                @forelse($blocos as $bloco)
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="fas {{ \App\Models\FunnelBlock::iconeTipo($bloco->tipo) }} text-gold flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <span>
                            <span class="fw-semibold d-block">{{ \App\Models\FunnelBlock::rotuloTipo($bloco->tipo) }}</span>
                            <span class="text-secondary">{{ $bloco->titulo ?: \App\Models\FunnelBlock::descricaoTipo($bloco->tipo) }}</span>
                            @unless($bloco->ativa)<x-admin.status rotulo="oculto" tom="neutral" class="ms-1" />@endunless
                        </span>
                    </li>
                @empty
                    <li class="text-secondary">Nenhum bloco: a página ainda não tem o que mostrar.</li>
                @endforelse
            </ul>
        </x-admin.card>
    </div>
</div>

<div class="mt-3">
    <x-admin.tabela-origem :linhas="$porCanal" :lider="$liderCanal"
        titulo="De onde veio o tráfego desta página" rotulo="Canal" icone="fa-diagram-project"
        descricao="Anúncio, rede social, busca, IA, indicação, e-mail, parceiro ou direto — a régua do painel de atribuição, limitada a esta campanha." />
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <x-admin.tabela-origem :linhas="$porRede" :lider="$liderRede"
            titulo="Redes e sites que trouxeram gente" rotulo="Rede / site" icone="fa-hashtag"
            descricao="Cada origem dentro dos canais que chegaram a esta página." />
    </div>
    <div class="col-lg-5">
        <x-admin.tabela-origem :linhas="$dispositivos" :lider="$liderDispositivo"
            titulo="Como abriram" rotulo="Dispositivo" icone="fa-mobile-screen"
            descricao="O aparelho decide a leitura da página — se o cadastro cai no desktop, o problema não é o tráfego." />
    </div>
</div>

<x-admin.table class="mt-3" :headers="['Chamada medida', 'O que a pessoa clicou', 'end:Cliques']"
    title="Clique por clique" icon="fa-hand-pointer"
    descricao="O rótulo é o que o botão declara no site: é assim que se descobre qual chamada da campanha funciona."
    empty="Nenhum clique medido no período.">
    @foreach($porClique as $linha)
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas {{ $linha->icone }} text-gold" aria-hidden="true"></i>
                    <span class="fw-semibold">{{ $linha->rotulo }}</span>
                </div>
            </td>
            <td class="small text-secondary">{{ $linha->grupo }}</td>
            <td class="text-end fw-semibold">{{ number_format($linha->cliques, 0, ',', '.') }}</td>
        </tr>
    @endforeach
</x-admin.table>

<x-admin.table class="mt-3"
    :headers="['Última atividade', 'Origem da visita', 'center:Views', 'center:Cliques', 'center:Cadastros', 'end:Jornada']"
    title="Visita por visita" icon="fa-list-check"
    descricao="Cada linha abre a trilha completa do visitante: o que ele viu, onde clicou e se cadastrou."
    empty="Nenhuma visita no período. Cole o link na campanha e volte aqui."
    :paginator="$visitas">
    @foreach($visitas as $v)
        <tr>
            <td class="small text-nowrap">
                {{ $v->ultima_em?->format('d/m/Y H:i') }}
                <div class="text-secondary">{{ $v->landing_path ?: '/' }}</div>
            </td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas {{ $v->iconeCanal() }} text-gold" aria-hidden="true"></i>
                    <x-admin.status :rotulo="$v->canalRotulo()" :tom="tom_por_status('canal', $v->canal)" />
                    <span class="small text-secondary">{{ $v->redeRotulo() }}</span>
                </div>
                <div class="small text-secondary mt-1">
                    {{ $v->motivo }}
                    @if($v->utm_campaign)<code>{{ $v->utm_campaign }}</code>@endif
                    @if($v->clique_id)<code>{{ $v->clique_id }}</code>@endif
                </div>
            </td>
            <td class="text-center text-secondary">{{ $v->visualizacoes }}</td>
            <td class="text-center text-secondary">{{ $v->eventos }}</td>
            <td class="text-center">{{ $v->leads_count ?: '—' }}</td>
            <td class="text-end">
                <a href="{{ route('admin.atribuicao.show', $v) }}" class="btn btn-sm btn-outline-secondary" title="Ver a jornada desta visita">
                    <i class="fas fa-route" aria-hidden="true"></i>
                </a>
            </td>
        </tr>
    @endforeach
</x-admin.table>

<x-admin.table class="mt-3" :headers="['Cadastrado', 'Contato', 'Kit / Frota', 'Origem', 'Status', 'end:Ações']"
    title="Cadastros desta campanha" icon="fa-user-plus"
    descricao="Os dez mais recentes. A ficha do lead guarda a jornada inteira e o clique do anúncio que o trouxe."
    empty="Ninguém se cadastrou ainda.">
    @foreach($leads as $l)
        <tr>
            <td>
                <a href="{{ route('admin.leads.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->name }}</a>
                <div class="small text-secondary">{{ $l->created_at->format('d/m/Y H:i') }}</div>
            </td>
            <td class="small">{{ $l->phone }}@if($l->email)<div class="text-secondary">{{ $l->email }}</div>@endif</td>
            <td class="small">{{ $l->kit ?: '—' }}<div class="text-secondary">{{ $l->fleet_size ? $l->fleet_size.' veículo(s)' : '' }}</div></td>
            <td class="small">
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <x-admin.status :rotulo="$l->canalRotulo()" :tom="$l->tomCanal()" />
                    @if($l->clique_id)<code>{{ $l->clique_id }}</code>@endif
                </div>
            </td>
            <td><x-admin.status :rotulo="\App\Models\Lead::label($l->status)" :tom="tom_por_status('funil', $l->status)" /></td>
            <td class="text-end">
                <a href="{{ route('admin.leads.show', $l) }}" class="btn btn-sm btn-outline-secondary" title="Abrir a ficha"><i class="fas fa-eye" aria-hidden="true"></i></a>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
$(function () {
    var raiz = getComputedStyle(document.documentElement);
    var tom = function (nome) { return raiz.getPropertyValue(nome).trim(); };

    var grafico = new Chart(document.getElementById('chartFunil'), {
        type: 'bar',
        data: {
            labels: @js(collect($porDia)->map(fn ($d) => \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m'))->all()),
            datasets: [
                { label: 'Visitas', data: @js(collect($porDia)->pluck('visitas')->all()), order: 2, tension: .35, type: 'line', fill: true, pointRadius: 0, borderWidth: 2 },
                { label: 'Cadastros', data: @js(collect($porDia)->pluck('leads')->all()), order: 1, borderRadius: 4, maxBarThickness: 22 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, align: 'end' } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
            }
        }
    });

    // As cores vêm dos tokens do tema: claro e escuro ficam coerentes, sem hex aqui.
    function pintar() {
        var visitas = grafico.data.datasets[0], cadastros = grafico.data.datasets[1];
        visitas.borderColor = tom('--chart-line');
        visitas.backgroundColor = tom('--chart-area');
        visitas.pointBackgroundColor = tom('--chart-line');
        cadastros.backgroundColor = tom('--t-teal-fg');
        ['x', 'y'].forEach(function (eixo) {
            grafico.options.scales[eixo].ticks.color = tom('--chart-tick');
            grafico.options.scales[eixo].borderColor = tom('--chart-grid');
            grafico.options.scales[eixo].grid.color = tom('--chart-grid');
        });
        grafico.options.plugins.legend.labels.color = tom('--chart-tick');
        grafico.update();
    }
    pintar();
    $(document).on('golden:theme', pintar);
});
</script>
@endpush
