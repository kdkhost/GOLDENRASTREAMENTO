@extends('portal.layout')

@section('title', 'Meus chamados — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Meus chamados</h1>
        <p>{{ $abertos }} {{ $abertos === 1 ? 'chamado aguardando' : 'chamados aguardando' }} a equipe.</p>
    </div>
    <a href="{{ route('portal.chamados.criar') }}" class="btn btn-gold btn-sm"><i class="fa-solid fa-plus"></i> Abrir chamado</a>
</div>

<div class="filtros">
    <a href="{{ route('portal.chamados') }}" class="{{ request()->filled('status') ? '' : 'atual' }}">Todos</a>
    @foreach($statuses as $chave => $rotulo)
    <a href="{{ route('portal.chamados', ['status' => $chave]) }}" class="{{ request('status') === $chave ? 'atual' : '' }}">{{ $rotulo }}</a>
    @endforeach
</div>

<div class="itens">
    @forelse($chamados as $chamado)
    <a class="item" href="{{ route('portal.chamados.show', $chamado) }}">
        <div class="item-nucleo">
            <b>{{ $chamado->assunto }}</b>
            <small>{{ $chamado->numero }} · aberto em {{ $chamado->created_at->format('d/m/Y') }} ·
                {{ $chamado->messages_count }} {{ $chamado->messages_count === 1 ? 'mensagem' : 'mensagens' }}</small>
        </div>
        <div class="item-lado">
            <span class="selo tone-{{ tom_por_status('chamado', $chamado->status) }}">{{ $chamado->statusLabel() }}</span>
            <small><span class="selo tone-{{ tom_por_status('prioridade', $chamado->prioridade) }}">{{ $chamado->prioridadeLabel() }}</span></small>
        </div>
    </a>
    @empty
    <div class="item vazio">
        {{ request()->filled('status') ? 'Nenhum chamado nesta situação.' : 'Você ainda não abriu chamados. Se precisar de suporte, é aqui que a equipe responde.' }}
    </div>
    @endforelse
</div>

@include('portal.pager', ['paginas' => $chamados])
@endsection
