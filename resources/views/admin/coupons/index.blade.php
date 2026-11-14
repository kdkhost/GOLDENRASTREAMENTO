@extends('admin.layouts.app')
@section('title', 'Cupons de desconto')
@section('page_title', 'Cupons de desconto')
@section('page_description', 'Crie códigos promocionais percentuais, valor fixo ou frete grátis para a loja.')

@section('page_actions')
<a href="{{ route('admin.catalogo.cupons.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo cupom</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Cupons cadastrados" :value="$kpis['total']" icon="fa-ticket" tone="blue" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Ativos" :value="$kpis['ativos']" icon="fa-circle-check" tone="green" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Total de usos" :value="$kpis['usos']" icon="fa-receipt" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Expirados" :value="$kpis['expirados']" icon="fa-calendar-xmark" tone="red" pequeno /></div>
</div>

<x-admin.filters :reset="route('admin.catalogo.cupons.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Código ou descrição..." />
    <x-admin.filter name="tipo" type="select" label="Tipo" col="col-6 col-md-3" :options="\App\Models\Coupon::TIPOS" empty="Todos os tipos" />
    <x-admin.filter name="estado" type="select" label="Status" col="col-6 col-md-3" :options="['ativos' => 'Ativos', 'inativos' => 'Inativos']" empty="Todos" />
</x-admin.filters>

<x-admin.table :headers="['Código', 'Desconto', 'Pedido mínimo', 'center:Usos', 'Validade', 'center:Status', 'end:Ações']"
    empty="Nenhum cupom cadastrado ainda."
    :paginator="$cupons">
    @foreach($cupons as $c)
        <tr>
            <td>
                <span class="badge bg-dark text-warning fw-bold px-2 py-1" style="letter-spacing:.04em">{{ $c->codigo }}</span>
                @if($c->descricao)
                    <div class="small text-secondary mt-1">{{ $c->descricao }}</div>
                @endif
            </td>
            <td class="small fw-semibold">
                {{ $c->rotuloDesconto() }}
                @if($c->desconto_maximo_cents)
                    <div class="text-secondary fw-normal">Teto: {{ brl($c->desconto_maximo_cents) }}</div>
                @endif
            </td>
            <td class="small">{{ $c->valor_minimo_cents > 0 ? brl($c->valor_minimo_cents) : 'Sem mínimo' }}</td>
            <td class="text-center small">
                {{ $c->usos }}@if($c->limite_usos) / {{ $c->limite_usos }}@endif
                @if($c->limite_por_cliente)
                    <div class="text-secondary">{{ $c->limite_por_cliente }}/cliente</div>
                @endif
            </td>
            <td class="small">
                @if($c->valido_de || $c->valido_ate)
                    {{ $c->valido_de ? $c->valido_de->format('d/m/Y H:i') : 'Imediato' }}
                    <div class="text-secondary">até {{ $c->valido_ate ? $c->valido_ate->format('d/m/Y H:i') : 'sem limite' }}</div>
                @else
                    <span class="text-secondary">Sem expiração</span>
                @endif
            </td>
            <td class="text-center">
                @if(!$c->ativo)
                    <x-admin.status rotulo="inativo" tom="secondary" />
                @elseif($c->estaExpirado())
                    <x-admin.status rotulo="expirado" tom="red" />
                @elseif($c->estaEsgotado())
                    <x-admin.status rotulo="esgotado" tom="amber" />
                @else
                    <x-admin.status rotulo="vigente" tom="green" />
                @endif
            </td>
            <td>
                <div class="table-actions">
                    <form method="POST" action="{{ route('admin.catalogo.cupons.toggle', $c) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $c->ativo ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $c->ativo ? 'Desativar' : 'Ativar' }}">
                            <i class="fas {{ $c->ativo ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                    </form>
                    <a href="{{ route('admin.catalogo.cupons.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.catalogo.cupons.destroy', $c)" confirm="Excluir cupom {{ $c->codigo }}?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
