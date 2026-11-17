<?php

namespace Tests\Feature;

use App\Mail\RedefinirSenhaEquipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
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

    public function test_tela_de_recuperacao_exibe_opcao_de_recuperar_por_celular_e_2fa(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Por E-mail')
            ->assertSee('Celular / 2FA')
            ->assertSee('Celular cadastrado na equipe')
            ->assertSee('Código de segurança do 2FA');
    }

    public function test_recuperar_por_celular_com_2fa_ativo_e_codigo_valido_redireciona_direto_para_redefinicao_e_informa_email(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $g2fa = new Google2FA;
        $secret = $g2fa->generateSecretKey(32);

        $admin->forceFill([
            'telefone' => '(11) 98888-7777',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $codigo = $g2fa->getCurrentOtp($secret);

        $response = $this->from(route('password.request'))->post(route('password.phone'), [
            'identificador' => '11988887777',
            'codigo_2fa' => $codigo,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('redefinir-senha/'.$admin->id, (string) $response->headers->get('Location'));
        $this->assertStringContainsString('signature=', (string) $response->headers->get('Location'));
        $response->assertSessionHas('info_email', "Identidade confirmada com sucesso pelo seu 2FA! O e-mail de login da sua conta é: {$admin->email}.");

        $this->assertDatabaseHas('activities', [
            'action' => 'recuperacao_senha_2fa',
            'subject_type' => class_basename($admin),
            'subject_id' => $admin->id,
        ]);
    }

    public function test_recuperar_por_celular_com_codigo_de_recuperacao_do_2fa_valido_queima_codigo_e_autoriza(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $g2fa = new Google2FA;
        $secret = $g2fa->generateSecretKey(32);

        $admin->forceFill([
            'telefone' => '(11) 97777-6666',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['ABCDE-12345', 'FGHIJ-67890'],
        ])->save();

        $response = $this->from(route('password.request'))->post(route('password.phone'), [
            'identificador' => '(11) 97777-6666',
            'codigo_2fa' => 'ABCDE-12345',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('redefinir-senha/'.$admin->id, (string) $response->headers->get('Location'));

        $admin->refresh();
        $this->assertSame(['FGHIJ-67890'], $admin->two_factor_recovery_codes);

        $this->assertDatabaseHas('activities', [
            'action' => 'recuperacao_senha_codigo_reserva',
            'subject_type' => class_basename($admin),
            'subject_id' => $admin->id,
        ]);
    }

    public function test_recuperar_por_celular_com_2fa_ativo_mas_sem_codigo_retorna_erro_especifico(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $g2fa = new Google2FA;

        $admin->forceFill([
            'telefone' => '(11) 96666-5555',
            'two_factor_secret' => $g2fa->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->from(route('password.request'))->post(route('password.phone'), [
            'identificador' => '11966665555',
            'codigo_2fa' => '',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('codigo_2fa');
    }

    public function test_recuperar_por_celular_com_2fa_invalido_registra_falha(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $g2fa = new Google2FA;

        $admin->forceFill([
            'telefone' => '(11) 95555-4444',
            'two_factor_secret' => $g2fa->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->from(route('password.request'))->post(route('password.phone'), [
            'identificador' => '11955554444',
            'codigo_2fa' => '999999',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('codigo_2fa');

        $this->assertDatabaseHas('activities', [
            'action' => 'dois_fatores_falhou',
            'subject_type' => class_basename($admin),
            'subject_id' => $admin->id,
        ]);
    }

    public function test_recuperar_por_celular_sem_2fa_ativo_dispara_email_e_exibe_pista_com_email_mascarado(): void
    {
        Mail::fake();

        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $admin->forceFill([
            'telefone' => '(11) 94444-3333',
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $response = $this->from(route('password.request'))->post(route('password.phone'), [
            'identificador' => '(11) 94444-3333',
        ]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        $this->assertStringContainsString('a***n@g****n.com.br', session('status'));

        Mail::assertQueued(RedefinirSenhaEquipe::class, function (RedefinirSenhaEquipe $mail) use ($admin): bool {
            return $mail->user->id === $admin->id;
        });
    }
}
