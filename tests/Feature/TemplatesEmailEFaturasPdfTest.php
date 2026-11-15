<?php

namespace Tests\Feature;

use App\Mail\SegundaViaFatura;
use App\Mail\TemplateCustomizadoMail;
use App\Models\Customer;
use App\Models\EmailFila;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Fatura\FaturaPdfService;
use App\Services\Mail\EmailTemplateService;
use App\Services\Mail\FilaDeEmailService;
use App\Services\Whmcs\WhmcsPluginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use ZipArchive;

class TemplatesEmailEFaturasPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    private function ativarWhmcsFake(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'api-id',
            'whmcs.secret' => 'api-secret',
            'whmcs.timeout' => 5,
            'whmcs.veiculacao.faturas' => true,
        ]);

        Http::fake(function (RequisicaoHttp $req) {
            $acao = (string) ($req['action'] ?? '');

            return match ($acao) {
                'GetPaymentMethods' => Http::response([
                    'result' => 'success',
                    'paymentmethods' => [
                        'paymentmethod' => [
                            ['module' => 'efipix', 'displayname' => 'PIX Banco Efi'],
                            ['module' => 'boletoasaas', 'displayname' => 'Boleto Asaas'],
                        ],
                    ],
                ]),
                'GetInvoice' => (int) ($req['invoiceid'] ?? 0) === 501
                    ? Http::response([
                        'result' => 'success',
                        'invoiceid' => 501,
                        'invoicenum' => 'GR-501',
                        'userid' => 77,
                        'date' => '2026-10-01',
                        'duedate' => '2026-10-10',
                        'subtotal' => '59.90',
                        'tax' => '0.00',
                        'tax2' => '0.00',
                        'credit' => '0.00',
                        'total' => '59.90',
                        'balance' => '59.90',
                        'status' => 'Unpaid',
                        'paymentmethod' => 'efipix',
                        'notes' => 'Plano mensal Golden',
                        'items' => [
                            'item' => [
                                ['id' => 1, 'type' => 'Hosting', 'description' => 'Rastreamento Frota 24h', 'amount' => '59.90', 'taxed' => 0],
                            ],
                        ],
                    ])
                    : Http::response([
                        'result' => 'success',
                        'invoiceid' => 999,
                        'userid' => 999,
                        'total' => '120.00',
                        'status' => 'Unpaid',
                    ]),
                'goldengetinvoicepaymentdata' => Http::response([
                    'result' => 'success',
                    'plugin' => 'golden_rastreamento',
                    'version' => '1.0.0',
                    'pong' => true,
                    'gateway_module' => 'efipix',
                    'gateway_display_name' => 'PIX Instantâneo Efi',
                    'gateway_type' => 'pix',
                    'pix_copia_cola' => '00020126580014br.gov.bcb.pix0136fatura-501-teste5204000053039865802BR',
                    'linha_digitavel' => '',
                    'checkout_url' => 'https://financeiro.exemplo.com.br/viewinvoice.php?id=501',
                    'cliente' => [
                        'nome' => 'Maria Compradora',
                        'email' => 'maria@exemplo.com.br',
                        'documento' => '123.456.789-01',
                    ],
                ]),
                default => Http::response(['result' => 'success']),
            };
        });
    }

    public function test_painel_lista_rotas_de_templates_e_edita_layout_responsivo_separado(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.templates-email.index'))
            ->assertOk()
            ->assertSee('Segunda Via de Fatura');

        $this->actingAs($this->admin)
            ->get(route('admin.templates-email.edit', 'segunda_via_fatura'))
            ->assertOk()
            ->assertSee('Preview em Tempo Real');

        // Atualiza o layout global separado
        $this->actingAs($this->admin)
            ->put(route('admin.templates-email.layout.update'), [
                'marca_nome' => 'GOLDEN VIP',
                'subtitulo' => 'Monitoramento 24h',
                'logo_url' => '',
                'cor_fundo_pagina' => '#f5f2eb',
                'cor_cartao' => '#ffffff',
                'cor_cabecalho_bg' => '#101418',
                'cor_cabecalho_texto' => '#f3f4f6',
                'cor_destaque' => '#d97706',
                'cor_texto' => '#111827',
                'cor_botao_bg' => '#d97706',
                'cor_botao_texto' => '#12161a',
                'cor_rodape_bg' => '#faf7f0',
                'cor_rodape_texto' => '#6b7280',
                'largura_maxima_px' => 620,
                'raio_borda_px' => 14,
                'alinhamento_cabecalho' => 'center',
                'mostrar_barra_superior' => '1',
                'mostrar_pre_cabecalho' => '1',
                'mostrar_whatsapp' => '1',
                'mostrar_links_rodape' => '1',
            ])
            ->assertRedirect(route('admin.templates-email.layout'));

        // Preview ao vivo de um template específico já herda o layout separado sem editar o corpo
        $previewResp = $this->actingAs($this->admin)
            ->postJson(route('admin.templates-email.preview', 'segunda_via_fatura'), [
                'assunto' => 'Segunda via {{fatura_numero}}',
                'corpo_html' => '<p>Corpo digitado ao vivo para {{cliente_nome}}.</p>',
                'usar_layout' => '1',
            ])
            ->assertOk()
            ->assertJsonStructure(['assunto', 'html']);

        $this->assertStringContainsString('GOLDEN VIP', $previewResp->json('html'));
        $this->assertStringContainsString('@media only screen and (max-width: 620px)', $previewResp->json('html'));
        $this->assertStringContainsString('Corpo digitado ao vivo para Mariana Oliveira Silva.', $previewResp->json('html'));
        $this->assertSame('Segunda via GR-2026-8841', $previewResp->json('assunto'));

        // Salvar e restaurar corpo do template mantém independência
        $this->actingAs($this->admin)
            ->put(route('admin.templates-email.update', 'segunda_via_fatura'), [
                'nome' => 'Segunda Via Personalizada',
                'assunto' => 'Fatura Custom {{fatura_numero}}',
                'corpo_html' => '<p>Texto novo {{fatura_valor}}</p>',
                'usar_layout' => '1',
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.templates-email.edit', 'segunda_via_fatura'));

        $this->assertSame('Fatura Custom {{fatura_numero}}', EmailTemplate::porChave('segunda_via_fatura')?->assunto);

        $this->actingAs($this->admin)
            ->post(route('admin.templates-email.restaurar', 'segunda_via_fatura'))
            ->assertRedirect(route('admin.templates-email.edit', 'segunda_via_fatura'));

        $this->assertSame('Fatura {{fatura_numero}} · Golden Rastreamento', EmailTemplate::porChave('segunda_via_fatura')?->assunto);
    }

    public function test_personalizador_arrasta_e_solta_gera_a4_cirurgico_e_pdf_binario_valido(): void
    {
        $this->ativarWhmcsFake();

        $this->actingAs($this->admin)
            ->get(route('admin.faturas-modelo.index'))
            ->assertOk()
            ->assertSee('PIX Banco Efi');

        $pdfService = app(FaturaPdfService::class);
        $modelo = $pdfService->configuracaoPadrao();

        // Simula arrasta e solta: move pagamento_gateway antes de itens_fatura, oculta codigo_barras_boleto e muda largura
        $blocosReordenados = collect($modelo['blocos'])->map(function (array $b): array {
            return match ($b['id']) {
                'pagamento_gateway' => array_merge($b, ['ordem' => 2, 'largura' => 50, 'fundo' => '#fff9eb']),
                'itens_fatura' => array_merge($b, ['ordem' => 5, 'largura' => 100]),
                'codigo_barras_boleto' => array_merge($b, ['visivel' => false]),
                default => $b,
            };
        })->sortBy('ordem')->values()->all();

        $this->actingAs($this->admin)
            ->put(route('admin.faturas-modelo.update'), [
                'modelo_json' => json_encode([
                    'estilo' => array_merge($modelo['estilo'], ['emitente_nome' => 'Golden Cirúrgica S.A.']),
                    'blocos' => $blocosReordenados,
                ]),
            ])
            ->assertRedirect(route('admin.faturas-modelo.index'));

        // Verifica impressão cirúrgica A4 respeitando blocos ocultos e ordem
        $impressao = $this->actingAs($this->admin)
            ->get(route('admin.faturas-modelo.imprimir'))
            ->assertOk()
            ->assertSee('Golden Cirúrgica S.A.')
            ->assertSee('width: 210mm', false)
            ->assertDontSee('Linha Digitável &amp; Ficha de Compensação', false);

        $html = $impressao->getContent();
        $this->assertLessThan(
            strpos($html, 'data-bloco-id="itens_fatura"'),
            strpos($html, 'data-bloco-id="pagamento_gateway"')
        );

        // Verifica geração binária PDF 1.4 real
        $respPdf = $this->actingAs($this->admin)
            ->get(route('admin.faturas-modelo.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $bytesPdf = $respPdf->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $bytesPdf);
        $this->assertStringContainsString('%%EOF', $bytesPdf);
    }

    public function test_download_plugin_whmcs_e_webhook_enfileira_fatura_pdf_com_anti_spam(): void
    {
        $this->ativarWhmcsFake();
        Mail::fake();

        // 1. Download do plugin instalável ZIP para WHMCS
        $zipResp = $this->actingAs($this->admin)
            ->get(route('admin.faturas-modelo.whmcs-plugin'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $tmpZip = tempnam(sys_get_temp_dir(), 'test_whmcs_zip_');
        file_put_contents($tmpZip, $zipResp->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmpZip) === true);
        $this->assertNotFalse($zip->locateName('modules/addons/golden_rastreamento/golden_rastreamento.php'));
        $this->assertNotFalse($zip->locateName('modules/addons/golden_rastreamento/hooks.php'));
        $this->assertNotFalse($zip->locateName('includes/api/goldengetinvoicepaymentdata.php'));
        $this->assertNotFalse($zip->locateName('LEIAME-INSTALACAO.txt'));
        $addonPhp = (string) $zip->getFromName('modules/addons/golden_rastreamento/golden_rastreamento.php');
        $zip->close();
        @unlink($tmpZip);

        $segredo = app(WhmcsPluginService::class)->obterOuGerarSegredo();
        $this->assertStringContainsString($segredo, $addonPhp);
        $this->assertStringContainsString('/api/webhooks/whmcs', $addonPhp);

        // 2. Webhook rejeita requisição sem assinatura
        $this->postJson('/api/webhooks/whmcs', [
            'evento' => 'InvoiceCreated',
            'invoiceid' => 501,
        ])->assertUnauthorized();

        // 3. Webhook autenticado com X-Golden-Signature enfileira fatura PDF na fila anti-spam
        $payload = json_encode([
            'evento' => 'InvoiceCreated',
            'invoiceid' => 501,
        ]);
        $assinatura = 'sha256='.hash_hmac('sha256', (string) $payload, $segredo);

        $this->call('POST', '/api/webhooks/whmcs', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GOLDEN_SIGNATURE' => $assinatura,
        ], (string) $payload)->assertStatus(202);

        $item = EmailFila::latest('id')->firstOrFail();
        $this->assertSame('maria@exemplo.com.br', $item->destinatario_email);
        $this->assertSame('fatura_pdf_anexo', $item->template_chave);
        $this->assertSame('fatura_pdf', $item->anexos[0]['tipo']);

        // Garante que cadência espaça segundo envio
        $filaService = app(FilaDeEmailService::class);
        $filaService->salvarConfiguracoes([
            'ativo' => true,
            'intervalo_entre_envios_seg' => 30,
            'limite_por_minuto' => 10,
            'limite_por_hora' => 50,
            'max_tentativas' => 3,
        ]);
        $segundo = $filaService->enfileirarTemplate('segunda_via_fatura', 'outro@exemplo.com.br', [
            'cliente_nome' => 'Carlos',
            'fatura_numero' => 'GR-502',
            'fatura_valor' => 'R$ 79,90',
            'fatura_vencimento' => '15/10/2026',
            'fatura_status' => 'Em aberto',
            'fatura_url' => 'https://exemplo.com/502',
            'whmcs_metodo' => 'PIX',
        ]);

        $this->assertGreaterThanOrEqual(
            $item->agendado_para->timestamp + 29,
            $segundo->agendado_para->timestamp
        );

        // Força o primeiro item estar pronto agora e executa comando Artisan
        $item->update(['agendado_para' => now()->subSecond()]);

        $this->artisan('golden:emails:processar-fila --limite=20')
            ->assertExitCode(0);

        $this->assertSame(EmailFila::ENVIADO, $item->fresh()->status);

        Mail::assertSent(TemplateCustomizadoMail::class, function (TemplateCustomizadoMail $mail): bool {
            return $mail->hasTo('maria@exemplo.com.br')
                && count($mail->attachments()) === 1
                && str_ends_with($mail->attachments()[0]->as ?? 'fatura-GR-501.pdf', '.pdf');
        });
    }

    public function test_portal_cliente_gera_pdf_e_impressao_apenas_das_proprias_faturas(): void
    {
        $this->ativarWhmcsFake();

        $cliente = Customer::create([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '(11) 98888-7777',
            'email' => 'maria@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
            'whmcs_id' => 77,
        ]);

        // Dono obtém PDF e impressão A4 cirúrgica com gateway Efi/PIX do plugin
        $pdfResp = $this->actingAs($cliente, 'cliente')
            ->get(route('portal.faturas.pdf', 501))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdfResp->getContent());

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.faturas.imprimir', 501))
            ->assertOk()
            ->assertSee('PIX Instantâneo Efi')
            ->assertSee('R$ 59,90');

        // Fatura #999 pertence ao userid=999 no WHMCS -> bloqueada com 404
        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.faturas.pdf', 999))
            ->assertNotFound();
    }

    public function test_fatura_pdf_e_emails_exibem_logo_e_dados_da_empresa_e_studios_seguem_tema_dark_light(): void
    {
        Setting::set('site_nome', 'Golden Frota & Segurança Ltda');
        Setting::set('empresa_cnpj', '12.345.678/0001-90');
        Setting::set('empresa_endereco', 'Av. Brasil, 2500');
        Setting::set('empresa_cidade', 'Curitiba/PR');
        Setting::set('empresa_cep', '80010-000');
        Setting::set('contato_telefone', '(41) 3333-4444');
        Setting::set('contato_whatsapp', '(41) 99999-8888');
        Setting::set('contato_email', 'faturamento@goldenfrota.com.br');

        // 1. Fatura cirúrgica HTML e PDF nativo contêm logo e dados configurados no painel
        $faturaService = app(FaturaPdfService::class);
        $htmlFatura = $faturaService->renderizarHtmlCirurgico();
        $this->assertStringContainsString('<img', $htmlFatura);
        $this->assertStringContainsString('Golden Frota &amp; Segurança Ltda', $htmlFatura);
        $this->assertStringContainsString('12.345.678/0001-90', $htmlFatura);
        $this->assertStringContainsString('Av. Brasil, 2500', $htmlFatura);
        $this->assertStringContainsString('Curitiba/PR', $htmlFatura);
        $this->assertStringContainsString('80010-000', $htmlFatura);
        $this->assertStringContainsString('(41) 3333-4444', $htmlFatura);
        $this->assertStringContainsString('faturamento@goldenfrota.com.br', $htmlFatura);

        $pdfBinario = $faturaService->gerarPdfBinario();
        $this->assertStringStartsWith('%PDF-1.4', $pdfBinario);
        $this->assertStringContainsString('12.345.678/0001-90', $pdfBinario);
        $this->assertStringContainsString('Golden Frota', $pdfBinario);
        if (is_file(public_path('img/logo-golden.png')) && function_exists('imagecreatefromstring')) {
            $this->assertStringContainsString('/Subtype /Image', $pdfBinario);
            $this->assertStringContainsString('/XObject << /Im1', $pdfBinario);
        }

        // 2. Layout responsivo de e-mail exibe logo e dados da empresa configurados
        $emailService = app(EmailTemplateService::class);
        $htmlEmailLayout = $emailService->envolverNoLayout('<p>Corpo de teste</p>', 'Assunto de teste');
        $this->assertStringContainsString('<img', $htmlEmailLayout);
        $this->assertStringContainsString('Golden Frota &amp; Segurança Ltda', $htmlEmailLayout);
        $this->assertStringContainsString('12.345.678/0001-90', $htmlEmailLayout);
        $this->assertStringContainsString('Av. Brasil, 2500', $htmlEmailLayout);
        $this->assertStringContainsString('41999998888', $htmlEmailLayout);
        $this->assertStringContainsString('faturamento@goldenfrota.com.br', $htmlEmailLayout);

        // 3. Mailable avulso (SegundaViaFatura) exibe logo e dados da empresa
        $cliente = Customer::create([
            'nome' => 'Carlos Teste',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '(41) 98888-7777',
            'email' => 'carlos@teste.com.br',
            'email_portal' => 'carlos@teste.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
        ]);

        $htmlSegundaVia = (new SegundaViaFatura($cliente, [
            'numero' => 'FAT-888',
            'vencimento' => now()->addDays(5),
            'total_cents' => 14990,
            'saldo_cents' => 14990,
            'status' => 'Unpaid',
            'metodo' => 'PIX',
            'url' => 'https://example.test/fat',
        ]))->render();

        $this->assertStringContainsString('<img', $htmlSegundaVia);
        $this->assertStringContainsString('Golden Frota &amp; Segurança Ltda', $htmlSegundaVia);
        $this->assertStringContainsString('12.345.678/0001-90', $htmlSegundaVia);
        $this->assertStringContainsString('Av. Brasil, 2500', $htmlSegundaVia);
        $this->assertStringContainsString('faturamento@goldenfrota.com.br', $htmlSegundaVia);

        // 4. Views do estúdio seguem estritamente os tokens dark/light sem blocos <style> ou hex hardcoded
        foreach ([
            'admin/faturas-modelo/index.blade.php',
            'admin/templates-email/edit.blade.php',
            'admin/templates-email/layout.blade.php',
        ] as $viewRelativa) {
            $conteudoView = file_get_contents(resource_path('views/'.$viewRelativa));
            $this->assertNotFalse($conteudoView);
            $this->assertStringNotContainsString('<style>', $conteudoView);
            $this->assertStringNotContainsString('#22262b', $conteudoView);
            $this->assertStringNotContainsString('#f4f1e9', $conteudoView);
            $this->assertStringNotContainsString('#e2ddd1', $conteudoView);
            $this->assertStringNotContainsString('background: #ffffff', $conteudoView);
        }

        $cssAdmin = file_get_contents(public_path('css/admin-golden.css'));
        $this->assertNotFalse($cssAdmin);
        $this->assertStringContainsString('.bloco-dnd-item', $cssAdmin);
        $this->assertStringContainsString('.a4-preview-stage', $cssAdmin);
        $this->assertStringContainsString('.preview-frame', $cssAdmin);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $cssAdmin));
    }

    public function test_templates_de_email_criacao_edicao_e_layout_exibem_dados_digitados_no_preview_em_tempo_real(): void
    {
        $this->actingAs($this->admin);

        // 1. Página de criação de template e rota de preview livre (em criação)
        $this->get(route('admin.templates-email.create'))
            ->assertOk()
            ->assertSee('Novo Template de E-mail')
            ->assertSee('Dados de Teste / Variáveis no Preview (Tempo Real)')
            ->assertSee('atualizarPreviewInstantaneo', false)
            ->assertSee('lerHtmlAoVivoDoEditor', false);

        $respPreviewLivre = $this->postJson(route('admin.templates-email.preview-livre'), [
            'nome' => 'Aviso Personalizado em Criação',
            'assunto' => 'Olá {{cliente_nome}}, fatura #{{fatura_numero}}',
            'pre_cabecalho' => 'Prévia ao vivo {{valor}}',
            'corpo_html' => '<p>Prezado <strong>{{cliente_nome}}</strong>, vencimento {{vencimento}}. Token novo: {{token_personalizado}}</p>',
            'variaveis_preview' => [
                'cliente_nome' => 'Empresa Alpha Logística',
                'fatura_numero' => 'GR-7700',
                'valor' => 'R$ 499,90',
                'vencimento' => '30/11/2026',
            ],
        ]);

        $respPreviewLivre->assertOk()
            ->assertJsonPath('nome', 'Aviso Personalizado em Criação')
            ->assertJsonPath('assunto', 'Olá Empresa Alpha Logística, fatura #GR-7700')
            ->assertJsonPath('pre_cabecalho', 'Prévia ao vivo R$ 499,90')
            ->assertJsonPath('usar_layout', true);

        $htmlLivre = (string) $respPreviewLivre->json('html');
        $corpoLivreResolvido = (string) $respPreviewLivre->json('corpo_html_resolvido');
        $this->assertStringContainsString('Empresa Alpha Logística', $htmlLivre);
        $this->assertStringContainsString('30/11/2026', $htmlLivre);
        $this->assertStringContainsString('{{token_personalizado}}', $htmlLivre);
        $this->assertStringContainsString('{{token_personalizado}}', $corpoLivreResolvido);
        $this->assertStringContainsString('data-email-body-slot', $htmlLivre);
        $this->assertStringContainsString('data-email-preheader-slot', $htmlLivre);
        $this->assertStringContainsString('data-email-marca-slot', $htmlLivre);

        // 2. Salvar o novo template criado via POST /admin/templates-email
        $respCreate = $this->post(route('admin.templates-email.store'), [
            'chave' => 'aviso_personalizado_alpha',
            'nome' => 'Aviso Personalizado em Criação',
            'categoria' => 'financeiro',
            'descricao' => 'Template criado via painel para testes ao vivo',
            'assunto' => 'Olá {{cliente_nome}}, fatura #{{fatura_numero}}',
            'pre_cabecalho' => 'Prévia {{valor}}',
            'corpo_html' => '<p>Conteúdo salvo para {{cliente_nome}}</p>',
            'ativo' => '1',
        ]);
        $respCreate->assertRedirect(route('admin.templates-email.edit', 'aviso_personalizado_alpha'));

        // 3. Edição e preview em tempo real com variáveis customizadas (incluindo proteção contra _method=PUT do FormData)
        $this->get(route('admin.templates-email.edit', 'aviso_personalizado_alpha'))
            ->assertOk()
            ->assertSee('aviso_personalizado_alpha')
            ->assertSee('Dados de Teste / Variáveis no Preview (Tempo Real)')
            ->assertSee('atualizarPreviewInstantaneo', false);

        $respPreviewEdicao = $this->postJson(route('admin.templates-email.preview', 'aviso_personalizado_alpha'), [
            '_method' => 'PUT',
            'nome' => 'Aviso Editado Agora',
            'assunto' => 'Atualização para {{cliente_nome}} · {{placa}}',
            'pre_cabecalho' => 'Rastreamento {{codigo_rastreio}}',
            'corpo_html' => '<p>Placa monitorada: <strong>{{placa}}</strong> · Extra: {{campo_novo}}</p>',
            'variaveis_preview' => [
                'cliente_nome' => 'Transportadora Beta',
                'placa' => 'XYZ-9988',
                'codigo_rastreio' => 'BR99887766',
            ],
        ]);

        $respPreviewEdicao->assertOk()
            ->assertJsonPath('nome', 'Aviso Editado Agora')
            ->assertJsonPath('assunto', 'Atualização para Transportadora Beta · XYZ-9988')
            ->assertJsonPath('pre_cabecalho', 'Rastreamento BR99887766')
            ->assertJsonPath('usar_layout', true);
        $this->assertStringContainsString('XYZ-9988', (string) $respPreviewEdicao->json('html'));
        $this->assertStringContainsString('{{campo_novo}}', (string) $respPreviewEdicao->json('corpo_html_resolvido'));

        // 4. Layout global respeita alternância de WhatsApp e texto editado no preview em tempo real (inclusive com _method=PUT)
        $respLayoutComWpp = $this->postJson(route('admin.templates-email.layout.preview'), [
            '_method' => 'PUT',
            'marca_nome' => 'Golden Ao Vivo LTDA',
            'texto_rodape' => 'Rodapé digitado em tempo real.',
            'mostrar_whatsapp' => '1',
        ]);
        $respLayoutComWpp->assertOk();
        $this->assertStringContainsString('Golden Ao Vivo LTDA', (string) $respLayoutComWpp->json('html'));
        $this->assertStringContainsString('Rodapé digitado em tempo real.', (string) $respLayoutComWpp->json('html'));
        $this->assertStringContainsString('WhatsApp', (string) $respLayoutComWpp->json('html'));

        $respLayoutSemWpp = $this->postJson(route('admin.templates-email.layout.preview'), [
            'marca_nome' => 'Golden Sem WPP LTDA',
            'texto_rodape' => 'Rodapé sem link whatsapp.',
            'mostrar_whatsapp' => '0',
        ]);
        $respLayoutSemWpp->assertOk();
        $this->assertStringContainsString('Golden Sem WPP LTDA', (string) $respLayoutSemWpp->json('html'));
        $this->assertStringContainsString('Rodapé sem link whatsapp.', (string) $respLayoutSemWpp->json('html'));
        $this->assertStringNotContainsString('WhatsApp:', (string) $respLayoutSemWpp->json('html'));
    }

    public function test_estudio_faturas_reflete_dados_digitados_em_tempo_real_no_preview_impressao_e_pdf(): void
    {
        $this->actingAs($this->admin);

        // Página inicial exibe aba de dados da fatura, espelho persistente e links com ?rascunho=1
        $this->get(route('admin.faturas-modelo.index'))
            ->assertOk()
            ->assertSee('Dados da Fatura')
            ->assertSee('Espelho Cirúrgico A4 (210mm × 297mm)')
            ->assertSee('rascunho=1');

        $modelo = app(FaturaPdfService::class)->configuracaoAtual();
        $modelo['estilo']['emitente_nome'] = 'Golden Frota Pro LTDA';
        $modelo['estilo']['pix_chave_fallback'] = 'pix-ao-vivo@goldenfrota.com.br';
        $modelo['estilo']['gateway_padrao_modulo'] = 'efibankpix';

        $faturaEditada = [
            'numero' => 'GR-LIVE-9001',
            'status' => 'Paid',
            'emitida' => '15/11/2026',
            'vencimento' => '25/11/2026',
            'credito_cents' => 1500,
            'imposto_cents' => 0,
            'pix_copia_cola' => '',
            'observacoes' => '<p>Observação HTML ao vivo do <strong>Summernote</strong>.</p>',
            'cliente' => [
                'nome' => 'Carlos Eduardo Tempo Real',
                'empresa' => 'TransCar Cargo S/A',
                'documento' => '11.222.333/0001-44',
                'email' => 'carlos@transcar.com.br',
            ],
            'itens' => [
                [
                    'descricao' => 'Monitoramento Satelital 24h',
                    'quantidade' => 2,
                    'valor_unitario_cents' => 12000,
                    'valor_cents' => 24000,
                ],
            ],
        ];

        $respPreview = $this->postJson(route('admin.faturas-modelo.preview'), [
            'modelo_json' => json_encode($modelo, JSON_THROW_ON_ERROR),
            'fatura_json' => json_encode($faturaEditada, JSON_THROW_ON_ERROR),
        ]);

        $respPreview->assertOk()
            ->assertJsonPath('numero', 'GR-LIVE-9001')
            ->assertJsonPath('total_formatado', 'R$ 225,00');

        $htmlPreview = (string) $respPreview->json('html');
        // emitente contendo 'Golden' não deve ser sobrescrito pelo fallback placeholder
        $this->assertStringContainsString('Golden Frota Pro LTDA', $htmlPreview);
        $this->assertStringContainsString('GR-LIVE-9001', $htmlPreview);
        $this->assertStringContainsString('Carlos Eduardo Tempo Real', $htmlPreview);
        $this->assertStringContainsString('Monitoramento Satelital 24h', $htmlPreview);
        $this->assertStringContainsString('R$ 225,00', $htmlPreview);
        $this->assertStringContainsString('Paga', $htmlPreview);
        $this->assertStringContainsString('pix-ao-vivo@goldenfrota.com.br', $htmlPreview);
        $this->assertStringContainsString('efibankpix', $htmlPreview);
        $this->assertStringContainsString('Observação HTML ao vivo do', $htmlPreview);

        // Impressão A4 e PDF (?rascunho=1) refletem exatamente os dados digitados em tempo real
        $respImprimir = $this->get(route('admin.faturas-modelo.imprimir', ['rascunho' => 1]));
        $respImprimir->assertOk();
        $respImprimir->assertSee('Golden Frota Pro LTDA', false);
        $respImprimir->assertSee('GR-LIVE-9001', false);
        $respImprimir->assertSee('Carlos Eduardo Tempo Real', false);
        $respImprimir->assertSee('R$ 225,00', false);

        $respPdf = $this->get(route('admin.faturas-modelo.pdf', ['rascunho' => 1]));
        $respPdf->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $pdfConteudo = $respPdf->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfConteudo);
        $this->assertStringContainsString('GR-LIVE-9001', $pdfConteudo);
        $this->assertStringContainsString('Golden Frota Pro LTDA', $pdfConteudo);

        // Restaurar limpa o cache de rascunho em tempo real
        $this->post(route('admin.faturas-modelo.restaurar'))
            ->assertRedirect(route('admin.faturas-modelo.index'));

        $this->get(route('admin.faturas-modelo.imprimir', ['rascunho' => 1]))
            ->assertOk()
            ->assertDontSee('GR-LIVE-9001', false);
    }

    public function test_fatura_a4_pdf_e_templates_de_email_usam_rotas_reais_do_portal_em_vez_de_minha_conta(): void
    {
        $pdfService = app(FaturaPdfService::class);
        $mailService = app(EmailTemplateService::class);

        // 1. Fatura padrão em HTML A4 e binário PDF usa /portal/faturas e nunca /minha-conta/faturas
        $htmlPadrao = $pdfService->renderizarHtmlCirurgico();
        $this->assertStringContainsString('/portal/faturas', $htmlPadrao);
        $this->assertStringNotContainsString('/minha-conta/', $htmlPadrao);

        $pdfPadrao = $pdfService->gerarPdfBinario();
        $this->assertStringContainsString('/portal/faturas', $pdfPadrao);
        $this->assertStringNotContainsString('/minha-conta/', $pdfPadrao);

        // 2. Quando checkout_url é explicitamente limpo, o HTML/PDF omite Link Checkout em vez de exibir fallback inexistente
        $semCheckout = $pdfService->faturaExemplo();
        $semCheckout['url'] = '';
        $semCheckout['checkout_url'] = '';
        $htmlSemCheckout = $pdfService->renderizarHtmlCirurgico($semCheckout);
        $pdfSemCheckout = $pdfService->gerarPdfBinario($semCheckout);
        $this->assertStringNotContainsString('Pagamento online:', $htmlSemCheckout);
        $this->assertStringNotContainsString('Link Checkout:', $pdfSemCheckout);

        // 3. E-mails transacionais e exemplos de variáveis usam /portal/faturas e /portal/chamados
        $exemplos = $mailService->exemplosVariaveis();
        $this->assertStringContainsString('/portal/faturas', $exemplos['fatura_url']);
        $this->assertStringContainsString('/portal/chamados', $exemplos['url_portal']);
        $this->assertStringNotContainsString('/minha-conta/', $exemplos['fatura_url']);
        $this->assertStringNotContainsString('/minha-conta/', $exemplos['url_portal']);

        $emailFatura = $mailService->renderizarPorChave('segunda_via_fatura', $exemplos);
        $this->assertStringContainsString('/portal/faturas', $emailFatura['html']);
        $this->assertStringNotContainsString('/minha-conta/', $emailFatura['html']);

        // 4. Rotas /portal/faturas e /portal/chamados realmente existem (não retornam 404)
        $this->assertTrue(Route::has('portal.faturas'));
        $this->assertTrue(Route::has('portal.chamados'));
    }

    public function test_editor_template_salva_variaveis_custom_texto_puro_layout_inline_e_simula_cores_em_tempo_real(): void
    {
        $this->actingAs($this->admin);

        // 1. Preview ao vivo aceita override inline de layout (cores, marca, HTML extra, switches)
        $respPreviewInline = $this->postJson(route('admin.templates-email.preview-livre'), [
            'nome' => 'Alerta Frota Online',
            'assunto' => 'Placa {{placa}} em alerta',
            'corpo_html' => '<p>Veículo <strong>{{placa}}</strong> monitorado.</p>',
            'variaveis_preview' => ['placa' => 'ABC-1D23'],
            'layout' => [
                'marca_nome' => 'Golden Telemetria Inline',
                'cor_botao_bg' => '#112233',
                'cabecalho_extra_html' => '<span>Central 24h</span>',
                'mostrar_whatsapp' => '0',
            ],
        ]);

        $respPreviewInline->assertOk()
            ->assertJsonPath('assunto', 'Placa ABC-1D23 em alerta');
        $htmlInline = (string) $respPreviewInline->json('html');
        $this->assertStringContainsString('Golden Telemetria Inline', $htmlInline);
        $this->assertStringContainsString('Central 24h', $htmlInline);
        $this->assertStringNotContainsString('WhatsApp:', $htmlInline);

        // 2. Store sanitiza chave, salva variaveis_custom, corpo_texto e layout global quando solicitado
        $respStore = $this->post(route('admin.templates-email.store'), [
            'chave' => 'Alerta Frota 24h!',
            'nome' => 'Alerta Frota 24h',
            'categoria' => 'sistema',
            'descricao' => 'Notificação de telemetria',
            'assunto' => 'Alerta {{placa}} · {{rastreador_id}}',
            'pre_cabecalho' => 'Evento registrado',
            'corpo_html' => '<p>Placa {{placa}} / Rastreador {{rastreador_id}}</p>',
            'corpo_texto' => 'Placa {{placa}} / Rastreador {{rastreador_id}} (texto puro)',
            'variaveis_custom_presente' => '1',
            'variaveis_custom' => [
                'placa' => 'Placa do veículo',
                'rastreador_id' => 'Serial do equipamento',
            ],
            'salvar_layout_junto' => '1',
            'layout' => [
                'marca_nome' => 'Golden Frota Online',
                'texto_rodape' => 'Rodapé salvo junto.',
            ],
            'usar_layout' => '1',
            'ativo' => '1',
        ]);

        $respStore->assertRedirect(route('admin.templates-email.edit', 'alerta_frota_24h'));

        $salvo = EmailTemplate::where('chave', 'alerta_frota_24h')->firstOrFail();
        $this->assertSame('Placa do veículo', $salvo->variaveis['placa'] ?? null);
        $this->assertSame('Serial do equipamento', $salvo->variaveis['rastreador_id'] ?? null);
        $this->assertSame('Placa {{placa}} / Rastreador {{rastreador_id}} (texto puro)', $salvo->corpo_texto);

        $layoutSalvo = app(EmailTemplateService::class)->layoutAtual();
        $this->assertSame('Golden Frota Online', $layoutSalvo['marca_nome']);
        $this->assertSame('Rodapé salvo junto.', $layoutSalvo['texto_rodape']);

        // 3. Salvamento AJAX retorna mensagem em português ('sucesso') sem recarregamento
        $respAjax = $this->putJson(route('admin.templates-email.update', 'alerta_frota_24h'), [
            'nome' => 'Alerta Frota Atualizado',
            'categoria' => 'sistema',
            'assunto' => 'Atualização {{placa}}',
            'pre_cabecalho' => 'Preheader',
            'corpo_html' => '<p>Corpo atualizado {{placa}}</p>',
            'corpo_texto' => 'Corpo texto {{placa}}',
            'variaveis_custom_presente' => '1',
            'variaveis_custom' => ['placa' => 'Placa atualizada'],
            'usar_layout' => '1',
            'ativo' => '1',
        ]);

        $respAjax->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mensagens.0.tipo', 'sucesso');

        // 4. Layout global exibe cabecalho_extra_html e css_adicional sem interferência do Summernote
        $this->get(route('admin.templates-email.layout'))
            ->assertOk()
            ->assertSee('cabecalho_extra_html', false)
            ->assertSee('id="css_adicional" name="css_adicional" rows="3" data-summernote="false"', false);
    }

    public function test_estudio_faturas_envia_teste_com_rascunho_ao_vivo_pagamento_data_e_codigo_barras(): void
    {
        $this->actingAs($this->admin);

        // Views expõem painéis data-aba-painel e campos pagamento_data / codigo_barras
        $this->get(route('admin.faturas-modelo.index'))
            ->assertOk()
            ->assertSee('data-aba-painel="montador"', false)
            ->assertSee('data-aba-painel="dados-fatura"', false)
            ->assertSee('data-fatura-campo="pagamento_data"', false)
            ->assertSee('data-fatura-campo="codigo_barras"', false);

        $modelo = app(FaturaPdfService::class)->configuracaoAtual();
        $faturaDraft = [
            'numero' => 'GR-RASCUNHO-77',
            'status' => 'Paid',
            'emitida' => '15/11/2026',
            'vencimento' => '20/11/2026',
            'pagamento_data' => '15/11/2026',
            'codigo_barras' => '23791999900000129903381260000000000000000040',
            'credito_cents' => 0,
            'imposto_cents' => 0,
            'cliente' => [
                'nome' => 'Cliente Draft AO VIVO',
            ],
            'itens' => [
                [
                    'descricao' => 'Plano Telemetria Draft',
                    'quantidade' => 1,
                    'valor_unitario_cents' => 9990,
                    'valor_cents' => 9990,
                ],
            ],
        ];

        $this->postJson(route('admin.faturas-modelo.preview'), [
            'modelo_json' => json_encode($modelo, JSON_THROW_ON_ERROR),
            'fatura_json' => json_encode($faturaDraft, JSON_THROW_ON_ERROR),
        ])->assertOk()
            ->assertJsonPath('numero', 'GR-RASCUNHO-77');

        // Sem whmcs_invoice_id, enviarTeste usa o rascunho em cache de sessão
        $this->post(route('admin.faturas-modelo.enviar-teste'), [
            'email_destino' => 'teste-rascunho@golden.com.br',
        ])->assertRedirect();

        $filaItem = EmailFila::where('destinatario_email', 'teste-rascunho@golden.com.br')
            ->latest('id')
            ->firstOrFail();

        $dadosAnexo = $filaItem->anexos[0]['dados'] ?? [];
        $this->assertSame('GR-RASCUNHO-77', $dadosAnexo['numero'] ?? null);
        $this->assertSame('15/11/2026', $dadosAnexo['paga_em'] ?? null);
        $this->assertSame('23791999900000129903381260000000000000000040', $dadosAnexo['codigo_barras'] ?? null);
    }
}
