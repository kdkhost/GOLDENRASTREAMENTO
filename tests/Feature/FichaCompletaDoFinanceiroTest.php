<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A ficha do painel lendo o financeiro inteiro.
 *
 * O botão "Puxar dados do financeiro" copia para o CRM só o que está em branco. Esta seção é o
 * outro lado: mostra tudo o que o WHMCS sabe — cadastro completo, serviços contratados e faturas
 * — lido na hora em que a ficha abre, sem copiar nada para a base local. O que estes testes
 * protegem, além dos dados aparecerem:
 *
 * 1. **A equipe vê o que o cliente não vê.** Contrato encerrado e fatura paga aparecem aqui mesmo
 *    com a vitrine do portal fechada: é o histórico que a equipe consulta ao atender.
 * 2. **"Não consegui perguntar" nunca vira "não tem".** Financeiro mudo mostra o aviso e não a
 *    tabela vazia — e, como as leituras dependem do cadastro, a página espera um tempo limite, não
 *    três.
 * 3. **Senha de serviço não sai do financeiro.** A lista de produtos pode trazer `username` e
 *    `password` do servidor; nenhum dos dois é mapeado nem desenhado.
 */
class FichaCompletaDoFinanceiroTest extends TestCase
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
            'email' => 'roberto@exemplo.com.br',
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

    /** O cadastro inteiro, no formato plano que `GetClientsDetails` devolve. */
    private function cadastro(array $extra = []): array
    {
        return array_merge([
            'result' => 'SUCCESS',
            'clientid' => 10045,
            'firstname' => 'Roberto',
            'lastname' => 'junior',
            'fullname' => 'Roberto junior',
            'companyname' => '',
            'email' => 'robertojuniorjr39@gmail.com',
            'address1' => 'Rua Atalaia, 77',
            'address2' => 'Aliança',
            'city' => 'Ribeirão Pires',
            'state' => 'SP',
            'postcode' => '09403150',
            'countryname' => 'Brasil',
            'phonenumber' => '11942228599',
            'groupid' => 3,
            'status' => 'Active',
            'language' => 'portuguese-br',
            'currency_code' => 'BRL',
            'credit' => '12.50',
            'datecreated' => '2023-04-05',
            'lastlogin' => 'Date: 29/10/2026 12:34<br>IP Address: 191.10.20.30',
            'notes' => 'Instalação feita na loja.',
            'customfields' => [
                ['id' => 10, 'value' => '08/01/1937'],
                ['id' => 17, 'value' => 'servidor 3'],
                ['id' => 18, 'value' => 'robertojuniorjr39@gmail.com'],
                ['id' => 2, 'value' => '230.349.768-01'],
                ['id' => 89, 'value' => 'indicado por João'],
                ['id' => 8, 'value' => 'NFE'],
                ['id' => 249, 'value' => 'Emitir nota após confirmação de pagamento'],
            ],
        ], $extra);
    }

    private function produtos(array $extra = []): array
    {
        return array_merge([
            'result' => 'SUCCESS',
            'products' => ['product' => [
                [
                    'id' => 12,
                    'name' => 'Rastreador J16 4G',
                    'groupname' => 'Rastreamento veicular',
                    'domain' => 'ABC1D23',
                    'servername' => 'srv3.golden.com',
                    'status' => 'Active',
                    'nextduedate' => '2026-11-10',
                    'recurringamount' => '59.90',
                    'billingcycle' => 'Monthly',
                    'regdate' => '2025-11-10',
                ],
                [
                    'id' => 7,
                    'name' => 'Rastreador antigo',
                    'groupname' => 'Rastreamento veicular',
                    'domain' => '',
                    'servername' => '',
                    'status' => 'Terminated',
                    'nextduedate' => '2024-01-10',
                    'recurringamount' => '49.90',
                    'billingcycle' => 'Monthly',
                    'regdate' => '2022-01-10',
                ],
            ]],
        ], $extra);
    }

    private function faturas(array $extra = []): array
    {
        return array_merge([
            'result' => 'SUCCESS',
            'invoices' => ['invoice' => [
                [
                    'id' => 9001,
                    'invoicenum' => '9001',
                    'date' => '2026-10-01',
                    'duedate' => '2026-10-10',
                    'datepaid' => '0000-00-00 00:00:00',
                    'total' => '120.00',
                    'balance' => '120.00',
                    'status' => 'Unpaid',
                ],
                [
                    'id' => 8800,
                    'invoicenum' => '',
                    'date' => '2026-09-01',
                    'duedate' => '2026-09-10',
                    'datepaid' => '2026-09-08 10:15:00',
                    'total' => '59.90',
                    'balance' => '0.00',
                    'status' => 'Paid',
                ],
            ]],
        ], $extra);
    }

    /** O financeiro de mentira, respondendo cada ação com a sua própria carga. */
    private function fingirFinanceiro(): void
    {
        Http::fake(function (RequisicaoHttp $requisicao) {
            return match ((string) ($requisicao['action'] ?? '')) {
                'GetClientsDetails' => Http::response($this->cadastro()),
                'GetClientGroups' => Http::response([
                    'result' => 'SUCCESS',
                    'groups' => ['group' => [['id' => 3, 'groupname' => 'Revendas']]],
                ]),
                'GetClientsProducts' => Http::response($this->produtos()),
                'GetInvoices' => Http::response($this->faturas()),
                default => Http::response(['result' => 'error', 'message' => 'ação não prevista no teste']),
            };
        });
    }

    private function ficha(Customer $cliente): string
    {
        return $this->visivel($this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))
            ->assertOk()
            ->getContent());
    }

    public function test_a_ficha_mostra_o_cadastro_completo_servicos_e_faturas(): void
    {
        $this->ligar();
        $this->fingirFinanceiro();
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $html = $this->ficha($cliente);

        // O cadastro do financeiro, inclusive o que o CRM não guarda.
        $this->assertStringContainsString('Cadastro no financeiro', $html);
        $this->assertStringContainsString('Conta #10045', $html);
        $this->assertStringContainsString('Revendas', $html);
        $this->assertStringContainsString('Português (Brasil)', $html);
        $this->assertStringContainsString('R$ 12,50', $html);
        $this->assertStringContainsString('05/04/2023', $html);
        $this->assertStringContainsString('29/10/2026 12:34', $html);
        $this->assertStringContainsString('Instalação feita na loja.', $html);
        $this->assertStringContainsString('Campo #89: indicado por João', $html);
        $this->assertStringContainsString('emitir nota fiscal', $html);

        // Serviços: o ativo com servidor, e o encerrado porque a equipe atende os dois.
        $this->assertStringContainsString('Serviços contratados', $html);
        $this->assertStringContainsString('Rastreador J16 4G', $html);
        $this->assertStringContainsString('Servidor srv3.golden.com', $html);
        $this->assertStringContainsString('Rastreador antigo', $html);
        $this->assertStringContainsString('Encerrado', $html);
        $this->assertStringContainsString('por mês', $html);

        // Faturas: a em aberto com saldo, a paga com data de pagamento.
        $this->assertStringContainsString('Faturas', $html);
        $this->assertStringContainsString('#9001', $html);
        $this->assertStringContainsString('R$ 120,00 em aberto.', $html);
        $this->assertStringContainsString('08/09/2026', $html);
    }

    public function test_a_equipe_ve_o_encerrado_e_o_pago_mesmo_com_a_vitrine_do_cliente_fechada(): void
    {
        config([
            'whmcs.veiculacao.servicos' => false,
            'whmcs.veiculacao.servicos_encerrados' => false,
            'whmcs.veiculacao.faturas' => false,
            'whmcs.veiculacao.faturas_pagas' => false,
            'whmcs.veiculacao.link_externo' => false,
        ]);
        $this->ligar();
        $this->fingirFinanceiro();
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $html = $this->ficha($cliente);

        $this->assertStringContainsString('Rastreador antigo', $html);
        $this->assertStringContainsString('Encerrado', $html);
        $this->assertStringContainsString('Paga em 08/09/2026', $html);

        // Sem link externo, a coluna "Abrir" não leva ninguém para fora do painel.
        $this->assertStringNotContainsString('clientarea.php', $html);
    }

    public function test_financeiro_mudo_avisa_que_nao_conseguiu_ler_e_nao_diz_que_esta_vazio(): void
    {
        $this->ligar();
        Http::fake(['*' => Http::response('fora do ar', 503)]);
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $html = $this->ficha($cliente);

        $this->assertStringContainsString('O financeiro não respondeu à consulta da conta #10045', $html);
        $this->assertStringContainsString('não pôde ser lido', $html);
        $this->assertStringNotContainsString('Nenhum serviço contratado', $html);
        $this->assertStringNotContainsString('Nenhuma fatura emitida', $html);

        // As leituras seguintes nem foram tentadas: uma espera de tempo limite, não três.
        Http::assertSentCount(1);
    }

    public function test_ficha_sem_vinculo_explica_o_caminho_e_nao_consulta_o_financeiro(): void
    {
        $this->ligar();
        Http::fake();
        $cliente = $this->conta();

        $html = $this->ficha($cliente);

        Http::assertNothingSent();
        $this->assertStringContainsString('Cadastro no financeiro', $html);
        $this->assertStringContainsString('Revincular ao financeiro', $html);
        $this->assertStringNotContainsString('Serviços contratados', $html);
    }

    public function test_sem_credencial_a_secao_do_financeiro_nao_aparece(): void
    {
        Http::fake();
        $cliente = $this->conta(['whmcs_id' => 10045]);

        $html = $this->ficha($cliente);

        $this->assertStringNotContainsString('Cadastro no financeiro', $html);
        $this->assertStringNotContainsString('Serviços contratados', $html);
        Http::assertNothingSent();
    }

    public function test_a_senha_e_o_usuario_do_servidor_nunca_aparecem_na_ficha(): void
    {
        $this->ligar();
        $produtos = $this->produtos();
        $produtos['products']['product'][0]['username'] = 'roberto@plataforma';
        $produtos['products']['product'][0]['password'] = 'SenhaDoServidor-123';

        Http::fake(fn (RequisicaoHttp $requisicao) => match ((string) ($requisicao['action'] ?? '')) {
            'GetClientsDetails' => Http::response($this->cadastro()),
            'GetClientGroups' => Http::response(['result' => 'SUCCESS', 'groups' => ['group' => [['id' => 3, 'groupname' => 'Revendas']]]]),
            'GetClientsProducts' => Http::response($produtos),
            'GetInvoices' => Http::response($this->faturas()),
            default => Http::response(['result' => 'error', 'message' => 'ação não prevista']),
        });

        $html = $this->ficha($this->conta(['whmcs_id' => 10045]));

        $this->assertStringContainsString('Rastreador J16 4G', $html);
        $this->assertStringNotContainsString('SenhaDoServidor-123', $html);
        $this->assertStringNotContainsString('roberto@plataforma', $html);
    }
}
