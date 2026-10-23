@extends('portal.layout')

@section('title', 'Abrir chamado — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Abrir um chamado</h1>
        <p>Conte o que está acontecendo. A equipe responde por aqui e avisamos no seu e-mail a cada mensagem nova.</p>
    </div>
    <a href="{{ route('portal.chamados') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Meus chamados</a>
</div>

<div class="bloco" style="max-width:760px">
    <form method="post" action="{{ route('portal.chamados.registrar') }}" novalidate>
        @csrf
        <div class="field">
            <label for="chAssunto">Assunto</label>
            <input id="chAssunto" name="assunto" type="text" maxlength="180" required
                   value="{{ old('assunto') }}" placeholder="Ex.: rastreador do placa ABC1D23 parou de reportar">
            @error('assunto')<span class="form-err">{{ $message }}</span>@enderror
        </div>

        <div class="row-2">
            <div class="field">
                <label for="chPrioridade">Prioridade</label>
                <select id="chPrioridade" name="prioridade">
                    @foreach($prioridades as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected(old('prioridade', 'normal') === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
                <span class="senha-dica">Use urgente só quando o veículo está impossibilitado de trabalhar</span>
                @error('prioridade')<span class="form-err">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="chPedido">Pedido relacionado</label>
                <select id="chPedido" name="pedido_id">
                    <option value="">Nenhum</option>
                    @foreach($pedidos as $opcao)
                    <option value="{{ $opcao->id }}" @selected((int) old('pedido_id', $pedido ?? 0) === $opcao->id)>
                        {{ $opcao->numero }} — {{ $opcao->created_at->format('d/m/Y') }} — {{ $opcao->statusLabel() }}
                    </option>
                    @endforeach
                </select>
                @error('pedido_id')<span class="form-err">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="field">
            <label for="chCorpo">O que está acontecendo</label>
            <textarea id="chCorpo" name="corpo" rows="7" maxlength="4000" required
                      placeholder="Descreva o veículo, o equipamento e o que já tentou fazer.">{{ old('corpo') }}</textarea>
            @error('corpo')<span class="form-err">{{ $message }}</span>@enderror
        </div>

        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> Abrir chamado</button>
    </form>
</div>
@endsection
