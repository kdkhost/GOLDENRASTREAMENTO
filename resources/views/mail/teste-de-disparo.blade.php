<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste de disparo</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Configurações · Prova de disparo</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">O disparo está funcionando</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Você pediu ao painel para testar o envio de e-mail, e esta mensagem saiu por ele. Se você a está lendo, o servidor aceitou a conexão, a credencial e o remetente — é exatamente o caminho que percorrem o link de senha do portal, o recibo do chamado e a segunda via da fatura.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f0;border-radius:12px;padding:4px 0">
                    @foreach($viagem as $rotulo => $valor)
                        <tr>
                            <td style="padding:6px 16px;font-size:12px;color:#6b7280;width:40%">{{ $rotulo }}</td>
                            <td style="padding:6px 16px;font-size:12px;color:#1b2127;word-break:break-all"><strong>{{ $valor }}</strong></td>
                        </tr>
                    @endforeach
                </table>

                <p style="font-size:12px;color:#6b7280;margin:18px 0 0">Disparada em {{ $disparado_em }} para {{ $destino }}. Não há nada a responder nem a clicar aqui.</p>
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
