<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo depoimento de {{ $depoimento->nome }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Conteúdo · Novo depoimento no portal</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">{{ $depoimento->nome }} escreveu um depoimento</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">
                    É um cliente com conta no portal, autor do <strong>texto de {{ mb_strlen($depoimento->texto) }} caracteres</strong> abaixo.
                    Nada vai para o ar antes da aprovação da equipe.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Nota</td>
                        <td style="padding:10px 14px;text-align:right">{{ $depoimento->nota }} de 5</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Cidade</td>
                        <td style="padding:10px 14px;text-align:right">{{ $depoimento->cidade ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Enviado em</td>
                        <td style="padding:10px 14px;text-align:right">{{ $depoimento->created_at->format('d/m/Y \H:i') }}</td>
                    </tr>
                </table>

                <p style="font-size:13px;line-height:1.6;margin:18px 0 0;padding:14px 16px;background:#faf7f0;border-left:3px solid #e0a33e;border-radius:0 10px 10px 0;white-space:pre-wrap">{{ str($depoimento->texto)->limit(600) }}</p>

                <p style="margin:22px 0">
                    <a href="{{ route('admin.conteudo.depoimentos.index', ['situacao' => 'pendente']) }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Aprovar no painel</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Depoimentos do painel, do Google e do portal aparecem misturados no carrossel do site — a aprovação é o que decide.
            </td>
        </tr>
    </table>
</body>
</html>
