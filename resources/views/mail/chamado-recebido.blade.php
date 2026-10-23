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
                <span style="color:#cfd6dd;font-size:12px;display:block">Rastreamento · Gestão</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Aberto o chamado {{ $chamado->numero }}</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Recebemos sua solicitação <strong>{{ $chamado->assunto }}</strong> (prioridade {{ $chamado->prioridadeLabel() }}). Já está na fila do atendimento e a equipe responde pelo portal e por e-mail.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Assunto</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->assunto }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Situação</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->statusLabel() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Aberto em</td>
                        <td style="padding:10px 14px;text-align:right">{{ $chamado->created_at->format('d/m/Y \H:i') }}</td>
                    </tr>
                </table>

                <p style="margin:22px 0">
                    <a href="{{ route('portal.chamados.show', $chamado) }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Ver a conversa do chamado</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0">Responda pelo portal: é lá que ficam todas as mensagens, sem perder o histórico.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280">
                Dúvidas? Fale no WhatsApp {{ \App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp')) }}.
                A Golden não presta vigilância humana, pronta resposta nem seguro.
            </td>
        </tr>
    </table>
</body>
</html>
