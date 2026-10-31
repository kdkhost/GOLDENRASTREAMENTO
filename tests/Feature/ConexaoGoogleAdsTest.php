<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleAdsMetrics;
use App\Livewire\RsaPreview;
use App\Models\GoogleAdsAccount;
use App\Models\User;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GoogleAdsCache;
use Google\ApiCore\ApiException;
use Google\Rpc\Code;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Provider as ProviderContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * A conexão de Google Ads, do consentimento à escrita.
 *
 * São as quatro pontas em que um erro custa caro: o pedido de acesso (sem `access_type=offline`
 * o Google nunca devolve token de renovação, e a conta "conecta" uma vez só), a guarda do token
 * (cifrado no banco, decifrado só em memória), a leitura da tela (nunca pode chamar a API em
 * requisição) e a escrita (a operação simulada antes da verdadeira). Cada teste fecha uma delas.
 *
 * Nenhum teste fala com o Google: o Socialite é mockado na fachada e a camada de serviço é
 * trocada no container por uma que devolve exatamente o que cada etapa precisa saber.
 */
class ConexaoGoogleAdsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $this->credenciaisPresentes();
        Queue::fake();
    }

    /**
     * As credenciais que a integração exige hoje, num valor de teste. Sem o par OAuth o painel
     * recusa qualquer chamada — e diz qual falta. O token de desenvolvedor segue configurado de
     * propósito: é o estado de quem já o tinha gravado antes de o Google aposentá-lo.
     */
    private function credenciaisPresentes(): void
    {
        config([
            'google_ads.developer_token' => 'token-de-teste',
            'google_ads.login_customer_id' => '1234567890',
            'services.google_ads.client_id' => 'id-cliente',
            'services.google_ads.client_secret' => 'segredo-cliente',
            'services.google_ads.redirect' => 'http://localhost/auth/google-ads/callback',
        ]);
    }

    /** A fachada do Socialite devolvendo o provedor que o teste desenhou. */
    private function mockarSocialite(?SocialiteUser $usuario = null): void
    {
        $provedor = Mockery::mock(ProviderContract::class);
        $provedor->shouldReceive('redirect')->andReturnUsing(fn () => redirect('https://accounts.google.com/o/oauth2/auth'));
        $provedor->shouldReceive('user')->andReturn($usuario);

        Socialite::shouldReceive('driver')->with('google-ads')->andReturn($provedor);
    }

    /** O consentimento como o Google o devolve: com o token de renovação no corpo. */
    private function usuarioAutorizado(string $token): SocialiteUser
    {
        $usuario = new SocialiteUser;
        $usuario->setToken('access-efemero');
        $usuario->setRefreshToken($token);

        return $usuario;
    }

    /** A camada de serviço do callback: quais contas o token alcança e o que elas são. */
    private function serviceDeConexao(array $alcanca, ?array $detalhes = null): GoogleAdsService
    {
        $servico = Mockery::mock(GoogleAdsService::class);
        $servico->shouldReceive('contasAutorizadas')->andReturn($alcanca);
        $servico->shouldReceive('detalhesDaConta')->andReturn($detalhes ?? ['nome' => null, 'moeda' => null, 'gerente' => false]);

        $this->app->instance(GoogleAdsService::class, $servico);

        return $servico;
    }

    public function test_a_tela_oferece_a_conexao_e_o_pedido_de_acesso_e_offline_com_o_escopo_de_anuncios(): void
    {
        $tela = $this->actingAs($this->admin)->get(route('admin.google-ads.index'));
        $tela->assertOk()->assertSee('Conectar conta Google Ads', false);

        // Aqui o provedor é o verdadeiro, montado pelo GoogleAdsServiceProvider: o que se
        // testa é o endereço e o que ele carrega, porque um `access_type` perdido significa
        // nenhuma credencial para o agendamento renovar.
        $resposta = $this->actingAs($this->admin)->get(route('google-ads.redirect'));
        $this->assertSame(302, $resposta->getStatusCode());

        $url = urldecode($resposta->headers->get('Location'));
        $this->assertStringContainsString('accounts.google.com/o/oauth2/auth', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('https://www.googleapis.com/auth/adwords', $url);
        $this->assertStringContainsString('redirect_uri=http://localhost/auth/google-ads/callback', $url);
    }

    public function test_sem_credencial_o_painel_recusa_e_diz_qual_falta_e_onde_se_preenche(): void
    {
        config(['services.google_ads.client_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('google-ads.redirect'))
            ->assertRedirect(route('admin.google-ads.index'))
            ->assertSessionHas('error', 'Faltam credenciais do Google Ads: GOOGLE_CLIENT_ID. Preencha em Sistema → Configurações → Chaves de integração.');

        $this->assertDatabaseCount('google_ads_accounts', 0);
    }

    public function test_o_cron_sem_credencial_avisa_em_vez_de_gritar_no_log(): void
    {
        config([
            'services.google_ads.client_id' => null,
            'services.google_ads.client_secret' => null,
        ]);

        $falta = 'Faltam as credenciais do Google Ads: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET.';

        // Chamado à mão: a pessoa quer a lista do que falta e um código de falha.
        $this->artisan('google-ads:sync')
            ->expectsOutputToContain($falta)
            ->assertExitCode(1);

        // Chamado pelo agendamento: conta nenhuma conectada é o estado normal de uma instalação
        // nova, e um ERROR a cada quinze minutos faz a equipe parar de ler o log.
        $this->artisan('google-ads:sync --agendamento')
            ->expectsOutputToContain($falta)
            ->assertExitCode(0);
    }

    public function test_o_callback_guarda_a_conta_com_o_token_cifrado_e_pede_a_primeira_sincronizacao(): void
    {
        $this->mockarSocialite($this->usuarioAutorizado('1//04token-de-renovacao'));
        $this->serviceDeConexao(['1234567890'], ['nome' => 'Golden Rastreamento', 'moeda' => 'BRL', 'gerente' => true]);

        $this->actingAs($this->admin)
            ->get(route('google-ads.callback'))
            ->assertRedirect(route('admin.google-ads.index'))
            ->assertSessionHas('success');

        $conta = GoogleAdsAccount::where('user_id', $this->admin->id)->sole();

        $this->assertSame('1234567890', $conta->customer_id);
        $this->assertSame('Golden Rastreamento', $conta->descriptive_name);
        $this->assertSame('BRL', $conta->currency_code);
        $this->assertSame(GoogleAdsAccount::ATIVO, $conta->status);
        // O cast devolve o texto que entrou — e o banco guarda outra coisa.
        $this->assertSame('1//04token-de-renovacao', $conta->refresh_token);
        $this->assertNotSame(
            '1//04token-de-renovacao',
            DB::table('google_ads_accounts')->where('id', $conta->id)->value('refresh_token'),
            'O token de renovação precisa sair do banco cifrado.'
        );

        Queue::assertPushed(SyncGoogleAdsMetrics::class, fn (SyncGoogleAdsMetrics $job): bool => $job->contaId === $conta->id
            && $job->dias === (int) config('google_ads.dias_padrao'));
    }

    public function test_reconectar_atualiza_a_mesma_fila_em_vez_de_duplicar_a_conta(): void
    {
        $existente = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
            'refresh_token' => '1//04token-velho',
            'status' => GoogleAdsAccount::DESCONECTADO,
            'motivo_desconexao' => 'O Google recusou o token de atualização.',
        ]);

        $this->mockarSocialite($this->usuarioAutorizado('1//04token-novo'));
        $this->serviceDeConexao(['1234567890'], ['nome' => 'Golden Rastreamento', 'moeda' => 'BRL', 'gerente' => true]);

        $this->actingAs($this->admin)->get(route('google-ads.callback'))->assertSessionHas('success');

        $conta = GoogleAdsAccount::where('user_id', $this->admin->id)->sole();

        $this->assertSame($existente->id, $conta->id, 'Reconectar criou uma conta nova em vez de atualizar a existente.');
        $this->assertSame('1//04token-novo', $conta->refresh_token);
        $this->assertSame(GoogleAdsAccount::ATIVO, $conta->status);
        $this->assertNull($conta->motivo_desconexao);
    }

    public function test_quem_desiste_no_google_volta_para_a_tela_sem_gravar_acesso(): void
    {
        $this->actingAs($this->admin)
            ->get(route('google-ads.callback', ['error' => 'access_denied']))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('google_ads_accounts', 0);
    }

    public function test_o_acesso_que_nao_alcancca_anunciante_algum_e_explicado_na_tela(): void
    {
        $this->mockarSocialite($this->usuarioAutorizado('1//04sem-anunciante'));
        $this->serviceDeConexao([]);

        $this->actingAs($this->admin)
            ->get(route('google-ads.callback'))
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'não alcança nenhuma conta'));

        $this->assertDatabaseCount('google_ads_accounts', 0);
    }

    public function test_a_tela_le_da_cache_e_enfileira_a_sincronizacao_em_vez_de_chamar_a_api(): void
    {
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.index'))
            ->assertOk()
            ->assertSee('Carregando');

        Queue::assertPushed(SyncGoogleAdsMetrics::class, 1);

        // A janela preenchida: agora a tela mostra os números, e não pede de novo.
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $resposta = $this->actingAs($this->admin)
            ->get(route('admin.google-ads.index'))
            ->assertOk()
            ->assertSee('Rastreamento de frota')
            // O dinheiro sai em reais, formatado pela casa, nunca em micro de moeda.
            ->assertSee('R$ 1.234,50')
            // O status vem traduzido: a tabela não mostra enumeração de API.
            ->assertSee('Ativa')
            // A régua do cartão de espera é a frase própria dele, não a palavra "Carregando":
            // o cartão do guia cita essa palavra de propósito, para a pessoa reconhecer o sintoma.
            ->assertDontSee('sincronizada em segundo plano');

        // O enum da API ainda aparece no HTML — como `value` do filtro e do formulário de
        // escrita, que são os códigos que o Google entende. O que não pode é vir a texto, e é
        // só o que a pessoa lê: por isso a régua é o conteúdo fora das etiquetas.
        $this->assertStringNotContainsString('ENABLED', strip_tags($resposta->getContent()));

        Queue::assertPushed(SyncGoogleAdsMetrics::class, 1);
    }

    public function test_a_tela_nao_oferece_a_conta_de_outro_usuario(): void
    {
        $deOutro = GoogleAdsAccount::factory()->create(['descriptive_name' => 'Conta do concorrente']);

        // O administrador vê as conexões da equipe de propósito — a agência opera as contas dos
        // outros. Quem não pode ver é o papel comum: para ele, só a conta que ele conectou.
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
        $this->assertNotSame($deOutro->user_id, $vendedor->id);

        $this->actingAs($vendedor)
            ->get(route('admin.google-ads.index'))
            ->assertOk()
            ->assertDontSee($deOutro->descriptive_name);
    }

    public function test_pausar_passa_pela_operacao_simulada_antes_de_escrever_de_verdade(): void
    {
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $servico = Mockery::mock(GoogleAdsService::class);
        $servico->shouldReceive('traduzStatus')->once()->with('PAUSED')->andReturn('Pausada');
        // A ordem é o ponto: uma escrita em conta de anunciante só depois de o Google aprovar
        // a mesma operação em validate_only. A conta vem pelo container (outra instância da
        // mesma linha), então o que se confere aqui é o id, não o objeto.
        $mesma_conta = Mockery::on(fn (GoogleAdsAccount $outra): bool => $outra->is($conta));
        $servico->shouldReceive('validarOperacao')->once()->with($mesma_conta, '1234', 'PAUSED')->ordered()->andReturn(['validou' => true]);
        $servico->shouldReceive('mudarStatus')->once()->with($mesma_conta, '1234', 'PAUSED')->ordered()->andReturn(['escreveu' => true]);
        $this->app->instance(GoogleAdsService::class, $servico);

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.campanhas.status', ['campanha' => '1234', 'dias' => 30]), [
                'status' => 'PAUSED',
                'conta' => $conta->id,
                'dias' => 30,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Rastreamento de frota agora está Pausada. A métrica está sendo atualizada.');

        // A linha velha, do Google antes da mudança, foi descartada e pedida de novo.
        $this->assertNull(GoogleAdsCache::metricas($conta, 30));
        Queue::assertPushed(SyncGoogleAdsMetrics::class, 1);
    }

    public function test_ninguem_escreve_na_conta_de_outro(): void
    {
        $deOutro = GoogleAdsAccount::factory()->create();
        GoogleAdsCache::gravar($deOutro, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $servico = Mockery::mock(GoogleAdsService::class);
        $servico->shouldNotReceive('validarOperacao');
        $servico->shouldNotReceive('mudarStatus');
        $this->app->instance(GoogleAdsService::class, $servico);

        $visitante = User::factory()->create(['email' => 'outro@golden.com.br', 'role' => 'vendedor']);

        $this->actingAs($visitante)
            ->post(route('admin.google-ads.campanhas.status', ['campanha' => '1234']), [
                'status' => 'PAUSED',
                'conta' => $deOutro->id,
                'dias' => 30,
            ])
            ->assertSessionHas('error');
    }

    public function test_o_job_desconecta_a_conta_e_avisa_dono_quando_o_google_recusa_a_credencial(): void
    {
        Mail::fake();
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);

        $servico = Mockery::mock(GoogleAdsService::class);
        $servico->shouldReceive('buscarCampanhasComMetricas')->andThrow(
            new ApiException('Credential denied.', Code::UNAUTHENTICATED, 'UNAUTHENTICATED')
        );
        $this->app->instance(GoogleAdsService::class, $servico);

        // O job é chamado à mão: `Queue::fake()` intercepta até o `dispatchSync`, que na
        // verdade vai por `connection('sync')->push()` — e um mock de fila nunca executa o
        // handle. O que se testa é exatamente o corpo que o worker roda.
        (new SyncGoogleAdsMetrics($conta->id, 30))->handle($servico);

        $conta->refresh();
        $this->assertSame(GoogleAdsAccount::DESCONECTADO, $conta->status);
        $this->assertStringContainsString('recusou a credencial', (string) $conta->motivo_desconexao);
        Mail::assertQueued(\App\Mail\ContaGoogleAdsDesconectada::class);

        // A tela mostra o motivo em vez de girar atrás de um job que já morreu.
        $erro = GoogleAdsCache::erro($conta, 30);
        $this->assertNotNull($erro);
        $this->assertStringContainsString('recusou a credencial', $erro['mensagem']);
    }

    public function test_o_editor_aplica_as_travas_do_google_e_mostra_o_corte_da_busca(): void
    {
        $tela = Livewire::test(RsaPreview::class);

        $tela->set('titulos.0', str_repeat('a', 31))
            ->assertSee('31/30')
            ->assertSee('maior do que o Google aceita', false);

        // Rota é por segmento: 20 caracteres na primeira não comem os 15 que a segunda tem.
        // A prévia só existe com título e descrição aprovados — é o Google quem monta a dupla.
        $tela->set('titulos.0', 'Rastreie sua frota agora')
            ->set('descricoes.0', 'Plataforma de rastreamento veicular 24 horas.')
            ->set('rota1', str_repeat('r', 20))
            ->set('rota2', '24h')
            ->assertSee(str_repeat('r', 15).'/24h');

        // O Google aceita no máximo quinze títulos e quatro descrições.
        for ($n = 0; $n < 20; $n++) {
            $tela->call('adicionarTitulo');
        }
        $tela->assertSet('titulos', fn (array $titulos): bool => count($titulos) === 15);
    }

    /** Uma linha no formato exato que `linhaParaTela` escreve — a tela lê destas chaves. */
    private function campanha(string $id, string $nome): array
    {
        return [
            'id' => $id,
            'resource_name' => 'customers/1234567890/campaigns/'.$id,
            'nome' => $nome,
            'status' => 'ENABLED',
            'status_rotulo' => 'Ativa',
            'impressoes' => 15000,
            'cliques' => 320,
            'ctr' => 2.13,
            'cpc_medio_centavos' => 386,
            'custo_centavos' => 123450,
            'conversoes' => 18,
            'valor_conversoes_centavos' => 450000,
            'roas' => 3.65,
            'custo_por_conversao_centavos' => 6858,
        ];
    }

    private function resumo(): array
    {
        return [
            'campanhas' => 1,
            'impressoes' => 15000,
            'cliques' => 320,
            'custo_centavos' => 123450,
            'conversoes' => 18,
            'valor_conversoes_centavos' => 450000,
            'ctr' => 2.13,
            'cpc_medio_centavos' => 386,
            'roas' => 3.65,
        ];
    }
}
