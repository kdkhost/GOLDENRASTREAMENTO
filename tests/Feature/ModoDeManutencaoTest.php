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
}

