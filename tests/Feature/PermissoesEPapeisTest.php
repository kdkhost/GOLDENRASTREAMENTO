<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\MenuPainel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissoesEPapeisTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'role' => 'superadmin',
            'status' => 'ativo',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'ativo',
        ]);
    }

    public function test_superadmin_e_admin_acessam_tela_de_permissoes_e_veem_todos_os_perfis(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.permissoes.index'));

        $response->assertOk();
        $response->assertSee('Super Administrador');
        $response->assertSee('Administrador');
        $response->assertSee('Vendedor');
        $response->assertSee('Contador');
        $response->assertSee('Marketing');
        $response->assertSee('Suporte');
    }

    public function test_admin_comum_nao_pode_editar_ou_excluir_superadmin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.edit', $this->superadmin))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->superadmin), [
                'name' => 'Tentativa',
                'email' => $this->superadmin->email,
                'role' => 'vendedor',
                'status' => 'ativo',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete(route('admin.usuarios.destroy', $this->superadmin))
            ->assertForbidden();
    }

    public function test_perfis_contador_marketing_e_suporte_obedecem_permissoes_de_ver_editar_e_deletar(): void
    {
        $contador = User::factory()->create(['role' => 'contador', 'status' => 'ativo']);
        $marketing = User::factory()->create(['role' => 'marketing', 'status' => 'ativo']);
        $suporte = User::factory()->create(['role' => 'suporte', 'status' => 'ativo']);

        // Contador: vê pedidos e auditoria, mas não vê configurações e não altera configurações
        $this->actingAs($contador)->get(route('admin.pedidos.index'))->assertOk();
        $this->actingAs($contador)->get(route('admin.activities.index'))->assertOk();
        $this->actingAs($contador)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($contador)->post(route('admin.settings.update'), ['_aba' => 'contato'])->assertForbidden();

        // Marketing: vê campanhas e conteúdo, mas não vê configurações nem usuários
        $this->actingAs($marketing)->get(route('admin.campanhas.index'))->assertOk();
        $this->actingAs($marketing)->get(route('admin.conteudo.paginas.index'))->assertOk();
        $this->actingAs($marketing)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($marketing)->get(route('admin.usuarios.index'))->assertForbidden();

        // Suporte: vê chamados e clientes, mas não vê configurações nem usuários
        $this->actingAs($suporte)->get(route('admin.chamados.index'))->assertOk();
        $this->actingAs($suporte)->get(route('admin.clientes.index'))->assertOk();
        $this->actingAs($suporte)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($suporte)->get(route('admin.usuarios.index'))->assertForbidden();
    }

    public function test_alterar_matriz_no_painel_bloqueia_ou_libera_ver_alterar_e_deletar_em_tempo_real(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor', 'status' => 'ativo']);
        $lead = Lead::create([
            'name' => 'Lead Teste RBAC',
            'phone' => '19999990000',
            'origin' => 'site',
            'status' => 'novo',
        ]);

        // Por padrão vendedor NÃO pode deletar leads
        $this->actingAs($vendedor)->delete(route('admin.leads.destroy', $lead))->assertForbidden();

        // Superadmin atualiza matriz liberando leads.deletar para vendedor e bloqueando admin de editar configurações
        $matriz = app(PermissionService::class)->padroes();
        $matriz['vendedor']['leads']['deletar'] = true;
        $matriz['admin']['configuracoes']['editar'] = false;

        $this->actingAs($this->superadmin)
            ->put(route('admin.permissoes.update'), ['permissoes' => $matriz])
            ->assertRedirect(route('admin.permissoes.index'));

        // Agora vendedor consegue deletar o lead
        $this->actingAs($vendedor)->delete(route('admin.leads.destroy', $lead))->assertRedirect();
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);

        // Admin continua vendo configurações, mas não consegue mais salvar alterações
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.settings.update'), ['_aba' => 'contato'])->assertForbidden();

        // Superadmin continua podendo salvar alterações normalmente
        $this->actingAs($this->superadmin)->post(route('admin.settings.update'), [
            '_aba' => 'contato',
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '5519997101483',
        ])->assertRedirect();

        // Restaurar padrões volta tudo ao normal
        $this->actingAs($this->superadmin)
            ->post(route('admin.permissoes.restaurar'))
            ->assertRedirect(route('admin.permissoes.index'));

        $this->assertFalse($vendedor->fresh()->podeDeletar('leads'));
        $this->assertTrue($this->admin->fresh()->podeEditar('configuracoes'));
    }

    public function test_menu_lateral_oculta_modulos_sem_permissao_para_contador_e_marketing(): void
    {
        $contador = User::factory()->create(['role' => 'contador', 'status' => 'ativo']);
        $menuContador = MenuPainel::montar([
            'notificacoes_novas' => 0,
            'leads_novos' => 0,
            'pedidos_abertos' => 0,
            'chamados_abertos' => 0,
            'conversas_aguardando' => 0,
            'crons_com_falha' => 0,
        ], false, ['loja_ativa' => true], 'golden.menu_painel', $contador);

        $titulosContador = collect($menuContador)->pluck('titulo')->all();
        $this->assertContains('Vendas', $titulosContador);
        $this->assertContains('Sistema', $titulosContador); // Tem Auditoria
        $this->assertNotContains('Conteúdo do site', $titulosContador);

        $sistemaFilhos = collect($menuContador)->firstWhere('titulo', 'Sistema')['filhos'] ?? [];
        $titulosSistemaContador = collect($sistemaFilhos)->pluck('titulo')->all();
        $this->assertSame(['Auditoria'], $titulosSistemaContador);
    }

    public function test_admin_consegue_definir_primeiro_superadmin_e_limite_maximo_de_2_e_respeitado(): void
    {
        // Remove o superadmin padrão do setUp para simular o cenário inicial sem superadmin
        $this->superadmin->delete();
        $this->assertSame(0, PermissionService::totalSuperAdmins());

        // Admin comum consegue se promover a primeiro Super Administrador
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'superadmin',
                'status' => 'ativo',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame('superadmin', $this->admin->fresh()->role);
        $this->assertSame(1, PermissionService::totalSuperAdmins());

        // Agora como superadmin, cria o 2º superadmin (limite = 2)
        $this->actingAs($this->admin->fresh())
            ->post(route('admin.usuarios.store'), [
                'name' => 'Segundo Superadmin',
                'email' => 'super2@golden.com.br',
                'role' => 'superadmin',
                'password' => 'SenhaSegura123!',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame(2, PermissionService::totalSuperAdmins());

        // Tentar criar um 3º superadmin deve ser bloqueado com erro de validação
        $this->actingAs($this->admin->fresh())
            ->post(route('admin.usuarios.store'), [
                'name' => 'Terceiro Superadmin',
                'email' => 'super3@golden.com.br',
                'role' => 'superadmin',
                'password' => 'SenhaSegura123!',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(2, PermissionService::totalSuperAdmins());

        // Também testa o botão de promoção direta em um 3º usuário (deve bloquear por limite)
        $outro = User::factory()->create(['role' => 'admin', 'status' => 'ativo']);
        $this->actingAs($this->admin->fresh())
            ->post(route('admin.usuarios.promover-superadmin', $outro))
            ->assertSessionHas('error');

        $this->assertSame('admin', $outro->fresh()->role);
    }
}
