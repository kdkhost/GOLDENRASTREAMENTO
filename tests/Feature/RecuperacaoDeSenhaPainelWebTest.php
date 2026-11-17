<?php

namespace Tests\Feature;

use App\Mail\RedefinirSenhaEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RecuperacaoDeSenhaPainelWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_tela_de_login_do_admin_exibe_link_esqueci_minha_senha(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Esqueci minha senha')
            ->assertSee(route('password.request'));
    }

    public function test_tela_de_solicitacao_de_recuperacao_carrega_com_sucesso(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Recuperar senha')
            ->assertSee('E-mail corporativo')
            ->assertSee('Enviar link de recuperação');
    }

    public function test_solicitar_recuperacao_para_email_inexistente_retorna_mensagem_neutra_sem_enviar_email(): void
    {
        Mail::fake();

        $this->post(route('password.email'), [
            'email' => 'naoexiste@golden.com.br',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertNothingQueued();
    }

    public function test_solicitar_recuperacao_para_usuario_ativo_dispara_email_com_link_assinado(): void
    {
        Mail::fake();

        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $this->post(route('password.email'), [
            'email' => 'admin@golden.com.br',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertQueued(RedefinirSenhaEquipe::class, function (RedefinirSenhaEquipe $mail) use ($admin): bool {
            return $mail->user->id === $admin->id
                && str_contains($mail->link, 'redefinir-senha/'.$admin->id)
                && str_contains($mail->link, 'signature=');
        });
    }

    public function test_link_assinado_valido_exibe_formulario_de_redefinicao(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $link = URL::temporarySignedRoute('password.reset', now()->addMinutes(60), ['user' => $admin->id]);

        $this->get($link)
            ->assertOk()
            ->assertSee('Definir nova senha')
            ->assertSee($admin->name)
            ->assertSee('Nova senha')
            ->assertSee('Confirmar nova senha');
    }

    public function test_link_com_assinatura_invalida_ou_adulterada_e_recusado(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $linkInvalido = route('password.reset', ['user' => $admin->id]).'?signature=invalida';

        $this->get($linkInvalido)
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');
    }

    public function test_redefinir_senha_com_sucesso_atualiza_hash_e_registra_auditoria(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $link = URL::temporarySignedRoute('password.reset', now()->addMinutes(60), ['user' => $admin->id]);

        $this->post($link, [
            'password' => 'NovaSenhaSegura123',
            'password_confirmation' => 'NovaSenhaSegura123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertTrue(Hash::check('NovaSenhaSegura123', $admin->password));
        $this->assertDatabaseHas('activities', [
            'action' => 'senha_redefinida',
            'subject_type' => class_basename($admin),
            'subject_id' => $admin->id,
        ]);
    }

    public function test_redefinicao_com_senhas_divergentes_retorna_erro_de_validacao(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $link = URL::temporarySignedRoute('password.reset', now()->addMinutes(60), ['user' => $admin->id]);

        $senhaAnterior = $admin->password;

        $this->post($link, [
            'password' => 'NovaSenhaSegura123',
            'password_confirmation' => 'OutraSenha456',
        ])
            ->assertSessionHasErrors('password');

        $admin->refresh();
        $this->assertSame($senhaAnterior, $admin->password);
    }
}
