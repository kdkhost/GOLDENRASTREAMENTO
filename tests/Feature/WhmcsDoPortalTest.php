<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O portal olhando para o financeiro (WHMCS).
 *
 * O que estes testes protegem é a diferença entre "não tem" e "não consegui perguntar": uma
 * tela vazia por falha de API faria o cliente achar que a fatura dele sumiu. Cobrem também a
 * regra de segurança do id — ele sai da ficha autenticada, nunca de um parâmetro da URL — e o
 * espelho dos dados e da senha, que nunca desfaz o que o portal já gravou.
 */
class WhmcsDoPortalTest extends TestCase
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
        ], $extra));
    }

    /** As três peças da integração, como se tivessem sido gravadas na tela do painel. */
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

    public function test_sem_credencial_as_telas_explicam_sem_chamar_o_financeiro(): void
    {
        Http::fake();
        $cliente = $this->conta();

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Área financeira em preparação.');

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('Área financeira em preparação.')
            ->assertDontSee('Nenhuma fatura emitida');

        Http::assertNothingSent();
    }

    public function test_a_tela_mostra_os_servicos_contratados_no_financeiro(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        $this->fingirWhmcs([
            'GetClientsProducts' => Http::response([
                'result' => 'success',
                'products' => ['product' => [[
                    'id' => 12,
                    'name' => 'Rastreador J16 4G',
                    'groupname' => 'Rastreamento veicular',
                    'domain' => 'ABC1D23',
                    'status' => 'Active',
                    'nextduedate' => '2026-11-10',
                    'recurringamount' => '59.90',
                    'billingcycle' => 'Monthly',
                    'regdate' => '2025-11-10',
                ]]],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertSee('ABC1D23')
            ->assertSee('Ativo')
            ->assertSee('por mês')
            ->assertSee('R$ 59,90')
            ->assertSee('10/11/2026')
            ->assertSee('https://financeiro.exemplo.com.br/clientarea.php?action=productdetails&amp;id=12', false);

        // O id da consulta é o da ficha, não um número que a URL possa trocar.
        Http::assertSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'GetClientsProducts'
            && (int) ($requisicao['clientid'] ?? 0) === 77);
    }

    public function test_a_tela_mostra_as_faturas_e_soma_o_que_esta_em_aberto(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        $this->fingirWhmcs([
            'GetInvoices' => Http::response([
                'result' => 'success',
                'invoices' => ['invoice' => [
                    [
                        'id' => 501,
                        'invoicenum' => 'GR-501',
                        'date' => '2026-10-01',
                        'duedate' => '2026-10-10',
                        'datepaid' => '',
                        'total' => '59.90',
                        'balance' => '59.90',
                        'status' => 'Unpaid',
                        'paymentmethod' => 'pix',
                    ],
                    [
                        'id' => 500,
                        'invoicenum' => '',
                        'date' => '2026-09-01',
                        'duedate' => '2026-09-10',
                        'datepaid' => '2026-09-09 10:00:00',
                        'total' => '59.90',
                        'balance' => '0.00',
                        'status' => 'Paid',
                        'paymentmethod' => 'pix',
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('R$ 59,90 em aberto.')
            ->assertSee('#GR-501')
            ->assertSee('Em aberto')
            ->assertSee('Paga em 09/09/2026')
            ->assertSee('Paga')
            ->assertSee('https://financeiro.exemplo.com.br/clientarea.php?action=viewinvoice&amp;id=501', false);

        Http::assertSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'GetInvoices'
            && (int) ($requisicao['userid'] ?? 0) === 77);
    }

    public function test_a_primeira_visita_descobre_o_id_pelo_email_e_grava_na_ficha(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsDetails' => Http::response(['result' => 'success', 'clientid' => 88]),
            'GetInvoices' => Http::response(['result' => 'success', 'invoices' => ['invoice' => []]]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('Nenhuma fatura emitida');

        $this->assertSame(88, $cliente->fresh()->whmcs_id);

        Http::assertSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'GetClientsDetails'
            && ($requisicao['email'] ?? '') === 'maria@exemplo.com.br');
    }

    public function test_conta_nao_encontrada_no_financeiro_tem_recado_proprio(): void
    {
        $this->ligar();
        $cliente = $this->conta();

        $this->fingirWhmcs([
            'GetClientsDetails' => Http::response(['result' => 'success', 'clientid' => '']),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Ainda não achei sua conta no financeiro.')
            ->assertDontSee('Nenhum serviço contratado');
    }

    public function test_financeiro_fora_do_ar_nao_vira_lista_vazia(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        Http::fake(['*' => Http::response('erro interno', 500)]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('O financeiro não respondeu agora.')
            ->assertDontSee('Nenhuma fatura emitida');
    }

    public function test_salvar_dados_espelha_no_financeiro_sem_mandar_o_email(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        $this->fingirWhmcs([
            'UpdateClient' => Http::response(['result' => 'success']),
        ]);

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.dados.salvar'), [
                'nome' => 'Maria Compradora Silva',
                'telefone' => '(35) 99999-0000',
                'email' => 'maria@exemplo.com.br',
                'endereco' => 'Rua A, 10',
                'cidade' => 'Três Pontas',
                'uf' => 'mg',
                'cep' => '37160-000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Maria Compradora Silva', $cliente->fresh()->nome);

        Http::assertSent(function (RequisicaoHttp $requisicao): bool {
            $dados = $requisicao->data();

            return ($requisicao['action'] ?? '') === 'UpdateClient'
                && (int) ($requisicao['clientid'] ?? 0) === 77
                && $dados['firstname'] === 'Maria'
                && $dados['lastname'] === 'Compradora Silva'
                && $dados['address1'] === 'Rua A, 10'
                && $dados['city'] === 'Três Pontas'
                && $dados['state'] === 'MG'
                && $dados['postcode'] === '37160000'
                && $dados['phonenumber'] === '(35) 99999-0000'
                && ! array_key_exists('email', $dados);
        });
    }

    public function test_trocar_a_senha_espelha_a_mesma_senha_no_financeiro(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        $this->fingirWhmcs([
            'UpdateClient' => Http::response(['result' => 'success']),
        ]);

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.senha.salvar'), [
                'senha_atual' => 'senha-portal-123',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $this->assertTrue(Hash::check('nova-senha-123', (string) $cliente->fresh()->password));

        Http::assertSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'UpdateClient'
            && ($requisicao['password2'] ?? '') === 'nova-senha-123');
    }

    public function test_falha_no_espelho_avisa_mas_nao_desfaz_o_que_o_portal_gravou(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        Http::fake(['*' => Http::response('erro interno', 500)]);

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.dados.salvar'), [
                'nome' => 'Maria Atualizada',
                'telefone' => '(35) 99999-0000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', 'O financeiro não respondeu agora, então os dados de lá continuam como estavam. Tente de novo mais tarde ou fale com a equipe.');

        $this->assertSame('Maria Atualizada', $cliente->fresh()->nome);
    }

    /**
     * O WHMCS real responde `SUCCESS` em caixa alta, e é assim que a tela do cliente precisa ler.
     * Comparar o campo em minúsculas deitava toda conversa bem-sucedida: o financeiro estaria no
     * ar, respondendo certo, e o cliente veria "Área financeira em preparação".
     */
    public function test_o_sucesso_do_whmcs_em_caixa_alta_entra_na_tela_do_cliente(): void
    {
        $this->ligar();
        $cliente = $this->conta(['whmcs_id' => 77]);

        $this->fingirWhmcs([
            'GetClientsProducts' => Http::response([
                'result' => 'SUCCESS',
                'products' => ['product' => [[
                    'id' => 12,
                    'name' => 'Rastreador J16 4G',
                    'groupname' => 'Rastreamento veicular',
                    'domain' => 'ABC1D23',
                    'status' => 'Active',
                    'nextduedate' => '2026-11-10',
                    'recurringamount' => '59.90',
                    'billingcycle' => 'Monthly',
                    'regdate' => '2025-11-10',
                ]]],
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertDontSee('Área financeira em preparação.');
    }

    public function test_o_segredo_do_whmcs_fica_cifrado_e_o_grupo_aparece_no_painel(): void
    {
        ChavesDeIntegracao::guardar('whmcs_secret', 'segredo-cru-do-whmcs');

        $cru = (string) DB::table('settings')->where('key', 'whmcs_secret')->value('value');

        $this->assertNotSame('segredo-cru-do-whmcs', $cru);
        $this->assertSame('segredo-cru-do-whmcs', ChavesDeIntegracao::doBanco('whmcs_secret'));

        $html = $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('WHMCS — financeiro e serviços')
            ->assertSee('WHMCS_API_SECRET', false)
            ->getContent();

        $this->assertStringNotContainsString('segredo-cru-do-whmcs', $html);
    }
}
