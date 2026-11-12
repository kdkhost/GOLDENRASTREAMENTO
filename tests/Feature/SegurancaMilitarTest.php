<?php

namespace Tests\Feature;

use App\Models\SecurityIpRule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegurancaMilitarTest extends TestCase
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

        Setting::set('shield_ativo', '1');
        Setting::set('shield_rigor', 'militar');
    }

    public function test_admin_consegue_visualizar_war_room_de_seguranca_militar(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.seguranca-militar.index'));

        $response->assertOk();
        $response->assertSee('Escudo de Segurança Militar');
        $response->assertSee('War Room');
        $response->assertSee('Central de Logs');
    }

    public function test_escudo_bloqueia_tentativa_de_injecao_sql(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
            ->get('/?busca=1%20UNION%20SELECT%20password%20FROM%20users');

        $response->assertStatus(403);
        $response->assertSee('Acesso Interrompido por Segurança');
        $response->assertSee('Bloqueio de Defesa Militar Ativo');

        $this->assertDatabaseHas('security_events', [
            'ip' => '198.51.100.12',
            'evento' => 'sqli',
            'nivel' => 'critico',
        ]);
    }

    public function test_escudo_bloqueia_scanner_hostil_sqlmap(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.88',
            'HTTP_USER_AGENT' => 'sqlmap/1.6#stable (https://sqlmap.org)',
        ])->get('/');

        $response->assertStatus(403);
        $response->assertSee('Acesso Interrompido por Segurança');

        $this->assertDatabaseHas('security_events', [
            'ip' => '203.0.113.88',
            'evento' => 'scanner',
        ]);
    }

    public function test_escudo_bloqueia_probe_de_arquivo_env(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->get('/.env');

        $response->assertStatus(403);
        $response->assertSee('Acesso Interrompido por Segurança');

        $this->assertDatabaseHas('security_events', [
            'ip' => '198.51.100.77',
            'evento' => 'sensitive_probe',
        ]);
    }

    public function test_ip_na_blacklist_e_bloqueado_imediatamente(): void
    {
        SecurityIpRule::create([
            'ip' => '198.51.100.99',
            'tipo' => 'blacklist',
            'motivo' => 'IP bloqueado por ataque anterior',
            'nivel_ameaca' => 'alto',
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->get('/');

        $response->assertStatus(403);
        $response->assertSee('Acesso Interrompido por Segurança');
    }

    public function test_admin_consegue_adicionar_ip_a_whitelist_e_blacklist(): void
    {
        $responseBlacklist = $this->actingAs($this->admin)->post(route('admin.seguranca-militar.ips.adicionar'), [
            'ip' => '192.0.2.1',
            'tipo' => 'blacklist',
            'motivo' => 'IP suspeito detectado em teste',
            'horas' => 24,
        ]);

        $responseBlacklist->assertRedirect(route('admin.seguranca-militar.index', ['aba' => 'ips']));
        $this->assertDatabaseHas('security_ip_rules', [
            'ip' => '192.0.2.1',
            'tipo' => 'blacklist',
        ]);

        $responseWhitelist = $this->actingAs($this->admin)->post(route('admin.seguranca-militar.ips.adicionar'), [
            'ip' => '192.0.2.2',
            'tipo' => 'whitelist',
            'motivo' => 'IP do escritório da empresa',
        ]);

        $responseWhitelist->assertRedirect(route('admin.seguranca-militar.index', ['aba' => 'ips']));
        $this->assertDatabaseHas('security_ip_rules', [
            'ip' => '192.0.2.2',
            'tipo' => 'whitelist',
        ]);
    }
}
