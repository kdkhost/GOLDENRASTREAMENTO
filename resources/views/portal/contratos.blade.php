@extends('portal.layout')

@section('title', 'Meus contratos — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Meus contratos</h1>
        <p>{{ $vigentes }} {{ $vigentes === 1 ? 'documento em vigor' : 'documentos em vigor' }} na sua conta.</p>
    </div>
    <a href="{{ route('portal.chamados.criar') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-headset"></i> Dúvida sobre um contrato</a>
</div>

<div class="itens">
    @forelse($contratos as $contrato)
    <div class="item">
        <div class="item-nucleo">
            <b>{{ $contrato->titulo }}</b>
            <small>{{ $contrato->numero }} · {{ $contrato->tipoLabel() }}
                @if($contrato->celebrado_em) · assinado em {{ $contrato->celebrado_em->format('d/m/Y') }} @endif
                @if($contrato->vigencia_ate) · vigência até {{ $contrato->vigencia_ate->format('d/m/Y') }} @endif</small>
            @if($contrato->observacoes)<small class="fine">{{ str($contrato->observacoes)->limit(160) }}</small>@endif
        </div>
        <div class="item-lado">
            @if($contrato->valor_mensal_cents)<span>{{ brl($contrato->valor_mensal_cents) }}/mês</span>@endif
            <small><span class="selo tone-{{ tom_por_status('contrato', $contrato->status) }}">{{ $contrato->statusLabel() }}</span></small>
        </div>
        <div>
            @if($contrato->temArquivo())
            <a class="btn btn-gold btn-sm" href="{{ route('portal.contratos.arquivo', $contrato) }}">
                <i class="fa-solid fa-file-arrow-down"></i> Baixar PDF
            </a>
            @else
            <span class="btn btn-ghost btn-sm inativa">Sem arquivo</span>
            @endif
        </div>
    </div>
    @empty
    <div class="item vazio">
        Nenhum contrato publicado na sua conta ainda. Assinatura e adesão aparecem aqui assim que a equipe registrar o documento.
    </div>
    @endforelse
</div>

@if($contratos->filter(fn ($c) => $c->vencendoEm())->isNotEmpty())
<div class="flash" role="status"><span class="err"><i class="fa-solid fa-clock"></i> Um contrato vence nos próximos 30 dias. Fale com a equipe para renovar sem ficar sem rastreamento.</span></div>
@endif
@endsection
