<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo chamado {{ $chamado->numero }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Atendimento · Novo chamado no portal</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">{{ $chamado->assunto }}</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">
                    {{ $chamado->customer?->nome ?? 'Cliente' }} abriu o chamado
                    <strong>{{ $chamado->numero }}</strong> pelo portal, com prioridade {{ $chamado->prioridadeLabel() }}.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Cliente</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->customer?->nome }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Telefone</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->customer?->telefone ?: '—' }}</td>
                    </tr>
                    @if($chamado->order)
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Pedido</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->order->numero }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Aberto em</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->created_at->format('d/m/Y \H:i') }}</td>
                    </tr>
                </table>

                @if($abertura = $chamado->messages()->oldest()->first())
                <p style="font-size:13px;line-height:1.6;margin:18px 0 0;padding:14px 16px;background:#faf7f0;border-left:3px solid #e0a33e;border-radius:0 10px 10px 0;white-space:pre-wrap">{{ str($abertura->corpo)->limit(420) }}</p>
                @endif

                <p style="margin:22px 0">
                    <a href="{{ route('admin.chamados.show', $chamado) }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Abrir o chamado no painel</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Chamados chegam por portal, telefone, WhatsApp e pelo painel — todos caem nesta mesma fila.
            </td>
        </tr>
    </table>
</body>
</html>
