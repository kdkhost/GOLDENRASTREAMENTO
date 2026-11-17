<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperação de acesso ao painel</title>
</head>
<body style="margin:0;padding:24px;background:#0d1117;font-family:Inter,Arial,sans-serif;color:#f0f6fc">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;margin:0 auto;background:#161b22;border:1px solid #30363d;border-radius:18px;overflow:hidden;box-shadow:0 12px 36px rgba(0,0,0,0.45)">
        <tr>
            <td style="background:#090d12;padding:24px 28px;border-bottom:1px solid #30363d">
                <span style="color:#d4af37;font-weight:800;font-size:20px;letter-spacing:.06em;display:inline-block">GOLDEN</span>
                <span style="color:#8b949e;font-size:12px;display:block;margin-top:2px;letter-spacing:.08em;text-transform:uppercase;font-weight:600">Painel Administrativo · Recuperação de Senha</span>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 28px">
                <h1 style="font-size:20px;font-weight:700;margin:0 0 12px;color:#ffffff">Olá, {{ $user->name }}!</h1>
                <p style="font-size:14px;line-height:1.65;margin:0 0 20px;color:#c9d1d9">
                    Recebemos uma solicitação para redefinir a senha da sua conta de acesso ao painel administrativo da <strong>{{ config('golden.marca.nome', 'Golden Rastreamento') }}</strong>.
                </p>
                <p style="font-size:14px;line-height:1.65;margin:0 0 24px;color:#c9d1d9">
                    Para cadastrar uma nova senha com segurança, clique no botão abaixo. Este link é pessoal e expira em <strong>{{ $minutos }} minutos</strong>:
                </p>

                <p style="margin:0 0 26px;text-align:left">
                    <a href="{{ $link }}" style="display:inline-block;background:linear-gradient(135deg,#d4af37,#f59e0b);color:#090d12;text-decoration:none;font-weight:700;font-size:15px;padding:14px 26px;border-radius:10px;box-shadow:0 4px 14px rgba(212,175,55,0.3)">
                        Redefinir minha senha do painel
                    </a>
                </p>

                <div style="padding:14px 18px;background:#0d1117;border:1px solid #30363d;border-radius:10px;margin-bottom:20px">
                    <p style="font-size:12px;color:#8b949e;margin:0 0 6px">
                        Se o botão acima não funcionar, copie e cole o endereço abaixo no seu navegador:
                    </p>
                    <p style="font-size:12px;color:#d4af37;margin:0;word-break:break-all;font-family:monospace">
                        {{ $link }}
                    </p>
                </div>

                <p style="font-size:12px;line-height:1.55;color:#8b949e;margin:0">
                    <strong>Não foi você quem solicitou?</strong> Não se preocupe. Nenhuma alteração foi realizada na sua conta e sua senha atual continua segura. Você pode ignorar este e-mail com tranquilidade.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 28px;background:#090d12;font-size:12px;color:#6e7681;border-top:1px solid #21262d;text-align:center">
                &copy; {{ date('Y') }} {{ config('golden.marca.nome', 'Golden Rastreamento') }} · Link seguro com assinatura criptográfica.
            </td>
        </tr>
    </table>
</body>
</html>
