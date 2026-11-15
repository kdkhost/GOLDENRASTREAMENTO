<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chamado {{ $chamado->numero }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        @include('mail.partials.cabecalho', ['subtitulo' => 'Chamado '.$chamado->numero.' · '.$chamado->assunto])
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
        @include('mail.partials.rodape')
    </table>
</body>
</html>
