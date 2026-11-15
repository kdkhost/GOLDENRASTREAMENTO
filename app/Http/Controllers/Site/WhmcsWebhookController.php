<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Mail\FilaDeEmailService;
use App\Services\Whmcs\Cliente as WhmcsCliente;
use App\Services\Whmcs\WhmcsPluginService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks autenticados do plugin Golden instalado no WHMCS
 * e coloca o envio da fatura PDF + dados de gateway na fila anti-spam.
 */
class WhmcsWebhookController extends Controller
{
    public function receber(
        Request $request,
        WhmcsPluginService $plugin,
        WhmcsCliente $whmcs,
        FilaDeEmailService $fila
    ): JsonResponse {
        $segredo = $plugin->obterOuGerarSegredo();
        $payloadBruto = (string) $request->getContent();
        $tokenHeader = trim((string) $request->header('X-Golden-Token', ''));
        $assinaturaHeader = trim((string) $request->header('X-Golden-Signature', ''));
        $assinaturaEsperada = 'sha256='.hash_hmac('sha256', $payloadBruto, $segredo);

        $autenticado = ($tokenHeader !== '' && hash_equals($segredo, $tokenHeader))
            || ($assinaturaHeader !== '' && hash_equals($assinaturaEsperada, $assinaturaHeader));

        if (! $autenticado) {
            return response()->json(['erro' => 'Assinatura do webhook WHMCS inválida.'], 401);
        }

        $evento = trim((string) $request->input('evento', 'InvoiceCreated'));
        $invoiceId = (int) $request->input('invoiceid', 0);

        if ($invoiceId <= 0) {
            return response()->json(['erro' => 'Parâmetro invoiceid obrigatório.'], 422);
        }

        $faturaEntrada = is_array($request->input('fatura')) ? $request->input('fatura') : [];
        $clienteEntrada = is_array($request->input('cliente')) ? $request->input('cliente') : [];

        // Enriquece via API conectada do WHMCS quando o payload não trouxer todos os itens/cliente
        $detalhes = $whmcs->detalharFatura($invoiceId);
        if (is_array($detalhes)) {
            $dadosFatura = array_merge($detalhes, array_filter($faturaEntrada, fn (mixed $v): bool => $v !== null && $v !== ''));
            if ($clienteEntrada !== []) {
                $dadosFatura['cliente'] = array_merge($detalhes['cliente'] ?? [], $clienteEntrada);
            }
        } else {
            $totalDecimal = (string) ($faturaEntrada['total'] ?? '0');
            $totalCents = (int) round(((float) str_replace(',', '.', $totalDecimal)) * 100);
            $dadosFatura = [
                'id' => $invoiceId,
                'numero' => (string) ($faturaEntrada['invoicenum'] ?? $invoiceId),
                'status' => (string) ($faturaEntrada['status'] ?? 'Unpaid'),
                'emitida' => (string) ($faturaEntrada['date'] ?? now()->format('Y-m-d')),
                'vencimento' => (string) ($faturaEntrada['duedate'] ?? now()->addDays(5)->format('Y-m-d')),
                'paga_em' => (string) ($faturaEntrada['datepaid'] ?? ''),
                'subtotal_cents' => $totalCents,
                'total_cents' => $totalCents,
                'saldo_cents' => $totalCents,
                'metodo' => (string) ($faturaEntrada['gateway_module'] ?? ''),
                'metodo_rotulo' => (string) ($faturaEntrada['gateway_display_name'] ?? 'Gateway WHMCS'),
                'gateway_tipo' => (string) ($faturaEntrada['gateway_type'] ?? 'gateway'),
                'pix_copia_cola' => (string) ($faturaEntrada['pix_copia_cola'] ?? ''),
                'linha_digitavel' => (string) ($faturaEntrada['linha_digitavel'] ?? ''),
                'codigo_barras' => (string) ($faturaEntrada['codigo_barras'] ?? ''),
                'nosso_numero' => (string) ($faturaEntrada['nosso_numero'] ?? ''),
                'checkout_url' => (string) ($faturaEntrada['checkout_url'] ?? url('/minha-conta/faturas')),
                'url' => (string) ($faturaEntrada['checkout_url'] ?? url('/minha-conta/faturas')),
                'cliente' => $clienteEntrada,
                'itens' => [],
            ];
        }

        $emailDestino = trim((string) ($dadosFatura['cliente']['email'] ?? $clienteEntrada['email'] ?? ''));
        if ($emailDestino === '' || ! filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['erro' => 'E-mail do cliente ausente na fatura.'], 422);
        }

        $chaveTemplate = match ($evento) {
            'InvoicePaymentReminder' => 'fatura_lembrete_vencimento',
            'InvoicePaid' => 'fatura_confirmada_whmcs',
            default => 'fatura_pdf_anexo',
        };

        $numeroFatura = (string) ($dadosFatura['numero'] ?? $invoiceId);
        $valorFormatado = 'R$ '.number_format(((int) ($dadosFatura['total_cents'] ?? 0)) / 100, 2, ',', '.');
        $vencimento = $dadosFatura['vencimento'] ?? '';
        if ($vencimento instanceof CarbonInterface) {
            $vencimento = $vencimento->format('d/m/Y');
        }

        $anexos = $chaveTemplate === 'fatura_pdf_anexo'
            ? [[
                'tipo' => 'fatura_pdf',
                'nome' => 'fatura-'.$numeroFatura.'.pdf',
                'dados' => $dadosFatura,
            ]]
            : [];

        $itemFila = $fila->enfileirarTemplate($chaveTemplate, $emailDestino, [
            'cliente_nome' => (string) ($dadosFatura['cliente']['nome'] ?? 'Cliente'),
            'fatura_numero' => $numeroFatura,
            'fatura_valor' => $valorFormatado,
            'fatura_vencimento' => (string) $vencimento,
            'fatura_url' => (string) ($dadosFatura['checkout_url'] ?? $dadosFatura['url'] ?? url('/minha-conta/faturas')),
            'gateway_nome' => (string) ($dadosFatura['metodo_rotulo'] ?? 'Gateway WHMCS'),
            'pix_copia_cola' => (string) ($dadosFatura['pix_copia_cola'] ?? 'Disponível no PDF anexo e no Portal'),
            'linha_digitavel' => (string) ($dadosFatura['linha_digitavel'] ?? ''),
            'data_pagamento' => now()->format('d/m/Y H:i'),
        ], [
            'nome' => (string) ($dadosFatura['cliente']['nome'] ?? ''),
            'anexos' => $anexos,
            'prioridade' => 3,
            'metadados' => [
                'origem' => 'whmcs_webhook',
                'evento' => $evento,
                'whmcs_invoice_id' => $invoiceId,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'fila_id' => $itemFila->id,
            'template' => $chaveTemplate,
            'agendado_para' => $itemFila->agendado_para?->toIso8601String(),
        ], 202);
    }
}
