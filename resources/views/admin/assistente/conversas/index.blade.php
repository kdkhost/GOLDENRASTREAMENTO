@extends('admin.layouts.app')
@section('title', 'Conversas do assistente')
@section('page_title', 'Conversas do assistente')
@section('page_description', 'O que abriu na bolha do site ou da página de funil. Quem tem conta fala pelo chamado.')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <x-admin.stat label="Abertas" :value="$kpis['abertas']" icon="fa-comment-dots" tone="blue" :href="route('admin.assistente.conversas.index', ['status' => 'aberta'])" />
    </div>
    <div class="col-12 col-md-4">
        <x-admin.stat label="Aguardando a equipe" :value="$kpis['aguardando']" icon="fa-hand" tone="amber" :href="route('admin.assistente.conversas.index', ['status' => 'aguardando_equipe'])" />
    </div>
    <div class="col-12 col-md-4">
        <x-admin.stat label="Resolvidas" :value="$kpis['resolvidas']" icon="fa-circle-check" tone="green" :href="route('admin.assistente.conversas.index', ['status' => 'resolvida'])" />
    </div>
</div>

<x-admin.filters :reset="route('admin.assistente.conversas.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Nome, contato, página ou o que foi dito..." />
    <x-admin.filter name="canal" type="select" label="De onde" col="col-6 col-md-3" :options="$canais" empty="Todos" />
    <x-admin.filter name="status" type="select" label="Situação" col="col-6 col-md-4" :options="$statuses" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Conversa', 'De onde', 'center:Situação', 'Última fala', 'end:Ações']"
    empty="Nenhuma conversa com esse filtro."
    :paginator="$conversas">
    @foreach($conversas as $c)
        @php $ultima = $c->mensagens->last(); @endphp
        <tr>
            <td>
                <span class="fw-semibold">{{ $c->quem() }}</span>
                <div class="small text-secondary text-break">
                    {{ $ultima ? \Illuminate\Support\Str::limit($ultima->textoVisivel(), 70) : 'sem falas ainda' }}
                </div>
            </td>
            <td>
                <span class="small">{{ $c->canalLabel() }}</span>
                @if($c->escala_motivo)<div class="small text-secondary">{{ $c->escalaLabel() }}</div>@endif
            </td>
            <td class="text-center"><x-admin.status :rotulo="$c->statusLabel()" :tom="tom_por_status('conversa', $c->status)" /></td>
            <td class="small text-secondary">{{ $c->ultima_fala_em ? $c->ultima_fala_em->format('d/m/Y H:i') : '—' }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.assistente.conversas.show', $c) }}" class="btn btn-sm btn-outline-secondary" title="Abrir a conversa"><i class="fas fa-arrow-right"></i></a>
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
