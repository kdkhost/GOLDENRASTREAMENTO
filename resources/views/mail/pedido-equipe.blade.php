<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Inter,Arial,sans-serif;font-size:14px;color:#1b2127">
    <tr><td style="padding-bottom:12px"><strong>Venda nova na loja virtual</strong> — pedido {{ $pedido->numero }}, {{ brl($pedido->total_cents) }}.</td></tr>
    <tr><td>
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e0d3;border-radius:12px">
            <tr><td style="padding:10px 14px;width:150px">Cliente</td><td style="padding:10px 14px">{{ $pedido->nome }} · {{ $pedido->telefone }} · {{ $pedido->email }}</td></tr>
            <tr><td style="padding:10px 14px">Documento</td><td style="padding:10px 14px">{{ $pedido->documentoFormatado() }} ({{ $pedido->tipo_pessoa === 'juridica' ? 'pessoa jurídica' : 'pessoa física' }})</td></tr>
            <tr><td style="padding:10px 14px">Entrega</td><td style="padding:10px 14px">{{ $pedido->entregaLabel() }}@if($pedido->entrega !== 'retira') — {{ $pedido->enderecoFormatado() }}@endif</td></tr>
            <tr><td style="padding:10px 14px">Pagamento</td><td style="padding:10px 14px">{{ $pedido->pagamentoLabel() }}</td></tr>
            <tr><td style="padding:10px 14px">Itens</td><td style="padding:10px 14px">
                @foreach($pedido->items as $item){{ $item->quantidade }}× {{ $item->nome }} ({{ brl($item->subtotal_cents) }})<br>@endforeach
            </td></tr>
            @if($pedido->notas)
            <tr><td style="padding:10px 14px">Observações</td><td style="padding:10px 14px">{{ $pedido->notas }}</td></tr>
            @endif
        </table>
    </td></tr>
    <tr><td style="padding-top:16px"><a href="{{ route('admin.pedidos.show', $pedido) }}">Abrir no painel</a> · <a href="{{ $pedido->registroUrl() }}">página pública do pedido</a></td></tr>
</table>
