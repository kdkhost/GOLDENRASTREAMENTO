<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fatura {{ $fatura['numero'] }}</title>
</head>
@php use App\Services\Whmcs\Cliente as Financeiro; @endphp
<body style="margin:0;padding:24px;background:#f4f1e9;font-family:Inter,Arial,sans-serif;color:#1b2127">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">
        <tr>
            <td style="background:#12161a;padding:20px 24px">
                <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em">GOLDEN</span>
                <span style="color:#cfd6dd;font-size:12px;display:block">Rastreamento · Financeiro</span>
            </td>
        </tr>
        <tr>
            <td style="padding:24px">
                <h1 style="font-size:19px;margin:0 0 8px">Olá, {{ $cliente->nome }}</h1>
                <p style="font-size:14px;line-height:1.6;margin:0 0 18px">Você pediu a segunda via pelo atendimento e ela está aqui embaixo. Este e-mail saiu para o endereço que está no seu cadastro — se você não pediu nada, ignore e avise a equipe.</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e0d3;border-radius:12px;font-size:14px">
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Fatura</td>
                        <td style="padding:10px 14px;text-align:right;font-weight:700">#{{ $fatura['numero'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Vencimento</td>
                        <td style="padding:10px 14px;text-align:right">{{ $fatura['vencimento'] ? $fatura['vencimento']->format('d/m/Y') : '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Valor</td>
                        <td style="padding:10px 14px;text-align:right">{{ brl($fatura['total_cents']) }}</td>
                    </tr>
                    @if((int) $fatura['saldo_cents'] > 0)
                        <tr>
                            <td style="padding:10px 14px;color:#6b7280">Em aberto</td>
                            <td style="padding:10px 14px;text-align:right;font-weight:700">{{ brl($fatura['saldo_cents']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 14px;color:#6b7280">Situação</td>
                        <td style="padding:10px 14px;text-align:right">{{ Financeiro::rotuloDaFatura((string) $fatura['status']) }}</td>
                    </tr>
                    @if($fatura['metodo'] !== '')
                        <tr>
                            <td style="padding:10px 14px;color:#6b7280">Forma de pagamento</td>
                            <td style="padding:10px 14px;text-align:right">{{ $fatura['metodo'] }}</td>
                        </tr>
                    @endif
                </table>

                <p style="margin:22px 0">
                    <a href="{{ $fatura['url'] !== '' ? $fatura['url'] : route('portal.faturas') }}" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:10px">Abrir a fatura e pagar</a>
                </p>
                <p style="font-size:12px;color:#6b7280;margin:0 0 10px">O botão abre o financeiro da sua conta. Para ver todas as faturas de uma vez, entre no portal do cliente com o seu e-mail e senha.</p>
                <p style="font-size:12px;color:#6b7280;margin:0">Cobrança que você não reconhece, vencimento errado ou pagamento já feito e não baixado? Fale com a equipe pelo portal: é lá que a conversa fica registrada.</p>
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
