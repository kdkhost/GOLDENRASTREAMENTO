<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\SessaoDoPainel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A ficha de quem trabalha no painel: as abas, a foto e o perfil que a equipe lê.
 *
 * O cadastro era uma caixa só com nome, e-mail e perfil. Agora ele tem o rosto da pessoa, cargo,
 * setor, admissão e apresentação — e a ficha conta o que a casa já sabia sobre ela: o último
 * acesso, as janelas abertas e os movimentos da auditoria. O que estes testes protegem é que a
 * foto só entra pelo campo certo, que trocá-la não deixa lixo no disco e que as abas não escondem
 * os campos que o servidor exige.
 */
class FichaDoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Cache::flush();
        Storage::fake('public');
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    private function enviarFoto(string $nome = 'rosto.jpg'): string
    {
        return $this->actingAs($this->admin)->postJson('/admin/uploads', [
            'campo' => 'usuario_avatar',
            'arquivo' => UploadedFile::fake()->image($nome, 256, 256),
        ])->assertOk()->json('arquivo.caminho');
    }

    /** @param array<string, mixed> $extras */
    private function dados(User $usuario, array $extras = []): array
    {
        return array_merge([
            'name' => $usuario->name,
            'email' => $usuario->email,
            'telefone' => $usuario->telefone,
            'role' => $usuario->role,
            'status' => $usuario->status,
            'password' => '',
        ], $extras);
    }

    public function test_a_ficha_abre_em_abas_e_traz_os_campos_do_perfil(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.usuarios.edit', $this->admin))
            ->assertOk()
            ->getContent();

        foreach (['acesso', 'perfil', 'seguranca', 'atividade'] as $aba) {
            $this->assertStringContainsString('data-aba="'.$aba.'"', $html, "A aba {$aba} sumiu da ficha.");
            $this->assertStringContainsString('data-aba-painel="'.$aba.'"', $html, "A aba {$aba} não tem painel.");
        }

        foreach (['cargo', 'setor', 'admissao', 'bio'] as $campo) {
            $this->assertStringContainsString('name="'.$campo.'"', $html, "O campo {$campo} não chegou à ficha.");
        }

        // O avatar é um dropzone: o nome do campo viaja em `data-nome`, e o input escondido nasce no JS.
        $this->assertStringContainsString('data-campo="usuario_avatar"', $html);
        $this->assertStringContainsString('data-nome="avatar"', $html);
        $this->assertStringContainsString('Foto do usuário', $html);
    }

    public function test_o_cadastro_novo_mostra_so_acesso_e_perfil(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-aba="acesso"', $html);
        $this->assertStringContainsString('data-aba="perfil"', $html);
        $this->assertStringNotContainsString('data-aba-painel="seguranca"', $html);
        $this->assertStringNotContainsString('data-aba-painel="atividade"', $html);
        $this->assertStringContainsString('Depois de criar, a ficha ganha as abas de Segurança e Atividade.', $html);
    }

    public function test_o_cadastro_guarda_cargo_setor_apresentacao_e_admissao(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Diego Alisson',
                'email' => 'diego@golden.com.br',
                'role' => 'vendedor',
                'password' => 'senha-forte-1',
                'cargo' => 'Gerente comercial',
                'setor' => 'Comercial',
                'bio' => 'Atende transportadoras de médio porte.',
                'admissao' => '2026-03-01',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.usuarios.index'));

        $diego = User::where('email', 'diego@golden.com.br')->firstOrFail();

        $this->assertSame('Gerente comercial', $diego->cargo);
        $this->assertSame('Comercial', $diego->setor);
        $this->assertSame('Atende transportadoras de médio porte.', $diego->bio);
        $this->assertSame('2026-03-01', $diego->admissao->toDateString());
    }

    public function test_a_foto_enviada_aparece_na_lista_e_no_topo_do_painel(): void
    {
        $caminho = $this->enviarFoto();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), $this->dados($this->admin, ['avatar' => $caminho]))
            ->assertSessionHasNoErrors();

        $this->assertSame($caminho, $this->admin->refresh()->avatar);
        Storage::disk('public')->assertExists($caminho);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('storage/'.$caminho, false)
            ->assertSee('thumb-avatar', false);

        // O topo é do layout: a foto de quem está logado chega a qualquer tela do painel.
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('storage/'.$caminho, false);
    }

    public function test_trocar_a_foto_apaga_o_arquivo_anterior(): void
    {
        $antiga = $this->enviarFoto('antiga.jpg');
        $this->admin->update(['avatar' => $antiga]);

        $nova = $this->enviarFoto('nova.jpg');

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), $this->dados($this->admin, ['avatar' => $nova]))
            ->assertSessionHasNoErrors();

        $this->assertSame($nova, $this->admin->refresh()->avatar);
        Storage::disk('public')->assertMissing($antiga);
        Storage::disk('public')->assertExists($nova);
    }

    public function test_foto_vinda_de_outro_campo_e_recusada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.update', $this->admin), $this->dados($this->admin, [
                'avatar' => 'produtos/capa.jpg',
            ]))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->admin->refresh()->avatar);
    }

    public function test_excluir_o_usuario_apaga_a_foto_do_disco(): void
    {
        $caminho = $this->enviarFoto();
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
        $vendedor->update(['avatar' => $caminho]);

        $this->actingAs($this->admin)
            ->delete(route('admin.usuarios.destroy', $vendedor))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $vendedor->id]);
        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_sem_foto_a_lista_desenha_as_iniciais_do_nome(): void
    {
        User::create([
            'name' => 'Diego Alisson',
            'email' => 'diego@golden.com.br',
            'password' => 'senha-forte-1',
            'role' => 'vendedor',
            'status' => 'ativo',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('avatar-letter">DA<', false);
    }

    public function test_o_login_grava_o_ultimo_acesso_com_a_rede(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'golden123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->admin->refresh();
        $this->assertNotNull($this->admin->ultimo_acesso_em);
        $this->assertSame('203.0.113.9', $this->admin->ultimo_acesso_ip);
    }

    public function test_a_aba_de_seguranca_mostra_o_ultimo_acesso_e_as_janelas(): void
    {
        $this->admin->forceFill([
            'ultimo_acesso_em' => now()->setDate(2026, 10, 27)->setTime(9, 42),
            'ultimo_acesso_ip' => '203.0.113.9',
        ])->saveQuietly();

        SessaoDoPainel::create([
            'user_id' => $this->admin->id,
            'sessao' => 'abc',
            'carimbo' => 'def',
            'ip' => '198.51.100.7',
            'agente' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            'ultimo_visto_em' => now()->subMinutes(3),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.edit', $this->admin))
            ->assertOk()
            ->assertSee('27/10/2026 às 09:42')
            ->assertSee('203.0.113.9')
            ->assertSee('Chrome Windows')
            ->assertSee('198.51.100.7')
            // A janela aberta pelo próprio pedido do teste conta junto com a que ele montou.
            ->assertSee('2 aparelhos conectados');
    }

    public function test_a_aba_de_atividade_mostra_os_movimentos_da_pessoa(): void
    {
        $this->actingAs($this->admin);
        Activity::log('usuario_editado', 'Usuário Diego atualizou a própria ficha.', $this->admin);

        $this->get(route('admin.usuarios.edit', $this->admin))
            ->assertOk()
            ->assertSee('Usuário Diego atualizou a própria ficha.')
            ->assertSee('Ver a auditoria completa');
    }
}
