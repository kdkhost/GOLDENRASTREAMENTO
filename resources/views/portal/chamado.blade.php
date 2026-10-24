@extends('portal.layout')

@section('title', 'Chamado '.$chamado->numero.' — Portal do cliente')
@section('page_title', $chamado->assunto)
@section('page_description', $chamado->numero.' · aberto em '.$chamado->created_at->format('d/m/Y \à\s H:i').' · '.$chamado->canalLabel())

@section('page_actions')
<a href="{{ route('portal.chamados') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Meus chamados</a>
@endsection

@section('content')
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><x-admin.stat label="Situação" :value="ucfirst($chamado->statusLabel())" icon="fa-flag" :tone="tom_por_status('chamado', $chamado->status)" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Prioridade" :value="ucfirst($chamado->prioridadeLabel())" icon="fa-bolt" :tone="tom_por_status('prioridade', $chamado->prioridade)" pequeno /></div>
    @if($chamado->order)
        <div class="col-6 col-lg-3"><x-admin.stat label="Pedido" :value="$chamado->order->numero" icon="fa-box-open" tone="blue" :href="route('portal.pedidos.show', $chamado->order)" pequeno /></div>
    @endif
    <div class="col-6 col-lg-3"><x-admin.stat label="Mensagens" :value="$chamado->messages->count()" icon="fa-comments" tone="gold" pequeno /></div>
</div>

@if($chamado->status === 'aguardando_cliente')
<div class="alert alert-warning shadow-sm"><i class="fas fa-triangle-exclamation me-1"></i> A equipe precisa de mais uma informação sua para continuar.</div>
@endif
@if($chamado->resolvido_em)
<div class="alert alert-success shadow-sm"><i class="fas fa-circle-check me-1"></i> Resolvido em {{ $chamado->resolvido_em->format('d/m/Y \à\s H:i') }}. Continuou com dúvida? Responda aqui mesmo.</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Conversa" icon="fa-comments" :descricao="$chamado->messages->count().' mensagem(ns) nesta thread'">
            <div class="thread">
                @foreach($chamado->messages as $mensagem)
                <div class="msg {{ $mensagem->daEquipe() ? 'equipe' : 'cliente' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                        <span class="fw-semibold small">
                            <i class="fas {{ $mensagem->daEquipe() ? 'fa-headset' : 'fa-user' }} me-1 text-gold" aria-hidden="true"></i>
                            {{ $mensagem->autor() }}
                        </span>
                        <small class="text-secondary">{{ $mensagem->created_at->format('d/m/Y \à\s H:i') }}</small>
                    </div>
                    <div class="small mt-1" style="white-space:pre-wrap">{{ $mensagem->corpo }}</div>
                </div>
                @endforeach
            </div>

            @if(! in_array($chamado->status, ['fechado'], true))
            <form method="POST" action="{{ route('portal.chamados.responder', $chamado) }}" class="row g-3 mt-2" novalidate>
                @csrf
                <x-admin.field name="corpo" type="textarea" label="Sua resposta" rows="5" :required="true" maxlength="4000"
                    placeholder="Escreva aqui o que a equipe precisa saber." />
                <div class="col-12">
                    <button type="submit" class="btn btn-golden btn-sm"><i class="fas fa-paper-plane me-1"></i> Enviar resposta</button>
                    <span class="form-hint ms-2">A equipe recebe o aviso no mesmo instante.</span>
                </div>
            </form>
            @else
            <p class="small text-secondary mb-0 mt-2">Este chamado está fechado. Precisa de outra coisa? <a href="{{ route('portal.chamados.criar') }}">Abra um novo chamado</a>.</p>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Resumo do chamado" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Número</dt><dd>{{ $chamado->numero }}</dd>
                <dt>Situação</dt><dd>{{ $chamado->statusLabel() }}</dd>
                <dt>Prioridade</dt><dd>{{ $chamado->prioridadeLabel() }}</dd>
                <dt>Aberto em</dt><dd>{{ $chamado->created_at->format('d/m/Y H:i') }}</dd>
                @if($chamado->respondido_em)<dt>Primeira resposta</dt><dd>{{ $chamado->respondido_em->format('d/m/Y H:i') }}</dd>@endif
                @if($chamado->resolvido_em)<dt>Resolvido em</dt><dd>{{ $chamado->resolvido_em->format('d/m/Y H:i') }}</dd>@endif
                <dt>Canal</dt><dd>{{ $chamado->canalLabel() }}</dd>
            </dl>
            <a href="{{ route('portal.chamados.criar') }}" class="btn btn-sm btn-outline-secondary w-100 mt-3"><i class="fas fa-plus me-1"></i> Abrir outro chamado</a>
        </x-admin.card>
    </div>
</div>
@endsection
