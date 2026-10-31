<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A tela de detalhes do serviço no portal: o que o cliente contratou, o que veio incluso nele e
 * o que está pendurado nele.
 *
 * O que estes testes protegem, além da tradução:
 *
 * - **O id da URL nunca vai ao financeiro.** A lista lida é a da conta autenticada e o número só
 *   escolhe qual linha abrir; número que não está nela é 404, sem pista e sem segunda chamada.
 * - **O que é interno fica interno.** Nota do financeiro, usuário e senha do serviço e motivo da
 *   suspensão não são desenhados para o cliente, ainda que a ficha da equipe os leia.
 * - **Falha não vira ausência.** Financeiro fora do ar é a tela que diz "não respondeu", nunca
 *   "você não tem nada" — inclusive quando o que falhou é só a consulta de adicionais.
 */
class DetalheDoServicoNoPortalTest extends TestCase
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
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '(35) 99888-7766',
            'email' => 'maria@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
            'whmcs_id' => 77,
        ], $extra));
    }

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

    /** @param  array<string, \Illuminate\Http\Client\Response>  $respostas */
    private function fingirWhmcs(array $respostas = []): void
    {
        Http::fake(fn (RequisicaoHttp $requisicao) => $respostas[(string) ($requisicao['action'] ?? '')]
            ?? Http::response(['result' => 'error', 'message' => 'ação não prevista no teste']));
    }

    /** O serviço como o financeiro devolve, com o que a equipe anota nele e o que é interno. */
    private function servico(array $extra = []): array
    {
        return array_merge([
            'id' => 12,
            'clientid' => 77,
            'name' => 'Rastreador J16 4G',
            'groupname' => 'Rastreamento veicular',
            'domain' => 'ABC1D23',
            'status' => 'Active',
            'billingcycle' => 'Monthly',
            'recurringamount' => '59.90',
            'firstpaymentamount' => '149.90',
            'paymentmethod' => 'golden_inter_boleto',
            'regdate' => '2025-11-10',
            'nextduedate' => '2026-11-10',
            'lastupdate' => '2026-10-20 15:00:00',
            'ordernumber' => '4572640950',
            'notes' => 'Nota interna: cobrar por telefone antes de suspender.',
            'username' => 'acesso-interno-do-servidor',
            'password' => 'senha-do-servidor',
            'suspensionreason' => '',
            'customfields' => ['customfield' => [
                ['name' => 'DescricaoDoveiculo', 'value' => 'PEUGEOT 207'],
                ['name' => 'Placa', 'value' => 'ERC2899'],
                ['name' => 'IMEI', 'value' => '5516910600826'],
                ['name' => 'SIMCARD', 'value' => '89551234567890123456'],
            ]],
            'configoptions' => ['configoption' => [
                ['label' => 'Cobertura', 'value' => 'Nacional'],
            ]],
        ], $extra);
    }

    /*
     * Os dois montadores de resposta abaixo não anotam tipo de retorno: `Http::response()` devolve
     * uma promise, e anotar `: Response` faria a peça inteira do enganar os testes em vez de
     * testar a tela.
     */

    /** @param  array<int, array<string, mixed>>  $servicos */
    private function produtos(array $servicos)
    {
        return Http::response(['result' => 'success', 'products' => ['product' => $servicos]]);
    }

    /** @param  array<int, array<string, mixed>>  $adicionais */
    private function addons(array $adicionais)
    {
        return Http::response(['result' => 'success', 'addons' => ['addon' => $adicionais]]);
    }

    public function test_a_lista_de_servicos_leva_ao_detalhe_de_cada_servico(): void
    {
        $this->ligar();
        $cliente = $this->conta();
        $this->fingirWhmcs(['GetClientsProducts' => $this->produtos([$this->servico()])]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertSee('Detalhes')
            ->assertSee('/portal/servicos/12', false);
    }

    public function test_o_detalhe_mostra_o_servico_os_itens_inclusos_e_o_valor_que_se_repete(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
            'GetClientsAddons' => $this->addons([]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('O que está contratado')
            ->assertSee('Rastreador J16 4G')
            ->assertSee('Rastreamento veicular')
            ->assertSee('Ativo')
            ->assertSee('R$ 59,90')
            ->assertSee('por mês')
            ->assertSee('R$ 149,90')
            ->assertSee('Boleto (Banco Inter)')
            ->assertSee('10/11/2026')
            ->assertSee('Itens inclusos no serviço')
            ->assertSee('Descrição do veículo')
            ->assertSee('PEUGEOT 207')
            ->assertSee('ERC2899')
            ->assertSee('5516910600826')
            ->assertSee('Cobertura')
            ->assertSee('Nacional')
            ->assertSee('Pedido de origem')
            ->assertSee('4572640950');
    }

    /*
     * O financeiro devolve o campo do serviço com o nome de sistema que a equipe digitou lá
     * dentro — `DescricaoDoveiculo`, `SIMCARD`. Na tela do cliente isso é inglês e é depuração, e a
     * casa não entrega nenhuma das duas coisas. O mapa da configuração troca o que está mapeado;
     * o que não está aparece com as palavras separadas, nunca grudado.
     */
    public function test_o_nome_de_sistema_do_campo_nao_chega_na_tela_do_cliente(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico([
                'customfields' => ['customfield' => [
                    ['name' => 'DescricaoDoveiculo', 'value' => 'PEUGEOT 207'],
                    ['name' => 'FornecedorChip', 'value' => 'ALGAR-MT'],
                    ['name' => 'SIMCARD', 'value' => '89551234567890123456'],
                    ['name' => 'DataInstalacao', 'value' => '10/11/2025'],
                ]],
            ])]),
            'GetClientsAddons' => $this->addons([]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Descrição do veículo')
            ->assertSee('Fornecedor do chip')
            ->assertSee('Chip (SIM)')
            ->assertSee('Data Instalacao')
            ->assertDontSee('DescricaoDoveiculo', false)
            ->assertDontSee('FornecedorChip', false)
            ->assertDontSee('SIMCARD', false)
            ->assertDontSee('DataInstalacao', false);
    }

    public function test_o_painel_manda_no_rotulo_do_campo(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        ChavesDeIntegracao::guardar('whmcs_rotulos_campos', 'SIMCARD=Cartão do chip');
        ChavesDeIntegracao::aplicar();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
            'GetClientsAddons' => $this->addons([]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Cartão do chip')
            ->assertDontSee('Chip (SIM)', false)
            ->assertDontSee('SIMCARD', false);
    }

    public function test_o_detalhe_so_mostra_o_adicional_pendurado_neste_servico(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
            'GetClientsAddons' => $this->addons([
                ['id' => 5, 'clientid' => 77, 'name' => 'SOS Veicular', 'serviceid' => 12,
                    'status' => 'Active', 'recurringamount' => '19.90', 'billingcycle' => 'Monthly',
                    'nextduedate' => '2026-11-10', 'regdate' => '2026-01-08'],
                ['id' => 6, 'clientid' => 77, 'name' => 'Adicional de outro serviço', 'serviceid' => 99,
                    'status' => 'Active', 'recurringamount' => '29.90'],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Itens vinculados')
            ->assertSee('SOS Veicular')
            ->assertSee('R$ 19,90')
            ->assertSee('08/01/2026')
            ->assertDontSee('Adicional de outro serviço');
    }

    public function test_o_adicional_sem_numero_de_servico_aparece_pelo_nome_a_que_pertence(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
            'GetClientsAddons' => $this->addons([
                ['id' => 7, 'clientid' => 77, 'name' => 'Chip extra', 'productname' => 'Rastreador J16 4G',
                    'status' => 'Active', 'recurringamount' => '9.90'],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Chip extra')
            ->assertSee('R$ 9,90');
    }

    public function test_numero_de_fora_da_lista_e_404_sem_segunda_consulta(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
            'GetClientsAddons' => $this->addons([
                ['id' => 5, 'clientid' => 77, 'name' => 'SOS Veicular', 'serviceid' => 999, 'status' => 'Active'],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 999))
            ->assertNotFound()
            ->assertDontSee('SOS Veicular');

        // Só a lista da conta foi pedida; o número que não é dela não virou consulta própria.
        Http::assertSentCount(1);
    }

    public function test_o_servico_que_declara_outro_dono_nao_abre_nem_na_tela_de_detalhe(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        // O item nomeia outra conta: o leitor dele descarta a linha, a lista fica sem aquele número
        // e a tela devolve 404 — sem revelar que o serviço existe em algum lugar.
        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico(['clientid' => 888])]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertNotFound()
            ->assertDontSee('Rastreador J16 4G');

        Http::assertSentCount(1);
    }

    public function test_o_servico_que_a_casa_nao_veicula_nao_abre_por_numero_na_url(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        // Encerrado fora da vitrine do cliente: a lista que a tela lê já vem sem ele, então o
        // número digitado à toa não encontra linha nenhuma e morre em 404.
        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico(['status' => 'Terminated'])]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertNotFound();

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertDontSee('Rastreador J16 4G');
    }

    public function test_nota_interna_acesso_do_servidor_e_motivo_da_suspensao_nao_saem_para_o_cliente(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico([
                'status' => 'Suspended',
                'suspensionreason' => 'Cobrança em andamento com o titular.',
            ])]),
            'GetClientsAddons' => $this->addons([]),
        ]);

        $html = $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertSee('Suspenso')
            ->assertSee('Fale com a equipe')
            ->getContent();

        $this->assertStringNotContainsString('Cobrança em andamento com o titular', $html);
        $this->assertStringNotContainsString('cobrar por telefone', $html);
        $this->assertStringNotContainsString('acesso-interno-do-servidor', $html);
        $this->assertStringNotContainsString('senha-do-servidor', $html);
    }

    public function test_financeiro_fora_do_ar_nao_vira_servico_inexistente(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        Http::fake(['*' => Http::response('erro interno', 500)]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('O financeiro não respondeu agora.')
            ->assertDontSee('Nenhum item extra registrado');
    }

    public function test_adicional_que_nao_responde_diz_o_que_faltou_em_vez_de_dizer_que_nao_tem(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico()]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('O financeiro não respondeu à consulta de adicionais')
            ->assertDontSee('Nenhum adicional vinculado a este serviço');
    }

    public function test_o_servico_sem_itens_inclusos_diz_isso_em_vez_de_deixar_a_tela_em_branco(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsProducts' => $this->produtos([$this->servico(), $this->servico([
                'id' => 13,
                'name' => 'Monitoramento de frota',
                'groupname' => 'Monitoramento',
                'customfields' => null,
                'configoptions' => null,
            ])]),
            'GetClientsAddons' => $this->addons([]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 13))
            ->assertOk()
            ->assertSee('Monitoramento de frota')
            ->assertSee('Nenhum item extra registrado neste serviço.');
    }

    public function test_veiculacao_desligada_fecha_a_tela_sem_consultar_o_financeiro(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        ChavesDeIntegracao::guardar('whmcs_veicula_servicos', '0');
        ChavesDeIntegracao::aplicar();
        $this->fingirWhmcs();

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos.show', 12))
            ->assertOk()
            ->assertSee('Esta área do portal está fechada.');

        Http::assertNothingSent();
    }
}
