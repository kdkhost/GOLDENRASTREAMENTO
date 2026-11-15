<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModoDeManutencaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'ativo',
        ]);
    }

    public function test_admin_consegue_visualizar_painel_de_manutencao(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.manutencao.index'));

        $response->assertOk();
        $response->assertSee('Modo de manutenção do sistema');
        $response->assertSee('SISTEMA OPERANDO NORMALMENTE');
    }

    public function test_admin_consegue_ativar_e_desativar_modo_de_manutencao(): void
    {
        // Alterna para ligado
        $responseToggle = $this->actingAs($this->admin)->post(route('admin.manutencao.toggle'));
        $responseToggle->assertRedirect(route('admin.manutencao.index'));
        $this->assertSame('1', Setting::get('manutencao_ativa'));

        // Alterna de volta para desligado
        $responseToggleOff = $this->actingAs($this->admin)->post(route('admin.manutencao.toggle'));
        $responseToggleOff->assertRedirect(route('admin.manutencao.index'));
        $this->assertSame('0', Setting::get('manutencao_ativa'));
    }

    public function test_quando_manutencao_esta_ativa_visitante_recebe_status_503(): void
    {
        Setting::set('manutencao_ativa', '1');
        Setting::set('manutencao_titulo', 'Melhorias em Andamento nos Servidores');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Melhorias em Andamento nos Servidores');
    }

    public function test_chave_de_bypass_permite_navegar_mesmo_em_manutencao(): void
    {
        Setting::set('manutencao_ativa', '1');
        $token = 'meu_token_secreto_super_seguro_123';
        Setting::set('manutencao_token_bypass', $token);

        // Acessa com o token no query param
        $response = $this->get('/?golden_bypass='.$token);

        $response->assertOk();
        $response->assertCookie(MaintenanceService::COOKIE_BYPASS, $token);
    }

    public function test_ip_na_whitelist_ignora_manutencao(): void
    {
        Setting::set('manutencao_ativa', '1');
        Setting::set('manutencao_ips_permitidos', "192.168.1.100\n10.0.0.5");

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])->get('/');

        $response->assertOk();
    }

    public function test_admin_consegue_atualizar_configuracoes_completas_incluindo_animacao_whatsapp_e_redes(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.manutencao.update'), [
            'ativa' => 1,
            'modo' => 'total',
            'titulo' => 'Upgrade de Servidores Golden',
            'subtitulo' => 'Rastreamento 100% Ativo via Satélite',
            'mensagem' => 'Estamos em melhoria de infraestrutura técnica.',
            'previsao_fim' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'exibir_cronometro' => 1,
            'exibir_plantao' => 1,
            'telefone' => '(19) 99710-1483',
            'email' => 'suporte@goldenrastreamento.com.br',
            'exibir_animacao' => 1,
            'velocidade_animacao' => 'rapida',
            'exibir_satelite' => 1,
            'exibir_whatsapp' => 1,
            'whatsapp_numero' => '5519997101483',
            'whatsapp_mensagem' => 'Gostaria de falar com o plantão.',
            'exibir_redes' => 1,
            'instagram' => 'https://instagram.com/goldenrastreamento',
            'facebook' => 'https://facebook.com/goldenrastreamento',
            'youtube' => 'https://youtube.com/@goldenrastreamento',
            'linkedin' => 'https://linkedin.com/company/goldenrastreamento',
        ]);

        $response->assertRedirect(route('admin.manutencao.index'));
        $response->assertSessionHas('success');

        $this->assertSame('1', Setting::get('manutencao_ativa'));
        $this->assertSame('Upgrade de Servidores Golden', Setting::get('manutencao_titulo'));
        $this->assertSame('1', Setting::get('manutencao_exibir_animacao'));
        $this->assertSame('rapida', Setting::get('manutencao_velocidade_animacao'));
        $this->assertSame('1', Setting::get('manutencao_exibir_satelite'));
        $this->assertSame('1', Setting::get('manutencao_exibir_whatsapp'));
        $this->assertSame('5519997101483', Setting::get('manutencao_whatsapp_numero'));
    }

    public function test_salvar_configuracoes_sem_campo_ativa_preserva_estado_ativo_da_manutencao(): void
    {
        Setting::set('manutencao_ativa', '1');

        $response = $this->actingAs($this->admin)->put(route('admin.manutencao.update'), [
            'modo' => 'total',
            'titulo' => 'Manutenção Preservada',
        ]);

        $response->assertRedirect(route('admin.manutencao.index'));
        $this->assertSame('1', Setting::get('manutencao_ativa'));
        $this->assertSame('Manutenção Preservada', Setting::get('manutencao_titulo'));
    }

    public function test_tela_de_manutencao_renderiza_abas_ajudas_e_paleta_golden(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.manutencao.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-abas="manutencao"', $html);
        $this->assertStringContainsString('data-ajuda="ajuda-tela"', $html);
        $this->assertStringNotContainsString('text-primary', $html);
        $this->assertStringNotContainsString('text-info', $html);
        $this->assertStringNotContainsString('bg-danger-subtle', $html);
        $this->assertStringNotContainsString('bg-success-subtle', $html);

        preg_match_all('/data-ajuda="(ajuda-[a-z0-9-]+)"/i', $html, $chamados);
        $this->assertGreaterThan(20, count(array_unique($chamados[1])));

        foreach (array_unique($chamados[1]) as $id) {
            $this->assertStringContainsString('data-ajuda-corpo="'.$id.'"', $html,
                "A gaveta de ajuda '{$id}' em /admin/manutencao ficou sem conteúdo.");
        }
    }

    public function test_tela_503_renderiza_elementos_personalizados_de_animacao_satelite_e_whatsapp(): void
    {
        Setting::set('manutencao_ativa', '1');
        Setting::set('manutencao_titulo', 'Upgrade Satelital em Andamento');
        Setting::set('manutencao_subtitulo', 'Monitoramento Ativo 24h');
        Setting::set('manutencao_exibir_animacao', '1');
        Setting::set('manutencao_exibir_satelite', '1');
        Setting::set('manutencao_exibir_whatsapp', '1');
        Setting::set('manutencao_whatsapp_numero', '5519997101483');
        Setting::set('manutencao_exibir_redes', '1');
        Setting::set('manutencao_instagram', 'https://instagram.com/goldenrastreamento');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Upgrade Satelital em Andamento');
        $response->assertSee('Monitoramento Ativo 24h');
        $response->assertSee('traffic-sat-canvas');
        $response->assertSee('wa.me/5519997101483');
        $response->assertSee('instagram.com/goldenrastreamento');
    }
}
