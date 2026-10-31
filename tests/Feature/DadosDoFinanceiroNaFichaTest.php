<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O preenchimento da ficha com o cadastro do financeiro.
 *
 * A regra que estes testes protegem cabe numa frase: **o que está escrito na ficha manda**. O
 * botão só completa o que está em branco, nunca troca um dado digitado pela equipe, e diz em
 * voz alta o que entrou e o que foi mantido. A rotina em massa usa o mesmo caminho — por isso o
 * último teste confere que simular não grava e aplicar grava.
 *
 * O mapeamento dos campos personalizados também fica travado aqui: a API do WHMCS devolve
 * `{id, value}` sem nome, e é o mapa de `config('whmcs.campos')` que decide que o campo 2 é o
 * CPF, o 10 é o nascimento e o 17/18 são o acesso à plataforma.
 */
class DadosDoFinanceiroNaFichaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    private function conta(array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'nome' => 'Roberto Francisco de Souza junior',
            'tipo' => 'fisica',
            'email' => 'maria@exemplo.com.br',
            'status' => 'ativo',
        ], $extra));
    }

    private function ligar(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador-da-api',
            'whmcs.secret' => 'segredo-da-api',
            'whmcs.timeout' => 5,
            'whmcs.campos.documento' => 2,
            'whmcs.campos.nascimento' => 10,
            'whmcs.campos.plataforma' => 17,
            'whmcs.campos.usuario' => 18,
        ]);
    }

    /** A resposta real de `GetClientsDetails` para a conta 10045, recortada no que a ficha lê. */
    private function fichaDoFinanceiro(array $extra = []): array
    {
        return array_merge([
            'result' => 'SUCCESS',
            'clientid' => 10045,
            'firstname' => 'Roberto',
            'lastname' => 'junior',
            'fullname' => 'Roberto junior',
            'email' => 'robertojuniorjr39@gmail.com',
            'address1' => 'Rua Atalaia, 77',
            'address2' => 'Aliança',
            'city' => 'Ribeirão Pires',
            'state' => 'SP',
            'postcode' => '09403150',
            'phonenumber' => '11942228599',
            'tax_id' => '',
            'customfields' => [
                ['id' => 10, 'value' => '08/01/1937'],
                ['id' => 17, 'value' => 'servidor 3'],
                ['id' => 18, 'value' => 'robertojuniorjr39@gmail.com'],
                ['id' => 2, 'value' => '230.349.768-01'],
                ['id' => 89, 'value' => ''],
            ],
        ], $extra);
    }

    private function fingirWhmcs(?array $resposta = null): void
    {
        Http::fake(['*' => Http::response($resposta ?? $this->fichaDoFinanceiro())]);
    }

    public function test_o_botao_completa_a_ficha_com_o_cadastro_do_financeiro(): void
    {
        $this->ligar();
        $this->fingirWhmcs();
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.puxar', $cliente))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $valor): bool => str_contains($valor, 'Endereço')
                && str_contains($valor, 'Plataforma'));

        $cliente->refresh();

        $this->assertSame('Rua Atalaia, 77 - Aliança', $cliente->endereco);
        $this->assertSame('Ribeirão Pires', $cliente->cidade);
        $this->assertSame('SP', $cliente->uf);
        $this->assertSame('09403150', $cliente->cep);
        $this->assertSame('230.349.768-01', $cliente->documento);
        $this->assertSame('1937-01-08', $cliente->nascimento->format('Y-m-d'));
        $this->assertSame('(11) 94222-8599', $cliente->telefone);
        $this->assertSame('servidor 3', $cliente->plataforma);
        $this->assertSame('robertojuniorjr39@gmail.com', $cliente->usuario_plataforma);
        $this->assertSame('fisica', $cliente->tipo);
        $this->assertTrue(Activity::where('action', 'cliente_dados_do_financeiro')->exists());
    }

    public function test_o_que_a_equipe_ja_escreveu_na_ficha_nao_e_trocado(): void
    {
        $this->ligar();
        $this->fingirWhmcs();
        $cliente = $this->conta([
            'whmcs_id' => 10045,
            'endereco' => 'Rua Antiga, 1',
            'cidade' => 'Ribeirão Pires',
        ]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.puxar', $cliente))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $valor): bool => str_contains($valor, 'Mantidos')
                && str_contains($valor, 'Endereço'));

        $cliente->refresh();

        $this->assertSame('Rua Antiga, 1', $cliente->endereco);
        $this->assertSame('Ribeirão Pires', $cliente->cidade);
        $this->assertSame('SP', $cliente->uf);
    }

    public function test_documento_novo_de_cnpj_torna_a_ficha_pessoa_juridica(): void
    {
        $this->ligar();
        $this->fingirWhmcs($this->fichaDoFinanceiro([
            'customfields' => [['id' => 2, 'value' => '12.345.678/0001-90']],
        ]));
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $this->actingAs($this->admin, 'web')->post(route('admin.clientes.puxar', $cliente));

        $cliente->refresh();

        $this->assertSame('12.345.678/0001-90', $cliente->documento);
        $this->assertSame('juridica', $cliente->tipo);
    }

    public function test_documento_que_ja_existia_manda_no_tipo_de_pessoa(): void
    {
        $this->ligar();
        $this->fingirWhmcs();
        $cliente = $this->conta(['whmcs_id' => 10045, 'documento' => '123.456.789-01']);

        $this->actingAs($this->admin, 'web')->post(route('admin.clientes.puxar', $cliente));

        $this->assertSame('123.456.789-01', $cliente->fresh()->documento);
        $this->assertSame('fisica', $cliente->fresh()->tipo);
    }

    public function test_ficha_sem_vinculo_explica_que_falta_revincular_e_nao_chama_o_financeiro(): void
    {
        $this->ligar();
        Http::fake();
        $cliente = $this->conta();

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.puxar', $cliente))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $valor): bool => str_contains($valor, 'Revincular'));

        Http::assertNothingSent();
        $this->assertNull($cliente->fresh()->endereco);
    }

    public function test_financeiro_mudo_nao_altera_nada(): void
    {
        $this->ligar();
        Http::fake(['*' => Http::response('fora do ar', 503)]);
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.puxar', $cliente))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $valor): bool => str_contains($valor, 'não respondeu'));

        $cliente->refresh();

        $this->assertNull($cliente->endereco);
        $this->assertNull($cliente->plataforma);
        $this->assertNull($cliente->documento);
        $this->assertFalse(Activity::where('action', 'cliente_dados_do_financeiro')->exists());
    }

    public function test_sem_credencial_o_botao_nao_aparece_na_ficha(): void
    {
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $html = $this->visivel($this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))
            ->assertOk()
            ->getContent());

        $this->assertStringNotContainsString('Puxar dados do financeiro', $html);
    }

    public function test_a_ficha_mostra_o_acesso_a_plataforma_e_a_edicao_grava_os_dois_campos(): void
    {
        $this->ligar();
        $cliente = $this->conta([
            'whmcs_id' => 10045,
            'plataforma' => 'servidor 2',
            'usuario_plataforma' => 'maria@exemplo.com.br',
        ]);

        $html = $this->visivel($this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString('Acesso à plataforma', $html);
        $this->assertStringContainsString('servidor 2', $html);
        $this->assertStringContainsString('Puxar dados do financeiro', $html);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.update', $cliente), [
                'nome' => $cliente->nome,
                'tipo' => 'fisica',
                'status' => 'ativo',
                'plataforma' => 'servidor 4',
                'usuario_plataforma' => 'novo@exemplo.com.br',
            ])
            ->assertRedirect();

        $cliente->refresh();

        $this->assertSame('servidor 4', $cliente->plataforma);
        $this->assertSame('novo@exemplo.com.br', $cliente->usuario_plataforma);
    }

    public function test_a_rotina_em_massa_simula_antes_de_gravar(): void
    {
        $this->ligar();
        $this->fingirWhmcs();
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $this->artisan('clientes:puxar-do-financeiro')->assertExitCode(0);
        $this->assertNull($cliente->fresh()->endereco, 'a simulação não pode gravar nada');

        $this->artisan('clientes:puxar-do-financeiro', ['--aplicar' => true])->assertExitCode(0);
        $this->assertSame('Rua Atalaia, 77 - Aliança', $cliente->fresh()->endereco);
        $this->assertTrue(Activity::where('action', 'cliente_dados_do_financeiro')->exists());
    }
}
