@extends('admin.layouts.app')
@section('title', 'Notificações')
@section('page_title', 'Central de notificações')
@section('page_description', 'Tudo o que aconteceu e pede a sua atenção. Abrir uma notificação a tira do sino; a lista guarda o histórico.')

@section('page_actions')
<form method="POST" action="{{ route('admin.notificacoes.lidas') }}" class="d-inline" data-atualiza=".app-content">
    @csrf
    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-check-double me-1"></i> Marcar todas como lidas</button>
</form>
<a href="{{ route('admin.notificacoes.preferencias') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sliders me-1"></i> Preferências</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Novas no sino" :value="$kpis['novas']" icon="fa-bell" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Chegaram hoje" :value="$kpis['hoje']" icon="fa-calendar-day" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Já lidas" :value="$kpis['lidas']" icon="fa-envelope-open-text" tone="teal" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Vencem em 3 dias" :value="$kpis['vencem']" icon="fa-hourglass-half" tone="amber" pequeno
        hint="ainda não lidas, saem sozinhas" /></div>
</div>

<x-admin.filters :reset="route('admin.notificacoes.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Título ou texto da notificação..." />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-4" :options="['novas' => 'Novas', 'lidas' => 'Já lidas']" empty="Todas" />
    <x-admin.filter name="tipo" type="select" label="Tipo" col="col-6 col-md-4" :options="$tipos" empty="Todos os tipos" />
</x-admin.filters>

<x-admin.table :headers="['Notificação', 'center:Tipo', 'center:Quando', 'center:Situação', 'end:Ações']"
    empty="Nenhuma notificação com esse filtro. As novas aparecem aqui no instante em que o sistema as dispara."
    :paginator="$notificacoes">
    @foreach($notificacoes as $n)
        <tr>
            <td>
                <div class="d-flex gap-2">
                    <span class="sino-icone tone-{{ $n->tom() }}"><i class="fas {{ $n->icone() }}" aria-hidden="true"></i></span>
                    <div>
                        <a href="{{ route('admin.notificacoes.show', $n) }}" class="fw-semibold text-decoration-none">{{ $n->titulo }}</a>
                        @if($n->texto)<div class="small text-secondary">{{ Str::limit($n->texto, 140) }}</div>@endif
                    </div>
                </div>
            </td>
            <td class="text-center"><x-admin.status :rotulo="$n->rotulo()" :tom="$n->tom()" /></td>
            <td class="text-center small text-secondary">{{ $n->created_at->format('d/m/Y H:i') }}</td>
            <td class="text-center">
                <x-admin.status :rotulo="$n->foiLida() ? 'Lida' : 'Nova'" :tom="$n->foiLida() ? 'neutral' : 'gold'" />
            </td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.notificacoes.show', $n) }}" class="btn btn-sm btn-outline-secondary" title="Abrir a notificação"><i class="fas fa-eye" aria-hidden="true"></i></a>
                    <x-admin.delete :action="route('admin.notificacoes.destroy', $n)" confirm="Excluir esta notificação da sua central?" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
