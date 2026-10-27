<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\Whmcs\Veiculacao;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O que a conexão com o financeiro veicula no portal.
 *
 * As credenciais dizem se a porta abre; estes interruptores dizem o que passa por ela. O que
 * estes testes protegem é a palavra "desligado" ter efeito de verdade: fechar uma tela é não
 * gastar a chamada (e tirar o item do menu, para o cliente não cair numa porta trancada),
 * filtrar estado e grupo é não mostrar o que a empresa decidiu não mostrar, e desligar o espelho
 * é o portal gravar só em si mesmo.
 *
 * Uma regra de casa atravessa o arquivo: `Http::fake()` acumula stubs e o primeiro que casa
 * vence, então cada teste instala o seu fake uma única vez e o roteia pela `action` recebida.
 * Dois fakes no mesmo teste deixariam o segundo mudo — e um teste mudo passa por acidente.
 */
class VeiculacaoDoWhmcsTest extends TestCase
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

    /** As três credenciais no ar, como se a tela de Chaves as tivesse gravado. */
    private function credenciais(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador-da-api',
            'whmcs.secret' => 'segredo-da-api',
            'whmcs.timeout' => 5,
            'whmcs.limite' => 50,
        ]);
    }

    private function veicular(string $interruptor, mixed $valor): void
    {
        config(['whmcs.veiculacao.'.$interruptor => $valor]);
    }

    /**
     * O único fake do teste: devolve a resposta preparada para a `action` pedida, e um erro
     * honesto para qualquer ação que o teste não previu — silenciar uma chamada inesperada é
     * justamente o bug que se quer pegar.
     *
     * Nem o closure nem os construtores de resposta abaixo declaram tipo de retorno: o `Http::response()`
     * do Laravel devolve uma promise, e o `fake()` aceita as duas formas.
     *
     * @param  array<string, mixed>  $porAcao
     */
    private function fingir(array $porAcao): void
    {
        Http::fake(function (RequisicaoHttp $requisicao) use ($porAcao) {
            $acao = (string) ($requisicao['action'] ?? '');

            return $porAcao[$acao] ?? Http::response([
                'result' => 'error',
                'message' => 'ação não prevista no teste: '.$acao,
            ]);
        });
    }

    /** @param  array<int, array<string, mixed>>  $produtos */
    private function produtos(array $produtos)
    {
        return Http::response(['result' => 'success', 'products' => ['product' => $produtos]]);
    }

    /** @param  array<int, array<string, mixed>>  $faturas */
    private function faturas(array $faturas)
    {
        return Http::response(['result' => 'success', 'invoices' => ['invoice' => $faturas]]);
    }

    private function servico(int $id, string $nome, string $status, string $grupo = 'Rastreamento veicular'): array
    {
        return [
            'id' => $id,
            'name' => $nome,
            'groupname' => $grupo,
            'domain' => 'ABC'.$id,
            'status' => $status,
            'nextduedate' => '2026-11-10',
            'recurringamount' => '59.90',
            'billingcycle' => 'Monthly',
            'regdate' => '2025-11-10',
        ];
    }

    private function fatura(int $id, string $status): array
    {
        return [
            'id' => $id,
            'invoicenum' => 'GR-'.$id,
            'date' => '2026-10-01',
            'duedate' => '2026-10-10',
            'datepaid' => $status === 'Paid' ? '2026-09-09 10:00:00' : '',
            'total' => '59.90',
            'balance' => $status === 'Paid' ? '0.00' : '59.90',
            'status' => $status,
            'paymentmethod' => 'pix',
        ];
    }

    public function test_desligar_a_veiculacao_de_servicos_fecha_a_tela_sem_gastar_a_chamada(): void
    {
        $this->credenciais();
        $this->veicular('servicos', false);
        $cliente = $this->conta();

        $this->fingir([]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Esta área do portal está fechada.')
            ->assertDontSee('Nenhum serviço contratado');

        Http::assertNothingSent();
    }

    public function test_desligar_a_veiculacao_de_faturas_fecha_a_tela_sem_gastar_a_chamada(): void
    {
        $this->credenciais();
        $this->veicular('faturas', false);
        $cliente = $this->conta();

        $this->fingir([]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('Esta área do portal está fechada.')
            ->assertDontSee('Nenhuma fatura emitida');

        Http::assertNothingSent();
    }

    public function test_a_tela_desligada_sai_esmaecida_do_menu_do_portal(): void
    {
        // A pessoa não pode chegar a uma porta trancada por um link do menu: o item fica
        // esmaecido com o aviso, e continua ali para explicar que a área existe.
        $this->credenciais();
        $this->veicular('faturas', false);
        $cliente = $this->conta();

        $this->fingir([]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('desligada')
            ->assertSee('opacity-50', false);

        Http::assertNotSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'GetInvoices');
    }

    public function test_os_dois_interruptores_desligados_deixam_o_grupo_financeiro_do_menu_inteiro(): void
    {
        $this->credenciais();
        $this->veicular('servicos', false);
        $this->veicular('faturas', false);
        $cliente = $this->conta();

        $this->fingir([]);

        $html = $this->actingAs($cliente, 'cliente')->get(route('portal.inicio'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, '>desligada<'));
    }

    public function test_um_servico_encerrado_so_aparece_para_quem_pedir(): void
    {
        $this->credenciais();
        $cliente = $this->conta();

        $this->fingir([
            'GetClientsProducts' => $this->produtos([
                $this->servico(12, 'Rastreador J16 4G', 'Active'),
                $this->servico(13, 'Rastreador antigo', 'Terminated'),
                $this->servico(14, 'Plataforma Gold', 'Suspended'),
            ]),
        ]);

        $tela = fn () => $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))->assertOk()->getContent();

        $padrao = $tela();
        $this->assertStringContainsString('Rastreador J16 4G', $padrao);
        $this->assertStringNotContainsString('Rastreador antigo', $padrao, 'Encerrado apareceu sem que ninguém ligou a veiculação.');
        $this->assertStringContainsString('Plataforma Gold', $padrao, 'Suspenso ainda é contrato — some só quem encerra.');

        $this->veicular('servicos_encerrados', true);
        $this->assertStringContainsString('Rastreador antigo', $tela());
    }

    public function test_a_fatura_paga_so_aparece_para_quem_quer_o_historico(): void
    {
        $this->credenciais();
        $cliente = $this->conta();

        $this->fingir([
            'GetInvoices' => $this->faturas([$this->fatura(501, 'Unpaid'), $this->fatura(500, 'Paid')]),
        ]);

        $tela = fn () => $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))->assertOk()->getContent();

        $comHistorico = $tela();
        $this->assertStringContainsString('#GR-501', $comHistorico);
        $this->assertStringContainsString('#GR-500', $comHistorico);

        $this->veicular('faturas_pagas', false);
        $soEmAberto = $tela();
        $this->assertStringContainsString('#GR-501', $soEmAberto);
        $this->assertStringNotContainsString('#GR-500', $soEmAberto, 'Fatura paga apareceu com a veiculação desligada.');
    }

    /**
     * A resposta única para "ainda há o que pagar?". O aviso do portal soma com ela e a segunda via
     * do chat escolhe com ela: quitsada é passado, e sem estado devolvido quem decide é o saldo.
     */
    public function test_a_mesma_regra_de_fatura_em_aberto_vale_para_o_portal_e_para_o_envio(): void
    {
        $emAberto = fn (array $fatura): bool => Veiculacao::faturaEmAberto($fatura);

        $this->assertTrue($emAberto(['status' => 'Unpaid', 'saldo_cents' => 5990]));
        $this->assertTrue($emAberto(['status' => 'Payment Pending', 'saldo_cents' => 5990]), 'Pagamento a caminho ainda é cobrança.');
        $this->assertTrue($emAberto(['status' => 'Collections', 'saldo_cents' => 5990]));
        $this->assertFalse($emAberto(['status' => 'Paid', 'saldo_cents' => 0]), 'Segunda via de fatura paga é cobrar duas vezes.');
        $this->assertFalse($emAberto(['status' => 'Refunded', 'saldo_cents' => 5990]));
        $this->assertFalse($emAberto(['status' => 'Cancelled', 'saldo_cents' => 5990]));
        $this->assertFalse($emAberto(['status' => '', 'saldo_cents' => 0]), 'Sem estado e sem saldo não há dívida.');
        $this->assertTrue($emAberto(['status' => '', 'saldo_cents' => 100]), 'Sem estado, o saldo é a única prova.');
    }

    public function test_os_grupos_autorizados_filtram_a_lista_sem_caixa_e_sem_espacejamento(): void
    {
        $this->credenciais();
        $this->veicular('grupos', ' Rastreamento Veicular ');
        $cliente = $this->conta();

        $this->fingir([
            'GetClientsProducts' => $this->produtos([
                $this->servico(12, 'Rastreador J16 4G', 'Active', 'Rastreamento veicular'),
                $this->servico(13, 'Câmera de frota', 'Active', 'Monitoramento de frota'),
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertDontSee('Câmera de frota');
    }

    public function test_sem_grupo_autorizado_a_vitrine_e_a_lista_inteira(): void
    {
        $this->credenciais();
        $this->veicular('grupos', '');
        $cliente = $this->conta();

        $this->fingir([
            'GetClientsProducts' => $this->produtos([
                $this->servico(12, 'Rastreador J16 4G', 'Active', 'Rastreamento veicular'),
                $this->servico(13, 'Câmera de frota', 'Active', 'Monitoramento de frota'),
            ]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertSee('Câmera de frota');
    }

    public function test_desligar_o_link_externo_deixa_a_lista_sem_saida_para_fora(): void
    {
        $this->credenciais();
        $this->veicular('link_externo', false);
        $cliente = $this->conta();

        $this->fingir([
            'GetClientsProducts' => $this->produtos([$this->servico(12, 'Rastreador J16 4G', 'Active')]),
            'GetInvoices' => $this->faturas([$this->fatura(501, 'Unpaid')]),
        ]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.servicos'))
            ->assertOk()
            ->assertSee('Rastreador J16 4G')
            ->assertDontSee('clientarea.php', false);

        $this->actingAs($cliente, 'cliente')->get(route('portal.faturas'))
            ->assertOk()
            ->assertSee('#GR-501')
            ->assertSee('Pelo atendimento')
            ->assertDontSee('clientarea.php', false);
    }

    public function test_desligar_o_espelho_de_dados_grava_so_no_portal(): void
    {
        $this->credenciais();
        $this->veicular('espelhar_dados', false);
        $cliente = $this->conta();

        $this->fingir(['UpdateClient' => Http::response(['result' => 'success'])]);

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.dados.salvar'), [
                'nome' => 'Maria Atualizada',
                'telefone' => '(35) 99999-0000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            // Desligado não é falha: sem o aviso de "o financeiro não respondeu".
            ->assertSessionMissing('warning');

        $this->assertSame('Maria Atualizada', $cliente->fresh()->nome);
        Http::assertNotSent(fn (RequisicaoHttp $requisicao): bool => ($requisicao['action'] ?? '') === 'UpdateClient');
    }

    public function test_desligar_o_espelho_da_senha_nao_escreve_no_financeiro(): void
    {
        $this->credenciais();
        $this->veicular('espelhar_senha', false);
        $cliente = $this->conta();

        $this->fingir(['UpdateClient' => Http::response(['result' => 'success'])]);

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.senha.salvar'), [
                'senha_atual' => 'senha-portal-123',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ])
            ->assertRedirect()
            ->assertSessionMissing('warning');

        Http::assertNothingSent();
    }

    public function test_a_sonda_descreve_o_que_o_financeiro_respondeu(): void
    {
        // Uma troca de cenário dentro do mesmo fake: o estado é lido na hora da chamada,
        // porque um segundo `Http::fake()` jamais seria alcançado.
        $esteCaso = 'ok';

        Http::fake(function () use (&$esteCaso) {
            return match ($esteCaso) {
                'sem_conta' => Http::response(['result' => 'success', 'clientid' => '']),
                // A forma como o WHMCS real responde "não conheço este e-mail": erro autenticado.
                'nao_encontrado' => Http::response(['result' => 'error', 'message' => 'Client Not Found'], 200),
                'recusada' => Http::response(['result' => 'error', 'message' => 'Invalid credentials'], 200),
                'http_erro' => Http::response('forbidden', 403),
                'sem_resposta' => throw new ConnectionException('cURL error 28: Operation timed out'),
                default => Http::response(['result' => 'success', 'clientid' => 88]),
            };
        });

        $sondar = function () use (&$esteCaso): array {
            $this->actingAs($this->admin)
                ->post(route('admin.settings.whmcs.sondar'), ['sonda_email' => 'maria@exemplo.com.br'])
                ->assertRedirect();

            return (array) session('sonda');
        };

        // Sem credencial não há o que testar, e nada sai pela rede.
        $semCredencial = $sondar();
        $this->assertSame('sem_credencial', $semCredencial['estado']);
        $this->assertStringContainsString('ainda não há o que testar', strtolower($semCredencial['titulo']));
        Http::assertNothingSent();

        $this->credenciais();

        $titulos = [
            'ok' => 'A conexão está no ar.',
            'sem_conta' => 'Conexão ok — conta, não.',
            'recusada' => 'O financeiro recusou a chamada.',
            'http_erro' => 'O financeiro respondeu com erro.',
            'sem_resposta' => 'O financeiro não respondeu.',
        ];

        foreach ($titulos as $estado => $titulo) {
            $esteCaso = $estado;
            $resultado = $sondar();

            $this->assertSame($estado, $resultado['estado'], 'A sonda descreveu errado: '.json_encode($resultado));
            $this->assertSame($titulo, $resultado['titulo']);
            $this->assertNotSame('', $resultado['texto']);
        }

        // No WHMCS que existe, "não conheço este e-mail" vem como erro autenticado (`result:
        // error`, `Client Not Found`, HTTP 200). Ler isso como credencial recusada faria o
        // administrador desconfiar de uma configuração que está certa.
        $esteCaso = 'nao_encontrado';
        $naoEncontrado = $sondar();
        $this->assertSame('sem_conta', $naoEncontrado['estado'], 'Conta inexistente virou recusa de credencial: '.json_encode($naoEncontrado));
        $this->assertSame('Conexão ok — conta, não.', $naoEncontrado['titulo']);

        // A recusa cita o motivo que veio do WHMCS, e o timeout cita a rede — é o que diferencia
        // credencial revogada de endereço errado sem que ninguém precise abrir o log do servidor.
        $esteCaso = 'recusada';
        $this->assertStringContainsString('Invalid credentials', $sondar()['texto']);

        $esteCaso = 'sem_resposta';
        $this->assertStringContainsString('timeout', strtolower($sondar()['texto']));
    }

    public function test_a_sonda_exige_um_email_e_nao_consulta_sem_ele(): void
    {
        $this->credenciais();
        $this->fingir([]);

        $this->actingAs($this->admin)->post(route('admin.settings.whmcs.sondar'), ['sonda_email' => ''])
            ->assertSessionHasErrors('sonda_email');

        Http::assertNothingSent();
    }

    public function test_salvar_os_interruptores_no_painel_grava_no_banco_e_manda_na_frente_do_env(): void
    {
        $esteGrupo = ChavesDeIntegracao::GRUPO_VEICULACAO;

        $this->actingAs($this->admin)->post(route('admin.settings.chaves'), [
            'grupo' => $esteGrupo,
            'whmcs_veicula_servicos' => '0',
            'whmcs_veicula_faturas' => '1',
            'whmcs_veicula_servicos_encerrados' => '1',
            'whmcs_veicula_faturas_pagas' => '0',
            'whmcs_veicula_link_externo' => '0',
            'whmcs_veicula_espelhar_dados' => '0',
            'whmcs_veicula_espelhar_senha' => '0',
            'whmcs_veicula_grupos' => 'Rastreamento veicular, Monitoramento de frota',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('0', Setting::get('whmcs_veicula_servicos'));

        // O que a tela gravou é o que o sistema lê, sem `config:clear` nem reinício: a sobreposição
        // do banco sobre o `.env` roda uma vez por requisição, então quem a refaz aqui é o boot.
        ChavesDeIntegracao::aplicar();

        $this->assertFalse(Veiculacao::abre('servicos'));
        $this->assertTrue(Veiculacao::abre('faturas'));
        $this->assertTrue(Veiculacao::mostraServico('Terminated'));
        $this->assertFalse(Veiculacao::mostraFatura('Paid'));
        $this->assertFalse(Veiculacao::mostraLink());
        $this->assertFalse(Veiculacao::espelha('dados'));
        $this->assertFalse(Veiculacao::espelha('senha'));
        $this->assertSame(
            ['rastreamento veicular', 'monitoramento de frota'],
            Veiculacao::grupos(),
            'A lista separada por vírgula tinha de virar o array que o config espera.'
        );
    }

    public function test_interruptor_em_branco_devolve_a_decisao_ao_env(): void
    {
        ChavesDeIntegracao::guardar('whmcs_veicula_servicos', '0');
        $this->assertSame('0', Setting::get('whmcs_veicula_servicos'));

        $this->actingAs($this->admin)->post(route('admin.settings.chaves'), [
            'grupo' => ChavesDeIntegracao::GRUPO_VEICULACAO,
            'whmcs_veicula_servicos' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(ChavesDeIntegracao::doBanco('whmcs_veicula_servicos'), 'O campo vazio tinha de sair do banco.');
    }

    public function test_a_aba_do_financeiro_mostra_o_estado_da_conexao_e_os_interruptores(): void
    {
        $this->credenciais();
        $this->veicular('grupos', 'Rastreamento veicular');

        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Estado da conexão')
            ->assertSee('https://financeiro.exemplo.com.br/includes/api.php', false)
            ->assertSee('Credenciais completas')
            ->assertSee('Sondar o financeiro')
            ->assertSee('Rastreamento veicular')
            ->assertSee('WHMCS — o que a conexão veicula')
            // O segredo nunca volta para o HTML: a aba diz o que falta, nunca o que existe.
            ->assertDontSee('segredo-da-api');
    }

    public function test_sem_credencial_o_painel_diz_o_que_falta_e_aponta_as_chaves(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Falta configurar')
            ->assertSee('Endereço do WHMCS')
            ->assertSee('chaves-whmcs', false);
    }
}
