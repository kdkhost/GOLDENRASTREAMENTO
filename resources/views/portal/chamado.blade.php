@extends('portal.layout')

@section('title', 'Chamado '.$chamado->numero.' — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>{{ $chamado->assunto }}</h1>
        <p>{{ $chamado->numero }} · aberto em {{ $chamado->created_at->format('d/m/Y \à\s H:i') }} · pelo {{ $chamado->canalLabel() }}</p>
    </div>
    <a href="{{ route('portal.chamados') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Meus chamados</a>
</div>

<div class="tiles">
    <div class="tile">
        <i class="fa-solid fa-flag"></i>
        <div><span>Situação</span><b><span class="selo tone-{{ tom_por_status('chamado', $chamado->status) }}">{{ $chamado->statusLabel() }}</span></b></div>
    </div>
    <div class="tile">
        <i class="fa-solid fa-bolt"></i>
        <div><span>Prioridade</span><b><span class="selo tone-{{ tom_por_status('prioridade', $chamado->prioridade) }}">{{ $chamado->prioridadeLabel() }}</span></b></div>
    </div>
    @if($chamado->order)
    <div class="tile">
        <i class="fa-solid fa-box-open"></i>
        <div><span>Pedido</span><b><a href="{{ route('portal.pedidos.show', $chamado->order) }}" style="color:inherit">{{ $chamado->order->numero }}</a></b></div>
    </div>
    @endif
</div>

@if($chamado->status === 'aguardando_cliente')
<div class="flash" role="status"><span class="err"><i class="fa-solid fa-triangle-exclamation"></i> A equipe precisa de mais uma informação sua para continuar.</span></div>
@endif
@if($chamado->resolvido_em)
<div class="flash" role="status"><span class="ok"><i class="fa-solid fa-circle-check"></i> Resolvido em {{ $chamado->resolvido_em->format('d/m/Y \à\s H:i') }}. Continuou com dúvida? Responda aqui mesmo.</span></div>
@endif

<div class="bloco" style="max-width:880px">
    <div class="bloco-cab"><h2>Conversa</h2></div>

    <div class="fio">
        @foreach($chamado->messages as $mensagem)
        <div class="msg {{ $mensagem->daEquipe() ? 'equipe' : 'cliente' }}">
            <small>
                <span class="quem">{{ $mensagem->autor() }}</span>
                <span>{{ $mensagem->created_at->format('d/m/Y \à\s H:i') }}</span>
                @if($mensagem->daEquipe())<span class="selo tone-teal">Equipe Golden</span>@endif
            </small>
            <p>{{ $mensagem->corpo }}</p>
        </div>
        @endforeach
    </div>

    @if(! in_array($chamado->status, ['fechado'], true))
    <form method="post" action="{{ route('portal.chamados.responder', $chamado) }}" novalidate>
        @csrf
        <div class="field">
            <label for="chResposta">Sua resposta</label>
            <textarea id="chResposta" name="corpo" rows="5" maxlength="4000" required
                      placeholder="Escreva aqui o que a equipe precisa saber.">{{ old('corpo') }}</textarea>
            @error('corpo')<span class="form-err">{{ $message }}</span>@enderror
        </div>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> Enviar resposta</button>
    </form>
    @else
    <p class="fine">Este chamado está fechado. Precisa de outra coisa? <a href="{{ route('portal.chamados.criar') }}" style="color:var(--gold-2);font-weight:700">Abra um novo chamado</a>.</p>
    @endif
</div>
@endsection
