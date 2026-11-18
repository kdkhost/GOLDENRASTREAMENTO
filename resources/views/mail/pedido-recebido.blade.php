<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido {{ $pedido->numero }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        @include('mail.partials.cabecalho', ['subtitulo' => 'Rastreamento · Gestão'])
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Obrigado, {{ $pedido->nome }}!</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Recebemos o pedido <strong>{{ $pedido->numero }}</strong>. Em breve confirmamos o pagamento ({{ $pedido->pagamentoLabel() }}) e combinamos {{ $pedido->entrega === 'retira' ? 'a retirada' : 'a entrega' }}. Você acompanha cada etapa pelo link abaixo.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    @foreach($pedido->items as $item)
                    <tr>
                        <td style="padding:10px 14px">
                            {{ $item->quantidade }}× {{ $item->nome }}
                            @if(($item->mensalidade_cents ?? 0) > 0)
                                <div style="font-size:12px;color:#0f766e">+ {{ brl($item->mensalidade_cents * $item->quantidade) }}/mês (plano)</div>
                            @endif
                        </td>
                        <td style="padding:10px 14px;text-align:right">{{ brl($item->subtotal_cents) }}</td>
                    </tr>
                    @endforeach
                    @if(($pedido->mensalidade_total_cents ?? 0) > 0)
                    <tr>
                        <td style="padding:10px 14px;color:#0f766e">1ª Mensalidade antecipada</td>
                        <td style="padding:10px 14px;text-align:right;color:#0f766e">{{ brl($pedido->mensalidade_total_cents) }}</td>
                    </tr>
                    @endif
                    @if($pedido->frete_cents > 0)
                    <tr>
                        <td style="padding:10px 14px">Frete</td>
                        <td style="padding:10px 14px;text-align:right">{{ brl($pedido->frete_cents) }}</td>
                    </tr>
                    @endif
                    @if(($pedido->desconto_cents ?? 0) > 0)
                    <tr>
                        <td style="padding:10px 14px;color:#059669">Desconto @if($pedido->cupom_codigo)({{ $pedido->cupom_codigo }})@endif</td>
                        <td style="padding:10px 14px;text-align:right;color:#059669">− {{ brl($pedido->desconto_cents) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:12px 14px;font-weight:700;border-top:1px solid #e5e0d3">
                            Total pago hoje
                            @if(($pedido->mensalidade_total_cents ?? 0) > 0)
                                <div style="font-size:12px;font-weight:normal;color:#0f766e">+ Recorrência: {{ brl($pedido->mensalidade_total_cents) }}/mês a partir do 2º mês</div>
                            @endif
                        </td>
                        <td style="padding:12px 14px;font-weight:700;text-align:right;border-top:1px solid #e5e0d3;color:#a97b16">{{ brl($pedido->total_cents) }}</td>
                    </tr>
                </table>

                <p style="margin:22px 0">
                    <a href="{{ $pedido->registroUrl() }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Acompanhar meu pedido</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0">Este link é pessoal: só quem o possui vê o status do pedido {{ $pedido->numero }}.</p>
            </td>
        </tr>
        @include('mail.partials.rodape', [
            'aviso' => 'Dúvidas? Responda este e-mail ou fale no WhatsApp '.e(\App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp'))).'. A Golden não presta vigilância humana, pronta resposta nem seguro.',
        ])
    </table>
</body>
</html>
