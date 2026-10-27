<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversa aguardando a equipe</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Atendimento · {{ $conversa->canalLabel() }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">O assistente parou nesta conversa</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">
                    Ele respondeu <strong>{{ $conversa->mensagens()->count() }} falas</strong> e travou aqui:
                    <strong>{{ \App\Models\Conversa::ESCALAS[$motivo] ?? $motivo }}</strong>.
                    Quem escreveu no chat já leu que a equipe vai continuar — falta alguém continuar de fato.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Visitante</td>
                        <td style="padding:10px 14px;text-align:right">{{ $conversa->quem() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Contato informado</td>
                        <td style="padding:10px 14px;text-align:right">{{ $conversa->contato ?: 'ainda não informou' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Página</td>
                        <td style="padding:10px 14px;text-align:right">{{ $conversa->pagina ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">Última fala</td>
                        <td style="padding:10px 14px;text-align:right">{{ $conversa->ultima_fala_em?->format('d/m/Y \H:i') }}</td>
                    </tr>
                </table>

                <p style="font-size:13px;line-height:1.6;margin:18px 0 0;padding:14px 16px;background:#faf7f0;border-left:3px solid #e0a33e;border-radius:0 10px 10px 0">
                    {{ str($prova)->limit(600) }}
                </p>

                <p style="margin:22px 0">
                    <a href="{{ route('admin.assistente.conversas.show', $conversa) }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Responder no painel</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Enquanto a equipe não assume, o assistente fica calado nesta conversa — ele não responde por cima de ninguém.
            </td>
        </tr>
    </table>
</body>
</html>
