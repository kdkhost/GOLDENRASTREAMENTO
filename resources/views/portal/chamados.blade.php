@extends('portal.layout')

@section('title', 'Meus chamados — Portal do cliente')
@section('page_title', 'Meus chamados')
@section('page_description', $abertos.' '.($abertos === 1 ? 'chamado aguardando' : 'chamados aguardando').' a equipe.')

@section('page_actions')
<a href="{{ route('portal.chamados.criar') }}" class="btn btn-sm btn-golden"><i class="fas fa-plus me-1"></i> Abrir chamado</a>
@endsection

@section('content')
<x-admin.filters :reset="route('portal.chamados')">
    <x-admin.filter name="status" type="select" label="Situação" col="col-12 col-md-4" :options="$statuses" empty="Todos os chamados" />
</x-admin.filters>

<x-admin.table :headers="['Chamado', 'center:Prioridade', 'center:Mensagens', 'center:Situação', 'end:Aberto em']"
    :paginator="$chamados"
    empty="{{ request()->filled('status') ? 'Nenhum chamado nesta situação.' : 'Você ainda não abriu chamados. Se precisar de suporte, é aqui que a equipe responde.' }}">
    @foreach($chamados as $chamado)
    <tr>
        <td>
            <a href="{{ route('portal.chamados.show', $chamado) }}" class="fw-semibold text-decoration-none">{{ $chamado->assunto }}</a>
            <div class="text-secondary small">{{ $chamado->numero }} · aberto em {{ $chamado->created_at->format('d/m/Y') }}</div>
        </td>
        <td class="text-center"><x-admin.status :rotulo="$chamado->prioridadeLabel()" :tom="tom_por_status('prioridade', $chamado->prioridade)" /></td>
        <td class="text-center small">{{ $chamado->messages_count }}</td>
        <td class="text-center"><x-admin.status :rotulo="$chamado->statusLabel()" :tom="tom_por_status('chamado', $chamado->status)" /></td>
        <td class="text-end small text-secondary">{{ $chamado->created_at->format('d/m/Y') }}</td>
    </tr>
    @endforeach
</x-admin.table>
@endsection
