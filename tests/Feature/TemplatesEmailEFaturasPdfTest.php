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
}
