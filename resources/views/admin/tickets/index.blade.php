@extends('admin.layouts.app')
@section('title', 'Chamados')
@section('page_title', 'Chamados do portal')
@section('page_description', 'Tudo que o cliente abre no portal cai nesta fila. A resposta sai daqui e chega no e-mail dele.')

@section('page_actions')
<a href="{{ route('admin.chamados.index', ['status' => 'aberto']) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-inbox me-1"></i> Só os novos</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Na fila" :value="$kpis['abertos']" icon="fa-inbox" tone="amber" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Sem nenhuma resposta" :value="$kpis['sem_resposta']" icon="fa-hourglass-half" tone="red" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Aguardando o cliente" :value="$kpis['aguardando']" icon="fa-user-clock" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Resolvidos e fechados" :value="$kpis['resolvidos']" icon="fa-circle-check" tone="teal" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.chamados.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nº do chamado, assunto ou cliente..." />
    <x-admin.filter name="status" type="select" label="Situação" col="col-6 col-md-4" :options="$statuses" empty="Todas as situações" />
    <x-admin.filter name="prioridade" type="select" label="Prioridade" col="col-6 col-md-4" :options="$prioridades" empty="Todas as prioridades" />
</x-admin.filters>

<x-admin.table :headers="['Chamado', 'Cliente', 'center:Prioridade', 'center:Mensagens', 'center:Situação', 'center:Aberto em', 'end:Última fala']"
    empty="Nenhum chamado com esse filtro. Eles aparecem aqui no momento em que o cliente abre pelo portal."
    :paginator="$chamados">
    @foreach($chamados as $c)
        <tr>
            <td>
                <a href="{{ route('admin.chamados.show', $c) }}" class="fw-semibold text-decoration-none">{{ $c->assunto }}</a>
                <div class="text-secondary small">{{ $c->numero }} · {{ $c->canalLabel() }}</div>
            </td>
            <td class="small">
                <a href="{{ route('admin.clientes.show', $c->customer) }}" class="text-decoration-none">{{ $c->customer?->nome ?? 'Cliente removido' }}</a>
                @if($c->order)<div class="text-secondary"><a href="{{ route('admin.pedidos.show', $c->order) }}">{{ $c->order->numero }}</a></div>@endif
            </td>
            <td class="text-center"><x-admin.status :rotulo="$c->prioridadeLabel()" :tom="tom_por_status('prioridade', $c->prioridade)" /></td>
            <td class="text-center small">{{ $c->messages_count }}</td>
            <td class="text-center"><x-admin.status :rotulo="$c->statusLabel()" :tom="tom_por_status('chamado', $c->status)" /></td>
            <td class="text-center small text-secondary">{{ $c->created_at->format('d/m/Y') }}</td>
            <td class="text-end small text-secondary">
                @if($c->registradoPelaEquipe())
                    <span class="text-danger fw-semibold">ainda sem resposta</span>
                @else
                    {{ $c->updated_at->diffForHumans() }}
                @endif
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
