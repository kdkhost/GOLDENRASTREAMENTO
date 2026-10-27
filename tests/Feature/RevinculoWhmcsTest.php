<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O botão que refaz o vínculo da ficha com o financeiro.
 *
 * O vínculo nasce sozinho na primeira visita ao portal e, depois disso, é o número gravado que
 * manda — nenhuma visita volta a conferir e-mail. É o que deixa a tela rápida e é o que a deixa
 * mentir quando a conta do WHMCS é recriada ou o e-mail muda numa das pontas. O que estes testes
 * protegem é a linha única da regra: **só se escreve na ficha o que o financeiro confirmou.** O
 * `ok` grava o id devolvido; o `sem_conta` desfaz o ponteiro velho, porque ficha ligada a uma
 * conta que o próprio e-mail não confirma é a porta de entrada da fatura alheia; e falha de
 * conversa não muda nada — vínculo nenhum se desfaz porque o servidor ficou mudo.
 */
class RevinculoWhmcsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->vendedor = User::where('role', 'vendedor')->firstOrFail();
    }

    private function conta(array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '(35) 99888-7766',
            'email' => 'maria@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
        ], $extra));
    }

    /** As três peças da integração, como se tivessem sido gravadas na tela de chaves do painel. */
    private function ligar(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador-da-api',
            'whmcs.secret' => 'segredo-da-api',
            'whmcs.timeout' => 5,
            'whmcs.limite' => 50,
        ]);
    }

    /** @param  array<string, mixed>  $respostas */
    private function fingirWhmcs(array $respostas = []): void
    {
        Http::fake(function (RequisicaoHttp $requisicao) use ($respostas) {
            $acao = (string) ($requisicao['action'] ?? '');

            return $respostas[$acao] ?? Http::response([
                'result' => 'error',
                'message' => 'ação não prevista no teste: '.$acao,
            ]);
        });
    }

    private function contaEncontrada(int $id): array
    {
        return ['GetClientsDetails' => Http::response(['result' => 'SUCCESS', 'clientid' => $id])];
    }

    private function semConta(): array
    {
        return ['GetClientsDetails' => Http::response(['result' => 'SUCCESS', 'clientid' => ''])];
    }

    /** O flash é uma frase inteira; o que o teste trava é o pedaço que importa. */
    private function contem(string $pedaco): \Closure
    {
        return fn (string $valor): bool => str_contains($valor, $pedaco);
    }

    public function test_o_financeiro_confirma_o_email_e_a_ficha_ganha_o_numero_dele(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 11]);
        $this->fingirWhmcs($this->contaEncontrada(88));

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('success', $this->contem('#88'));

        $this->assertSame(88, $cliente->fresh()->whmcs_id);
        $this->assertTrue(Activity::where('action', 'cliente_revinculado')->exists());
    }

    public function test_quando_o_numero_ja_e_o_mesmo_nada_e_alterado(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 88]);
        $this->fingirWhmcs($this->contaEncontrada(88));

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('info', $this->contem('Nada mudou'));

        $this->assertSame(88, $cliente->fresh()->whmcs_id);
        $this->assertFalse(Activity::where('action', 'cliente_revinculado')->exists());
    }

    public function test_a_conta_ja_vinculada_a_outra_ficha_nao_e_trocada(): void
    {
        $this->ligar();
        $dona = $this->conta([
            'nome' => 'Dona da Conta',
            'email' => 'dona@exemplo.com.br',
            'email_portal' => 'dona@exemplo.com.br',
            'whmcs_id' => 88,
        ]);
        $cliente = $this->conta(['whmcs_id' => 11]);
        $this->fingirWhmcs($this->contaEncontrada(88));

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('error', $this->contem('Dona da Conta'));

        $this->assertSame(11, $cliente->fresh()->whmcs_id);
        $this->assertSame(88, $dona->fresh()->whmcs_id);
    }

    public function test_o_financeiro_nao_confirma_o_email_e_o_vinculo_velho_e_desfeito(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 11]);
        $this->fingirWhmcs($this->semConta());

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('warning', $this->contem('foi desfeito'));

        $this->assertNull($cliente->fresh()->whmcs_id);
        $this->assertTrue(Activity::where('action', 'cliente_desvinculado')->exists());
    }

    public function test_sem_vinculo_velho_a_ficha_continua_como_estava(): void
    {
        $this->ligar();
        $cliente = $this->conta();
        $this->fingirWhmcs($this->semConta());

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertNull($cliente->fresh()->whmcs_id);
        $this->assertFalse(Activity::where('action', 'cliente_desvinculado')->exists());
    }

    public function test_falha_de_conversa_nao_desfaz_vinculo_nenhum(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 11]);

        foreach ([
            'muda' => fn () => Http::fake(fn () => Http::connectionFailed()),
            'http errado' => fn () => Http::fake(['*' => Http::response('fora do ar', 503)]),
            'credencial recusada' => fn () => $this->fingirWhmcs([
                'GetClientsDetails' => Http::response(['result' => 'FAILURE', 'message' => 'Invalid credential supplied']),
            ]),
        ] as $motivo => $fingir) {
            $fingir();

            $this->actingAs($this->admin, 'web')
                ->post(route('admin.clientes.revincular', $cliente))
                ->assertRedirect()
                ->assertSessionHas('error');

            $this->assertSame(11, $cliente->fresh()->whmcs_id, "o vínculo não pode ter mudado por causa de: {$motivo}");
        }
    }

    public function test_ficha_sem_email_nao_gasta_chamada_nenhuma(): void
    {
        $this->ligar();
        $cliente = $this->conta(['email' => null, 'email_portal' => null, 'whmcs_id' => 11]);
        Http::fake();

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('error', $this->contem('não tem e-mail'));

        Http::assertNothingSent();
        $this->assertSame(11, $cliente->fresh()->whmcs_id);
    }

    public function test_sem_credencial_a_rota_nao_chama_e_a_ficha_nao_oferece_o_botao(): void
    {
        Http::fake();
        $cliente = $this->conta(['whmcs_id' => 11]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect()
            ->assertSessionHas('error', $this->contem('três credenciais'));

        Http::assertNothingSent();

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))
            ->assertOk()
            ->assertDontSee('Revincular ao financeiro');
    }

    public function test_a_ficha_oferece_o_botao_com_a_conta_ligada(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 11]);

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))
            ->assertOk()
            ->assertSee('Revincular ao financeiro')
            ->assertSee('maria@exemplo.com.br');
    }

    public function test_o_vendedor_refaz_o_vinculo_e_quem_so_le_enxerga_proibido(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 11]);
        $this->fingirWhmcs($this->contaEncontrada(88));

        $this->actingAs($this->vendedor, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertRedirect();

        $this->assertSame(88, $cliente->fresh()->whmcs_id);

        $visualizador = User::create([
            'name' => 'Só Olha',
            'email' => 'olha@exemplo.com.br',
            'password' => 'qualquer-senha-123',
            'role' => 'visualizador',
            'status' => 'ativo',
        ]);

        $this->fingirWhmcs($this->contaEncontrada(99));

        $this->actingAs($visualizador, 'web')
            ->post(route('admin.clientes.revincular', $cliente))
            ->assertForbidden();

        $this->assertSame(88, $cliente->fresh()->whmcs_id);
    }
}
