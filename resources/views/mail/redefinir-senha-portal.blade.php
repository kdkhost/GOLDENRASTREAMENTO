<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso ao portal</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Portal do cliente · Recuperação de acesso</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Olá, {{ $cliente->nome }}</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Pediram uma senha nova para a sua conta do portal. Use o botão abaixo para escolher outra — o link vale <strong>{{ $minutos }} minutos</strong> e só funciona uma conta: a sua.</p>

                <p style="margin:0 0 18px">
                    <a href="{{ $link }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Definir minha senha</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0 0 10px">Se o botão não abrir, copie este endereço no navegador:<br><span style="word-break:break-all">{{ $link }}</span></p>
                <p style="font-size:12px;color:#6b7280;margin:0">Não foi você? Ignore o e-mail — a senha atual continua valendo e nada muda na sua conta.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Golden Rastreamento · Este link é pessoal e não deve ser compartilhado.
            </td>
        </tr>
    </table>
</body>
</html>
