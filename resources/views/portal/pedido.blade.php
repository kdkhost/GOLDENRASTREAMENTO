@extends('portal.layout')

@section('title', 'Pedido '.$pedido->numero.' — Portal do cliente')
@section('page_title', 'Pedido '.$pedido->numero)
@section('page_description', 'Feito em '.$pedido->created_at->format('d/m/Y \à\s H:i').' · '.$pedido->pagamentoLabel())

@section('page_actions')
<a href="{{ route('portal.chamados.criar', ['pedido' => $pedido->id]) }}" class="btn btn-sm btn-golden"><i class="fas fa-headset me-1"></i> Falar sobre este pedido</a>
<a href="{{ route('portal.pedidos') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Meus pedidos</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.card title="Onde está" icon="fa-route">
            <x-slot:actions><x-admin.status :rotulo="$pedido->statusLabel()" :tom="tom_por_status('pedido', $pedido->status)" /></x-slot:actions>
            <ul class="timeline mb-0">
                @foreach($pedido->jornada() as $etapa)
                <li class="{{ $etapa['atual'] ? 'atual' : '' }}">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold small">
                            <i class="fas {{ $etapa['quando'] ? 'fa-circle-check text-success' : 'fa-clock text-secondary' }} me-1"></i>{{ $etapa['titulo'] }}
                        </span>
                        <small class="text-secondary text-nowrap">{{ $etapa['quando'] ? $etapa['quando']->format('d/m/Y') : '—' }}</small>
                    </div>
                </li>
                @endforeach
            </ul>
        </x-admin.card>

        <x-admin.table class="mt-3" :headers="['Produto', 'center:Qtd.', 'end:Unitário', 'end:Total']" empty="Pedido sem itens.">
            @foreach($pedido->items as $item)
            <tr>
                <td>
                    {{ $item->nome }}
                    <div class="small text-secondary">{{ $item->sku ?: 'Código interno' }}</div>
                </td>
                <td class="text-center">{{ $item->quantidade }}</td>
                <td class="text-end small">{{ brl($item->preco_unit_cents) }}</td>
                <td class="text-end small fw-semibold">{{ brl($item->subtotal_cents) }}</td>
            </tr>
            @endforeach
        </x-admin.table>

        <x-admin.card title="Totais" icon="fa-calculator" class="mt-3">
            <dl class="defs mb-0">
                <dt>Subtotal</dt><dd>{{ brl($pedido->subtotal_cents) }}</dd>
                <dt>Frete ({{ $pedido->entregaLabel() }})</dt><dd>{{ $pedido->frete_cents > 0 ? brl($pedido->frete_cents) : 'Grátis' }}</dd>
                @if($pedido->desconto_cents > 0)
                    <dt>Desconto</dt><dd>− {{ brl($pedido->desconto_cents) }}</dd>
                @endif
                <dt>Total</dt><dd class="text-gold">{{ brl($pedido->total_cents) }}</dd>
            </dl>
        </x-admin.card>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Entrega" icon="fa-truck-fast">
            <dl class="defs mb-0">
                <dt>Modalidade</dt><dd>{{ $pedido->entregaLabel() }}</dd>
                @if($pedido->entrega === 'retira')
                    <dt>Local</dt><dd>{{ $golden['endereco'] }} — {{ $golden['cidade_uf'] }}</dd>
                @else
                    <dt>Endereço</dt><dd>{{ $pedido->enderecoFormatado() }}</dd>
                @endif
                <dt>Contato</dt><dd>{{ $pedido->nome }}<div class="form-hint">{{ $pedido->telefone }}</div></dd>
                @if($pedido->pago_em)<dt>Pago em</dt><dd>{{ $pedido->pago_em->format('d/m/Y') }}</dd>@endif
                @if($pedido->enviado_em)<dt>Enviado em</dt><dd>{{ $pedido->enviado_em->format('d/m/Y') }}</dd>@endif
            </dl>
        </x-admin.card>

        <x-admin.card title="Documentos e observações" icon="fa-paperclip" class="mt-3">
            @if($pedido->notas)
                <p class="small" style="white-space:pre-wrap">{{ $pedido->notas }}</p>
            @endif
            @if(count($pedido->documentos ?? []))
                <ul class="timeline mb-0">
                    @foreach((array) $pedido->documentos as $caminho)
                    <li>
                        <a href="{{ asset('storage/'.$caminho) }}" target="_blank" rel="noopener" class="fw-semibold small text-decoration-none">
                            <i class="fas fa-file-arrow-down me-1 text-gold"></i>{{ basename($caminho) }}
                        </a>
                        <div class="small text-secondary">Comprovante ou nota do pedido</div>
                    </li>
                    @endforeach
                </ul>
            @endif
            @if(! $pedido->notas && ! count($pedido->documentos ?? []))
                <p class="small text-secondary mb-0">Nada anotado neste pedido. Precisando de algo? <a href="{{ route('portal.chamados.criar', ['pedido' => $pedido->id]) }}">Abra um chamado</a>.</p>
            @endif
        </x-admin.card>

        <x-admin.card title="Acompanhar sem entrar aqui" icon="fa-link" class="mt-3">
            <p class="small text-secondary">Este pedido tem um link de acompanhamento que mostra a mesma linha do tempo, sem senha.</p>
            <x-admin.link-copiar :valor="$pedido->registroUrl()" label="Link do pedido" hint="basta abrir no celular" />
        </x-admin.card>
    </div>
</div>
@endsection
