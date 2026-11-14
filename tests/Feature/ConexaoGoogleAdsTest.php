<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleAdsMetrics;
use App\Livewire\GoogleAdsDashboard;
use App\Livewire\RsaPreview;
use App\Mail\ContaGoogleAdsDesconectada;
use App\Models\GoogleAdsAccount;
use App\Models\User;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Services\GoogleAds\GoogleAdsEditor;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GoogleAdsCache;
use Google\Ads\GoogleAds\Lib\V26\GoogleAdsClient;
use Google\Ads\GoogleAds\V26\Services\Client\CampaignServiceClient;
use Google\Ads\GoogleAds\V26\Services\MutateCampaignResult;
use Google\Ads\GoogleAds\V26\Services\MutateCampaignsResponse;
use Google\ApiCore\ApiException;
use Google\Rpc\Code;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\Provider as ProviderContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
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
        $this->serviceDeConexao(['1234567890'], ['nome' => 'Golden Rastreamento', 'moeda' => 'BRL', 'gerente' => false]);

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

    public function test_o_token_sem_escopo_de_anuncios_aponta_a_janela_anonima_em_vez_de_mostrar_o_erro_do_google(): void
    {
        $this->mockarSocialite($this->usuarioAutorizado('1//04sem-escopo'));

        // Acontece quando o Chrome já está conectado à conta Google: ele completa o
        // consentimento sozinho e devolve um token só de perfil e e-mail.
        $servico = Mockery::mock(GoogleAdsService::class);
        $servico->shouldReceive('contasAutorizadas')->andThrow(new RuntimeException(
            '{"error":{"code":403,"message":"Request had insufficient authentication scopes.",'
            .'"status":"PERMISSION_DENIED","details":[{"@type":"type.googleapis.com/google.rpc.ErrorInfo",'
            .'"reason":"ACCESS_TOKEN_SCOPE_INSUFFICIENT"}]}}'
        ));
        $this->app->instance(GoogleAdsService::class, $servico);

        $this->actingAs($this->admin)
            ->get(route('google-ads.callback'))
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'janela anônima')
                && ! str_contains($mensagem, 'PERMISSION_DENIED'));

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

    /**
     * Pausar escreve e devolve o endereço da campanha.
     *
     * O endereço sai do resultado do mutate, e o nome do método mudou entre as versões da API:
     * chamar um acessor que não existe estoura o PHP depois de o Google já ter gravado, e a tela
     * fica sem resposta sobre uma mudança que aconteceu. Este teste exercita o caminho real, com
     * o cliente da biblioteca no lugar, para que a assinatura fique presa ao SDK.
     */
    public function test_pausar_devolve_o_endereco_da_campanha_em_vez_de_estourar_depois_de_escrever(): void
    {
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);
        $endereco = 'customers/'.$conta->customer_id.'/campaigns/12345678';

        $servicoCampanhas = Mockery::mock(CampaignServiceClient::class);
        $servicoCampanhas->shouldReceive('mutateCampaigns')
            ->once()
            ->andReturn(new MutateCampaignsResponse(['results' => [new MutateCampaignResult(['resource_name' => $endereco])]]));

        $cliente = Mockery::mock(GoogleAdsClient::class);
        $cliente->shouldReceive('getCampaignServiceClient')->once()->andReturn($servicoCampanhas);

        $servico = Mockery::mock(GoogleAdsService::class)->makePartial();
        $servico->shouldReceive('clientFor')->once()->with($conta)->andReturn($cliente);

        $resposta = $servico->mudarStatus($conta, '12345678', 'PAUSED');

        $this->assertTrue($resposta['escreveu']);
        $this->assertSame($endereco, $resposta['resource_name']);
        $this->assertSame('Pausada', $resposta['status_rotulo']);
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
        Mail::assertQueued(ContaGoogleAdsDesconectada::class);

        // A tela mostra o motivo em vez de girar atrás de um job que já morreu.
        $erro = GoogleAdsCache::erro($conta, 30);
        $this->assertNotNull($erro);
        $this->assertStringContainsString('recusou a credencial', $erro['mensagem']);
    }

    public function test_o_agendamento_nao_enfileira_a_conta_de_gerente_e_explica_o_que_sobrou(): void
    {
        $gerente = GoogleAdsAccount::factory()->gerente()->create([
            'user_id' => $this->admin->id,
            'descriptive_name' => 'Gerente da agência',
        ]);
        $anunciante = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);

        // Uma chamada de métrica no gerente tem resposta certa — a recusa —, e o agendamento
        // rodando a cada quinze minutos é o que enchia `google_ads_api_logs` de falha prevista.
        $this->artisan('google-ads:sync')
            ->expectsOutputToContain('1 conta(s) enfileirada(s)')
            ->assertSuccessful();

        Queue::assertPushed(SyncGoogleAdsMetrics::class, fn (SyncGoogleAdsMetrics $job): bool => $job->contaId === $anunciante->id);
        Queue::assertNotPushed(SyncGoogleAdsMetrics::class, fn (SyncGoogleAdsMetrics $job): bool => $job->contaId === $gerente->id);

        // Sobrando só o gerente, o silêncio seria pior que a falha: a equipe não teria como
        // saber se o cron morreu ou se não há mesmo o que sincronizar.
        $anunciante->delete();

        $this->artisan('google-ads:sync --agendamento')
            ->expectsOutputToContain('o Google não devolve métrica de gerente')
            ->assertSuccessful();

        Queue::assertPushed(SyncGoogleAdsMetrics::class, 1);
    }

    public function test_o_job_aprende_que_a_conta_e_de_gerente_na_recusa_e_para_de_bater_nela(): void
    {
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $servico = Mockery::mock(GoogleAdsService::class);
        // `once()` é o teste: se a segunda rodada chamasse o Google de novo, o Mockery reclama.
        $servico->shouldReceive('buscarCampanhasComMetricas')->once()->andThrow(new ApiException(
            'Error 0: Requested metrics for a manager account. (Error: REQUESTED_METRICS_FOR_MANAGER)',
            Code::INVALID_ARGUMENT,
            'REQUESTED_METRICS_FOR_MANAGER'
        ));
        $this->app->instance(GoogleAdsService::class, $servico);

        (new SyncGoogleAdsMetrics($conta->id, 30))->handle($servico);

        $conta->refresh();
        $this->assertTrue($conta->gerente);
        // A conta continua conectada e útil para o copiloto; o que cai é a janela de métrica,
        // que nunca teve número.
        $this->assertSame(GoogleAdsAccount::ATIVO, $conta->status);
        $this->assertNull(GoogleAdsCache::metricas($conta, 30));

        (new SyncGoogleAdsMetrics($conta->id, 30))->handle($servico);
    }

    public function test_a_camada_de_servico_recusa_o_gerente_antes_de_falar_com_o_google(): void
    {
        $gerente = GoogleAdsAccount::factory()->gerente()->create([
            'descriptive_name' => 'Gerente da agência',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('o Google não devolve métrica de gerente');

        app(GoogleAdsService::class)->buscarCampanhasComMetricas($gerente, 30);
    }

    public function test_a_tela_nao_oferece_o_gerente_nem_pede_numero_que_ele_nao_tem(): void
    {
        GoogleAdsAccount::factory()->gerente()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '9201387315',
            'descriptive_name' => 'Gerente da agência',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.index'))
            ->assertOk()
            // A régua é o botão, não o título: o cartão do guia cita a frase da tela de
            // propósito, e o guia mora num `<template>` dentro desta mesma página.
            ->assertSee('Conectar outra conta')
            ->assertDontSee('Tudo pronto para conectar')
            // Fora do seletor e fora de qualquer número: oferecê-la seria uma tela girando
            // em "Carregando…" para sempre, atrás de um job que o Google recusa.
            ->assertDontSee('Gerente da agência')
            ->assertDontSee('9201387315');

        Queue::assertPushed(SyncGoogleAdsMetrics::class, 0);
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

    public function test_o_estudio_rsa_aplica_modelos_sorteia_combinacoes_e_limpa_rascunho(): void
    {
        $tela = Livewire::test(RsaPreview::class);

        $tela->call('aplicarModelo', 'veicular')
            ->assertSet('rota1', 'rastreamento')
            ->assertSet('rota2', '24h')
            ->assertSee('Rastreamento Veicular 24h')
            ->assertSee('Dentro das travas do Google');

        $tela->call('sortearCombinacoes')
            ->assertSet('semente', 1);

        $tela->call('limparRascunho')
            ->assertSet('url_final', '')
            ->assertSet('titulos', ['', '', ''])
            ->assertSet('descricoes', ['']);
    }

    public function test_o_estudio_rsa_publica_anuncio_real_em_grupo_do_google_ads(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
            'status' => GoogleAdsAccount::ATIVO,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/googleAds:search' => Http::sequence()
                ->push([
                    'results' => [[
                        'campaign' => [
                            'id' => '24456385340',
                            'name' => 'Golden Rastreamento',
                            'status' => 'ENABLED',
                        ],
                    ]],
                ])
                ->push([
                    'results' => [[
                        'campaign' => ['id' => '24456385340'],
                        'adGroup' => [
                            'id' => '999888777',
                            'resourceName' => 'customers/1234567890/adGroups/999888777',
                            'name' => 'Grupo Veicular',
                            'status' => 'ENABLED',
                        ],
                    ]],
                ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/adGroupAds:mutate' => Http::response([
                'results' => [[
                    'resourceName' => 'customers/1234567890/adGroupAds/999888777~555444333',
                ]],
            ]),
        ]);

        $this->actingAs($this->admin);

        $tela = Livewire::test(RsaPreview::class)
            ->assertSet('contaId', $conta->id)
            ->assertSet('campanhaId', '24456385340')
            ->assertSet('grupoResource', 'customers/1234567890/adGroups/999888777')
            ->call('aplicarModelo', 'veicular')
            ->call('publicarNoGoogleAds')
            ->assertSee('Anúncio publicado com sucesso no Google Ads');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'adGroupAds:mutate')) {
                return false;
            }

            $op = $request->data()['operations'][0]['create'] ?? [];

            return ($op['adGroup'] ?? '') === 'customers/1234567890/adGroups/999888777'
                && ($op['status'] ?? '') === 'PAUSED'
                && count($op['ad']['responsiveSearchAd']['headlines'] ?? []) === 8;
        });
    }

    public function test_o_estudio_rsa_bloqueia_texto_fora_da_politica_do_google(): void
    {
        $tela = Livewire::test(RsaPreview::class);

        $tela->set('titulos', ['RASTREAMENTO AGORA', 'Proteção Veicular 24h', 'Golden Rastreamento'])
            ->set('descricoes', ['Ligue (11) 99999-8888!!'])
            ->assertSee('Evite palavras inteiras em MAIÚSCULAS')
            ->assertSee('Remova pontuação repetida');
    }

    public function test_substituir_imagem_rejeita_dimensao_invalida_antes_da_api(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
            'status' => GoogleAdsAccount::ATIVO,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/googleAds:search' => Http::response([
                'results' => [[
                    'campaign' => [
                        'id' => '24456385340',
                        'name' => 'Golden Rastreamento',
                        'status' => 'ENABLED',
                    ],
                ]],
            ]),
        ]);

        $arquivoPequeno = UploadedFile::fake()->image('pequena.png', 50, 50);

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.campanhas.recursos.imagem.substituir', [
                'campanha' => '24456385340',
                'conta' => $conta->id,
            ]), [
                'vinculo_antigo' => 'customers/1234567890/campaignAssets/1',
                'arquivo' => $arquivoPequeno,
                'tipo' => 'BUSINESS_LOGO',
            ])
            ->assertRedirect();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'assets:mutate'));
    }

    public function test_dashboard_google_ads_nao_fica_em_loop_infinito_quando_fila_nao_processa(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
            'status' => GoogleAdsAccount::ATIVO,
        ]);

        Queue::fake();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/googleAds:search' => Http::response([
                'results' => [],
            ]),
        ]);

        $this->actingAs($this->admin);

        $tela = Livewire::test(GoogleAdsDashboard::class)
            ->assertSet('tentativasPoll', 0)
            ->call('verificarSincronizacao')
            ->assertSet('tentativasPoll', 1)
            ->call('verificarSincronizacao')
            ->assertSet('tentativasPoll', 2);

        // Na 2a checagem o componente sincroniza direto e sai do estado de carregamento infinito.
        $tela->assertDontSee('A métrica destes 30 dias está sendo sincronizada.');
    }

    public function test_a_tela_de_recursos_da_campanha_renderiza_com_sucesso(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/googleAds:search' => Http::response([
                'results' => [
                    [
                        'campaign' => [
                            'id' => '24456385340',
                            'name' => 'Golden Rastreamento',
                            'status' => 'ENABLED',
                            'advertisingChannelType' => 'SEARCH',
                            'campaignBudget' => 'customers/1234567890/campaignBudgets/1',
                            'startDateTime' => '2026-11-12 10:00:00',
                            'endDateTime' => '2030-11-12 10:00:00',
                        ],
                        'campaignBudget' => [
                            'amountMicros' => '15000000',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.campanhas.recursos', ['campanha' => '24456385340', 'conta' => $conta->id]))
            ->assertOk()
            ->assertSee('Recursos da campanha')
            ->assertSee('Imagens vinculadas')
            ->assertSee('Sitelinks')
            ->assertSee('Frases de destaque');
    }

    public function test_tela_de_novo_grupo_de_anuncios_carrega_com_sucesso(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/googleAds:search' => Http::response([
                'results' => [
                    [
                        'campaign' => [
                            'id' => '24456385340',
                            'name' => 'Golden Rastreamento',
                            'status' => 'ENABLED',
                            'advertisingChannelType' => 'SEARCH',
                            'campaignBudget' => 'customers/1234567890/campaignBudgets/1',
                            'startDateTime' => '2026-11-12 10:00:00',
                            'endDateTime' => '2030-11-12 10:00:00',
                        ],
                        'campaignBudget' => [
                            'amountMicros' => '15000000',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.campanhas.grupos.novo', ['campanha' => '24456385340', 'conta' => $conta->id]))
            ->assertOk()
            ->assertSee('Novo grupo de anúncios')
            ->assertSee('O grupo de anúncios')
            ->assertSee('Palavras-chave')
            ->assertSee('O anúncio responsivo de pesquisa');
    }

    public function test_vincular_imagem_usa_field_type_ad_image_no_google_ads(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tempFile, 'fake-image-bytes');

        $campanha = [
            'id' => '24456385340',
            'resource' => 'customers/1234567890/campaigns/24456385340',
        ];

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/assets:mutate' => Http::response([
                'results' => [
                    ['resourceName' => 'customers/1234567890/assets/999'],
                ],
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/campaignAssets:mutate' => function (Request $request) {
                $payload = $request->data();
                $fieldType = data_get($payload, 'operations.0.create.fieldType');
                if ($fieldType !== 'AD_IMAGE') {
                    return Http::response(['error' => 'UNSUPPORTED_FIELD_TYPE'], 400);
                }

                return Http::response([
                    'results' => [
                        ['resourceName' => 'customers/1234567890/campaignAssets/24456385340~999~AD_IMAGE'],
                    ],
                ]);
            },
        ]);

        $editor = app(GoogleAdsEditor::class);
        $res = $editor->vincularImagem($conta, $campanha, $tempFile, 'Logo', 'AD_IMAGE');

        $this->assertSame('customers/1234567890/assets/999', $res['asset']);
        $this->assertSame('customers/1234567890/campaignAssets/24456385340~999~AD_IMAGE', $res['vinculo']);

        @unlink($tempFile);
    }

    public function test_vincular_imagem_com_tipo_business_logo_usa_field_type_business_logo(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tempFile, 'fake-image-bytes');

        $campanha = [
            'id' => '24456385340',
            'resource' => 'customers/1234567890/campaigns/24456385340',
        ];

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/assets:mutate' => Http::response([
                'results' => [
                    ['resourceName' => 'customers/1234567890/assets/888'],
                ],
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/campaignAssets:mutate' => function (Request $request) {
                $payload = $request->data();
                $fieldType = data_get($payload, 'operations.0.create.fieldType');
                if ($fieldType !== 'BUSINESS_LOGO') {
                    return Http::response(['error' => 'UNSUPPORTED_FIELD_TYPE'], 400);
                }

                return Http::response([
                    'results' => [
                        ['resourceName' => 'customers/1234567890/campaignAssets/24456385340~888~BUSINESS_LOGO'],
                    ],
                ]);
            },
        ]);

        $editor = app(GoogleAdsEditor::class);
        $res = $editor->vincularImagem($conta, $campanha, $tempFile, 'Logo', 'BUSINESS_LOGO');

        $this->assertSame('customers/1234567890/assets/888', $res['asset']);
        $this->assertSame('customers/1234567890/campaignAssets/24456385340~888~BUSINESS_LOGO', $res['vinculo']);
        $this->assertSame('BUSINESS_LOGO', $res['tipo']);

        @unlink($tempFile);
    }

    public function test_vincular_nome_empresa_usa_field_type_business_name(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        $campanha = [
            'id' => '24456385340',
            'resource' => 'customers/1234567890/campaigns/24456385340',
        ];

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/assets:mutate' => Http::response([
                'results' => [
                    ['resourceName' => 'customers/1234567890/assets/777'],
                ],
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/campaignAssets:mutate' => function (Request $request) {
                $payload = $request->data();
                $fieldType = data_get($payload, 'operations.0.create.fieldType');
                if ($fieldType !== 'BUSINESS_NAME') {
                    return Http::response(['error' => 'UNSUPPORTED_FIELD_TYPE'], 400);
                }

                return Http::response([
                    'results' => [
                        ['resourceName' => 'customers/1234567890/campaignAssets/24456385340~777~BUSINESS_NAME'],
                    ],
                ]);
            },
        ]);

        $editor = app(GoogleAdsEditor::class);
        $res = $editor->vincularNomeEmpresa($conta, $campanha, 'Golden Rastreamento');

        $this->assertSame('customers/1234567890/assets/777', $res['asset']);
        $this->assertSame('customers/1234567890/campaignAssets/24456385340~777~BUSINESS_NAME', $res['vinculo']);
    }

    public function test_vincular_imagem_traduz_erro_unsupported_field_type_do_google_ads(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tempFile, 'fake-image-bytes');

        $campanha = [
            'id' => '24456385340',
            'resource' => 'customers/1234567890/campaigns/24456385340',
        ];

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/assets:mutate' => Http::response([
                'results' => [
                    ['resourceName' => 'customers/1234567890/assets/999'],
                ],
            ]),
            'https://googleads.googleapis.com/v26/customers/1234567890/campaignAssets:mutate' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'Request contains an invalid argument.',
                    'status' => 'INVALID_ARGUMENT',
                    'details' => [
                        [
                            '@type' => 'type.googleapis.com/google.ads.googleads.v26.errors.GoogleAdsFailure',
                            'errors' => [
                                [
                                    'errorCode' => ['assetLinkError' => 'UNSUPPORTED_FIELD_TYPE'],
                                    'message' => 'The given field type is not supported to be added directly through asset links.',
                                    'trigger' => ['stringValue' => 'AD_IMAGE'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 400),
        ]);

        $editor = app(GoogleAdsEditor::class);

        try {
            $editor->vincularImagem($conta, $campanha, $tempFile, 'Logo');
            $this->fail('Deveria ter lançado GoogleAdsFalhou.');
        } catch (GoogleAdsFalhou $e) {
            $this->assertStringContainsString('60 dias', $e->motivoPublico);
            $this->assertStringContainsString('Sitelinks e frases de destaque', $e->motivoPublico);
        } finally {
            @unlink($tempFile);
        }
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
