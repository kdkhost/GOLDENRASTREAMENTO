<?php

namespace Tests\Feature;

use App\Livewire\RelatorioDoGoogleAds;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsPlano;
use App\Models\GoogleAdsRelatorio;
use App\Models\User;
use App\Services\GoogleAds\PlanoDeCampanha;
use App\Support\GoogleAdsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O copiloto do Google Ads, da configuração à campanha pausada.
 *
 * A pergunta que estes testes respondem é a que o painel tem de responder sozinho: onde se
 * configura a IA que escreve o anúncio, o que acontece com o que ela devolve, quem pode publicar
 * e — o mais importante — o que impede o Google de ser tocado antes da hora.
 *
 * São quatro portas e cada teste fecha uma:
 *
 * · a ficha do copiloto é editada no painel e mora no banco (nunca no `.env`);
 * · o plano que a IA escreve passa pelas travas do Google antes de existir como rascunho;
 * · só o administrador aprova e aplica, e aplicar sem credencial falha em português sem uma
 *   única chamada de rede;
 * · a escrita simulada cria a campanha **pausada** e o relatório guarda a foto do período.
 *
 * Nenhum teste fala com o Google nem com a IA: `Http::fake` devolve exatamente o que cada etapa
 * espera, e é isso que prova a montagem do JSON sem gastar cota nem conta de anunciante.
 */
class CopilotoDoGoogleAdsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        Queue::fake();
        $this->credenciaisDoGoogleAds();
        $this->iaPronta();
    }

    /** As credenciais da API do Ads, como se estivessem gravadas no painel. */
    private function credenciaisDoGoogleAds(): void
    {
        config([
            'google_ads.developer_token' => 'token-de-teste',
            'google_ads.login_customer_id' => '1234567890',
            'services.google_ads.client_id' => 'id-cliente',
            'services.google_ads.client_secret' => 'segredo-cliente',
        ]);
    }

    /** A capacidade de conversa ligada no Gemini — a mesma que alimenta a Ana. */
    private function iaPronta(): void
    {
        config([
            'assistente.chaves.gemini' => 'chave-de-teste',
            'assistente.rodada.conversa' => 'gemini/gemini-3-flash-preview',
        ]);
    }

    /** A resposta do Gemini no formato que o provedor lê: uma fala com o JSON dentro. */
    private function respostaDoGemini(array|string $conteudo): array
    {
        $texto = is_array($conteudo) ? json_encode($conteudo, JSON_UNESCAPED_UNICODE) : $conteudo;

        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => $texto]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 480],
        ];
    }

    /** O plano como a IA deveria devolvê-lo. `$ajustes` troca campos pontuais. */
    private function planoDaIA(array $ajustes = []): array
    {
        return array_replace_recursive([
            'nome' => 'Rastreamento para frotas',
            'objetivo' => 'leads',
            'estrategia_lance' => 'MAXIMIZE_CLICKS',
            'cpc_maximo_centavos' => 250,
            'orcamento_diario' => 120,
            'url' => 'https://goldenrastreamento.com.br/frota',
            'regioes' => ['São Paulo', 'Campinas'],
            'idiomas' => ['Português'],
            'grupos' => [[
                'nome' => 'Rastreamento de frota',
                'tema' => 'gestão de veículos',
                'palavras_chave' => [
                    ['texto' => 'rastreamento de frota', 'correspondencia' => 'frase'],
                    ['texto' => 'rastreador para transportadora', 'correspondencia' => 'ampla'],
                ],
                'negativas' => ['grátis', 'gratis'],
            ]],
            'anuncios' => [
                'titulos' => [
                    'Rastreie sua frota',
                    'Gestão de frota 24h',
                    'Rastreamento veicular',
                ],
                'descricoes' => [
                    'Plataforma de rastreamento veicular com suporte 24 horas.',
                    'Acompanhe cada veículo da frota em tempo real.',
                ],
                'caminhos' => ['frota', '24h'],
            ],
            'justificativa' => 'Estrutura enxuta, focada no serviço de frota e na região de atuação.',
        ], $ajustes);
    }

    /** O briefing do formulário, com os campos que o controlador valida. */
    private function briefing(array $ajustes = []): array
    {
        return array_replace([
            'nome' => 'Rastreamento para frotas',
            'objetivo' => 'leads',
            'orcamento' => 120,
            'url' => 'https://goldenrastreamento.com.br/frota',
            'regioes' => "São Paulo\nCampinas",
            'publico' => 'Transportadoras com frota de 10 a 200 veículos.',
            'oferta' => 'Instalação no mesmo dia e suporte 24 horas.',
            'observacoes' => 'Não prometer prazo de entrega.',
        ], $ajustes);
    }

    /**
     * Um plano já persistido, passado pelo validador de verdade.
     *
     * Montar o JSON à mão nos testes de escrita deixaria o formato do plano duplicado em dois
     * lugares — e o dia em que o validador mudar, o teste continuaria passando com um plano que
     * não existe mais.
     */
    private function planoPersistido(string $status = GoogleAdsPlano::RASCUNHO, ?User $dono = null): GoogleAdsPlano
    {
        $conferido = PlanoDeCampanha::conferir($this->planoDaIA(), $this->briefing());

        return GoogleAdsPlano::create([
            'user_id' => ($dono ?? $this->admin)->id,
            'nome' => $conferido['plano']['nome'],
            'status' => $status,
            'objetivo' => $conferido['plano']['objetivo'],
            'orcamento_diario_centavos' => $conferido['plano']['orcamento_diario_centavos'],
            'briefing_json' => $this->briefing(),
            'plano_json' => $conferido['plano'],
            'validacao_json' => [
                'problemas' => $conferido['problemas'],
                'avisos' => $conferido['avisos'],
                'forca' => $conferido['forca'],
            ],
            'modelo' => 'gemini/gemini-3-flash-preview',
        ]);
    }

    /** Uma linha de campanha no formato que a cache guarda — é o que o relatório lê. */
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

    /**
     * A resposta simulada da API de escrita, na ordem em que cada etapa é chamada.
     *
     * O padrão geral fica por último porque o fake escolhe o primeiro que casa: um endpoint
     * esquecido cai no genérico e devolve `results`, que é o que quase todas as etapas leem.
     */
    private function fakeDaEscrita(string $customerId = '1234567890'): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'expires_in' => 3600,
            ]),
            'https://googleads.googleapis.com/*/geoTargetConstants:suggest' => Http::response([
                'geoTargetConstantSuggestions' => [
                    ['geoTargetConstant' => ['resourceName' => 'geoTargetConstants/1001']],
                ],
            ]),
            'https://googleads.googleapis.com/*/campaignBudgets:mutate' => Http::response([
                'results' => [['resourceName' => "customers/{$customerId}/campaignBudgets/111"]],
            ]),
            'https://googleads.googleapis.com/*/campaigns:mutate' => Http::response([
                'results' => [['resourceName' => "customers/{$customerId}/campaigns/999"]],
            ]),
            'https://googleads.googleapis.com/*/adGroups:mutate' => Http::response([
                'results' => [['resourceName' => "customers/{$customerId}/adGroups/222"]],
            ]),
            'https://googleads.googleapis.com/*' => Http::response([
                'results' => [['resourceName' => "customers/{$customerId}/objects/333"]],
            ]),
        ]);
    }

    /** A tela existe, abre pelo painel e está no menu — a resposta ao "onde fica?". */
    public function test_o_copiloto_e_o_relatorio_abrem_pelo_painel_e_estao_no_menu(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.copiloto.index'))
            ->assertOk()
            ->assertSee('Copiloto do Ads')
            ->assertSee('Pedir uma campanha')
            ->assertSee('A ficha do copiloto')
            ->assertSee('Gerar o plano');

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.relatorio.index'))
            ->assertOk()
            ->assertSee('Relatório do Google Ads');
    }

    /** A ficha é editada no painel e vai para o banco — não para o `.env`. */
    public function test_a_ficha_do_copiloto_e_editada_no_painel_e_fica_no_banco(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.ficha'), [
                'nome' => 'Max',
                'cargo' => 'Copiloto de tráfego da Golden',
                'tom' => 'direto e técnico',
                'instrucoes' => 'Nunca prometa posição no Google.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $ficha = \App\Models\GoogleAdsCopiloto::sole();
        $this->assertSame('direto e técnico', $ficha->tom);
        $this->assertSame('Nunca prometa posição no Google.', $ficha->instrucoes);

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.copiloto.index'))
            ->assertSee('Copiloto de tráfego da Golden')
            ->assertSee('Nunca prometa posição no Google.');
    }

    /**
     * O briefing vira plano gravado, com as travas do Google aplicadas antes de a pessoa ver.
     *
     * O título de 40 caracteres e o orçamento abaixo do mínimo da casa são o ponto: o modelo
     * pode errar a conta, e o que chega ao banco é o que o Google aceitaria.
     */
    public function test_o_briefing_vira_plano_gravado_com_as_travas_do_google(): void
    {
        // O `array_replace` no topo troca a lista de títulos inteira: um merge recursivo manteria
        // os dois últimos títulos do plano base e o descarte do longo não seria observável.
        $planoComTituloLongo = array_replace($this->planoDaIA(['orcamento_diario' => 5]), [
            'anuncios' => [
                'titulos' => [
                    'Rastreamento de frota completo e seguro',
                    'Rastreie sua frota',
                    'Gestão de frota 24h',
                    'Rastreamento veicular',
                ],
                'descricoes' => $this->planoDaIA()['anuncios']['descricoes'],
                'caminhos' => $this->planoDaIA()['anuncios']['caminhos'],
            ],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($this->respostaDoGemini($planoComTituloLongo)),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.gerar'), $this->briefing())
            ->assertRedirect()
            ->assertSessionHas('success');

        $plano = GoogleAdsPlano::sole();

        $this->assertSame(GoogleAdsPlano::RASCUNHO, $plano->status);
        $this->assertSame($this->admin->id, $plano->user_id);
        $this->assertSame('Transportadoras com frota de 10 a 200 veículos.', $plano->briefing_json['publico']);

        // O título longo foi descartado, e o que sobrou são os três válidos.
        $this->assertCount(3, $plano->plano()['anuncios']['titulos']);
        $this->assertStringContainsString(
            'foram descartados por passar de 30 caracteres',
            implode(' ', $plano->avisos()),
        );

        // R$ 5,00/dia é abaixo do mínimo da casa: o plano entrou com o piso e avisou.
        $this->assertSame(1000, $plano->orcamento_diario_centavos);
        $this->assertStringContainsString('abaixo do mínimo da casa', implode(' ', $plano->avisos()));

        $this->assertSame([], $plano->problemas());
        $this->assertSame('gemini/gemini-3-flash-preview', $plano->modelo);

        // A ordem enviada à IA leva a ficha da casa e o pedido de português.
        Http::assertSent(function (Request $requisicao): bool {
            if (! str_contains($requisicao->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $ordem = json_encode($requisicao->data()['systemInstruction'] ?? [], JSON_UNESCAPED_UNICODE);

            return str_contains($ordem, 'Max') && str_contains($ordem, 'Responda sempre em português do Brasil');
        });
    }

    /** Sem a chave da IA, o pedido é recusado em português e nada é gravado. */
    public function test_sem_ia_pronta_o_pedido_e_recusado_em_portugues_e_nada_e_gravado(): void
    {
        config([
            'assistente.chaves.gemini' => null,
            'assistente.rodada.conversa' => null,
        ]);

        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.gerar'), $this->briefing())
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'não tem uma IA pronta'));

        $this->assertSame(0, GoogleAdsPlano::count());
        Http::assertNothingSent();

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.copiloto.index'))
            ->assertOk()
            ->assertSee('Falta a chave de IA')
            ->assertSee('falta a chave de Google Gemini');
    }

    /** Quem atende pede o plano; quem publica no Google é só o administrador. */
    public function test_o_vendedor_pede_plano_mas_nao_aprova_nem_aplica_nem_ve_o_do_outro(): void
    {
        $doAdmin = $this->planoPersistido();
        $doVendedor = $this->planoPersistido(GoogleAdsPlano::RASCUNHO, $this->vendedor);
        $aprovado = $this->planoPersistido(GoogleAdsPlano::APROVADO);
        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);

        // O plano de outro não aparece para quem não é administrador.
        $this->actingAs($this->vendedor)
            ->get(route('admin.google-ads.copiloto.show', $doAdmin))
            ->assertNotFound();

        // Nem o próprio plano ele aprova: aprovar é ato de administrador.
        $this->actingAs($this->vendedor)
            ->post(route('admin.google-ads.copiloto.aprovar', $doVendedor))
            ->assertForbidden();

        $this->actingAs($this->vendedor)
            ->post(route('admin.google-ads.copiloto.aplicar', $aprovado), ['conta' => $conta->id])
            ->assertForbidden();

        $this->assertSame(GoogleAdsPlano::RASCUNHO, $doVendedor->refresh()->status);
        $this->assertSame(GoogleAdsPlano::APROVADO, $aprovado->refresh()->status);
    }

    /** Aprovar não publica: só muda o estado e libera o passo seguinte. */
    public function test_aprovar_libera_o_plano_sem_escrever_no_google(): void
    {
        $plano = $this->planoPersistido();

        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.aprovar', $plano))
            ->assertSessionHas('success');

        $this->assertSame(GoogleAdsPlano::APROVADO, $plano->refresh()->status);
        $this->assertNull($plano->aplicado_em);
        Http::assertNothingSent();
    }

    /** Sem credencial da API, aplicar falha em português e não sai uma única requisição. */
    public function test_aplicar_sem_credencial_do_google_falha_em_portugues_sem_tocar_a_api(): void
    {
        config([
            'services.google_ads.client_id' => null,
            'services.google_ads.client_secret' => null,
        ]);

        $conta = GoogleAdsAccount::factory()->create(['user_id' => $this->admin->id]);
        $plano = $this->planoPersistido(GoogleAdsPlano::APROVADO);

        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.aplicar', $plano), ['conta' => $conta->id])
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'credenciais da API'));

        $plano->refresh();
        $this->assertSame(GoogleAdsPlano::FALHOU, $plano->status);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', (string) $plano->erro);
        Http::assertNothingSent();
    }

    /**
     * Com a API simulada, aplicar cria a campanha **pausada** e grava a prova no plano.
     *
     * É o teste que prova a montagem inteira: o token renovado, a região resolvida, o orçamento,
     * a campanha, os grupos, o anúncio e as palavras — cada chamada no corpo que o Google espera.
     */
    public function test_aplicar_com_a_api_simulada_cria_a_campanha_pausada_e_marca_o_plano(): void
    {
        $this->fakeDaEscrita();

        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);
        $plano = $this->planoPersistido(GoogleAdsPlano::APROVADO);

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.aplicar', $plano), ['conta' => $conta->id])
            ->assertSessionHas('success', fn (string $mensagem): bool => str_contains($mensagem, 'pausada'));

        $plano->refresh();

        $this->assertSame(GoogleAdsPlano::APLICADO, $plano->status);
        $this->assertSame('999', $plano->campanhaCriada());
        $this->assertNotNull($plano->aplicado_em);
        $this->assertSame('PAUSED', $plano->aplicacao_json['status']);
        $this->assertSame($conta->id, $plano->google_ads_account_id);

        // O corpo da campanha é o que o Google exige: pesquisa, pausada e com teto de lance.
        Http::assertSent(function (Request $requisicao): bool {
            if (! str_contains($requisicao->url(), 'campaigns:mutate')) {
                return false;
            }

            $corpo = $requisicao->data();

            return data_get($corpo, 'operations.0.create.status') === 'PAUSED'
                && data_get($corpo, 'operations.0.create.advertisingChannelType') === 'SEARCH'
                && data_get($corpo, 'operations.0.create.maximizeClicks') !== null;
        });

        // O token saiu do refresh token gravado, e a região foi resolvida pela sugestão.
        Http::assertSent(fn (Request $requisicao): bool => str_contains($requisicao->url(), 'oauth2.googleapis.com/token'));
        Http::assertSent(fn (Request $requisicao): bool => str_contains($requisicao->url(), 'geoTargetConstants:suggest'));

        $this->assertDatabaseHas('google_ads_api_logs', [
            'google_ads_account_id' => $conta->id,
            'endpoint' => 'escrita.plano.aplicado',
        ]);
    }

    /** Um plano já aplicado não é aplicado de novo — nem volta para a fila de rascunho. */
    public function test_o_plano_aplicado_nao_e_aplicado_duas_vezes(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);
        $plano = $this->planoPersistido(GoogleAdsPlano::APLICADO);

        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.google-ads.copiloto.aplicar', $plano), ['conta' => $conta->id])
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'já foi aplicado'));

        Http::assertNothingSent();
    }

    /** O relatório entrega a métrica ao copiloto uma vez e guarda a foto do período. */
    public function test_o_relatorio_pede_a_leitura_e_guarda_a_foto_do_periodo(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($this->respostaDoGemini([
                'diagnostico' => 'A conta entrega cliques baratos, mas a conversão ainda não foi medida com clareza.',
                'destaques' => ['CTR de 2,13% acima da média do setor.'],
                'recomendacoes' => [[
                    'tipo' => 'medicao',
                    'titulo' => 'Marcar a conversão de orçamento',
                    'detalhe' => 'Sem conversão registrada, o custo por contato não pode ser comparado com o fechamento.',
                    'impacto' => 'alto',
                ]],
            ])),
        ]);

        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $tela = Livewire::actingAs($this->admin)
            ->test(RelatorioDoGoogleAds::class)
            ->call('gerar')
            ->assertHasNoErrors()
            ->assertDispatched('golden-aviso');

        $relatorio = GoogleAdsRelatorio::sole();

        $this->assertSame($conta->id, $relatorio->google_ads_account_id);
        $this->assertSame(30, $relatorio->dias);
        $this->assertStringContainsString('cliques baratos', $relatorio->diagnostico());
        $this->assertSame('alto', $relatorio->recomendacoes()[0]['impacto']);
        $this->assertSame(15000, $relatorio->resumo_json['impressoes']);

        // Ver a análise antiga não gasta uma segunda chamada de IA.
        $tela->call('ver', $relatorio->id)->assertHasNoErrors();
        Http::assertSentCount(1);
    }

    /** O CSV do relatório sai na régua da casa: BOM, ponto e vírgula e colunas em ordem. */
    public function test_o_csv_do_relatorio_sai_no_padrao_da_casa(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
            'descriptive_name' => 'Golden Rastreamento',
        ]);
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $resposta = $this->actingAs($this->admin)->get(route('admin.google-ads.relatorio.exportar', [
            'conta' => $conta->id,
            'dias' => 30,
        ]));

        $resposta->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $resposta->assertDownload();
        $this->assertStringContainsString(
            'golden-google-ads-30dias-',
            (string) $resposta->headers->get('content-disposition')
        );

        $conteudo = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'O CSV precisa do BOM para o Excel em português ler os acentos.');
        $this->assertStringContainsString(
            '"Campanha";"Cliques";"Conta";"Conversões";"CPC médio (R$)";"CTR (%)";"Custo (R$)";"ID";"Impressões"',
            $conteudo
        );
        $this->assertStringContainsString('"Rastreamento de frota"', $conteudo);
        $this->assertStringContainsString('"1.234,50"', $conteudo);
    }

    /** A tela do relatório desenha a métrica da cache, sem esperar o Google. */
    public function test_a_tela_do_relatorio_mostra_a_metrica_da_cache(): void
    {
        $conta = GoogleAdsAccount::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => '1234567890',
        ]);
        GoogleAdsCache::gravar($conta, 30, [$this->campanha('1234', 'Rastreamento de frota')], $this->resumo());

        $this->actingAs($this->admin)
            ->get(route('admin.google-ads.relatorio.index', ['conta' => $conta->id, 'dias' => 30]))
            ->assertOk()
            ->assertSee('Analisar com o copiloto')
            ->assertSee('Rastreamento de frota')
            ->assertSee('Baixar CSV');
    }
}
