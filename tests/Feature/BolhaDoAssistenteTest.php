<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\User;
use App\Services\Assistente\Motor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A bolha de atendimento no site e na página de funil, do botão à fila da equipe.
 *
 * O que estes testes fecham é a régua que foi combinada antes de escrever uma linha:
 *
 * 1. **A tela não é a barreira.** Botão, folha de estilo, script e endpoints respondem à mesma
 *    pergunta (`Assistente::atendeVisitante`), e o servidor repete a pergunta antes de gastar
 *    uma chamada de IA. Desligada a ficha, o peso some da página e os três endereços recusam.
 * 2. **Indexador não conversa.** Robô de busca não ganha botão nem abre conversa, porque
 *    atendimento que ninguém leu enche a fila do painel de mentira.
 * 3. **Uma conversa por visitante, anônima até o cadastro.** A conversa vive na sessão; o
 *    cadastro continua sendo do formulário que a casa já tem, e é o `LeadService` que amarra as
 *    duas — não existe segundo formulário dentro do balão.
 * 4. **Sem fonte, a máquina chama a equipe** em vez de inventar, e a resposta da equipe chega ao
 *    cliente pela varredura.
 */
class BolhaDoAssistenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_a_bolha_sobe_na_home_com_a_folha_o_script_e_o_canal_certo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('css/golden-assistente.css?v=', $html);
        $this->assertStringContainsString('js/golden-assistente.js?v=', $html);
        $this->assertStringContainsString('id="iaFloat"', $html);
        $this->assertStringContainsString('id="iaPainel"', $html);
        $this->assertStringContainsString('Ana', $html);

        $rosto = $this->rostoDaBolha($html);
        $this->assertSame('site', $rosto['canal']);
        $this->assertSame('/assistente/abrir', parse_url($rosto['abrir'], PHP_URL_PATH));
        $this->assertSame('/assistente/fala', parse_url($rosto['fala'], PHP_URL_PATH));
        $this->assertSame('/assistente/falas', parse_url($rosto['falas'], PHP_URL_PATH));
    }

    /** Ficha desligada: nem o botão, nem a folha, nem o script — a página não paga por nada. */
    public function test_a_ficha_desligada_tira_o_botao_a_folha_e_o_script_da_pagina(): void
    {
        Assistente::garanta()->update(['ativo' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['iaFloat', 'golden-assistente.css', 'golden-assistente.js', 'GOLDEN_IA'] as $sobras) {
            $this->assertStringNotContainsString($sobras, $html, "A bolha desligada ainda deixou {$sobras} na página.");
        }
    }

    public function test_o_funil_anuncia_o_canal_funil_e_o_interruptor_dele_manda_sozinho(): void
    {
        $funil = $this->get('/c/frota-j16')->assertOk()->getContent();
        $this->assertSame('funil', $this->rostoDaBolha($funil)['canal']);

        Assistente::garanta()->update(['no_funil' => false]);

        $this->assertStringNotContainsString('iaFloat', $this->get('/c/frota-j16')->assertOk()->getContent());
        $this->assertStringContainsString('iaFloat', $this->get('/')->assertOk()->getContent(),
            'Desligar o funil também derrubou a bolha do site.');
    }

    public function test_um_indexador_nao_recebe_o_botao(): void
    {
        $html = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('iaFloat', $html);
        $this->assertStringNotContainsString('golden-assistente.js', $html);
    }

    /** O mesmo user-agent que não ganha botão tampoco ganha conversa: o servidor é a barreira. */
    public function test_um_indexador_nao_abre_conversa_pelo_endpoint(): void
    {
        $this->withHeaders(['User-Agent' => 'curl/8.0'])
            ->postJson('/assistente/abrir')
            ->assertOk()
            ->assertJson(['ativo' => false]);

        $this->assertSame(0, Conversa::count());
    }

    public function test_a_bolha_abre_a_conversa_na_sessao_e_sauda_uma_unica_vez(): void
    {
        $abertura = $this->postJson('/assistente/abrir', ['pagina' => '/planos'])
            ->assertOk()
            ->assertJson(['ativo' => true, 'status' => 'aberta']);

        $conversa = Conversa::findOrFail((int) $abertura->json('conversa'));
        $this->assertSame('site', $conversa->canal);
        $this->assertSame('/planos', $conversa->pagina);
        $this->assertCount(1, $conversa->mensagens);
        $this->assertSame('assistente', $conversa->mensagens()->first()->autor);

        // Voltar à página não repete o "olá" na tela de quem já está no meio da conversa.
        $this->postJson('/assistente/abrir')->assertOk();
        $this->assertSame(1, Conversa::count(), 'A bolha abriu uma conversa nova na segunda visita.');
        $this->assertSame(1, ConversaMensagem::count(), 'A saudação se repetiu na mesma conversa.');
    }

    public function test_a_ficha_desligada_recusa_os_tres_enderecos(): void
    {
        Assistente::garanta()->update(['ativo' => false]);

        $this->postJson('/assistente/abrir')->assertOk()->assertJson(['ativo' => false]);
        $this->postJson('/assistente/fala', ['texto' => 'Oi, tem planos?'])->assertStatus(403)->assertJson(['ativo' => false]);
        $this->getJson('/assistente/falas')->assertOk()->assertJson(['ativo' => false]);

        $this->assertSame(0, Conversa::count());
    }

    /** Sem nada na base que sustente a resposta, a máquina se cala e a equipe entra. */
    public function test_a_pergunta_sem_fonte_escalou_para_a_equipe_em_vez_de_inventar(): void
    {
        config(['assistente.chaves.gemini' => null, 'assistente.chaves.openai' => null]);

        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Vocês instalam em motocicletas?'])
            ->assertOk()
            ->assertJson(['status' => 'aguardando_equipe', 'ativo' => true]);

        $conversa = Conversa::firstOrFail();
        $this->assertSame('sem_fonte', $conversa->escala_motivo);

        $falas = $resposta->json('falas');
        $this->assertCount(2, $falas);
        $this->assertSame('cliente', $falas[0]['autor']);
        $this->assertSame('assistente', $falas[1]['autor']);
        $this->assertSame(Motor::fraseDeEscalada(), $falas[1]['texto']);
    }

    /** Com fonte e provedor, a resposta vem da máquina e a fonte fica gravada junto dela. */
    public function test_a_pergunta_com_fonte_recebe_a_resposta_do_provedor_com_a_prova_gravada(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'A instalação leva cerca de 40 minutos.']]]]],
            'usageMetadata' => ['promptTokenCount' => 180, 'candidatesTokenCount' => 12],
        ], 200)]);

        ConhecimentoItem::create([
            'titulo' => 'Tempo de instalação',
            'corpo' => 'A instalação do rastreador leva 40 minutos e é feita no endereço do cliente.',
            'tags' => 'instalacao, tempo',
            'origem' => 'painel',
            'ativo' => '1',
        ]);

        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Quanto tempo leva a instalação?'])
            ->assertOk()
            ->assertJson(['status' => 'aberta']);

        Http::assertSentCount(1);

        $fala = ConversaMensagem::where('autor', 'assistente')->latest('id')->firstOrFail();
        $this->assertSame('gemini', $fala->provedor);
        $this->assertSame('A instalação leva cerca de 40 minutos.', $fala->corpo);
        $this->assertSame(180, $fala->tokens_entrada);
        $this->assertSame('Tempo de instalação', $fala->fontes()[0]['titulo']);

        $this->assertSame('A instalação leva cerca de 40 minutos.', $resposta->json('falas.1.texto'));
        $this->assertNull(Conversa::firstOrFail()->escala_motivo);
    }

    public function test_a_mensagem_longa_demais_e_recusada_antes_de_gastar_uma_chamada_de_ia(): void
    {
        $this->postJson('/assistente/fala', ['texto' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('texto');

        $this->assertSame(0, Conversa::count(), 'A recusa abriu conversa antes de validar a fala.');
    }

    /** É pela varredura que a resposta escrita no painel chega ao cliente anônimo. */
    public function test_a_resposta_da_equipe_chega_na_varredura_da_bolha(): void
    {
        $conversa = Conversa::abrir('site');
        ConversaMensagem::create([
            'conversa_id' => $conversa->id, 'autor' => 'cliente', 'tipo' => 'texto',
            'corpo' => 'Meu rastreador parou de atualizar.',
        ]);

        $this->session([Conversa::SESSAO => $conversa->id]);
        $ultima = ConversaMensagem::latest('id')->firstOrFail();

        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.assistente.conversas.responder', $conversa), ['corpo' => 'Vou verificar agora, só um instantinho.'])
            ->assertSessionHasNoErrors();

        $varredura = $this->getJson('/assistente/falas?de='.$ultima->id)
            ->assertOk()
            ->assertJson(['ativo' => true, 'conversa' => $conversa->id]);

        $falas = $varredura->json('falas');
        $this->assertCount(1, $falas);
        $this->assertSame('equipe', $falas[0]['autor']);
        $this->assertSame($admin->name, $falas[0]['assinatura']);
        $this->assertStringContainsString('verificar agora', $falas[0]['texto']);
    }

    public function test_a_varredura_sem_conversa_na_sessao_nao_mostra_nada(): void
    {
        $conversa = Conversa::abrir('site');
        ConversaMensagem::create([
            'conversa_id' => $conversa->id, 'autor' => 'cliente', 'tipo' => 'texto', 'corpo' => 'Alô?',
        ]);

        // Outro visitante, mesma conversa no banco: sem a sessão, não há conversa para mostrar.
        $this->getJson('/assistente/falas')->assertOk()->assertJson(['ativo' => false]);
    }

    /**
     * A relação de falas chega ordenada da mais antiga, e é assim que o histórico se pinta. Um
     * `latest()` por cima não desfaz aquele primeiro critério — só empata. Separadas no tempo, as
     * falas mostravam ao assistente a saudação como "última palavra do cliente", e ele parava de
     * responder sem erro nenhum na tela.
     */
    public function test_a_fala_que_chega_depois_da_saudacao_e_a_que_o_assistente_responde(): void
    {
        $conversa = Conversa::abrir('site');
        $this->session([Conversa::SESSAO => $conversa->id]);

        $this->postJson('/assistente/abrir')->assertOk();

        $this->travel(2)->minutes();
        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Qual o preço do rastreador?'])->assertOk();

        // Voltam só as falas novas — a do cliente e a da máquina — mas são três no banco: sem a
        // última fala certa, a resposta da máquina simplesmente não acontece.
        $this->assertCount(2, $resposta->json('falas'));
        $this->assertSame('assistente', $resposta->json('falas.1.autor'));
        $this->assertSame(3, $conversa->mensagens()->count());
        $this->assertSame('aguardando_equipe', $conversa->refresh()->status);

        $this->travel(2)->minutes();
        ConversaMensagem::create([
            'conversa_id' => $conversa->id, 'autor' => 'equipe', 'tipo' => 'texto', 'corpo' => 'Já vejo, um instantinho.',
        ]);

        $this->assertSame('equipe', $conversa->ultimaMensagem()->autor,
            '`ultimaMensagem()` devolveu a fala mais antiga, não a que chegou depois.');
    }

    /** O cadastro é do formulário da casa — e é ele que vira a conversa em negócio. */
    public function test_o_cadastro_pelo_formulario_da_casa_amarra_a_conversa_ao_lead(): void
    {
        $conversa = Conversa::abrir('site');
        $this->session([Conversa::SESSAO => $conversa->id]);

        $this->post('/leads', [
            'name' => 'Maria Aparecida',
            'phone' => '(11) 99999-8888',
            'message' => 'A Ana me explicou os planos no chat.',
            'consent' => '1',
        ])->assertSessionHasNoErrors();

        $conversa->refresh();
        $this->assertNotNull($conversa->lead_id);
        $this->assertSame('Maria Aparecida', $conversa->nome);

        $sistema = ConversaMensagem::where('autor', 'sistema')->latest('id')->firstOrFail();
        $this->assertStringContainsString('O cadastro virou o lead '.$conversa->lead_id, $sistema->corpo);
    }

    public function test_o_contato_recebe_o_resumo_da_conversa_pela_url(): void
    {
        $resumo = 'Eu: quero rastrear dois caminhões. / Ana: tenho três planos.';

        $this->get('/contato?message='.urlencode($resumo))
            ->assertOk()
            ->assertSee('quero rastrear dois caminhões', false)
            ->assertSee('data-lead-form', false);
    }

    /**
     * O JSON que a parcial entrega ao script — é o contrato entre os dois lados da bolha, e um
     * endereço errado aqui é um botão mudo na tela do cliente.
     */
    private function rostoDaBolha(string $html): array
    {
        $this->assertMatchesRegularExpression('#window\.GOLDEN_IA = \{.*?\};#s', $html);
        preg_match('#window\.GOLDEN_IA = (\{.*?\});#s', $html, $achados);

        $rosto = json_decode($achados[1], true);
        $this->assertIsArray($rosto, 'O JSON da bolha não é legível para o script.');

        return $rosto;
    }
}
