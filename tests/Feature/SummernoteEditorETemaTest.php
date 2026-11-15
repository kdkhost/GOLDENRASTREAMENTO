<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\ErrorPage;
use App\Models\Faq;
use App\Models\FunnelPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Fatura\FaturaPdfService;
use App\Services\Mail\EmailTemplateService;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummernoteEditorETemaTest extends TestCase
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

    public function test_helpers_golden_html_renderizam_rich_text_sem_escapar_e_higienizam_xss(): void
    {
        $rich = '<p>Olá <strong>Golden</strong></p><ul><li>Item 1</li></ul>';
        $this->assertSame($rich, golden_html($rich));

        $escapado = '&lt;p&gt;Texto &lt;strong&gt;formatado&lt;/strong&gt;&lt;/p&gt;';
        $this->assertSame('<p>Texto <strong>formatado</strong></p>', golden_html($escapado));

        $textoPuro = "Linha 1\nLinha 2 & <teste>";
        $convertido = golden_html($textoPuro);
        $this->assertStringContainsString('<br />', $convertido);
        $this->assertStringContainsString('&lt;teste&gt;', $convertido);

        $xss = '<script>alert(1)</script><p onclick="evil()">Ok</p><a href="javascript:alert(2)">Link</a>';
        $limpo = golden_html($xss);
        $this->assertStringNotContainsString('<script', $limpo);
        $this->assertStringNotContainsString('onclick', $limpo);
        $this->assertStringNotContainsString('javascript:', $limpo);
        $this->assertStringContainsString('<p>Ok</p>', $limpo);
    }

    public function test_helpers_golden_texto_puro_limpa_html_do_summernote_em_ips_whatsapp_layout_e_pdf(): void
    {
        $ipsHtml = '<p>187.54.120.30</p><p>201.89.44.12<br></p>';
        $this->assertSame("187.54.120.30\n201.89.44.12", golden_texto_puro($ipsHtml));

        $manutencao = app(MaintenanceService::class);
        $manutencao->salvarConfiguracoes([
            'modo' => MaintenanceService::MODO_TOTAL,
            'titulo' => 'Manutenção',
            'ips_permitidos' => $ipsHtml,
        ]);
        $this->assertSame(['187.54.120.30', '201.89.44.12'], $manutencao->ipsPermitidos());

        $linkWhats = wa_url('<p>Olá&nbsp;<strong>Golden</strong>!</p>', '5519997101483');
        $this->assertStringNotContainsString('%3Cp%3E', $linkWhats);
        $this->assertStringContainsString('text=Ol%C3%A1+Golden%21', $linkWhats);

        $this->actingAs($this->admin)
            ->put(route('admin.templates-email.layout.update'), [
                'marca_nome' => 'GOLDEN',
                'subtitulo' => 'Rastreamento',
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
                'css_adicional' => '<p>.card { color: #e0a33e; }</p>',
            ])
            ->assertRedirect(route('admin.templates-email.layout'));

        $layout = app(EmailTemplateService::class)->layoutAtual();
        $this->assertSame('.card { color: #e0a33e; }', $layout['css_adicional']);

        $pdfService = app(FaturaPdfService::class);
        $config = $pdfService->configuracaoAtual();
        $config['estilo']['rodape_texto'] = '<p>Rodapé <strong>Golden</strong></p>';

        $faturaComHtml = array_merge($pdfService->faturaExemplo(), [
            'observacoes' => '<p>Observação <strong>importante</strong></p>',
        ]);

        $htmlA4 = $pdfService->renderizarHtmlCirurgico($faturaComHtml, $config);
        $this->assertStringContainsString('<p>Observação <strong>importante</strong></p>', $htmlA4);
        $this->assertStringNotContainsString('&lt;p&gt;', $htmlA4);

        $binario = $pdfService->gerarPdfBinario($faturaComHtml, $config);
        $this->assertStringStartsWith('%PDF-1.4', $binario);
        $this->assertStringNotContainsString('<p>', $binario);
    }

    public function test_layouts_admin_portal_site_e_funil_carregam_summernote_e_css_dark_light(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.templates-email.edit', 'segunda_via_fatura'))
            ->assertOk()
            ->assertSee('summernote-lite.min.css', false)
            ->assertSee('golden-summernote.css', false)
            ->assertSee('summernote-lite.min.js', false)
            ->assertSee('summernote-pt-BR.min.js', false)
            ->assertSee('golden-summernote.js', false);

        $this->actingAs($this->admin)
            ->get(route('admin.manutencao.index'))
            ->assertOk()
            ->assertSee('data-summernote="true"', false)
            ->assertSee('golden-summernote.js', false);

        $cliente = Customer::create([
            'nome' => 'Carlos Cliente',
            'documento' => '111.222.333-44',
            'tipo' => 'fisica',
            'email' => 'carlos@exemplo.com.br',
            'email_portal' => 'carlos@exemplo.com.br',
            'password' => 'segredo123',
            'status' => 'ativo',
        ]);

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.chamados.criar'))
            ->assertOk()
            ->assertSee('summernote-lite.min.css', false)
            ->assertSee('golden-summernote.css', false)
            ->assertSee('golden-summernote.js', false)
            ->assertSee('data-summernote="true"', false);

        $this->get(route('site.contato'))
            ->assertOk()
            ->assertSee('summernote-lite.min.css', false)
            ->assertSee('golden-summernote.css', false)
            ->assertSee('golden-summernote.js', false);

        $funil = FunnelPage::create([
            'nome' => 'Campanha Frota',
            'slug' => 'campanha-frota-sn',
            'status' => 'publicada',
            'titulo' => 'Frota Segura',
        ]);
        $funil->blocks()->create([
            'tipo' => 'formulario',
            'titulo' => 'Solicite contato',
            'ordem' => 1,
            'ativa' => true,
        ]);

        $this->get('/c/campanha-frota-sn')
            ->assertOk()
            ->assertSee('golden-summernote.css', false)
            ->assertSee('golden-summernote.js', false);

        $cssConteudo = file_get_contents(public_path('css/golden-summernote.css')) ?: '';
        $this->assertStringContainsString('[data-bs-theme="dark"]', $cssConteudo);
        $this->assertStringContainsString('[data-bs-theme="light"]', $cssConteudo);
        $this->assertStringContainsString('.note-editor.note-frame', $cssConteudo);

        $jsConteudo = file_get_contents(public_path('js/golden-summernote.js')) ?: '';
        $this->assertStringContainsString(':not(.note-codable)', $jsConteudo);
        $this->assertStringContainsString('.note-editor', $jsConteudo);
        $this->assertStringContainsString('ehTextareaElegivel', $jsConteudo);
    }

    public function test_views_publicas_e_erro_nao_escapam_html_salvo_pelo_summernote(): void
    {
        Faq::create([
            'pergunta' => 'Como funciona o chip M2M?',
            'resposta' => '<p>Opera em <strong>multi-operadora</strong> automaticamente.</p>',
            'ordem' => 1,
            'ativa' => true,
        ]);

        $this->get(route('site.faq'))
            ->assertOk()
            ->assertSee('<p>Opera em <strong>multi-operadora</strong> automaticamente.</p>', false)
            ->assertDontSee('&lt;p&gt;Opera em', false);

        ErrorPage::updateOrCreate(
            ['code' => 404],
            [
                'title' => 'Página não encontrada',
                'subtitle' => 'Rota indisponível',
                'message' => '<p>Verifique o link ou fale com a <strong>equipe Golden</strong>.</p>',
                'primary_button_text' => 'Voltar ao início',
                'primary_button_url' => '/',
                'icon' => 'fa-compass',
                'active' => true,
            ]
        );

        $this->actingAs($this->admin)
            ->get(route('admin.erros.preview', 404))
            ->assertOk()
            ->assertSee('<p>Verifique o link ou fale com a <strong>equipe Golden</strong>.</p>', false)
            ->assertDontSee('&lt;p&gt;Verifique o link', false);
    }

    public function test_blocos_claros_no_summernote_e_templates_de_email_garantem_contraste_no_modo_escuro(): void
    {
        $cssSummernote = file_get_contents(public_path('css/golden-summernote.css'));
        $this->assertNotFalse($cssSummernote);
        $this->assertStringContainsString('[style*="background:#f" i]', $cssSummernote);
        $this->assertStringContainsString('color: #1b2127;', $cssSummernote);
        $this->assertStringContainsString('border-color: #e5e0d3;', $cssSummernote);

        // Simula template legado já gravado no banco antes da correção (sem color explícito)
        $legacyHtml = <<<'HTML'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;margin:16px 0;background:#faf8f2;">
    <tr>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;color:#6b7280;font-size:13px;">Número</td>
        <td style="padding:11px 16px;border-bottom:1px solid #ece7db;text-align:right;font-weight:700;">{{fatura_numero}}</td>
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
HTML;

        EmailTemplate::updateOrCreate(
            ['chave' => 'fatura_pdf_anexo'],
            [
                'nome' => 'Fatura em PDF Anexo + Gateway WHMCS',
                'categoria' => 'financeiro',
                'assunto' => 'Fatura {{fatura_numero}} em PDF',
                'corpo_html' => $legacyHtml,
                'usar_layout' => true,
                'ativo' => true,
            ]
        );

        $service = app(EmailTemplateService::class);
        $service->garantirPadroes();

        $atualizado = EmailTemplate::where('chave', 'fatura_pdf_anexo')->firstOrFail();
        $this->assertStringContainsString('background:#faf8f2;color:#1b2127;', $atualizado->corpo_html);
        $this->assertStringContainsString('text-align:right;font-weight:700;color:#12161a;', $atualizado->corpo_html);
        $this->assertStringContainsString('text-align:right;font-weight:600;color:#12161a;', $atualizado->corpo_html);
        $this->assertStringContainsString('background:#f8fafc;color:#1b2127;', $atualizado->corpo_html);
        $this->assertStringContainsString('<strong style="color:#12161a;">PIX / Linha Digitável:</strong>', $atualizado->corpo_html);
        $this->assertStringContainsString('font-family:monospace;word-break:break-all;color:#1b2127;', $atualizado->corpo_html);

        // Execução repetida deve ser idempotente
        $service->garantirPadroes();
        $reexecutado = EmailTemplate::where('chave', 'fatura_pdf_anexo')->firstOrFail();
        $this->assertSame($atualizado->corpo_html, $reexecutado->corpo_html);
    }
}
