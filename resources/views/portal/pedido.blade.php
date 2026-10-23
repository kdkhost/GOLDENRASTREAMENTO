@extends('portal.layout')

@section('title', 'Pedido '.$pedido->numero.' — Portal do cliente')

@section('content')
<div class="portal-titulo">
    <div>
        <h1>Pedido {{ $pedido->numero }}</h1>
        <p>Feito em {{ $pedido->created_at->format('d/m/Y \à\s H:i') }} · {{ $pedido->pagamentoLabel() }}</p>
    </div>
    <a href="{{ route('portal.pedidos') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Todos os pedidos</a>
</div>

<div class="bloco">
    <div class="bloco-cab">
        <h2>Onde está</h2>
        <span class="selo tone-{{ tom_por_status('pedido', $pedido->status) }}">{{ $pedido->statusLabel() }}</span>
    </div>

    <ul class="jornada">
        @foreach($pedido->jornada() as $etapa)
        <li class="{{ $etapa['quando'] ? 'feito' : '' }}">
            <i class="fa-solid {{ $etapa['quando'] ? 'fa-check' : 'fa-clock' }}"></i>
            <b>{{ $etapa['titulo'] }}</b>
            <small class="fine">{{ $etapa['quando'] ? $etapa['quando']->format('d/m') : '' }}</small>
        </li>
        @endforeach
    </ul>

    <table class="tabela">
        <thead>
            <tr><th>Item</th><th class="fim">Qtd.</th><th class="fim">Unitário</th><th class="fim">Total</th></tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $item)
            <tr>
                <td>{{ $item->nome }}<br><small class="fine">{{ $item->sku ?: 'Código interno' }}</small></td>
                <td class="fim">{{ $item->quantidade }}</td>
                <td class="fim">{{ brl($item->preco_unit_cents) }}</td>
                <td class="fim">{{ brl($item->subtotal_cents) }}</td>
            </tr>
            @endforeach
            <tr><td colspan="3" class="fim">Subtotal</td><td class="fim">{{ brl($pedido->subtotal_cents) }}</td></tr>
            <tr><td colspan="3" class="fim">Frete ({{ $pedido->entregaLabel() }})</td><td class="fim">{{ $pedido->frete_cents > 0 ? brl($pedido->frete_cents) : 'Grátis' }}</td></tr>
            @if($pedido->desconto_cents > 0)
            <tr><td colspan="3" class="fim">Desconto</td><td class="fim">− {{ brl($pedido->desconto_cents) }}</td></tr>
            @endif
            <tr><td colspan="3" class="fim"><b>Total</b></td><td class="fim"><b>{{ brl($pedido->total_cents) }}</b></td></tr>
        </tbody>
    </table>
</div>

<div class="row-2">
    <div class="bloco">
        <div class="bloco-cab"><h2>Entrega</h2></div>
        <table class="tabela">
            <tbody>
                <tr><th>Modalidade</th><td>{{ $pedido->entregaLabel() }}</td></tr>
                @if($pedido->entrega === 'retira')
                <tr><th>Local</th><td>{{ $golden['endereco'] }} — {{ $golden['cidade_uf'] }}</td></tr>
                @else
                <tr><th>Endereço</th><td>{{ $pedido->enderecoFormatado() }}</td></tr>
                @endif
                <tr><th>Contato</th><td>{{ $pedido->nome }}<br><span class="fine">{{ $pedido->telefone }}</span></td></tr>
                @if($pedido->pago_em)<tr><th>Pago em</th><td>{{ $pedido->pago_em->format('d/m/Y') }}</td></tr>@endif
                @if($pedido->enviado_em)<tr><th>Enviado em</th><td>{{ $pedido->enviado_em->format('d/m/Y') }}</td></tr>@endif
            </tbody>
        </table>
    </div>

    <div class="bloco">
        <div class="bloco-cab"><h2>Documentos e observações</h2></div>
        @if($pedido->notas)
        <p style="white-space:pre-wrap">{{ $pedido->notas }}</p>
        @endif
        @if(count($pedido->documentos ?? []))
        <div class="itens">
            @foreach((array) $pedido->documentos as $caminho)
            <a class="item" href="{{ asset('storage/'.$caminho) }}" target="_blank" rel="noopener">
                <div class="item-nucleo"><b>{{ basename($caminho) }}</b><small>Comprovante ou nota do pedido</small></div>
                <div class="item-lado"><i class="fa-solid fa-file-arrow-down"></i></div>
            </a>
            @endforeach
        </div>
        @endif
        @if(! $pedido->notas && ! count($pedido->documentos ?? []))
        <p class="fine">Nada anotado neste pedido. Precisando de algo? <a href="{{ route('portal.chamados.criar', ['pedido' => $pedido->id]) }}" style="color:var(--gold-2);font-weight:700">Abra um chamado</a>.</p>
        @endif
    </div>
</div>

<p class="fine" style="margin-top:6px">Este pedido também tem <a href="{{ $pedido->registroUrl() }}" style="color:var(--gold-2);font-weight:700">acompanhamento por link</a>, sem precisar entrar no portal.</p>
@endsection
