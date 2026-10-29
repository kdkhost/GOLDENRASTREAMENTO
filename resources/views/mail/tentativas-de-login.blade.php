<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tentativas de acesso ao painel</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Segurança do acesso · Aviso automático</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Alguém tentou entrar na sua conta</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Foram {{ $tentativas }} tentativas seguidas com senha errada. O painel fechou a porta por {{ $bloqueio }} a partir da última tentativa. Nada foi alterado na sua conta — ninguém entrou.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f0;border-radius:12px;padding:4px 0">
                    <tr>
                        <td style="padding:6px 16px;font-size:12px;color:#6b7280;width:40%">Conta</td>
                        <td style="padding:6px 16px;font-size:12px;word-break:break-all"><strong>{{ $email }}</strong></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 16px;font-size:12px;color:#6b7280;width:40%">Origem</td>
                        <td style="padding:6px 16px;font-size:12px;word-break:break-all"><strong>{{ $ip }}</strong></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 16px;font-size:12px;color:#6b7280;width:40%">Navegador</td>
                        <td style="padding:6px 16px;font-size:12px;word-break:break-all"><strong>{{ $agente }}</strong></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 16px;font-size:12px;color:#6b7280;width:40%">Última tentativa</td>
                        <td style="padding:6px 16px;font-size:12px"><strong>{{ $registro_em }}</strong></td>
                    </tr>
                </table>

                <p style="font-size:14px;line-height:1.6;margin:18px 0 0">Foi você? Tente de novo depois do prazo, com calma — o teclado é o lugar mais provável do erro. Não foi? Troque a senha assim que o bloqueio passar. Se a sua conta tiver verificação em duas etapas, ela continua sendo a sua melhor defesa.</p>

                <p style="font-size:12px;color:#6b7280;margin:18px 0 0">Esta mensagem não tem link de "proteger minha conta". Abra o painel pelo endereço que você já conhece e vá em Configurações → Segurança do acesso. Avisos assim saem uma vez por janela de bloqueio, não a cada erro de digitação.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                {{ config('golden.marca.nome') }} · {{ config('golden.marca.cidade') }}
            </td>
        </tr>
    </table>
</body>
</html>
