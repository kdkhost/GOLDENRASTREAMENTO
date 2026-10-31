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
 * As seções novas da ficha: adicionais, domínios, pagamentos, créditos, pedidos, contatos,
 * chamados e orçamentos do financeiro.
 *
 * `FichaCompletaDoFinanceiroTest` cobre o cadastro, os serviços e as faturas. Esta classe cobre o
 * resto — e o resto é justamente onde o vazamento mora, porque três consultas do WHMCS
 * (`GetOrders`, `GetContacts`, `GetQuotes`) foram conferidas na produção respondendo sem o recado
 * de quem é o dono, ou ignorando o filtro que a ficha pediu. O que estes testes protegem:
 *
 * 1. **Nenhum dado de outra conta chega à tela.** Item que declara outro dono fica de fora;
 *    resposta que nomeia outra conta sai inteira; item que não declara dono só entra na consulta
 *    que filtrou por conta.
 * 2. **Credencial não atravessa a ficha.** Serviço, contato, usuário da conta e cartão têm campos
 *    de senha, chave de redefinição, token e número completo; nenhum deles é lido nem desenhado.
 * 3. **Lido-e-vazio, não-lido e não-consultado são três respostas diferentes na tela.** O financeiro
 *    que não respondeu nunca aparece como "não tem", e o que nem foi perguntado explica por quê.
 */
class SecoesDoFinanceiroNaFichaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** A carga que o financeiro de mentira devolve para cada ação. O que falta vira recusa. */
    private array $cargas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $this->ligar();
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

    private function conta(array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'nome' => 'Roberto Francisco de Souza junior',
            'tipo' => 'fisica',
            'email' => 'roberto@exemplo.com.br',
            'status' => 'ativo',
            'whmcs_id' => 10045,
        ], $extra));
    }

    /** O cadastro da conta #10045, com o número de usuário que libera pedidos e contatos. */
    private function cadastro(array $extra = []): array
    {
        return array_merge([
            'result' => 'SUCCESS',
            'clientid' => 10045,
            'userid' => 777,
            'fullname' => 'Roberto junior',
            'email' => 'roberto@exemplo.com.br',
            'status' => 'Active',
            'currency_code' => 'BRL',
        ], $extra);
    }

    private function fingir(): void
    {
        // Sem anotar o tipo de retorno: o `Http::fake()` devolve uma promise quando o chamador
        // usa `Http::pool`, e uma assinatura `: Response` faria toda consulta virar
        // "sem_resposta" — o enganaria os testes em vez de testar a tela.
        Http::fake(fn (RequisicaoHttp $requisicao) => Http::response(
            $this->cargas[(string) ($requisicao['action'] ?? '')]
                ?? ['result' => 'error', 'message' => 'ação não prevista no teste'],
        ));
    }

    private function ficha(Customer $cliente): string
    {
        $resposta = $this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente));

        $resposta->assertOk();

        return $this->visivel($resposta->getContent());
    }

    /** As oito cargas cheias, uma por seção nova, cada item declarado dono da conta consultada. */
    private function todasAsSecoes(): void
    {
        $this->cargas = [
            'GetClientsDetails' => $this->cadastro(),
            'GetClientsAddons' => ['result' => 'SUCCESS', 'addons' => ['addon' => [[
                'id' => 5, 'clientid' => 10045, 'name' => 'Proteção de dados', 'productname' => 'Rastreador J16 4G',
                'status' => 'Active', 'recurringamount' => '19.90', 'billingcycle' => 'Monthly',
                'nextduedate' => '2026-12-10', 'serviceid' => 12,
            ]]]],
            'GetClientsDomains' => ['result' => 'SUCCESS', 'domains' => ['domain' => [[
                'id' => 31, 'clientid' => 10045, 'domain' => 'goldentrack.com.br', 'type' => 'Register',
                'registrar' => 'OpenSRS', 'status' => 'Active', 'registrationdate' => '2020-03-04',
                'expirydate' => '2027-03-04', 'nextduedate' => '2027-03-04', 'recurringamount' => '39.90',
                'billingcycle' => 'Annually', 'auto_renew' => 1,
            ]]]],
            'GetTransactions' => ['result' => 'SUCCESS', 'transactions' => ['transaction' => [[
                'id' => 88, 'clientid' => 10045, 'invoiceid' => 9001, 'date' => '2026-09-08',
                'description' => 'Pagamento da fatura 9001', 'amountin' => '59.90', 'fees' => '0.00',
                'gateway' => 'golden_inter_boleto', 'transid' => 'INTER-2026-000888',
            ]]]],
            'GetCredits' => ['result' => 'SUCCESS', 'credits' => ['credit' => [[
                'id' => 12, 'date' => '2026-08-01', 'description' => 'Cortesia pela instalação demorada', 'amount' => '12.50',
            ]]]],
            'GetOrders' => ['result' => 'SUCCESS', 'orders' => ['order' => [[
                'id' => 2040, 'userid' => 777, 'ordernum' => 'ZXCVBNMLKJ', 'date' => '2025-11-10',
                'status' => 'Active', 'amount' => '59.90', 'paymentmethod' => 'pix', 'invoiceid' => 9001,
                'lineitems' => ['lineitem' => [
                    ['producttype' => 'Hosting', 'product' => 'Rastreador J16 4G', 'amount' => '59.90', 'qty' => 1],
                    ['producttype' => 'Domain', 'domain' => 'goldentrack.com.br', 'amount' => '39.90'],
                ]],
            ]]]],
            'GetContacts' => ['result' => 'SUCCESS', 'contacts' => ['contact' => [[
                'id' => 66, 'userid' => 777, 'name' => 'Marcos Aguiar', 'email' => 'marcos@exemplo.com.br',
                'phonenumber' => '1133334444', 'subaccount' => 1, 'permissions' => 'invoices,tickets',
                'invoice_emails' => 1, 'ticket_emails' => 0, 'created_at' => '2024-02-02',
            ]]]],
            'GetTickets' => ['result' => 'SUCCESS', 'tickets' => ['ticket' => [[
                'id' => 701, 'clientid' => 10045, 'tid' => '701', 'subject' => 'Rastreador parado',
                'deptname' => 'Technical Support', 'status' => 'Open', 'priority' => 'High',
                'date' => '2026-10-02', 'lastreply' => '2026-10-03 15:20:00', 'admin' => 'Ana',
            ]]]],
            'GetQuotes' => ['result' => 'SUCCESS', 'quotes' => ['quote' => [[
                'id' => 90, 'clientid' => 10045, 'subject' => 'Proposta de 3 rastreadores', 'stage' => 'Delivered',
                'datecreated' => '2026-07-01', 'datesent' => '2026-07-02', 'validuntil' => '2026-07-31',
                'total' => '420.00', 'customernotes' => 'Instalação na loja sem custo.',
                'items' => ['item' => [['description' => 'Rastreador J16 4G', 'amount' => '140.00']]],
            ]]]],
        ];
    }

    public function test_cada_secao_do_financeiro_aparece_na_ficha_traduzida(): void
    {
        $this->todasAsSecoes();
        $this->fingir();

        $html = $this->ficha($this->conta());

        $this->assertStringContainsString('Adicionais contratados', $html);
        $this->assertStringContainsString('Proteção de dados', $html);
        $this->assertStringContainsString('Do serviço Rastreador J16 4G', $html);
        $this->assertStringContainsString('10/12/2026', $html);

        $this->assertStringContainsString('Domínios', $html);
        $this->assertStringContainsString('goldentrack.com.br', $html);
        $this->assertStringContainsString('Registrado aqui', $html);
        $this->assertStringContainsString('renova sozinho', $html);
        $this->assertStringContainsString('cobra 04/03/2027', $html);
        $this->assertStringContainsString('por ano', $html);

        $this->assertStringContainsString('Pagamentos e movimentos', $html);
        $this->assertStringContainsString('Pagamento da fatura 9001', $html);
        $this->assertStringContainsString('Boleto (Banco Inter)', $html);
        $this->assertStringContainsString('R$ 59,90', $html);
        $this->assertStringContainsString('INTER-2026-000888', $html);

        $this->assertStringContainsString('Movimentos de crédito', $html);
        $this->assertStringContainsString('Cortesia pela instalação demorada', $html);
        $this->assertStringContainsString('R$ 12,50', $html);

        $this->assertStringContainsString('Pedidos no financeiro', $html);
        $this->assertStringContainsString('#ZXCVBNMLKJ', $html);
        $this->assertStringContainsString('Concluído', $html);
        $this->assertStringContainsString('Pix', $html);
        $this->assertStringContainsString('Serviço', $html);

        $this->assertStringContainsString('Chamados no financeiro', $html);
        $this->assertStringContainsString('Rastreador parado', $html);
        $this->assertStringContainsString('Suporte técnico', $html);
        $this->assertStringContainsString('Aberto', $html);
        $this->assertStringContainsString('Alta', $html);

        $this->assertStringContainsString('Orçamentos', $html);
        $this->assertStringContainsString('Proposta de 3 rastreadores', $html);
        $this->assertStringContainsString('Enviado', $html);
        $this->assertStringContainsString('Instalação na loja sem custo.', $html);
        $this->assertStringContainsString('R$ 420,00', $html);

        $this->assertStringContainsString('Contatos da conta', $html);
        $this->assertStringContainsString('Marcos Aguiar', $html);
        $this->assertStringContainsString('Subconta', $html);
        $this->assertStringContainsString('Faturas', $html);
        $this->assertStringContainsString('Recebe: Faturas, Chamados', $html);

        // Nenhum estado do financeiro ficou na língua de origem.
        foreach (['Active', 'Delivered', 'Technical Support', 'Hosting', 'golden_inter_boleto', 'Monthly', 'Register'] as $emIngles) {
            $this->assertStringNotContainsString($emIngles, $html);
        }
    }

    public function test_item_que_declara_outra_conta_fica_fora_e_o_da_ficha_continua(): void
    {
        $this->todasAsSecoes();
        $this->cargas['GetClientsProducts'] = ['result' => 'SUCCESS', 'products' => ['product' => [
            ['id' => 12, 'clientid' => 10045, 'name' => 'Rastreador J16 4G', 'status' => 'Active'],
            ['id' => 13, 'clientid' => 99999, 'name' => 'Rastreador de outra pessoa', 'status' => 'Active'],
        ]]];
        $this->cargas['GetInvoices'] = ['result' => 'SUCCESS', 'invoices' => ['invoice' => [
            ['id' => 9001, 'clientid' => 10045, 'status' => 'Unpaid', 'total' => '59.90', 'date' => '2026-10-01'],
            ['id' => 9002, 'userid' => 555, 'status' => 'Unpaid', 'total' => '700.00', 'date' => '2026-10-01'],
        ]]];
        $this->cargas['GetClientsAddons']['addons']['addon'][] =
            ['id' => 6, 'clientid' => 99999, 'name' => 'Proteção de outra pessoa', 'status' => 'Active'];
        $this->cargas['GetClientsDomains']['domains']['domain'][] =
            ['id' => 32, 'clientid' => 99999, 'domain' => 'outropessoa.com.br', 'status' => 'Active'];
        $this->cargas['GetTransactions']['transactions']['transaction'][] =
            ['id' => 89, 'clientid' => 99999, 'description' => 'Pagamento de outra pessoa', 'amountin' => '9.90'];
        $this->cargas['GetTickets']['tickets']['ticket'][] =
            ['id' => 702, 'clientid' => 99999, 'tid' => '702', 'subject' => 'Chamado de outra pessoa', 'status' => 'Open'];
        $this->fingir();

        $html = $this->ficha($this->conta());

        foreach (['Rastreador de outra pessoa', '#9002', 'Proteção de outra pessoa', 'outropessoa.com.br',
            'Pagamento de outra pessoa', 'Chamado de outra pessoa'] as $deOutraConta) {
            $this->assertStringNotContainsString($deOutraConta, $html);
        }

        $this->assertStringContainsString('Rastreador J16 4G', $html);
        $this->assertStringContainsString('Proteção de dados', $html);
    }

    public function test_resposta_que_nomeia_outra_conta_sai_inteira_da_ficha(): void
    {
        $this->todasAsSecoes();

        // O financeiro respondeu a lista dizendo ser da conta 99999: nada dali entra na ficha.
        $this->cargas['GetClientsProducts'] = ['result' => 'SUCCESS', 'clientid' => 99999, 'products' => ['product' => [
            ['id' => 12, 'name' => 'Rastreador de outra pessoa', 'status' => 'Active'],
        ]]];
        $this->cargas['GetCredits']['clientid'] = 99999;
        $this->fingir();

        $html = $this->ficha($this->conta());

        $this->assertStringNotContainsString('Rastreador de outra pessoa', $html);
        $this->assertStringNotContainsString('Cortesia pela instalação demorada', $html);

        // E a tela diz que não leu, em vez de dizer que a conta não tem nada.
        $this->assertStringContainsString('consulta de serviços', $html);
        $this->assertStringContainsString('não pôde ser lida', $html);
        $this->assertStringNotContainsString('Nenhum serviço contratado', $html);
    }

    public function test_pedido_contato_e_orcamento_so_entram_quando_declaram_a_conta_da_ficha(): void
    {
        $this->todasAsSecoes();

        // Conferido na produção: essas três consultas respondem sem dono ou ignoram o filtro.
        $this->cargas['GetOrders']['orders']['order'][] = [
            'id' => 2041, 'userid' => 555, 'ordernum' => 'OUTRO1', 'status' => 'Active', 'amount' => '990.00',
        ];
        $this->cargas['GetOrders']['orders']['order'][] = [
            'id' => 2042, 'ordernum' => 'SEMDONO', 'status' => 'Active', 'amount' => '1.00',
        ];
        $this->cargas['GetContacts']['contacts']['contact'][] = [
            'id' => 67, 'userid' => 555, 'name' => 'Contato de outra pessoa', 'email' => 'outra@exemplo.com.br',
        ];
        $this->cargas['GetContacts']['contacts']['contact'][] = [
            'id' => 68, 'name' => 'Contato sem dono', 'email' => 'sem dono@exemplo.com.br',
        ];
        $this->cargas['GetQuotes']['quotes']['quote'][] = [
            'id' => 91, 'clientid' => 99999, 'subject' => 'Orçamento de outra pessoa', 'stage' => 'Delivered',
        ];
        $this->cargas['GetQuotes']['quotes']['quote'][] = [
            'id' => 92, 'subject' => 'Orçamento sem dono', 'stage' => 'Open',
        ];
        $this->fingir();

        $html = $this->ficha($this->conta());

        foreach (['OUTRO1', 'SEMDONO', 'Contato de outra pessoa', 'Contato sem dono',
            'Orçamento de outra pessoa', 'Orçamento sem dono'] as $foraDaConta) {
            $this->assertStringNotContainsString($foraDaConta, $html);
        }

        $this->assertStringContainsString('ZXCVBNMLKJ', $html);
        $this->assertStringContainsString('Marcos Aguiar', $html);
        $this->assertStringContainsString('Proposta de 3 rastreadores', $html);
    }

    public function test_o_financeiro_sem_numero_de_usuario_nao_pergunta_pedidos_nem_contatos(): void
    {
        $this->todasAsSecoes();
        unset($this->cargas['GetClientsDetails']['userid']);
        $this->fingir();

        $html = $this->ficha($this->conta());

        $acoes = collect(Http::recorded())->map(fn (array $par): string => (string) ($par[0]['action'] ?? ''));

        $this->assertFalse($acoes->contains('GetOrders'));
        $this->assertFalse($acoes->contains('GetContacts'));

        // A seção não sumiu em silêncio: ela diz que não foi consultada, e por quê.
        $this->assertStringContainsString('Os pedidos não foram consultados', $html);
        $this->assertStringContainsString('traria pedido de outras pessoas', $html);
        $this->assertStringContainsString('Os contatos não foram consultados', $html);
        $this->assertStringNotContainsString('Nenhum pedido registrado', $html);
    }

    public function test_senhas_chaves_de_redefinicao_token_e_numero_completo_do_cartao_nao_aparecem(): void
    {
        $this->todasAsSecoes();
        $this->cargas['GetClientsDetails']['users'] = ['user' => [[
            'id' => 777, 'name' => 'Roberto junior', 'email' => 'roberto@exemplo.com.br', 'owner' => 1,
            'password' => 'SenhaDoUsuario-999', 'token' => 'tokenDeLogin-8888', 'pwresetkey' => 'chaveDeReset-7777',
        ]]];
        $this->cargas['GetClientsDetails']['cardtype'] = 'Visa';
        $this->cargas['GetClientsDetails']['lastfour'] = '4242';
        $this->cargas['GetClientsDetails']['expmonth'] = '12';
        $this->cargas['GetClientsDetails']['expyear'] = '2028';
        $this->cargas['GetClientsDetails']['cardnumber'] = '4111111111111111';
        $this->cargas['GetClientsDetails']['cvv'] = '999';
        $this->cargas['GetClientsProducts'] = ['result' => 'SUCCESS', 'products' => ['product' => [[
            'id' => 12, 'clientid' => 10045, 'name' => 'Rastreador J16 4G', 'status' => 'Active',
            'username' => 'roberto@plataforma', 'password' => 'SenhaDoServidor-123', 'domain' => 'ABC1D23',
        ]]]];
        $this->cargas['GetContacts']['contacts']['contact'][0]['password'] = 'SenhaDoContato-456';
        $this->cargas['GetContacts']['contacts']['contact'][0]['pwresetkey'] = 'chaveDeResetDoContato-321';
        $this->fingir();

        $html = $this->ficha($this->conta());

        foreach (['SenhaDoUsuario-999', 'tokenDeLogin-8888', 'chaveDeReset-7777', 'SenhaDoServidor-123',
            'roberto@plataforma', 'SenhaDoContato-456', 'chaveDeResetDoContato-321', '4111111111111111', '999'] as $segredo) {
            $this->assertStringNotContainsString($segredo, $html);
        }

        // O cartão vira bandeira e últimos dígitos, que é o que a equipe pode ver.
        $this->assertStringContainsString('Visa', $html);
        $this->assertStringContainsString('4242', $html);
        $this->assertStringContainsString('Roberto junior', $html);
    }

    public function test_secao_de_contrato_lida_e_vazia_diz_nenhum_e_a_de_historico_sai_da_ficha(): void
    {
        $this->todasAsSecoes();
        $this->cargas['GetClientsAddons'] = ['result' => 'SUCCESS'];
        $this->cargas['GetCredits'] = ['result' => 'SUCCESS'];
        $this->fingir();

        $html = $this->ficha($this->conta());

        $this->assertStringContainsString('Adicionais contratados', $html);
        $this->assertStringContainsString('Nenhum adicional contratado nesta conta do financeiro.', $html);

        // Crédito lido e vazio não é assunto para a equipe: a seção sai da página, sem aviso.
        $this->assertStringNotContainsString('Movimentos de crédito', $html);
    }

    public function test_secao_que_o_financeiro_nao_respondeu_nunca_diz_que_nao_tem(): void
    {
        $this->todasAsSecoes();
        unset($this->cargas['GetCredits'], $this->cargas['GetQuotes'], $this->cargas['GetClientsDomains']);
        $this->fingir();

        $html = $this->ficha($this->conta());

        $this->assertStringContainsString('consulta de créditos', $html);
        $this->assertStringContainsString('consulta de orçamentos', $html);
        $this->assertStringContainsString('de domínios', $html);

        $this->assertStringNotContainsString('Nenhum movimento de crédito', $html);
        $this->assertStringNotContainsString('Nenhum orçamento para esta conta', $html);
        $this->assertStringNotContainsString('Nenhum domínio nesta conta', $html);
    }

    public function test_a_ficha_do_financeiro_e_lida_em_um_lote_so(): void
    {
        $this->todasAsSecoes();
        $this->cargas['GetClientsProducts'] = ['result' => 'SUCCESS', 'products' => ['product' => []]];
        $this->cargas['GetInvoices'] = ['result' => 'SUCCESS', 'invoices' => ['invoice' => []]];
        $this->fingir();

        $this->ficha($this->conta());

        $acoes = collect(Http::recorded())->map(fn (array $par): string => (string) ($par[0]['action'] ?? ''));

        // As dez consultas do histórico saem juntas, depois do cadastro — nenhuma seção fica
        // esperando a anterior, e a página não vaza para outra conta no meio do lote.
        $this->assertSame('GetClientsDetails', $acoes->first());
        $this->assertSame(10, $acoes->skip(1)->count());
        $this->assertTrue($acoes->contains('GetQuotes'));
        $this->assertTrue($acoes->contains('GetOrders'));
        $this->assertTrue($acoes->contains('GetContacts'));
    }
}
