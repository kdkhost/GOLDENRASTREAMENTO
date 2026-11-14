<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido {{ $pedido->numero }} Enviado</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05)">
        <tr>
            <td style="background:#12161a;padding:22px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:20px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block;margin-top:2px">Rastreamento Veicular & Gestão</span>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 24px">
                <div style="display:inline-block;background:#eef6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;margin-bottom:12px">
                    PEDIDO A CAMINHO
                </div>
                <h1 style="font-size:20px;margin:0 0 10px;color:#111827">Seu pedido foi despachado, {{ $pedido->nome }}!</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 20px;color:#4b5563">
                    Temos ótimas notícias! O seu pedido <strong>#{{ $pedido->numero }}</strong> já foi embalado, testado e despachado via <strong>{{ $transportadora }}</strong>. Você pode acompanhar todas as movimentações da entrega em tempo real diretamente em nosso site.
                </p>

                {{-- Box de Código de Rastreamento --}}
                <div style="background:#faf7f0;border:2px dashed #d4a017;border-radius:12px;padding:18px;text-align:center;margin-bottom:24px">
                    <span style="font-size:12px;color:#785c13;text-transform:uppercase;letter-spacing:.08em;font-weight:700;display:block">Código de Rastreamento</span>
                    <span style="font-size:22px;font-weight:800;color:#12161a;letter-spacing:1px;display:block;margin:6px 0">{{ $codigo }}</span>
                    <span style="font-size:12px;color:#6b7280">Serviço: {{ $transportadora }}</span>
                </div>

                {{-- Botão de Rastreio --}}
                <div style="text-align:center;margin:28px 0">
                    <a href="{{ $urlRastreio }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:10px;box-shadow:0 3px 6px rgba(224,163,62,0.3)">
                        Rastrear Entrega em Tempo Real
                    </a>
                </div>

                {{-- Dados da Entrega --}}
                <div style="border-top:1px solid #e5e7eb;padding-top:18px;margin-top:20px;font-size:13px;color:#6b7280">
                    <strong style="color:#111827;display:block;margin-bottom:4px">Endereço de destino:</strong>
                    {{ $pedido->enderecoFormatado() }}
                </div>

                <div style="border-top:1px solid #e5e7eb;padding-top:18px;margin-top:16px;font-size:13px;color:#6b7280">
                    <strong style="color:#111827;display:block;margin-bottom:4px">Itens do pedido:</strong>
                    <ul style="margin:4px 0 0;padding-left:18px;color:#4b5563">
                        @foreach($pedido->items as $item)
                            <li>{{ $item->quantidade }}× {{ $item->nome }}</li>
                        @endforeach
                    </ul>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 24px;background:#faf7f0;font-size:12px;color:#6b7280;line-height:1.5;border-top:1px solid #f3ede2">
                Dúvidas sobre sua entrega? Responda a este e-mail ou fale diretamente no WhatsApp da Golden: <strong>{{ \App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp')) }}</strong>.
            </td>
        </tr>
    </table>
</body>
</html>
