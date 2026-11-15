<?php

namespace App\Services\Whmcs;

use App\Models\Setting;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Gera o pacote instalável (.zip) do plugin oficial da Golden Rastreamento
 * para o WHMCS (`modules/addons/golden_rastreamento` + endpoint de API
 * externa `includes/api/goldengetinvoicepaymentdata.php`).
 */
class WhmcsPluginService
{
    public const WEBHOOK_SECRET_KEY = 'whmcs_webhook_secret';

    /**
     * Garante e devolve o token compartilhado usado entre o plugin WHMCS e este painel.
     */
    public function obterOuGerarSegredo(): string
    {
        $atual = trim((string) Setting::get(self::WEBHOOK_SECRET_KEY, ''));
        if ($atual !== '') {
            return $atual;
        }

        $novo = 'gwh_'.Str::random(40);
        Setting::set(self::WEBHOOK_SECRET_KEY, $novo);

        return $novo;
    }

    /**
     * Gera o arquivo `.zip` em disco temporário e devolve os bytes binários prontos para download.
     */
    public function gerarPacoteZip(): string
    {
        $segredo = $this->obterOuGerarSegredo();
        $webhookUrl = url('/api/webhooks/whmcs');
        $painelUrl = rtrim(url('/'), '/');

        $tempPath = tempnam(sys_get_temp_dir(), 'golden_whmcs_');
        if ($tempPath === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para gerar o plugin WHMCS.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            throw new RuntimeException('Falha ao inicializar o arquivo ZIP do plugin WHMCS.');
        }

        $arquivos = [
            'modules/addons/golden_rastreamento/golden_rastreamento.php' => $this->codigoAddonPhp($webhookUrl, $segredo, $painelUrl),
            'modules/addons/golden_rastreamento/hooks.php' => $this->codigoHooksPhp(),
            'includes/api/goldengetinvoicepaymentdata.php' => $this->codigoApiCustomPhp(),
            'LEIAME-INSTALACAO.txt' => $this->conteudoLeiame($webhookUrl, $segredo),
        ];

        foreach ($arquivos as $caminho => $conteudo) {
            $zip->addFromString($caminho, $conteudo);
        }

        $zip->close();

        $binario = (string) file_get_contents($tempPath);
        @unlink($tempPath);

        return $binario;
    }

    private function codigoAddonPhp(string $webhookUrl, string $segredo, string $painelUrl): string
    {
        $webhookEscaped = addslashes($webhookUrl);
        $segredoEscaped = addslashes($segredo);
        $painelEscaped = addslashes($painelUrl);

        return <<<PHP
<?php

/**
 * Módulo Addon Golden Rastreamento — Ponte de Gateways, PIX/Boleto e Webhooks para Faturas PDF.
 *
 * Gerado automaticamente pelo painel Golden Rastreamento ({$painelEscaped}).
 */

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

function golden_rastreamento_config(): array
{
    return [
        'name' => 'Golden Rastreamento - Gateways & Faturas PDF',
        'description' => 'Expõe gateways, payload PIX/linha digitável e dispara webhooks de fatura com PDF para a fila anti-spam da Golden.',
        'version' => '1.0.0',
        'author' => 'Golden Rastreamento',
        'language' => 'portuguese-br',
        'fields' => [
            'webhook_url' => [
                'FriendlyName' => 'URL do Webhook Golden',
                'Type' => 'text',
                'Size' => '80',
                'Default' => '{$webhookEscaped}',
                'Description' => 'Endpoint no painel Golden que recebe eventos de fatura.',
            ],
            'webhook_secret' => [
                'FriendlyName' => 'Chave Secreta HMAC',
                'Type' => 'text',
                'Size' => '64',
                'Default' => '{$segredoEscaped}',
                'Description' => 'Token de assinatura enviado no cabeçalho X-Golden-Token e X-Golden-Signature.',
            ],
            'enviar_fatura_criada' => [
                'FriendlyName' => 'Enviar fatura PDF na emissão',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Notificar a fila Golden quando uma fatura for gerada no WHMCS.',
            ],
            'enviar_lembrete_vencimento' => [
                'FriendlyName' => 'Enviar lembretes de vencimento',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Disparar lembrete pela fila Golden antes/no vencimento.',
            ],
            'enviar_pagamento_confirmado' => [
                'FriendlyName' => 'Enviar recibo quando pago',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Notificar a Golden quando o gateway liquidar a fatura.',
            ],
        ],
    ];
}

function golden_rastreamento_activate(): array
{
    return [
        'status' => 'success',
        'description' => 'Plugin Golden Rastreamento ativado. Libere a ação API goldengetinvoicepaymentdata na sua API Role se necessário.',
    ];
}

function golden_rastreamento_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'Plugin Golden Rastreamento desativado.',
    ];
}

function golden_rastreamento_output(array \$vars): void
{
    \$gateways = [];
    try {
        \$rows = Capsule::table('tblpaymentgateways')
            ->select('gateway', 'setting', 'value')
            ->whereIn('setting', ['name', 'visible', 'type'])
            ->get();

        foreach (\$rows as \$row) {
            \$gateways[\$row->gateway][\$row->setting] = \$row->value;
        }
    } catch (\\Throwable \$e) {
        echo '<div class="errorbox">Erro ao consultar gateways: ' . htmlspecialchars(\$e->getMessage()) . '</div>';
    }

    echo '<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;max-width:860px;">';
    echo '<h2 style="margin-top:0;">Golden Rastreamento — Ponte de Gateways & Faturas</h2>';
    echo '<p><strong>Webhook configurado:</strong> <code>' . htmlspecialchars((string) (\$vars['webhook_url'] ?? '')) . '</code></p>';
    echo '<p><strong>Endpoint API customizado:</strong> <code>action=goldengetinvoicepaymentdata</code> (ativo em <code>includes/api/goldengetinvoicepaymentdata.php</code>)</p>';
    echo '<h3>Gateways de Pagamento Detectados no WHMCS</h3>';
    echo '<table class="datatable" width="100%" cellspacing="1" cellpadding="6"><thead><tr><th>Módulo</th><th>Nome Exibido</th><th>Visível</th></tr></thead><tbody>';

    foreach (\$gateways as \$modulo => \$info) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars((string) \$modulo) . '</code></td>';
        echo '<td>' . htmlspecialchars((string) (\$info['name'] ?? \$modulo)) . '</td>';
        echo '<td>' . ((string) (\$info['visible'] ?? '') === 'on' ? 'Sim' : 'Não') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Coleta metadados de gateway, PIX e boleto disponíveis na base WHMCS para a fatura.
 */
function golden_extrair_dados_gateway_fatura(int \$invoiceId): array
{
    \$invoice = Capsule::table('tblinvoices')->where('id', \$invoiceId)->first();
    if (! \$invoice) {
        return [];
    }

    \$modulo = (string) (\$invoice->paymentmethod ?? '');
    \$displayName = (string) Capsule::table('tblpaymentgateways')
        ->where('gateway', \$modulo)
        ->where('setting', 'name')
        ->value('value');

    if (\$displayName === '') {
        \$displayName = \$modulo !== '' ? \$modulo : 'Gateway WHMCS';
    }

    \$notes = (string) (\$invoice->notes ?? '');
    \$pix = '';
    \$linha = '';
    \$barras = '';
    \$nossoNumero = '';

    if (preg_match('/(000201[0-9A-Za-z\.\/\-\+\=\*\s]{25,400})/', \$notes, \$mPix) === 1) {
        \$pix = preg_replace('/\s+/', '', \$mPix[1]) ?? '';
    }
    if (preg_match('/(\d{5}[\.\s]?\d{5}\s+\d{5}[\.\s]?\d{6}\s+\d{5}[\.\s]?\d{6}\s+\d\s+\d{14})/', \$notes, \$mLinha) === 1) {
        \$linha = trim(preg_replace('/\s+/', ' ', \$mLinha[1]) ?? '');
    }

    // Inspeciona últimos registros do gateway log envolvendo a fatura (Efi/Gerencianet, Asaas, Mercado Pago, PagSeguro, etc.)
    try {
        \$logs = Capsule::table('tblgatewaylog')
            ->where('data', 'like', '%' . \$invoiceId . '%')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['data', 'result']);

        foreach (\$logs as \$log) {
            \$blob = (string) \$log->data . ' ' . (string) \$log->result;
            if (\$pix === '' && preg_match('/(000201[0-9A-Za-z\.\/\-\+\=\*]{25,400})/', \$blob, \$lPix) === 1) {
                \$pix = \$lPix[1];
            }
            if (\$linha === '' && preg_match('/(\d{5}[\.\s]?\d{5}\s*\d{5}[\.\s]?\d{6}\s*\d{5}[\.\s]?\d{6}\s*\d\s*\d{14})/', \$blob, \$lLin) === 1) {
                \$linha = trim(preg_replace('/\s+/', ' ', \$lLin[1]) ?? '');
            }
            if (\$nossoNumero === '' && preg_match('/nosso[_\s-]*numero["\':=\s]+([0-9A-Za-z\-]{5,30})/i', \$blob, \$lNn) === 1) {
                \$nossoNumero = \$lNn[1];
            }
        }
    } catch (\\Throwable \$e) {
        // Log opcional — falha silenciosa preservando fluxo do WHMCS
    }

    \$lower = strtolower(\$modulo . ' ' . \$displayName);
    \$gatewayType = 'gateway';
    if (str_contains(\$lower, 'pix')) {
        \$gatewayType = 'pix';
    } elseif (str_contains(\$lower, 'boleto') || str_contains(\$lower, 'efi') || str_contains(\$lower, 'gerencianet') || str_contains(\$lower, 'asaas') || str_contains(\$lower, 'inter') || str_contains(\$lower, 'cora')) {
        \$gatewayType = 'boleto_pix';
    } elseif (str_contains(\$lower, 'card') || str_contains(\$lower, 'cartao') || str_contains(\$lower, 'stripe') || str_contains(\$lower, 'mercadopago') || str_contains(\$lower, 'pagseguro')) {
        \$gatewayType = 'cartao';
    }

    return [
        'invoice_id' => \$invoiceId,
        'invoicenum' => (string) (\$invoice->invoicenum ?: \$invoiceId),
        'userid' => (int) \$invoice->userid,
        'status' => (string) \$invoice->status,
        'total' => (string) \$invoice->total,
        'date' => (string) \$invoice->date,
        'duedate' => (string) \$invoice->duedate,
        'datepaid' => (string) (\$invoice->datepaid ?? ''),
        'gateway_module' => \$modulo,
        'gateway_display_name' => \$displayName,
        'gateway_type' => \$gatewayType,
        'pix_copia_cola' => \$pix,
        'linha_digitavel' => \$linha,
        'codigo_barras' => \$barras,
        'nosso_numero' => \$nossoNumero,
    ];
}
PHP;
    }

    private function codigoHooksPhp(): string
    {
        return <<<'PHP'
<?php

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/golden_rastreamento.php';

function golden_rastreamento_config_addon(): array
{
    $rows = Capsule::table('tbladdonmodules')
        ->where('module', 'golden_rastreamento')
        ->pluck('value', 'setting')
        ->all();

    return is_array($rows) ? $rows : [];
}

function golden_rastreamento_disparar_webhook(string $evento, int $invoiceId): void
{
    $cfg = golden_rastreamento_config_addon();
    $url = trim((string) ($cfg['webhook_url'] ?? ''));
    $secret = trim((string) ($cfg['webhook_secret'] ?? ''));

    if ($url === '' || $secret === '' || $invoiceId <= 0) {
        return;
    }

    $flagEvento = match ($evento) {
        'InvoiceCreated' => ($cfg['enviar_fatura_criada'] ?? '') === 'on',
        'InvoicePaymentReminder' => ($cfg['enviar_lembrete_vencimento'] ?? '') === 'on',
        'InvoicePaid' => ($cfg['enviar_pagamento_confirmado'] ?? '') === 'on',
        default => true,
    };

    if (! $flagEvento) {
        return;
    }

    $dadosFatura = golden_extrair_dados_gateway_fatura($invoiceId);
    if ($dadosFatura === []) {
        return;
    }

    $cliente = Capsule::table('tblclients')->where('id', (int) $dadosFatura['userid'])->first();
    $systemUrl = rtrim((string) Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');

    $payload = json_encode([
        'evento' => $evento,
        'invoiceid' => $invoiceId,
        'fatura' => array_merge($dadosFatura, [
            'checkout_url' => $systemUrl !== '' ? $systemUrl . '/viewinvoice.php?id=' . $invoiceId : '',
        ]),
        'cliente' => $cliente ? [
            'id' => (int) $cliente->id,
            'nome' => trim(((string) $cliente->firstname) . ' ' . ((string) $cliente->lastname)),
            'empresa' => (string) ($cliente->companyname ?? ''),
            'email' => (string) $cliente->email,
            'telefone' => (string) ($cliente->phonenumber ?? ''),
            'documento' => (string) ($cliente->tax_id ?? ''),
            'endereco' => trim(((string) ($cliente->address1 ?? '')) . ' ' . ((string) ($cliente->address2 ?? ''))),
            'cidade' => (string) ($cliente->city ?? ''),
            'uf' => (string) ($cliente->state ?? ''),
            'cep' => (string) ($cliente->postcode ?? ''),
        ] : null,
        'disparado_em' => date('c'),
    ]);

    if ($payload === false) {
        return;
    }

    $signature = hash_hmac('sha256', $payload, $secret);

    $ch = curl_init($url);
    if ($ch === false) {
        return;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Golden-Token: ' . $secret,
            'X-Golden-Signature: sha256=' . $signature,
        ],
    ]);

    @curl_exec($ch);
    curl_close($ch);
}

add_hook('InvoiceCreated', 1, function (array $vars): void {
    golden_rastreamento_disparar_webhook('InvoiceCreated', (int) ($vars['invoiceid'] ?? 0));
});

add_hook('InvoicePaymentReminder', 1, function (array $vars): void {
    golden_rastreamento_disparar_webhook('InvoicePaymentReminder', (int) ($vars['invoiceid'] ?? 0));
});

add_hook('InvoicePaid', 1, function (array $vars): void {
    golden_rastreamento_disparar_webhook('InvoicePaid', (int) ($vars['invoiceid'] ?? 0));
});
PHP;
    }

    private function codigoApiCustomPhp(): string
    {
        return <<<'PHP'
<?php

/**
 * Custom External API Endpoint: goldengetinvoicepaymentdata
 * Colocar em: /includes/api/goldengetinvoicepaymentdata.php no seu servidor WHMCS.
 */

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

$addonPath = dirname(__DIR__, 2) . '/modules/addons/golden_rastreamento/golden_rastreamento.php';
if (file_exists($addonPath)) {
    require_once $addonPath;
}

if (! empty($_POST['ping']) || ! empty($_REQUEST['ping'])) {
    $gateways = Capsule::table('tblpaymentgateways')
        ->select('gateway', 'setting', 'value')
        ->whereIn('setting', ['name', 'visible'])
        ->get();

    $agrupados = [];
    foreach ($gateways as $row) {
        $agrupados[$row->gateway][$row->setting] = $row->value;
    }

    $apiresults = [
        'result' => 'success',
        'plugin' => 'golden_rastreamento',
        'version' => '1.0.0',
        'pong' => true,
        'gateways_count' => count($agrupados),
        'gateways' => $agrupados,
    ];

    return;
}

$invoiceId = (int) ($_POST['invoiceid'] ?? $_REQUEST['invoiceid'] ?? 0);
if ($invoiceId <= 0) {
    $apiresults = ['result' => 'error', 'message' => 'Parâmetro invoiceid obrigatório.'];

    return;
}

$invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (! $invoice) {
    $apiresults = ['result' => 'error', 'message' => 'Fatura não encontrada no WHMCS.'];

    return;
}

$dadosGateway = function_exists('golden_extrair_dados_gateway_fatura')
    ? golden_extrair_dados_gateway_fatura($invoiceId)
    : [];

$cliente = Capsule::table('tblclients')->where('id', (int) $invoice->userid)->first();
$systemUrl = rtrim((string) Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value'), '/');

$apiresults = array_merge([
    'result' => 'success',
    'plugin' => 'golden_rastreamento',
    'version' => '1.0.0',
    'invoiceid' => $invoiceId,
    'gateway_module' => (string) ($invoice->paymentmethod ?? ''),
    'gateway_display_name' => (string) ($dadosGateway['gateway_display_name'] ?? $invoice->paymentmethod ?? ''),
    'gateway_type' => (string) ($dadosGateway['gateway_type'] ?? 'gateway'),
    'pix_copia_cola' => (string) ($dadosGateway['pix_copia_cola'] ?? ''),
    'linha_digitavel' => (string) ($dadosGateway['linha_digitavel'] ?? ''),
    'codigo_barras' => (string) ($dadosGateway['codigo_barras'] ?? ''),
    'nosso_numero' => (string) ($dadosGateway['nosso_numero'] ?? ''),
    'checkout_url' => $systemUrl !== '' ? $systemUrl . '/viewinvoice.php?id=' . $invoiceId : '',
    'cliente' => $cliente ? [
        'nome' => trim(((string) $cliente->firstname) . ' ' . ((string) $cliente->lastname)),
        'empresa' => (string) ($cliente->companyname ?? ''),
        'email' => (string) $cliente->email,
        'telefone' => (string) ($cliente->phonenumber ?? ''),
        'documento' => (string) ($cliente->tax_id ?? ''),
        'endereco' => trim(((string) ($cliente->address1 ?? '')) . ' ' . ((string) ($cliente->address2 ?? ''))),
        'cidade' => (string) ($cliente->city ?? ''),
        'uf' => (string) ($cliente->state ?? ''),
        'cep' => (string) ($cliente->postcode ?? ''),
    ] : [],
]);
PHP;
    }

    private function conteudoLeiame(string $webhookUrl, string $segredo): string
    {
        return <<<TXT
===========================================================================
GOLDEN RASTREAMENTO — PLUGIN DE GATEWAY & FATURAS PDF PARA WHMCS (v1.0.0)
===========================================================================

Este pacote conecta o seu servidor WHMCS ao painel Golden Rastreamento para:
1. Ler gateways de pagamento ativos no WHMCS (PIX, Boleto, Cartão, etc.);
2. Expor PIX Copia e Cola, Linha Digitável e dados cadastrais direto na
   Fatura PDF cirúrgica gerada pela Golden;
3. Enviar webhooks automáticos de faturas criadas, lembretes e pagamentos
   confirmados diretamente para a fila anti-spam de e-mails da Golden.

PASSOS PARA INSTALAÇÃO NO WHMCS:
---------------------------------------------------------------------------
1. Extraia o conteúdo deste ZIP mantendo a estrutura de pastas e envie para
   a raiz da instalação do seu WHMCS (geralmente /public_html ou /whmcs):
   - modules/addons/golden_rastreamento/golden_rastreamento.php
   - modules/addons/golden_rastreamento/hooks.php
   - includes/api/goldengetinvoicepaymentdata.php

2. Acesse o painel Admin do WHMCS:
   Configurações (Setup) -> Módulos Addon (Addon Modules).
   Localize "Golden Rastreamento - Gateways & Faturas PDF" e clique em Ativar.

3. Clique em "Configure" no addon e verifique os campos pré-configurados:
   - URL do Webhook Golden : {$webhookUrl}
   - Chave Secreta HMAC    : {$segredo}
   Dê permissão de acesso ao grupo "Full Administrator".

4. Permissões da API Role no WHMCS (WHMCS 8+):
   Vá em Configurações -> Equipe -> Gerenciar Credenciais da API -> API Roles.
   Edite a role usada pela Golden e marque também a ação customizada:
   [x] goldengetinvoicepaymentdata
   (Além de GetPaymentMethods, GetInvoice, GetInvoices, GetClientsDetails).

5. Volte ao painel Golden -> Personalização de Faturas (PDF) -> Aba
   "Gateway WHMCS & Plugin" para validar a conexão em tempo real!
TXT;
    }
}
