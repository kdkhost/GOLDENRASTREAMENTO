<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitEvent;
use App\Services\Atribuidor;
use App\Services\Rastreio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * A régua de origem do painel: quem clicou, de onde clicou e o que o visitante fez até
 * se cadastrar. Cada teste cobre um degrau da escada — o classificador (a decisão),
 * o registro (a jornada no banco), o herdeiro (o lead) e a tela (o painel de atribuição).
 */
class AtribuicaoDeOrigemTest extends TestCase
{
    use RefreshDatabase;

    private string $cookie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->cookie = config('golden.rastreio.cookie');
        // O farol usa fetch com credentials:'same-origin', então o cookie de visitante vai
        // junto em todo POST JSON. Em teste o Laravel só repete isso com withCredentials() —
        // sem ele, cadastro por formulário AJAX perderia a origem da jornada.
        $this->withCredentials();
    }

    public function test_o_classificador_le_o_clique_de_anuncio_em_cada_plataforma(): void
    {
        $esperado = [
            'gclid' => 'google', 'fbclid' => 'facebook', 'ttclid' => 'tiktok',
            'msclkid' => 'bing', 'li_fat_id' => 'linkedin', 'yclid' => 'yahoo',
        ];

        foreach ($esperado as $parametro => $rede) {
            $resposta = Atribuidor::classificar(null, null, null, $parametro);
            $this->assertSame('anuncio', $resposta['canal'], "{$parametro} precisa contar como anúncio pago");
            $this->assertTrue($resposta['pago'], "{$parametro} é tráfego pago");
            $this->assertSame($rede, $resposta['rede']);
            $this->assertSame("clique:{$parametro}", $resposta['motivo']);
        }
    }

    public function test_a_marco_do_gestor_de_trafego_vence_o_referer_cru(): void
    {
        // O mesmo acesso com três sinais: a prova mais forte (o clique) decide.
        $resposta = Atribuidor::classificar('facebook', 'cpc', 'google.com', 'fbclid');
        $this->assertSame('anuncio', $resposta['canal']);
        $this->assertSame('facebook', $resposta['rede']);

        // Sem clique, utm_medium manda; sem utm_medium, utm_source; só referer resta o referer.
        $this->assertSame('rede_social', Atribuidor::classificar('instagram', 'social', null)['canal']);
        $this->assertSame('rede_social', Atribuidor::classificar('instagram', null, null)['canal']);
        $this->assertSame('busca_organica', Atribuidor::classificar(null, null, 'google.com')['canal']);
        $this->assertSame('busca_organica', Atribuidor::classificar(null, 'organic', 'google.com')['canal']);
        $this->assertSame('direto', Atribuidor::classificar(null, null, null)['canal']);
    }

    public function test_a_busca_por_ia_e_separada_do_robo_que_varre_a_web(): void
    {
        $humana = Atribuidor::classificar(null, null, null, null, 'Mozilla/5.0 (compatible; ChatGPT-User/1.0)');
        $this->assertSame('busca_ia', $humana['canal']);
        $this->assertSame('chatgpt', $humana['rede']);
        $this->assertFalse(Atribuidor::robo('Mozilla/5.0 (compatible; ChatGPT-User/1.0)') === 'indexador');

        // O robô de índice não é gente e não vira KPI.
        $robo = Atribuidor::classificar(null, null, null, null, 'GPTBot/1.2');
        $this->assertSame('indexador', $robo['canal']);
        $this->assertSame('indexador', Atribuidor::robo('GPTBot/1.2'));
        $this->assertFalse(Atribuidor::eValida('indexador'));

        // A pessoa que pediu ao assistente e clicou no link: o site dela conta, o crawler não.
        $this->assertSame('busca_ia', Atribuidor::classificar('perplexity.ai', 'ai-search', null)['canal']);
        $this->assertSame('busca_ia', Atribuidor::classificar('chatgpt.com', null, null)['canal']);

        // O navegador embutido do WhatsApp é gente, não robô.
        $this->assertNull(Atribuidor::robo('Mozilla/5.0 (Linux; Android 13) WhatsApp/2.23.1.1'));
    }

    public function test_o_aplicativo_social_onde_o_clique_aconteceu_fica_no_registro(): void
    {
        $instagram = Atribuidor::dispositivo('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Instagram');
        $this->assertSame('instagram', $instagram['navegador']);
        $this->assertSame('celular', $instagram['dispositivo']);
        $this->assertSame('ios', $instagram['sistema']);

        $desktop = Atribuidor::dispositivo('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36');
        $this->assertSame('computador', $desktop['dispositivo']);
        $this->assertSame('chrome', $desktop['navegador']);
        $this->assertSame('windows', $desktop['sistema']);
    }

    public function test_o_site_registra_a_visita_e_continua_a_mesma_janela(): void
    {
        $this->get('/?utm_source=instagram&utm_medium=social&utm_campaign=reels-kit')
            ->assertOk();

        $visita = Visit::firstOrFail();
        $this->assertSame('rede_social', $visita->canal);
        $this->assertSame('instagram', $visita->rede);
        $this->assertFalse($visita->pago);
        $this->assertSame('utm_medium social', $visita->motivo);
        $this->assertSame('reels-kit', $visita->utm_campaign);
        $this->assertSame(1, $visita->visualizacoes);
        $this->assertNotEmpty($visita->ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', $visita->ip_hash);

        // Segunda tela do mesmo visitante: continua a visita, não abre outra.
        $id = $visita->visitor_id;
        $this->withCookies([$this->cookie => $id])->get('/planos')->assertOk();

        $this->assertSame(1, Visit::count(), 'Navegar dentro do site não pode abrir visita nova.');
        $this->assertSame(2, Visit::first()->visualizacoes);
    }

    public function test_um_clique_de_outra_origem_abre_visita_nova(): void
    {
        $this->get('/?utm_source=instagram&utm_medium=social')->assertOk();
        $id = Visit::first()->visitor_id;

        // Voltou duas semanas depois, agora por um anúncio do Google: é outra visita.
        Visit::first()->update(['ultima_em' => now()->subDays(14)]);
        $this->withCookies([$this->cookie => $id])->get('/planos?gclid=abc123')->assertOk();

        $this->assertSame(2, Visit::count());
        $nova = Visit::latest('id')->first();
        $this->assertSame('anuncio', $nova->canal);
        $this->assertSame('google', $nova->rede);
        $this->assertTrue($nova->pago);
        $this->assertSame('abc123', $nova->clique_valor);
    }

    public function test_o_referer_do_proprio_site_nao_e_tratado_como_origem(): void
    {
        $this->withServerVariables([
            'HTTP_REFERER' => 'http://localhost/solucoes',
        ])->get('/contato')->assertOk();

        $visita = Visit::firstOrFail();
        $this->assertSame('direto', $visita->canal);
        $this->assertNull($visita->origem_host);
    }

    public function test_o_farol_registra_o_clique_na_jornada_da_visita(): void
    {
        $this->get('/?utm_source=meta&utm_medium=paid-social')->assertOk();
        $visita = Visit::firstOrFail();

        $this->withCookies([$this->cookie => $visita->visitor_id])
            ->postJson('/rastreio/evento', ['tipo' => 'whatsapp', 'path' => '/planos', 'destino' => 'https://wa.me/55000'])
            ->assertNoContent();

        $evento = VisitEvent::latest('id')->firstOrFail();
        $this->assertSame($visita->id, $evento->visit_id);
        $this->assertSame('whatsapp', $evento->tipo);
        $this->assertSame('Abriu o WhatsApp', $evento->rotulo());
        $this->assertSame(1, $visita->fresh()->eventos);
    }

    public function test_o_farol_recusa_o_que_nao_e_clique_medido(): void
    {
        $this->get('/')->assertOk();
        $id = Visit::first()->visitor_id;

        // 'view' já é anotado pelo middleware; aceitar por POST duplicaria a contagem.
        $this->withCookies([$this->cookie => $id])
            ->postJson('/rastreio/evento', ['tipo' => 'view'])
            ->assertStatus(422);

        $this->withCookies([$this->cookie => $id])
            ->postJson('/rastreio/evento', ['tipo' => 'nada_disso'])
            ->assertStatus(422);
    }

    public function test_a_visita_de_robo_entra_no_registro_mas_fora_dos_numeros(): void
    {
        $this->withHeaders(['User-Agent' => 'GPTBot/1.2'])->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/planos')->assertOk();

        $this->assertGreaterThan(0, Visit::count());
        $this->assertSame(0, Visit::pessoas()->count(), 'Robô não pode aparecer em KPI de gente.');
        $this->assertTrue(Visit::first()->bot);
    }

    public function test_o_lead_herda_o_primeiro_e_o_ultimo_toque_da_jornada(): void
    {
        // Conheceu pelo Instagram...
        $this->get('/?utm_source=instagram&utm_medium=social')->assertOk();
        $id = Visit::first()->visitor_id;

        // ...voltou semanas depois por um anúncio do Google...
        Visit::first()->update(['ultima_em' => now()->subDays(10)]);
        $this->withCookies([$this->cookie => $id])->get('/planos?gclid=xyz')->assertOk();

        // ...e cadastrou nessa segunda visita.
        $this->withCookies([$this->cookie => $id])->postJson('/leads', [
            'name' => 'Marcelo Teste',
            'phone' => '11999990000',
            'kit' => 'J16 4G',
            'consent' => '1',
        ])->assertStatus(201);

        $lead = $this->leadCapturado();
        $this->assertSame('anuncio', $lead->canal, 'Último toque é o que fechou.');
        $this->assertSame('google', $lead->rede);
        $this->assertTrue($lead->pago);
        $this->assertSame('rede_social', $lead->canal_primeiro, 'Primeiro toque é como conheceu.');
        $this->assertSame('instagram', $lead->rede_primeiro);
        $this->assertSame(2, $lead->toques);
        $this->assertNotNull($lead->primeiro_toque_em);
        $this->assertStringContainsString('→', $lead->origemResumida(), 'Primeiro e último toque diferentes precisam aparecer juntos.');
    }

    public function test_a_campanha_do_lead_recebe_o_canal_real_da_origem(): void
    {
        $this->get('/?utm_source=google&utm_medium=organic')->assertOk();
        $id = Visit::first()->visitor_id;

        $this->withCookies([$this->cookie => $id])->postJson('/leads', [
            'name' => 'Vindo da busca',
            'phone' => '21988887777',
            'consent' => '1',
        ])->assertStatus(201);

        $lead = $this->leadCapturado();
        $campanha = $lead->campaign;

        $this->assertNotNull($campanha);
        $this->assertSame('busca_organica', $campanha->channel, 'Busca orgânica não é anúncio pago.');
        $this->assertSame('google', $campanha->source);
        $this->assertSame('organic', $campanha->medium);
        $this->assertSame($lead->visit_id, $campanha->leads()->first()->visit_id);
    }

    public function test_a_cadastro_sem_visita_previa_ainda_ganha_origem(): void
    {
        // API, formulários sem cookie ou navegação anônima: o lead não pode ficar cego.
        $this->postJson('/leads', [
            'name' => 'Sem cookie',
            'phone' => '31977776666',
            'consent' => '1',
            'utm_source' => 'youtube',
            'utm_medium' => 'shorts',
        ])->assertStatus(201);

        $lead = $this->leadCapturado();
        $this->assertSame('rede_social', $lead->canal);
        $this->assertSame('youtube', $lead->rede);
        $this->assertSame(1, $lead->toques);
        $this->assertNotNull($lead->visitor_id);
    }

    public function test_o_lead_nao_ganha_o_proprio_site_como_origem(): void
    {
        $this->get('/planos?utm_source=parceiro&utm_medium=referral')->assertOk();
        $id = Visit::first()->visitor_id;

        // Ele navehou do /contato da Golden para o formulário: isso é navegação interna, não origem.
        $this->withCookies([$this->cookie => $id])
            ->withServerVariables(['HTTP_REFERER' => 'http://localhost/contato'])
            ->postJson('/leads', ['name' => 'Interno', 'phone' => '11911112222', 'consent' => '1'])
            ->assertStatus(201);

        $this->assertNull($this->leadCapturado()->referrer, 'O próprio site não pode aparecer como "veio de".');
    }

    public function test_o_painel_de_atribuicao_mostra_a_mesma_regua_das_telas(): void
    {
        $this->get('/?utm_source=instagram&utm_medium=reels')->assertOk();
        $this->withHeaders(['User-Agent' => 'GPTBot/1.2'])->get('/')->assertOk();

        $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail());

        $pagina = $this->get(route('admin.atribuicao.index'))->assertOk();
        $pagina->assertSee('Rede social');
        $pagina->assertSee('Instagram');
        $pagina->assertSee('Robôs à parte');
        $this->assertSame(1, Visit::pessoas()->count());

        // O filtro de canal usa exatamente o vocabulário do config.
        $pagina = $this->get(route('admin.atribuicao.index', ['canal' => 'busca_ia']))->assertOk();
        $pagina->assertSee('Busca por IA');
    }

    public function test_a_jornada_de_uma_visita_aparece_na_ficha(): void
    {
        $this->get('/planos?utm_source=partnershop&gclid=um-clique')->assertOk();
        $visita = Visit::firstOrFail();

        $this->withCookies([$this->cookie => $visita->visitor_id])
            ->postJson('/rastreio/evento', ['tipo' => 'cta', 'path' => '/planos', 'destino' => '/contato'])
            ->assertNoContent();

        $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail());

        $this->get(route('admin.atribuicao.show', $visita))
            ->assertOk()
            ->assertSee('Clicou em uma chamada')
            ->assertSee('/planos');
    }

    public function test_o_link_rastreado_da_campanha_carrega_a_marcao_que_o_painel_le(): void
    {
        $campanha = Campaign::create([
            'name' => 'Reels Kits',
            'source' => 'instagram',
            'medium' => 'reels',
            'content' => 'criativo-azul',
            'channel' => 'rede_social',
        ]);

        $url = $campanha->linkRastreado(url('/planos'));
        $this->assertStringContainsString('utm_source=instagram', $url);
        $this->assertStringContainsString('utm_medium=reels', $url);
        $this->assertStringContainsString('utm_campaign=Reels+Kits', $url);
        $this->assertStringContainsString('utm_content=criativo-azul', $url);

        // O link gerado pela própria campanha volta para a campanha quando alguém clica.
        $request = Request::create($url, 'GET');
        $sinais = Atribuidor::sinais($request);
        $resposta = Atribuidor::classificar($sinais['utm_source'], $sinais['utm_medium'], $sinais['origem_host']);
        $this->assertSame('rede_social', $resposta['canal']);
        $this->assertSame('instagram', $resposta['rede']);
    }

    public function test_o_registro_nao_estoura_os_limites_de_tamanho_do_banco(): void
    {
        $largo = str_repeat('m', 400);

        $this->get("/planos?utm_campaign={$largo}&utm_source={$largo}&utm_medium=reels")->assertOk();

        $visita = Visit::firstOrFail();
        $this->assertLessThanOrEqual(190, strlen((string) $visita->utm_campaign));
        $this->assertLessThanOrEqual(120, strlen((string) $visita->utm_source));
        $this->assertLessThanOrEqual(190, strlen((string) $visita->landing_path));
        // A rede pode cair no utm_source cru; o MySQL não perdoa os 40 caracteres da coluna.
        $this->assertLessThanOrEqual(40, strlen((string) $visita->rede));
        $this->assertLessThanOrEqual(40, strlen((string) $visita->motivo));

        // Um referer de parceiro inventado (o cabeçalho é do cliente, não nosso) também não
        // pode estourar: o host tem 120 e o path 190 no banco.
        $this->withHeaders([
            'referer' => 'http://'.str_repeat('p', 200).'.parceiro.example/'.str_repeat('c', 300),
        ])->get('/contato?utm_campaign=parceiro')->assertOk();

        $vindaDeFora = Visit::latest('id')->firstOrFail();
        $this->assertSame('parceiro', $vindaDeFora->canal);
        $this->assertNotNull($vindaDeFora->origem_host, 'O referer externo precisa chegar na coluna, senão o teste não prova nada.');
        $this->assertLessThanOrEqual(120, strlen((string) $vindaDeFora->origem_host));
        $this->assertLessThanOrEqual(190, strlen((string) $vindaDeFora->origem_path));
        $this->assertLessThanOrEqual(40, strlen((string) $vindaDeFora->rede));

        $this->withCookies([$this->cookie => $visita->visitor_id])
            ->postJson('/rastreio/evento', ['tipo' => 'cta', 'path' => '/'.str_repeat('p', 300), 'destino' => $largo])
            ->assertNoContent();

        $evento = VisitEvent::latest('id')->firstOrFail();
        $this->assertLessThanOrEqual(190, strlen((string) $evento->path));
        $this->assertLessThanOrEqual(255, strlen((string) $evento->destino));
    }

    public function test_o_teto_de_eventos_por_visita_protege_a_tabela(): void
    {
        config(['golden.rastreio.max_eventos' => 3]);

        $this->get('/')->assertOk();
        $visita = Visit::firstOrFail();

        $pedido = Request::create('/planos', 'GET');
        $pedido->cookies->set($this->cookie, $visita->visitor_id);

        for ($i = 0; $i < 6; $i++) {
            (new Rastreio)->registrarEvento($pedido, 'cta', '/planos', null, null);
        }

        // A cota é dos cliques medidos; a tela que passou tem contador próprio.
        $this->assertSame(3, $visita->fresh()->eventos);
        $this->assertSame(3, VisitEvent::where('tipo', 'cta')->count());
    }

    public function test_a_poda_apaga_o_que_passou_da_retencao(): void
    {
        $this->get('/?utm_source=google&utm_medium=organic')->assertOk();
        $velha = Visit::firstOrFail();
        $this->withCookies([$this->cookie => $velha->visitor_id])
            ->postJson('/rastreio/evento', ['tipo' => 'cta', 'path' => '/planos'])
            ->assertNoContent();

        $this->get('/contato?utm_source=instagram&utm_medium=reels')->assertOk();
        $nova = Visit::latest('id')->firstOrFail();
        $velha->update(['aberta_em' => now()->subDays(400), 'ultima_em' => now()->subDays(400)]);

        $this->artisan('rastreio:podar')->assertSuccessful();

        $this->assertSame(1, Visit::count(), 'A visita fora da retenção tinha de sair.');
        $this->assertSame($nova->id, Visit::firstOrFail()->id);
        $this->assertSame(0, VisitEvent::where('visit_id', $velha->id)->count(), 'Os eventos não podem ficar órfãos.');
    }

    /**
     * O seed traz leads de demonstração, então "o lead deste teste" é sempre o mais
     * recente — nunca o primeiro da tabela.
     */
    private function leadCapturado(): Lead
    {
        return Lead::latest('id')->firstOrFail();
    }
}
