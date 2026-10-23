<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Quem opera o servidor pela SSH não pode depender de memória para entrar no painel:
 * o comando de senha é o caminho de recuperação, então ele precisa falhar de forma
 * clara (e-mail errado, senha curta) em vez de travar esperando entrada.
 */
class RecuperarSenhaDoPainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_trocar_a_senha_de_um_usuario_existente(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $this->artisan('app:define-senha', ['email' => 'admin@golden.com.br', 'senha' => 'senha-nova-123'])
            ->expectsOutputToContain('Senha de admin@golden.com.br (admin) atualizada')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(Hash::check('senha-nova-123', $admin->password));
        $this->assertDatabaseHas('activities', ['action' => 'senha_redefinida', 'user_name' => 'Sistema']);
    }

    public function test_email_desconhecido_mostra_a_lista_de_usuarios(): void
    {
        $this->artisan('app:define-senha', ['email' => 'ninguem@golden.com.br', 'senha' => 'qualquer-senha-boa'])
            ->expectsOutputToContain('Nenhum usuário com o e-mail ninguem@golden.com.br')
            ->expectsOutputToContain('admin@golden.com.br')
            ->assertFailed();
    }

    public function test_senha_curta_nao_grava_nada(): void
    {
        $antes = User::where('email', 'admin@golden.com.br')->value('password');

        $this->artisan('app:define-senha', ['email' => 'admin@golden.com.br', 'senha' => 'abc'])
            ->expectsOutputToContain('pelo menos 8 caracteres')
            ->assertFailed();

        $this->assertSame($antes, User::where('email', 'admin@golden.com.br')->value('password'));
        $this->assertSame(0, Activity::where('action', 'senha_redefinida')->count());
    }

    public function test_pode_reativar_uma_conta_desligada(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
        $vendedor->update(['status' => 'inativo']);

        $this->artisan('app:define-senha', [
            'email' => 'vendedor@golden.com.br',
            'senha' => 'volta-ao-trabalho-123',
            '--reativar' => true,
        ])->assertSuccessful();

        $this->assertSame('ativo', $vendedor->fresh()->status);
    }
}
