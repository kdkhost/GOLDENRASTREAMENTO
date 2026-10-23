@extends('admin.layouts.app')
@section('title', 'Clientes')
@section('page_title', 'Clientes (CRM)')
@section('page_description', 'Carteira convertida dos leads, mais os cadastros diretos.')

@section('page_actions')
<a href="{{ route('admin.clientes.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo cliente</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Ativos" :value="$kpis['ativos']" icon="fa-user-check" tone="teal" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Prospectos" :value="$kpis['prospectos']" icon="fa-seedling" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Total na base" :value="$kpis['base']" icon="fa-users" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Com pedidos" :value="$kpis['comPedidos']" icon="fa-receipt" tone="violet" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.clientes.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-5" placeholder="Nome, telefone ou e-mail..." />
    <x-admin.filter name="status" type="select" label="Status" :options="\App\Models\Customer::STATUS" empty="Todos os status" col="col-6 col-md-3" />
</x-admin.filters>

<x-admin.table :headers="['Cliente', 'Contato', 'Segmento', 'center:Frota', 'center:Status', 'center:Pedidos', 'center:Interações', 'end:Ações']"
    empty="Nenhum cliente com esse filtro. Converta um lead marcado como Vendido ou cadastre um cliente."
    :paginator="$clientes">
    @foreach($clientes as $c)
        <tr>
            <td>
                <a href="{{ route('admin.clientes.show', $c) }}" class="fw-semibold text-decoration-none">{{ $c->nome }}</a>
                <div class="small text-secondary mt-1">{{ $c->cidade ?: '—' }}{{ $c->uf ? ' / '.$c->uf : '' }}@if($c->owner) · {{ $c->owner->name }}@endif</div>
            </td>
            <td class="small">{{ $c->telefone ?: '—' }}<div class="text-secondary">{{ $c->email ?: '—' }}</div></td>
            <td class="small">{{ config('golden.perfis')[$c->segmento] ?? '—' }}</td>
            <td class="text-center small">{{ $c->frota_tamanho ?: '—' }}</td>
            <td class="text-center"><x-admin.status :rotulo="\App\Models\Customer::label($c->status)" :tom="tom_por_status('cliente', $c->status)" /></td>
            <td class="text-center">{{ $c->orders_count }}</td>
            <td class="text-center">{{ $c->interactions_count }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.clientes.show', $c) }}#ficha" class="btn btn-sm btn-outline-secondary" title="Editar ficha"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.clientes.destroy', $c)" confirm="Excluir o cliente {{ $c->nome }}? As interações e pedidos dele também saem." />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
