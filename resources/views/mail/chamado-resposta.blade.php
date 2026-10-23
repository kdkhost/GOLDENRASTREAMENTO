<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chamado {{ $chamado->numero }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Chamado {{ $chamado->numero }} · {{ $chamado->assunto }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">{{ $chamada }}</h1>
                <p style="font-size:13px;color:#6b7280;margin:0 0 14px">
                    {{ $mensagem->autor() }} escreveu em {{ $mensagem->created_at->format('d/m/Y \H:i') }}
                    — situação atual: {{ $chamado->statusLabel() }}.
                </p>

                <div style="font-size:14px;line-height:1.6;padding:16px;background:#faf7f0;border-left:3px solid #e0a33e;border-radius:0 10px 10px 0;white-space:pre-wrap">{{ str($mensagem->corpo)->limit(1200) }}</div>

                <p style="margin:22px 0">
                    <a href="{{ $link }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Responder no {{ $chamado->numero }}</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0">A conversa completa fica no chamado — responder por aqui mantém o histórico junto.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Golden Rastreamento · {{ \App\Models\Setting::get('contato_email', config('golden.email')) }}
            </td>
        </tr>
    </table>
</body>
</html>
