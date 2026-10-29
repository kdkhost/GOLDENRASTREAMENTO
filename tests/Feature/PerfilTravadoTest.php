<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O que a tela mostra preenchido tem que chegar ao servidor.
 *
 * O administrador não conseguia salvar o próprio perfil — a tela respondia "O campo role é
 * obrigatório" diante de um select aberto em "Administrador", com o valor à vista. O motivo não
 * estava na regra nem no banco: o campo da própria pessoa era desabilitado para impedir o
 * auto-rebaixamento, e controle desabilitado não viaja no envio. A trava é de tela, nunca do
 * pedido; quem decide se o valor muda é o controller.
 */
class PerfilTravadoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    /** @param array<string, mixed> $extras */
    private function dados(array $extras = []): array
    {
        return array_merge([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'telefone' => '(19) 99710-1483',
            'role' => 'admin',
            'status' => 'ativo',
            'password' => '',
        ], $extras);
    }

    public function test_o_proprio_perfil_salva_com_os_campos_travados_na_tela(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), $this->dados());

        $resposta->assertSessionHasNoErrors();
        $resposta->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'telefone' => '(19) 99710-1483',
        ]);
    }

    public function test_a_tela_deixa_ver_o_valor_e_o_manda_junto(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.usuarios.edit', $this->admin))
            ->assertOk()
            ->getContent();

        // Travado: o controle aparece desabilitado e o valor viaja no campo oculto do mesmo nome.
        $this->assertStringContainsString('name="role"', $html);
        $this->assertStringContainsString('<input type="hidden" name="role" value="admin">', $html);
        $this->assertStringContainsString('<input type="hidden" name="status" value="ativo">', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*name="role"[^>]*disabled/', $html);

        // Ninguém mais na casa tem o campo travado.
        $outro = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
        $deste = $this->actingAs($this->admin)
            ->get(route('admin.usuarios.edit', $outro))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<input type="hidden" name="role"', $deste);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="role"[^>]*disabled/', $deste);
    }

    public function test_quem_edita_a_si_mesmo_nao_se_rebaixa_nem_se_desativa(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), $this->dados([
                'role' => 'visualizador',
                'status' => 'inativo',
            ]))
            ->assertSessionHasNoErrors();

        $this->admin->refresh();
        $this->assertSame('admin', $this->admin->role);
        $this->assertSame('ativo', $this->admin->status);
    }

    public function test_mudar_o_perfil_de_outro_usuario_continua_livre(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $vendedor), [
                'name' => $vendedor->name,
                'email' => $vendedor->email,
                'telefone' => $vendedor->telefone,
                'role' => 'visualizador',
                'status' => 'inativo',
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $vendedor->refresh();
        $this->assertSame('visualizador', $vendedor->role);
        $this->assertSame('inativo', $vendedor->status);
    }

    public function test_o_campo_travado_e_a_troca_de_interruptor_nao_apagam_o_valor_guardado(): void
    {
        // O mesmo defeito em outro lugar da casa: com "frete combinado" ligado, os dois campos de
        // valor ficavam desabilitados, não eram enviados, e a gravação lia ausência como zero.
        Setting::set('loja_livre', '1');
        Setting::set('loja_frete_cents', 2490);
        Setting::set('loja_frete_gratis_acima_cents', 99700);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<input type="hidden" name="loja_frete" value="24.90">', $html);
        $this->assertStringContainsString('<input type="hidden" name="loja_frete_gratis_acima" value="997.00">', $html);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.loja-regras'), [
                'loja_livre' => '1',
                'loja_frete' => '24.90',
                'loja_frete_gratis_acima' => '997.00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2490, (int) Setting::get('loja_frete_cents'));
        $this->assertSame(99700, (int) Setting::get('loja_frete_gratis_acima_cents'));
    }
}
