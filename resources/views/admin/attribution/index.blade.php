@extends('admin.layouts.app')
@section('title', 'Atribuição de tráfego')
@section('page_title', 'Atribuição de tráfego')
@section('page_description', 'De onde veio cada visita, cada clique e cada cadastro do site.')

@section('page_actions')
    <a href="{{ route('admin.atribuicao.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-file-csv me-1" aria-hidden="true"></i> Exportar CSV
    </a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Visitas de gente" :value="number_format($kpis['visitas'], 0, ',', '.')" icon="fa-person-walking" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Telas vistas" :value="number_format($kpis['views'], 0, ',', '.')" icon="fa-eye" tone="teal" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Cliques medidos" :value="number_format($kpis['cliques'], 0, ',', '.')" icon="fa-hand-pointer" tone="violet" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Cadastros" :value="number_format($kpis['leads'], 0, ',', '.')" icon="fa-user-plus" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Visita → cadastro" :value="$kpis['taxa'].'%'" icon="fa-percent" :tone="tom_por_flag($kpis['taxa'] > 0)" pequeno hint="No período e nos filtros acima." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Tráfego pago" :value="number_format($kpis['pagos'], 0, ',', '.')" icon="fa-bullhorn" tone="red" pequeno hint="Visitas com clique de anúncio." /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Robôs à parte" :value="number_format($kpis['robos'], 0, ',', '.')" icon="fa-robot" tone="neutral" pequeno hint="Fora de todos os números acima." /></div>
    <div class="col-6 col-lg-3">
        <x-admin.card title="Como chegaram" icon="fa-mobile-screen" class="h-100">
            <div class="d-flex flex-wrap gap-2">
                @forelse($dispositivos as $d)
                    <x-admin.status :rotulo="\App\Models\Visit::rotuloDispositivo($d->grupo).' · '.number_format($d->visitas, 0, ',', '.')" tom="neutral" />
                @empty
                    <span class="text-secondary small">Sem visitas no período.</span>
                @endforelse
            </div>
        </x-admin.card>
    </div>
</div>

<x-admin.filters :reset="route('admin.atribuicao.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Página, site de origem, campanha, criativo..." />
    <x-admin.filter name="inicio" type="date" label="De" col="col-6 col-md-2" :value="$periodo[0]" />
    <x-admin.filter name="fim" type="date" label="Até" col="col-6 col-md-2" :value="$periodo[1]" />
    <x-admin.filter name="canal" type="select" label="Canal" col="col-6 col-md-2" :options="$canais" empty="Todos os canais" />
    <x-admin.filter name="rede" type="select" label="Rede / site" col="col-6 col-md-2" :options="$redes" empty="Todas" />
    <x-admin.filter name="dispositivo" type="select" label="Dispositivo" col="col-6 col-md-2" :options="\App\Models\Visit::dispositivos()" empty="Qualquer um" />
</x-admin.filters>

<x-admin.tabela-origem
    :linhas="$porCanal"
    :lider="$porCanal->max('visitas') ?: 1"
    titulo="Origem do tráfego (canal)"
    rotulo="Canal"
    icone="fa-diagram-project"
    descricao="Anúncio, rede social, busca orgânica, busca por IA, indicação, e-mail, parceiro ou direto. É a régua do Atribuidor, a mesma que aparece no lead." />

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <x-admin.tabela-origem
            :linhas="$porRede"
            :lider="$porRede->max('visitas') ?: 1"
            titulo="Redes e sites que trouxeram gente"
            rotulo="Rede / site"
            icone="fa-hashtag"
            descricao="As doze maiores origens dentro de cada canal." />
    </div>
    <div class="col-lg-5">
        <x-admin.tabela-origem
            :linhas="$porIa"
            :lider="$porIa->max('visitas') ?: 1"
            titulo="Busca por IA"
            rotulo="Assistente"
            icone="fa-robot"
            descricao="ChatGPT, Gemini, Perplexity, Copilot, Claude e cia. — a pessoa pediu e clicou no link." />
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <x-admin.tabela-origem
            :linhas="$porLanding"
            :lider="$porLanding->max('visitas') ?: 1"
            titulo="Página onde a visita começou"
            rotulo="Destino"
            icone="fa-file-lines"
            descricao="A primeira tela do site naquele acesso." />
    </div>
    <div class="col-lg-6">
        <x-admin.tabela-origem
            :linhas="$porOrigem"
            :lider="$porOrigem->max('visitas') ?: 1"
            titulo="Sites de onde vieram"
            rotulo="Origem (host)"
            icone="fa-globe"
            descricao="O referer real, sem as marcações de campanha." />
    </div>
</div>

<x-admin.table class="mt-3"
    :headers="['Última atividade', 'Origem', 'center:Views', 'center:Cliques', 'center:Leads', 'end:Jornada']"
    title="Visita por visita"
    icon="fa-list-check"
    descricao="Cada linha abre a trilha completa: o que a pessoa viu, onde clicou e se cadastrou."
    empty="Nenhuma visita com esse filtro."
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
@endsection
