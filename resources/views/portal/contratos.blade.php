@extends('portal.layout')

@section('title', 'Meus contratos — Portal do cliente')
@section('page_title', 'Meus contratos')
@section('page_description', $vigentes.' '.($vigentes === 1 ? 'documento em vigor' : 'documentos em vigor').' na sua conta.')

@section('page_actions')
<a href="{{ route('portal.chamados.criar') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-headset me-1"></i> Dúvida sobre um contrato</a>
@endsection

@section('content')
@if($contratos->filter(fn ($c) => $c->vencendoEm())->isNotEmpty())
<div class="alert alert-warning shadow-sm"><i class="fas fa-clock me-1"></i> Um contrato vence nos próximos 30 dias. Fale com a equipe para renovar sem ficar sem rastreamento.</div>
@endif

<x-admin.table :headers="['Documento', 'center:Nº', 'Vínculo', 'center:Assinatura', 'center:Vigência', 'center:Situação', 'end:Valor mensal', 'end:Arquivo']"
    empty="Nenhum contrato publicado na sua conta ainda. Assinatura e adesão aparecem aqui assim que a equipe registrar o documento.">
    @foreach($contratos as $contrato)
    <tr>
        <td>
            <span class="fw-semibold">{{ $contrato->titulo }}</span>
            @if($contrato->observacoes)
                <div class="text-secondary small">{{ str($contrato->observacoes)->limit(160) }}</div>
            @endif
        </td>
        <td class="text-center small text-secondary">{{ $contrato->numero }}</td>
        <td class="small">{{ $contrato->tipoLabel() }}</td>
        <td class="text-center small">{{ $contrato->celebrado_em ? $contrato->celebrado_em->format('d/m/Y') : '—' }}</td>
        <td class="text-center small">{{ $contrato->vigencia_ate ? $contrato->vigencia_ate->format('d/m/Y') : '—' }}</td>
        <td class="text-center"><x-admin.status :rotulo="$contrato->statusLabel()" :tom="tom_por_status('contrato', $contrato->status)" /></td>
        <td class="text-end small">{{ $contrato->valor_mensal_cents ? brl($contrato->valor_mensal_cents) : '—' }}</td>
        <td class="text-end">
            @if($contrato->temArquivo())
                <a class="btn btn-sm btn-golden" href="{{ route('portal.contratos.arquivo', $contrato) }}"><i class="fas fa-file-arrow-down me-1"></i> PDF</a>
            @else
                <span class="btn btn-sm btn-outline-secondary disabled">Sem arquivo</span>
            @endif
        </td>
    </tr>
    @endforeach
</x-admin.table>
@endsection
