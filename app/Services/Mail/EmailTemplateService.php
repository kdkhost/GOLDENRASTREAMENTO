<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestra o layout responsivo global (separado do conteúdo) e o catálogo
 * de templates transacionais editáveis no painel administrativo.
 */
class EmailTemplateService
{
    public const LAYOUT_SETTING_KEY = 'email_layout_config';

    /**
     * Configuração padrão do layout externo responsivo de e-mails.
     *
     * @return array{
     *   marca_nome: string,
     *   subtitulo: string,
     *   logo_url: string,
     *   cor_fundo_pagina: string,
     *   cor_cartao: string,
     *   cor_cabecalho_bg: string,
     *   cor_cabecalho_texto: string,
     *   cor_destaque: string,
     *   cor_texto: string,
     *   cor_botao_bg: string,
     *   cor_botao_texto: string,
     *   cor_rodape_bg: string,
     *   cor_rodape_texto: string,
     *   largura_maxima_px: int,
     *   raio_borda_px: int,
     *   alinhamento_cabecalho: string,
     *   mostrar_barra_superior: bool,
     *   mostrar_pre_cabecalho: bool,
     *   cabecalho_extra_html: string,
     *   texto_rodape: string,
     *   rodape_legal: string,
     *   mostrar_whatsapp: bool,
     *   mostrar_links_rodape: bool,
     *   css_adicional: string
     * }
     */
    public function layoutPadrao(): array
    {
        $empresa = golden_empresa();

        return [
            'marca_nome' => $empresa['nome'] !== '' ? $empresa['nome'] : 'GOLDEN',
            'subtitulo' => $empresa['slogan'] !== '' ? $empresa['slogan'] : 'Rastreamento Veicular & Gestão',
            'logo_url' => (string) ($empresa['logo_url'] ?? ''),
            'cor_fundo_pagina' => '#f4f1e9',
            'cor_cartao' => '#ffffff',
            'cor_cabecalho_bg' => '#12161a',
            'cor_cabecalho_texto' => '#cfd6dd',
            'cor_destaque' => '#e0a33e',
            'cor_texto' => '#1b2127',
            'cor_botao_bg' => '#e0a33e',
            'cor_botao_texto' => '#12161a',
            'cor_rodape_bg' => '#faf7f0',
            'cor_rodape_texto' => '#6b7280',
            'largura_maxima_px' => 580,
            'raio_borda_px' => 16,
            'alinhamento_cabecalho' => 'left',
            'mostrar_barra_superior' => true,
            'mostrar_pre_cabecalho' => true,
            'cabecalho_extra_html' => '',
            'texto_rodape' => 'Dúvidas? Responda a este e-mail ou fale com o nosso atendimento.',
            'rodape_legal' => 'Golden Rastreamento · Comunicação transacional automática.',
            'mostrar_whatsapp' => true,
            'mostrar_links_rodape' => true,
            'css_adicional' => '',
        ];
    }

    /**
     * Retorna o layout atual mesclado com os valores padrão.
     *
     * @return array<string, mixed>
     */
    public function layoutAtual(): array
    {
        $padrao = $this->layoutPadrao();
        $salvo = Setting::get(self::LAYOUT_SETTING_KEY);

        if (! is_string($salvo) || trim($salvo) === '') {
            return $padrao;
        }

        $decodificado = json_decode($salvo, true);
        if (! is_array($decodificado)) {
            return $padrao;
        }

        $resultado = array_merge($padrao, $decodificado);

        if (trim((string) ($resultado['logo_url'] ?? '')) === '') {
            $resultado['logo_url'] = (string) ($padrao['logo_url'] ?? '');
        }

        $marcaSalva = trim((string) ($resultado['marca_nome'] ?? ''));
        if ($marcaSalva === '' || strcasecmp($marcaSalva, 'GOLDEN') === 0) {
            $resultado['marca_nome'] = (string) ($padrao['marca_nome'] ?? 'GOLDEN');
        }

        return $resultado;
    }

    /**
     * Salva o layout responsivo global separadamente dos templates.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function salvarLayout(array $dados): array
    {
        $padrao = $this->layoutPadrao();
        $limpo = [];

        foreach ($padrao as $chave => $valorPadrao) {
            if (is_bool($valorPadrao)) {
                $limpo[$chave] = (bool) ($dados[$chave] ?? false);
            } elseif (is_int($valorPadrao)) {
                $numero = (int) ($dados[$chave] ?? $valorPadrao);
                $limpo[$chave] = $chave === 'largura_maxima_px'
                    ? max(440, min(720, $numero))
                    : max(0, min(28, $numero));
            } elseif (str_starts_with($chave, 'cor_')) {
                $limpo[$chave] = $this->higienizarCor((string) ($dados[$chave] ?? $valorPadrao), (string) $valorPadrao);
            } else {
                $limpo[$chave] = (string) ($dados[$chave] ?? $valorPadrao);
            }
        }

        if (! in_array($limpo['alinhamento_cabecalho'], ['left', 'center'], true)) {
            $limpo['alinhamento_cabecalho'] = 'left';
        }

        Setting::set(self::LAYOUT_SETTING_KEY, json_encode($limpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $limpo;
    }

    /**
     * Restaura o layout responsivo global ao padrão de fábrica.
     */
    public function restaurarLayout(): void
    {
        Setting::forget(self::LAYOUT_SETTING_KEY);
    }

    /**
     * Catálogo padrão dos templates personalizáveis no painel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogoPadrao(): array
    {
        return [
            [
                'chave' => 'segunda_via_fatura',
                'nome' => 'Segunda Via de Fatura (Portal / Assistente)',
                'categoria' => 'financeiro',
                'descricao' => 'Disparado quando o cliente solicita a 2ª via da mensalidade no portal ou com a Luna.',
                'assunto' => 'Fatura {{fatura_numero}} · Golden Rastreamento',
                'pre_cabecalho' => 'Sua fatura {{fatura_numero}} no valor de {{fatura_valor}} está disponível.',
                'rota_preview' => '/admin/templates-email/segunda_via_fatura',
                'variaveis' => [
                    'cliente_nome' => 'Nome do titular',
                    'fatura_numero' => 'Número da fatura (ex: GR-501)',
                    'fatura_valor' => 'Valor formatado (ex: R$ 59,90)',
                    'fatura_vencimento' => 'Data de vencimento (ex: 10/10/2026)',
                    'fatura_status' => 'Situação da fatura (Em aberto, Vencida, Paga)',
                    'fatura_url' => 'Link seguro para visualização/pagamento',
                    'whmcs_metodo' => 'Forma de pagamento registrada',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Olá, {{cliente_nome}}</h1>
<p>Segue a segunda via solicitada da sua fatura na Golden Rastreamento.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;margin:16px 0;background:#faf8f2;">
    <tr>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Fatura</td>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:700;">{{fatura_numero}}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Valor</td>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:700;font-size:16px;">{{fatura_valor}}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Vencimento</td>
        <td style="padding:12px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:600;">{{fatura_vencimento}}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Situação</td>
        <td style="padding:12px 16px;text-align:right;font-weight:600;">{{fatura_status}}</td>
    </tr>
</table>
<p style="margin:22px 0;">
    <a href="{{fatura_url}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Abrir fatura para pagamento
    </a>
</p>
<p style="font-size:12px;color:#6b7280;">Caso o botão não funcione, acesse:<br><span style="word-break:break-all;">{{fatura_url}}</span></p>
HTML,
            ],
            [
                'chave' => 'fatura_pdf_anexo',
                'nome' => 'Fatura em PDF Anexo + Gateway WHMCS',
                'categoria' => 'financeiro',
                'descricao' => 'Enviado automaticamente ou manualmente com a fatura em PDF cirúrgica anexada e dados do gateway.',
                'assunto' => 'Fatura {{fatura_numero}} em PDF · Vencimento {{fatura_vencimento}}',
                'pre_cabecalho' => 'Fatura {{fatura_numero}} ({{fatura_valor}}) anexada em PDF com opções de pagamento.',
                'rota_preview' => '/admin/templates-email/fatura_pdf_anexo',
                'variaveis' => [
                    'cliente_nome' => 'Nome do cliente',
                    'fatura_numero' => 'Número da fatura',
                    'fatura_valor' => 'Valor total',
                    'fatura_vencimento' => 'Vencimento',
                    'gateway_nome' => 'Gateway do WHMCS ativo',
                    'pix_copia_cola' => 'Código PIX Copia e Cola (quando aplicável)',
                    'linha_digitavel' => 'Linha digitável do boleto (quando aplicável)',
                    'fatura_url' => 'URL de pagamento online',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Olá, {{cliente_nome}}</h1>
<p>Sua fatura <strong>{{fatura_numero}}</strong> já foi emitida e segue <strong>anexada neste e-mail em formato PDF</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;margin:16px 0;background:#faf8f2;">
    <tr>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Número</td>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:700;">{{fatura_numero}}</td>
    </tr>
    <tr>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Valor total</td>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:700;">{{fatura_valor}}</td>
    </tr>
    <tr>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Vencimento</td>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:600;">{{fatura_vencimento}}</td>
    </tr>
    <tr>
        <td style="padding:11px 16px;color:#6b7280;font-size:13px;">Forma de pagamento</td>
        <td style="padding:11px 16px;text-align:right;font-weight:600;">{{gateway_nome}}</td>
    </tr>
</table>
<p style="font-size:13px;background:#f8fafc;border-left:3px solid #e0a33e;padding:10px 14px;border-radius:8px;">
    <strong>PIX / Linha Digitável:</strong><br>
    <span style="font-family:monospace;word-break:break-all;">{{pix_copia_cola}}</span>
</p>
<p style="margin:22px 0;">
    <a href="{{fatura_url}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Pagar Fatura Online
    </a>
</p>
HTML,
            ],
            [
                'chave' => 'fatura_lembrete_vencimento',
                'nome' => 'Lembrete de Vencimento de Fatura',
                'categoria' => 'financeiro',
                'descricao' => 'Aviso cordial antes do vencimento para evitar suspensão do rastreamento.',
                'assunto' => 'Lembrete: fatura {{fatura_numero}} vence em {{fatura_vencimento}}',
                'pre_cabecalho' => 'Evite atrasos: sua fatura {{fatura_numero}} vence em {{fatura_vencimento}}.',
                'rota_preview' => '/admin/templates-email/fatura_lembrete_vencimento',
                'variaveis' => [
                    'cliente_nome' => 'Nome do cliente',
                    'fatura_numero' => 'Número da fatura',
                    'fatura_valor' => 'Valor total',
                    'fatura_vencimento' => 'Data de vencimento',
                    'fatura_url' => 'Link da fatura',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Lembrete de vencimento</h1>
<p>Olá, <strong>{{cliente_nome}}</strong>. Este é apenas um lembrete de que a sua fatura <strong>{{fatura_numero}}</strong> no valor de <strong>{{fatura_valor}}</strong> vence em <strong>{{fatura_vencimento}}</strong>.</p>
<p style="margin:22px 0;">
    <a href="{{fatura_url}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Acessar Fatura Agora
    </a>
</p>
<p style="font-size:12px;color:#6b7280;">Se o pagamento já foi realizado, desconsidere este aviso automático.</p>
HTML,
            ],
            [
                'chave' => 'fatura_confirmada_whmcs',
                'nome' => 'Pagamento Confirmado (WHMCS)',
                'categoria' => 'financeiro',
                'descricao' => 'Recibo automático disparado quando o WHMCS confirma a liquidação da fatura.',
                'assunto' => 'Pagamento confirmado · Fatura {{fatura_numero}}',
                'pre_cabecalho' => 'Recebemos o pagamento da fatura {{fatura_numero}}.',
                'rota_preview' => '/admin/templates-email/fatura_confirmada_whmcs',
                'variaveis' => [
                    'cliente_nome' => 'Nome do cliente',
                    'fatura_numero' => 'Número da fatura',
                    'fatura_valor' => 'Valor pago',
                    'data_pagamento' => 'Data da confirmação',
                    'gateway_nome' => 'Gateway utilizado',
                    'fatura_url' => 'Link do recibo',
                ],
                'corpo_html' => <<<'HTML'
<div style="display:inline-block;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;margin-bottom:10px;">PAGAMENTO CONFIRMADO</div>
<h1 style="font-size:20px;margin:0 0 8px;">Obrigado, {{cliente_nome}}!</h1>
<p>Confirmamos o pagamento da sua fatura <strong>{{fatura_numero}}</strong> no valor de <strong>{{fatura_valor}}</strong> via <strong>{{gateway_nome}}</strong> em {{data_pagamento}}.</p>
<p style="margin:20px 0;">
    <a href="{{fatura_url}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Visualizar Comprovante
    </a>
</p>
HTML,
            ],
            [
                'chave' => 'pedido_recebido',
                'nome' => 'Confirmação de Pedido na Loja',
                'categoria' => 'loja',
                'descricao' => 'Enviado logo após o fechamento do pedido na loja Golden.',
                'assunto' => 'Pedido {{pedido_numero}} recebido · Golden Rastreamento',
                'pre_cabecalho' => 'Recebemos seu pedido {{pedido_numero}} com sucesso.',
                'rota_preview' => '/admin/templates-email/pedido_recebido',
                'variaveis' => [
                    'cliente_nome' => 'Nome do comprador',
                    'pedido_numero' => 'Código do pedido',
                    'pedido_total' => 'Valor total do pedido',
                    'url_rastreio' => 'Link público de acompanhamento',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Pedido recebido, {{cliente_nome}}!</h1>
<p>Confirmamos o recebimento do seu pedido <strong>{{pedido_numero}}</strong> no valor total de <strong>{{pedido_total}}</strong>.</p>
<p>Nossa equipe técnica já iniciou a separação, configuração e testes do seu equipamento.</p>
<p style="margin:22px 0;">
    <a href="{{url_rastreio}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Acompanhar Meu Pedido
    </a>
</p>
HTML,
            ],
            [
                'chave' => 'pedido_enviado_rastreio',
                'nome' => 'Pedido Enviado + Código de Rastreio',
                'categoria' => 'loja',
                'descricao' => 'Enviado quando o rastreamento é lançado no pedido da loja.',
                'assunto' => 'Seu pedido {{pedido_numero}} foi enviado · Rastreio {{codigo_rastreio}}',
                'pre_cabecalho' => 'Código de rastreio {{codigo_rastreio}} liberado.',
                'rota_preview' => '/admin/templates-email/pedido_enviado_rastreio',
                'variaveis' => [
                    'cliente_nome' => 'Nome do comprador',
                    'pedido_numero' => 'Código do pedido',
                    'codigo_rastreio' => 'Código da transportadora',
                    'transportadora' => 'Transportadora responsável',
                    'url_rastreio' => 'URL de rastreamento em tempo real',
                ],
                'corpo_html' => <<<'HTML'
<div style="display:inline-block;background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;margin-bottom:10px;">PEDIDO DESPACHADO</div>
<h1 style="font-size:20px;margin:0 0 8px;">Seu pedido está a caminho, {{cliente_nome}}!</h1>
<p>O pedido <strong>{{pedido_numero}}</strong> foi postado via <strong>{{transportadora}}</strong>.</p>
<div style="background:#faf7f0;border:2px dashed #e0a33e;border-radius:12px;padding:16px;text-align:center;margin:16px 0;">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#785c13;font-weight:700;">Código de Rastreamento</div>
    <div style="font-size:21px;font-weight:800;color:#12161a;margin-top:4px;">{{codigo_rastreio}}</div>
</div>
<p style="margin:22px 0;text-align:center;">
    <a href="{{url_rastreio}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Rastrear Entrega em Tempo Real
    </a>
</p>
HTML,
            ],
            [
                'chave' => 'redefinir_senha_portal',
                'nome' => 'Recuperação de Senha do Portal',
                'categoria' => 'portal',
                'descricao' => 'Link temporário assinado para o cliente definir uma nova senha no portal.',
                'assunto' => 'Recuperação de acesso ao portal · Golden Rastreamento',
                'pre_cabecalho' => 'Use o link seguro para redefinir sua senha.',
                'rota_preview' => '/admin/templates-email/redefinir_senha_portal',
                'variaveis' => [
                    'cliente_nome' => 'Nome do cliente',
                    'link_redefinicao' => 'Link assinado de redefinição',
                    'minutos_validade' => 'Minutos até expirar',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Olá, {{cliente_nome}}</h1>
<p>Recebemos um pedido de redefinição de senha para a sua conta do Portal Golden. O link abaixo vale por <strong>{{minutos_validade}} minutos</strong>.</p>
<p style="margin:22px 0;">
    <a href="{{link_redefinicao}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Definir Minha Nova Senha
    </a>
</p>
<p style="font-size:12px;color:#6b7280;">Se você não pediu essa troca, basta ignorar esta mensagem.</p>
HTML,
            ],
            [
                'chave' => 'chamado_resposta',
                'nome' => 'Atualização / Resposta em Chamado',
                'categoria' => 'suporte',
                'descricao' => 'Notifica o cliente quando a equipe responde a um ticket de suporte.',
                'assunto' => 'Nova resposta no chamado {{chamado_protocolo}}',
                'pre_cabecalho' => 'Seu atendimento {{chamado_protocolo}} recebeu uma atualização.',
                'rota_preview' => '/admin/templates-email/chamado_resposta',
                'variaveis' => [
                    'cliente_nome' => 'Nome do cliente',
                    'chamado_protocolo' => 'Número do protocolo',
                    'chamado_assunto' => 'Assunto do chamado',
                    'resposta_resumo' => 'Trecho da resposta',
                    'url_portal' => 'Link direto para o chamado',
                ],
                'corpo_html' => <<<'HTML'
<h1 style="font-size:20px;margin:0 0 8px;">Atualização no chamado {{chamado_protocolo}}</h1>
<p>Olá, <strong>{{cliente_nome}}</strong>. Nossa equipe respondeu ao chamado <strong>{{chamado_assunto}}</strong>:</p>
<blockquote style="margin:14px 0;padding:12px 16px;background:#faf7f0;border-left:3px solid #e0a33e;border-radius:8px;color:#374151;">
    {{resposta_resumo}}
</blockquote>
<p style="margin:22px 0;">
    <a href="{{url_portal}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Responder no Portal
    </a>
</p>
HTML,
            ],
            [
                'chave' => 'alerta_empresa',
                'nome' => 'Alerta Operacional da Central',
                'categoria' => 'sistema',
                'descricao' => 'Alertas internos para a equipe (leads novos, pedidos pagos, eventos de logística).',
                'assunto' => '[Golden] {{titulo_alerta}}',
                'pre_cabecalho' => '{{tipo_alerta}} · {{titulo_alerta}}',
                'rota_preview' => '/admin/templates-email/alerta_empresa',
                'variaveis' => [
                    'titulo_alerta' => 'Título do alerta',
                    'tipo_alerta' => 'Categoria do evento',
                    'mensagem_alerta' => 'Descrição detalhada',
                    'url_painel' => 'Link direto do painel',
                ],
                'corpo_html' => <<<'HTML'
<div style="display:inline-block;background:#12161a;color:#e0a33e;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;margin-bottom:10px;">{{tipo_alerta}}</div>
<h1 style="font-size:20px;margin:0 0 8px;">{{titulo_alerta}}</h1>
<p style="background:#f8fafc;border-left:3px solid #e0a33e;padding:12px 16px;border-radius:8px;">{{mensagem_alerta}}</p>
<p style="margin:22px 0;">
    <a href="{{url_painel}}" data-btn="true" class="golden-btn" style="display:inline-block;background:#e0a33e;color:#12161a;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:10px;">
        Abrir no Painel Administrativo
    </a>
</p>
HTML,
            ],
        ];
    }

    /**
     * Garante que todos os templates padrão estejam gravados na tabela.
     *
     * @return Collection<int, EmailTemplate>
     */
    public function garantirPadroes(): Collection
    {
        if (! Schema::hasTable('email_templates')) {
            return collect();
        }

        $existentes = EmailTemplate::pluck('chave')->all();

        foreach ($this->catalogoPadrao() as $item) {
            if (! in_array($item['chave'], $existentes, true)) {
                EmailTemplate::create(array_merge($item, [
                    'usar_layout' => true,
                    'ativo' => true,
                ]));
            }
        }

        return EmailTemplate::orderBy('categoria')->orderBy('nome')->get();
    }

    /**
     * Restaura um template específico para o modelo de fábrica.
     */
    public function restaurarTemplate(EmailTemplate $template): EmailTemplate
    {
        $padrao = collect($this->catalogoPadrao())->firstWhere('chave', $template->chave);
        if (is_array($padrao)) {
            $template->update([
                'nome' => $padrao['nome'],
                'categoria' => $padrao['categoria'],
                'descricao' => $padrao['descricao'],
                'assunto' => $padrao['assunto'],
                'pre_cabecalho' => $padrao['pre_cabecalho'],
                'corpo_html' => $padrao['corpo_html'],
                'variaveis' => $padrao['variaveis'],
                'usar_layout' => true,
                'ativo' => true,
            ]);
        }

        return $template->fresh();
    }

    /**
     * Dados de exemplo usados nos previews em tempo real.
     *
     * @return array<string, string>
     */
    public function exemplosVariaveis(): array
    {
        return [
            'cliente_nome' => 'Mariana Oliveira Silva',
            'fatura_numero' => 'GR-2026-8841',
            'fatura_valor' => 'R$ 129,90',
            'fatura_vencimento' => now()->addDays(5)->format('d/m/Y'),
            'fatura_status' => 'Em aberto',
            'fatura_url' => url('/minha-conta/faturas'),
            'whmcs_metodo' => 'PIX Instantâneo · WHMCS',
            'gateway_nome' => 'PIX Banco Inter / WHMCS',
            'pix_copia_cola' => '00020126580014br.gov.bcb.pix0136golden-rastreamento-demo-pix-5204000053039865802BR',
            'linha_digitavel' => '23793.38128 60000.000003 00000.000401 1 99990000012990',
            'data_pagamento' => now()->format('d/m/Y H:i'),
            'pedido_numero' => 'GR-9045',
            'pedido_total' => 'R$ 349,90',
            'codigo_rastreio' => 'AA123456789BR',
            'transportadora' => 'Correios SEDEX',
            'url_rastreio' => url('/rastrear?codigo=AA123456789BR'),
            'link_redefinicao' => url('/portal/redefinir-senha/token-demonstracao'),
            'minutos_validade' => '60',
            'chamado_protocolo' => '#CH-4102',
            'chamado_assunto' => 'Configuração de alerta de ignição',
            'resposta_resumo' => 'Realizamos a calibração remota do módulo e os alertas de ignição já estão ativos.',
            'url_portal' => url('/minha-conta/chamados'),
            'titulo_alerta' => 'Novo pedido confirmado na loja',
            'tipo_alerta' => 'Pedido Pago',
            'mensagem_alerta' => 'Um cliente concluiu o pagamento do pedido GR-9045 com frete expresso.',
            'url_painel' => url('/admin/dashboard'),
        ];
    }

    /**
     * Substitui tokens `{{chave}}` com segurança HTML no corpo ou assunto.
     *
     * @param  array<string, mixed>  $variaveis
     */
    public function substituirVariaveis(string $texto, array $variaveis, bool $escaparHtml = true): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($variaveis, $escaparHtml): string {
            $chave = $matches[1];
            if (! array_key_exists($chave, $variaveis)) {
                return '';
            }

            $valor = (string) $variaveis[$chave];
            if (! $escaparHtml || str_ends_with($chave, '_html')) {
                return $valor;
            }

            return e($valor);
        }, $texto);
    }

    /**
     * Envolve um fragmento de corpo HTML no layout externo responsivo.
     *
     * @param  array<string, mixed>|null  $layoutOverride
     */
    public function envolverNoLayout(
        string $corpoHtml,
        string $assunto = '',
        ?string $preCabecalho = null,
        ?array $layoutOverride = null
    ): string {
        $empresa = golden_empresa();
        $layout = $layoutOverride ? array_merge($this->layoutAtual(), $layoutOverride) : $this->layoutAtual();

        if (trim((string) ($layout['logo_url'] ?? '')) === '') {
            $layout['logo_url'] = (string) ($empresa['logo_url'] ?? '');
        }

        $whatsapp = $empresa['whatsapp'] !== ''
            ? $empresa['whatsapp']
            : (string) Setting::get('contato_whatsapp', config('golden.whatsapp', ''));

        return view('mail.layout-responsivo', [
            'layout' => $layout,
            'assunto' => $assunto,
            'preCabecalho' => $preCabecalho ?? '',
            'corpoHtml' => $corpoHtml,
            'whatsapp' => $whatsapp,
            'empresa' => $empresa,
        ])->render();
    }

    /**
     * Variáveis padrão com dados atuais da empresa configurada no painel administrativo.
     *
     * @return array<string, string>
     */
    public function variaveisEmpresa(): array
    {
        $empresa = golden_empresa();

        return [
            'empresa_nome' => $empresa['nome'],
            'empresa_documento' => $empresa['documento'],
            'empresa_endereco' => $empresa['endereco_completo'],
            'empresa_cidade' => $empresa['cidade_uf'],
            'empresa_cep' => $empresa['cep'],
            'empresa_telefone' => $empresa['telefone'],
            'empresa_whatsapp' => $empresa['whatsapp'],
            'empresa_email' => $empresa['email'],
            'empresa_logo_url' => $empresa['logo_url'],
        ];
    }

    /**
     * Renderiza um template salvo (ou fallback do catálogo) com variáveis e layout separado.
     *
     * @param  array<string, mixed>  $variaveis
     * @param  array<string, mixed>|null  $layoutOverride
     * @return array{assunto: string, html: string, texto: string, usar_layout: bool}
     */
    public function renderizarPorChave(
        string $chave,
        array $variaveis,
        ?array $layoutOverride = null,
        ?string $corpoOverride = null,
        ?string $assuntoOverride = null,
        ?string $preCabecalhoOverride = null,
        ?bool $usarLayoutOverride = null
    ): array {
        $template = Schema::hasTable('email_templates') ? EmailTemplate::porChave($chave) : null;
        $fallback = collect($this->catalogoPadrao())->firstWhere('chave', $chave);

        $assuntoBase = $assuntoOverride ?? $template?->assunto ?? ($fallback['assunto'] ?? 'Comunicação Golden');
        $preCabecalhoBase = $preCabecalhoOverride ?? $template?->pre_cabecalho ?? ($fallback['pre_cabecalho'] ?? '');
        $corpoBase = $corpoOverride ?? $template?->corpo_html ?? ($fallback['corpo_html'] ?? '<p>Mensagem sem conteúdo.</p>');
        $usarLayout = $usarLayoutOverride ?? ($template ? $template->usar_layout : true);

        $variaveis = array_merge($this->variaveisEmpresa(), $variaveis);
        $assunto = $this->substituirVariaveis($assuntoBase, $variaveis, false);
        $preCabecalho = $this->substituirVariaveis((string) $preCabecalhoBase, $variaveis, false);
        $corpoResolvido = $this->substituirVariaveis($corpoBase, $variaveis, true);

        $html = $usarLayout
            ? $this->envolverNoLayout($corpoResolvido, $assunto, $preCabecalho, $layoutOverride)
            : $corpoResolvido;

        $textoLimpo = trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>', '</h1>', '</h2>'], "\n", $corpoResolvido))) ?? '');

        return [
            'assunto' => $assunto,
            'html' => $html,
            'texto' => $textoLimpo,
            'usar_layout' => $usarLayout,
        ];
    }

    /**
     * Higieniza uma cor hexadecimal recebida da interface.
     */
    private function higienizarCor(string $valor, string $padrao): string
    {
        $valor = trim($valor);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor) === 1) {
            return $valor;
        }

        return $padrao;
    }
}
