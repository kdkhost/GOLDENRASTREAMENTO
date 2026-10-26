@extends('admin.layouts.app')
@section('title', $notificacao->titulo)
@section('page_title', $notificacao->titulo)
@section('page_description', $notificacao->rotulo().' · recebida em '.$notificacao->created_at->format('d/m/Y H:i'))

@section('page_actions')
@if($destino = $notificacao->link())
    <a href="{{ $destino }}" class="btn btn-sm btn-golden"><i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir o registro</a>
@endif
<a href="{{ route('admin.notificacoes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        @if($era_nova)
            <div class="callout ok mb-3">
                <span><i class="fas fa-check me-1" aria-hidden="true"></i>
                    <strong>Lida agora.</strong> Esta notificação saiu do sino — o histórico da central continua aqui.</span>
            </div>
        @endif

        <x-admin.card title="Mensagem" icon="fa-envelope-open-text"
            descricao="O aviso é uma cópia do fato. Quem manda continua sendo a ficha do registro.">
            <div class="d-flex gap-3">
                <span class="sino-icone tone-{{ $notificacao->tom() }}"><i class="fas {{ $notificacao->icone() }}" aria-hidden="true"></i></span>
                <div class="flex-grow-1">
                    <div class="small text-secondary mb-2">{{ $notificacao->rotulo() }} · {{ $notificacao->created_at->diffForHumans() }}</div>
                    @if($notificacao->texto)
                        <p class="mb-0" style="white-space:pre-wrap">{{ $notificacao->texto }}</p>
                    @else
                        <p class="text-secondary mb-0">Este aviso não trouxe texto; os detalhes estão no painel ao lado.</p>
                    @endif
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Detalhes" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Tipo</dt><dd><x-admin.status :rotulo="$notificacao->rotulo()" :tom="$notificacao->tom()" /></dd>
                <dt>Recebida em</dt><dd>{{ $notificacao->created_at->format('d/m/Y H:i') }}</dd>
                <dt>Lida em</dt><dd>{{ $notificacao->lida_em?->format('d/m/Y H:i') ?? 'ainda não' }}</dd>
                @if($notificacao->expira_em)
                    <dt>Vence em</dt><dd>{{ $notificacao->expira_em->format('d/m/Y H:i') }}</dd>
                @endif
            </dl>

            @if($notificacao->dadosEtiquetados() !== [])
                <div class="zone-title mt-3">O que aconteceu</div>
                <dl class="defs mb-0">
                    @foreach($notificacao->dadosEtiquetados() as $linha)
                        <dt>{{ $linha['rotulo'] }}</dt><dd>{{ $linha['valor'] }}</dd>
                    @endforeach
                </dl>
            @endif
        </x-admin.card>

        <x-admin.card title="Excluir" icon="fa-triangle-exclamation" class="mt-3">
            <p class="small text-secondary">Excluir apaga esta notificação só da sua central. Para só parar de receber avisos deste tipo,
                use <a href="{{ route('admin.notificacoes.preferencias') }}">Preferências</a>.</p>
            <x-admin.delete :action="route('admin.notificacoes.destroy', $notificacao)" label="Excluir notificação"
                confirm="Excluir esta notificação da sua central?" />
        </x-admin.card>
    </div>
</div>
@endsection
