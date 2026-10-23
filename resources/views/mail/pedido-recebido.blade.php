<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido {{ $pedido->numero }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Rastreamento · Gestão</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Obrigado, {{ $pedido->nome }}!</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Recebemos o pedido <strong>{{ $pedido->numero }}</strong>. Em breve confirmamos o pagamento ({{ $pedido->pagamentoLabel() }}) e combinamos {{ $pedido->entrega === 'retira' ? 'a retirada' : 'a entrega' }}. Você acompanha cada etapa pelo link abaixo.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    @foreach($pedido->items as $item)
                    <tr>
                        <td style="padding:10px 14px">{{ $item->quantidade }}× {{ $item->nome }}</td>
                        <td style="padding:10px 14px;text-align:right">{{ brl($item->subtotal_cents) }}</td>
                    </tr>
                    @endforeach
                    @if($pedido->frete_cents > 0)
                    <tr>
                        <td style="padding:10px 14px">Frete</td>
                        <td style="padding:10px 14px;text-align:right">{{ brl($pedido->frete_cents) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:12px 14px;font-weight:700;border-top:1px solid #e5e0d3">Total</td>
                        <td style="padding:12px 14px;font-weight:700;text-align:right;border-top:1px solid #e5e0d3;color:#a97b16">{{ brl($pedido->total_cents) }}</td>
                    </tr>
                </table>

                <p style="margin:22px 0">
                    <a href="{{ $pedido->registroUrl() }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Acompanhar meu pedido</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0">Este link é pessoal: só quem o possui vê o status do pedido {{ $pedido->numero }}.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Dúvidas? Responda este e-mail ou fale no WhatsApp {{ \App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp')) }}.
                A Golden não presta vigilância humana, pronta resposta nem seguro.
            </td>
        </tr>
    </table>
</body>
</html>
