<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.06)">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block;margin-top:2px">Notificações e Alertas em Tempo Real</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <div style="margin-bottom:14px">
                    @php
                        $badgeBg = match($tipo) {
                            'lead_novo' => '#e0a33e',
                            'pedido_novo' => '#2563eb',
                            'depoimento_novo' => '#8b5cf6',
                            'coleta_solicitada' => '#d97706',
                            'entrega_atualizada' => '#0d9488',
                            default => '#475569',
                        };
                        $badgeTxt = match($tipo) {
                            'lead_novo' => 'Novo Lead Recebido',
                            'pedido_novo' => 'Nova Venda Realizada',
                            'depoimento_novo' => 'Novo Depoimento / Avaliação',
                            'coleta_solicitada' => 'Nova Coleta de Encomenda',
                            'entrega_atualizada' => 'Acompanhamento de Entrega',
                            default => 'Alerta do Sistema',
                        };
                    @endphp
                    <span style="display:inline-block;background:{{ $badgeBg }};color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em">
                        {{ $badgeTxt }}
                    </span>
                </div>

                <h1 style="font-size:20px;margin:0 0 10px;color:#12161a">{{ $titulo }}</h1>

                @if($texto)
                <p style="font-size:14px;line-height:1.6;color:#4b5563;margin:0 0 18px;background:#f8fafc;padding:12px 16px;border-radius:10px;border-left:3px solid {{ $badgeBg }}">
                    {{ $texto }}
                </p>
                @endif

                @if(!empty($dados))
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:12px;font-size:13px;margin-bottom:20px;overflow:hidden">
                    @foreach($dados as $chave => $valor)
                        @if(is_scalar($valor) && $valor !== '' && !in_array($chave, ['lead_id', 'pedido_id', 'depoimento_id']))
                        <tr>
                            <td style="padding:9px 14px;color:#6b7280;background:#f9fafb;width:140px;border-bottom:1px solid #e5e7eb;font-weight:600;text-transform:capitalize">
                                {{ str_replace('_', ' ', $chave) }}
                            </td>
                            <td style="padding:9px 14px;color:#111827;border-bottom:1px solid #e5e7eb">
                                {{ $valor }}
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </table>
                @endif

                @if(!empty($url))
                <p style="margin:22px 0 10px">
                    <a href="{{ $url }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:10px">
                        Visualizar Detalhes no Painel →
                    </a>
                </p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280;border-top:1px solid #f1ece1">
                Este e-mail é gerado automaticamente pela Central de Alertas da <strong>Golden Rastreamento</strong>.
                Para alterar os e-mails ou configurações de recebimento, acesse o painel em Sistema → Notificações.
            </td>
        </tr>
    </table>
</body>
</html>

