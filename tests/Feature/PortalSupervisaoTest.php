<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\Whmcs\Veiculacao;
use App\Support\ChavesDeIntegracao;
use App\Support\Supervisao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A janela de supervisão: o painel dentro da conta de um cliente, sem ser o cliente.
 *
 * O que estes testes protegem é a promessa inteira do recurso, e ela tem três pernas. A primeira
 * é a **igualdade**: quem supervisiona lê as mesmas telas, com os mesmos filtros de veiculação,
 * porque são as telas do portal — não uma cópia para a equipe. A segunda é o **cadeado**: dentro
 * da janela nada se grava, em nenhuma tela, e o que ficou de fora é o que a pessoa não escreveu.
 * A terceira é a **porta de saída**: a janela tem dono, tem hora e fecha sozinha; sessão de
 * supervisão que vence e continua navegando é a falha que o `AcessoSupervisionado` não pode ter.
 *
 * Detalhe que sustenta o conjunto: um login de verdade apaga o marcador. Sem isso, a pessoa que
 * digita a própria senha no portal herdaria uma barra dizendo que está na conta de outra pessoa
 * — e o cadeado travaria a escrita dela.
 */
class PortalSupervisaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** O `whmcs_id` é único na tabela: cada ficha criada num teste recebe o seu próprio. */
    private int $proximoIdNoFinanceiro = 77;

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
            'whmcs_id' => $this->proximoIdNoFinanceiro++,
        ], $extra));
    }

    /** Abre a janela do jeito que a ficha faz: um POST do painel, com o administrador logado. */
    private function supervisionar(Customer $cliente): void
    {
        // O guard vem escrito de propósito: `actingAs()` sem ele troca o guard padrão da aplicação,
        // e um teste que já abriu o portal antes enviaria o administrador pela porta do cliente.
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.portal.entrar', $cliente))
            ->assertRedirect(route('portal.inicio'));
    }

    public function test_o_painel_abre_o_portal_do_cliente_e_a_barra_avisa_quem_esta_olhando(): void
    {
        $cliente = $this->conta();

        $this->supervisionar($cliente);

        $this->assertTrue(Auth::guard('cliente')->check(), 'O guard do portal não recebeu a ficha.');
        $this->assertTrue(Auth::guard('web')->check(), 'Sair do painel não: a sessão da equipe tem de continuar.');

        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('Supervisão aberta na conta de Maria Compradora — modo somente leitura.')
            ->assertSee($this->admin->name)
            ->assertSee('Sair da conta do cliente');
    }

    public function test_dentro_da_janela_nada_do_portal_e_gravado(): void
    {
        $cliente = $this->conta();
        $this->supervisionar($cliente);

        $this->post(route('portal.dados.salvar'), ['nome' => 'Maria Gravada', 'telefone' => '(35) 91111-1111'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->post(route('portal.chamados.registrar'), [
            'assunto' => 'Chamado que não deveria existir',
            'mensagem' => 'Esta mensagem não tinha de chegar ao chamado.',
            'categoria' => 'instalação',
        ])->assertRedirect()->assertSessionHas('error');

        $this->post(route('portal.senha.salvar'), [
            'senha_atual' => 'senha-portal-123',
            'password' => 'senha-alheia-123',
            'password_confirmation' => 'senha-alheia-123',
        ])->assertRedirect()->assertSessionHas('error');

        $estaIntacta = $cliente->fresh();
        $this->assertSame('Maria Compradora', $estaIntacta->nome);
        $this->assertSame('(35) 99888-7766', $estaIntacta->telefone);
        $this->assertSame(0, $estaIntacta->tickets()->count(), 'A janela de leitura criou chamado no portal.');
        $this->assertTrue(Hash::check('senha-portal-123', $estaIntacta->password), 'A supervisão trocou a senha do cliente.');
    }

    public function test_tentar_gravar_deixa_rastro_na_auditoria(): void
    {
        $cliente = $this->conta();
        $this->supervisionar($cliente);

        $this->post(route('portal.dados.salvar'), ['nome' => 'Maria Gravada']);

        $this->assertDatabaseHas('activities', [
            'action' => 'portal_supervisao_bloqueado',
            'user_id' => $this->admin->id,
            'subject_type' => 'Customer',
            'subject_id' => $cliente->id,
        ]);
        $this->assertDatabaseHas('activities', [
            'action' => 'portal_supervisao_aberta',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_sair_da_conta_devolve_o_administrador_a_ficha_e_apaga_a_janela(): void
    {
        $cliente = $this->conta();
        $this->supervisionar($cliente);

        $this->post(route('portal.supervisao.sair'))
            ->assertRedirect(route('admin.clientes.show', $cliente))
            ->assertSessionHas('success');

        $this->assertNull(session(Supervisao::SESSAO));
        $this->assertTrue(Auth::guard('cliente')->guest(), 'O guard do portal continuou aberto depois de sair.');
        $this->assertTrue(Auth::guard('web')->check(), 'Quem voltou foi para o portal, não para fora do painel.');

        $this->get(route('portal.inicio'))->assertRedirect(route('portal.login'));
    }

    public function test_a_janela_expira_e_derruba_a_sessao_em_vezt_de_virar_conta_normal(): void
    {
        $cliente = $this->conta();
        $this->supervisionar($cliente);

        $this->travel(46)->minutes();

        $this->get(route('portal.inicio'))
            ->assertRedirect(route('portal.login'))
            ->assertSessionHas('warning');

        $this->assertTrue(Auth::guard('cliente')->guest());
        $this->assertNull(session(Supervisao::SESSAO));

        // E a escrita, que é o que a janela impedia, não voltou a existir para ninguém: a sessão
        // inteira caiu, então o POST abaixo bate na tela de entrada, não no cadastro da pessoa.
        $this->post(route('portal.dados.salvar'), ['nome' => 'Maria Gravada'])
            ->assertRedirect(route('portal.login'));
        $this->assertSame('Maria Compradora', $cliente->fresh()->nome);
    }

    public function test_o_prazo_da_janela_vem_da_tela_de_configuracoes(): void
    {
        config(['whmcs.veiculacao.supervisao_minutos' => 5]);

        $cliente = $this->conta();
        $this->supervisionar($cliente);

        $this->travel(4)->minutes();
        $this->get(route('portal.inicio'))->assertOk()->assertSee('Supervisão aberta');

        $this->travel(2)->minutes();
        $this->get(route('portal.inicio'))->assertRedirect(route('portal.login'));
    }

    public function test_desligar_a_supervisao_esconde_o_botao_e_fecha_a_janela_aberta(): void
    {
        $cliente = $this->conta();

        // A gaveta da ajuda carrega o texto do guia daquela tela dentro de `<template>` — o
        // navegador não desenha nada ali, e cita o botão pelo nome. O que muda com a veiculação é o
        // botão de verdade, na parte desenhada da página.
        $liga = $this->visivel($this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))->assertOk()->getContent());
        $this->assertStringContainsString('Ver a conta do cliente', $liga);

        $this->supervisionar($cliente);

        config(['whmcs.veiculacao.supervisao' => false]);

        $desliga = $this->visivel($this->actingAs($this->admin, 'web')
            ->get(route('admin.clientes.show', $cliente))->assertOk()->getContent());
        $this->assertStringNotContainsString('Ver a conta do cliente', $desliga);

        // Desligar no meio vale na hora: quem estava dentro cai fora na próxima requisição.
        $this->get(route('portal.inicio'))->assertRedirect(route('portal.login'));

        $this->post(route('admin.clientes.portal.entrar', $cliente))->assertForbidden();
    }

    public function test_a_rota_recusa_quem_nao_e_administrador(): void
    {
        $cliente = $this->conta();
        $vendedor = User::where('role', 'vendedor')->firstOrFail();

        $this->actingAs($vendedor, 'web')->post(route('admin.clientes.portal.entrar', $cliente))
            ->assertForbidden();

        $this->assertTrue(Auth::guard('cliente')->guest());
    }

    public function test_sem_conta_no_portal_ou_com_a_ficha_inativa_nao_ha_janela(): void
    {
        $semConta = $this->conta(['password' => null, 'email_portal' => null, 'email' => 'sem.conta@exemplo.com.br']);
        $inativa = $this->conta(['status' => 'inativo', 'email' => 'inativa@exemplo.com.br', 'email_portal' => 'inativa@exemplo.com.br']);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.portal.entrar', $semConta))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.clientes.portal.entrar', $inativa))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue(Auth::guard('cliente')->guest());
        $this->assertNull(session(Supervisao::SESSAO));
    }

    public function test_o_cliente_de_verdade_continua_escrevendo_na_propria_conta(): void
    {
        // A prova de que o cadeado é da janela, não do portal: sem marcador, o dono grava.
        $cliente = $this->conta();

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.dados.salvar'), ['nome' => 'Maria Atualizada', 'telefone' => '(35) 99999-0000'])
            ->assertSessionHas('success');

        $this->assertSame('Maria Atualizada', $cliente->fresh()->nome);
    }

    public function test_um_login_de_verdade_nao_herda_a_janela_aberta_anteriormente(): void
    {
        $dona = $this->conta();
        $esta = $this->conta([
            'nome' => 'Outra Pessoa',
            'email' => 'outra@exemplo.com.br',
            'email_portal' => 'outra@exemplo.com.br',
            'documento' => '987.654.321-00',
        ]);

        $this->supervisionar($dona);

        $this->post(route('portal.login.post'), ['email' => 'outra@exemplo.com.br', 'password' => 'senha-portal-123'])
            ->assertRedirect(route('portal.inicio'));

        $this->assertNull(session(Supervisao::SESSAO), 'A pessoa entrou com a própria senha e herdou a barra de supervisão.');

        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertDontSee('Supervisão aberta');

        $this->post(route('portal.dados.salvar'), ['nome' => 'Outra Corrigida', 'telefone' => '(35) 99999-0000'])->assertSessionHas('success');
        $this->assertSame('Outra Corrigida', $esta->fresh()->nome);
        $this->assertSame('Maria Compradora', $dona->fresh()->nome);
    }

    public function test_o_supervisor_ve_exatamente_o_que_o_cliente_ve_do_financeiro(): void
    {
        // Não existe uma tela de leitura feita para a equipe: existe o portal. Por isso a regra de
        // vitrine que vale para o cliente vale, linha por linha, para quem supervisiona — e é esta
        // assersão cruzada que impede alguém de "adaptar" a supervisão no futuro.
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador',
            'whmcs.secret' => 'segredo',
            'whmcs.timeout' => 5,
            'whmcs.limite' => 50,
            'whmcs.veiculacao.servicos_encerrados' => false,
            'whmcs.veiculacao.link_externo' => false,
        ]);

        $cliente = $this->conta();

        Http::fake(fn (RequisicaoHttp $requisicao) => ($requisicao['action'] ?? '') === 'GetClientsProducts'
            ? Http::response(['result' => 'success', 'products' => ['product' => [
                ['id' => 12, 'name' => 'Rastreador J16 4G', 'groupname' => 'Rastreamento veicular', 'domain' => 'ABC12', 'status' => 'Active', 'nextduedate' => '2026-11-10', 'recurringamount' => '59.90', 'billingcycle' => 'Monthly'],
                ['id' => 13, 'name' => 'Rastreador antigo', 'groupname' => 'Rastreamento veicular', 'domain' => 'ABC13', 'status' => 'Terminated', 'nextduedate' => '2026-11-10', 'recurringamount' => '39.90', 'billingcycle' => 'Monthly'],
            ]]])
            : Http::response(['result' => 'error', 'message' => 'ação não prevista: '.($requisicao['action'] ?? '')]));

        $oQueOClienteVe = $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))->assertOk()->getContent();

        $this->logoutClient();
        $this->supervisionar($cliente);
        $oQueOPainelVe = $this->get(route('portal.servicos'))->assertOk()->getContent();

        foreach ([$oQueOClienteVe, $oQueOPainelVe] as $indice => $html) {
            $this->assertStringContainsString('Rastreador J16 4G', $html, 'A lista sumiu (tela '.($indice + 1).').');
            $this->assertStringNotContainsString('Rastreador antigo', $html, 'Encerrado apareceu (tela '.($indice + 1).').');
            $this->assertStringNotContainsString('clientarea.php', $html, 'O link para fora apareceu com o interruptor desligado (tela '.($indice + 1).').');
        }

        $this->assertStringContainsString('Supervisão aberta', $oQueOPainelVe);
        $this->assertStringNotContainsString('Supervisão aberta', $oQueOClienteVe);
    }

    /** Fecha só o guard do portal: a sessão do painel continua, como na vida real ao alternar telas. */
    private function logoutClient(): void
    {
        Auth::guard('cliente')->logout();
    }

    public function test_os_dois_campos_novos_sao_salvos_no_banco_e_mandam_na_frente_do_env(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.settings.chaves'), [
            'grupo' => ChavesDeIntegracao::GRUPO_VEICULACAO,
            'whmcs_veicula_supervisao' => '0',
            'whmcs_veicula_supervisao_minutos' => '20',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('0', Setting::get('whmcs_veicula_supervisao'));
        $this->assertSame('20', Setting::get('whmcs_veicula_supervisao_minutos'));

        ChavesDeIntegracao::aplicar();

        $this->assertFalse(Veiculacao::supervisao());
        $this->assertSame(20, Veiculacao::minutosDeSupervisao());
    }
}
