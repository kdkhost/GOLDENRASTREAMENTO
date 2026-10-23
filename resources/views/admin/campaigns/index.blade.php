@extends('admin.layouts.app')
@section('title', 'Campanhas')
@section('page_title', 'Campanhas de tráfego pago')
@section('page_description', 'Origem dos leads e taxa de conversão por campanha.')

@section('page_actions')
<a href="{{ route('admin.campanhas.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova campanha</a>
@endsection

@section('content')
<x-admin.table :headers="['Campanha', 'Canal', 'center:Leads', 'center:Novos', 'center:Vendidos', 'center:Conversão', 'end:Receita', 'end:Ações']" empty="Nenhuma campanha cadastrada.">
    @foreach($campanhas as $c)
        <tr>
            <td>
                <a href="{{ route('admin.leads.index', ['campanha' => $c->id]) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a>
                <div class="small text-secondary mt-1">
                    <code>{{ $c->source ?: '—' }}</code> · <code>{{ $c->medium ?: '—' }}</code>
                    @unless($c->ativa)<span class="badge tone-neutral ms-2">pausada</span>@endunless
                </div>
            </td>
            <td><span class="badge bg-body-secondary text-body">{{ $c->canalLabel() }}</span></td>
            <td class="text-center">{{ $c->leads_count }}</td>
            <td class="text-center">{{ $c->leads_novos }}</td>
            <td class="text-center">{{ $c->leads_vendidos }}</td>
            <td class="text-center">
                @php $taxo = $c->leads_count ? round($c->leads_vendidos / $c->leads_count * 100, 1) : 0; @endphp
                <span class="badge-status {{ $taxo > 0 ? 'st-vendido' : 'st-novo' }}">{{ $taxo }}%</span>
            </td>
            <td class="text-end small">{{ $c->leads_receita ? brl($c->leads_receita) : '—' }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.campanhas.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <form method="POST" action="{{ route('admin.campanhas.toggle', $c) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" title="{{ $c->ativa ? 'Pausar' : 'Ativar' }}"><i class="fas fa-{{ $c->ativa ? 'pause' : 'play' }}"></i></button>
                    </form>
                    @if(auth()->user()->isAdmin())
                        <x-admin.delete :action="route('admin.campanhas.destroy', $c)" confirm="Excluir campanha? Os leads permanecem, apenas perdem o vínculo." />
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
