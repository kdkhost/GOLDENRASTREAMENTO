@extends('portal.layout')

@section('title', 'Início — Portal do cliente')
@section('page_title', 'Olá, '.str($cliente->nome)->explode(' ')->first())
@section('page_description', 'Aqui estão seus pedidos, chamados e contratos com a Golden.')

@section('page_actions')
<a href="{{ route('portal.depoimento') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-quote-left me-1"></i> Deixar um depoimento</a>
<a href="{{ route('portal.chamados.criar') }}" class="btn btn-sm btn-golden"><i class="fas fa-plus me-1"></i> Abrir chamado</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Pedidos" :value="$totais['pedidos']" icon="fa-box-open" tone="blue" :href="route('portal.pedidos')" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Investido" :value="brl($investido_cents)" icon="fa-receipt" tone="gold" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Chamados abertos" :value="$abertos" icon="fa-headset" tone="amber" :href="route('portal.chamados')" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Contratos" :value="$totais['contratos']" icon="fa-file-signature" tone="teal" :href="route('portal.contratos')" pequeno />
    </div>
</div>

<x-admin.table title="Últimos pedidos" icon="fa-box-open" :headers="['Pedido', 'center:Itens', 'center:Data', 'center:Situação', 'end:Total']"
    empty="Nenhum pedido ainda. Dá para montar o seu na loja do site ou falar com um especialista.">
    <x-slot:actions><a href="{{ route('portal.pedidos') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a></x-slot:actions>
    @foreach($pedidos as $pedido)
    <tr>
        <td><a href="{{ route('portal.pedidos.show', $pedido) }}" class="fw-semibold text-decoration-none">{{ $pedido->numero }}</a></td>
        <td class="text-center small">{{ $pedido->items_count }} {{ $pedido->items_count === 1 ? 'item' : 'itens' }}</td>
        <td class="text-center small text-secondary">{{ $pedido->created_at->format('d/m/Y') }}</td>
        <td class="text-center"><x-admin.status :rotulo="$pedido->statusLabel()" :tom="tom_por_status('pedido', $pedido->status)" /></td>
        <td class="text-end fw-semibold">{{ brl($pedido->total_cents) }}</td>
    </tr>
    @endforeach
</x-admin.table>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <x-admin.table title="Chamados" icon="fa-headset" :headers="['Assunto', 'center:Situação']"
            empty="Nada aberto por aqui. Precisando de algo? Abra um chamado — a equipe responde pelo portal.">
            <x-slot:actions><a href="{{ route('portal.chamados') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a></x-slot:actions>
            @foreach($chamados as $chamado)
            <tr>
                <td>
                    <a href="{{ route('portal.chamados.show', $chamado) }}" class="fw-semibold text-decoration-none">{{ $chamado->assunto }}</a>
                    <div class="text-secondary small">{{ $chamado->numero }} · {{ $chamado->created_at->format('d/m/Y') }}</div>
                </td>
                <td class="text-center"><x-admin.status :rotulo="$chamado->statusLabel()" :tom="tom_por_status('chamado', $chamado->status)" /></td>
            </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-lg-6">
        <x-admin.table title="Contratos" icon="fa-file-signature" :headers="['Documento', 'center:Situação']"
            empty="Sua ficha ainda não tem contrato publicado no portal.">
            <x-slot:actions><a href="{{ route('portal.contratos') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a></x-slot:actions>
            @foreach($contratos as $contrato)
            <tr>
                <td>
                    <a href="{{ route('portal.contratos') }}" class="fw-semibold text-decoration-none">{{ $contrato->titulo }}</a>
                    <div class="text-secondary small">{{ $contrato->numero }} · {{ $contrato->tipoLabel() }}</div>
                </td>
                <td class="text-center"><x-admin.status :rotulo="$contrato->statusLabel()" :tom="tom_por_status('contrato', $contrato->status)" /></td>
            </tr>
            @endforeach
        </x-admin.table>
    </div>
</div>
@endsection
