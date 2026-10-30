<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Assistente\Midia;
use App\Services\Assistente\Motor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_o_assistente_entra_como_atendente_da_caixa_do_whatsapp(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('css/golden-assistente.css?v=', $html);
        $this->assertStringContainsString('js/golden-assistente.js?v=', $html);
        $this->assertStringContainsString('id="waPopup"', $html);
        $this->assertStringContainsString('class="wa-channel wa-channel-ia"', $html);
        $this->assertStringContainsString('data-ia-abrir', $html);
        $this->assertStringContainsString('id="iaPainel"', $html);

        // O robô é o primeiro atendente da lista: entra antes de qualquer canal do WhatsApp.
        $this->assertGreaterThan(strpos($html, 'class="wa-body"'), strpos($html, 'data-ia-abrir'));
        $this->assertLessThan(
            strpos($html, 'data-cta="canal_emergencia"'),
            strpos($html, 'data-ia-abrir'),
            'O assistente ficou depois dos canais do WhatsApp na caixa.'
        );

        $rosto = $this->rostoDaBolha($html);
        $this->assertSame('site', $rosto['canal']);
        $this->assertSame('/assistente/abrir', parse_url($rosto['abrir'], PHP_URL_PATH));
        $this->assertSame('/assistente/fala', parse_url($rosto['fala'], PHP_URL_PATH));
        $this->assertSame('/assistente/falas', parse_url($rosto['falas'], PHP_URL_PATH));
    }

    /** A ficha que ninguém editou não deixa texto solto no HTML: tudo vem do padrão da casa. */
    public function test_o_atendente_mostra_os_textos_da_casa_quando_a_ficha_nao_escreveu_nenhum(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Falar com Ana"', $html);
        $this->assertStringContainsString('<b>Ana</b>', $html);
        $this->assertStringContainsString(config('assistente.persona.cargo'), $html);
        $this->assertStringContainsString('Online agora', $html);
        $this->assertStringContainsString('placeholder="Escreva sua mensagem…"', $html);
        $this->assertStringContainsString('Prefiro falar com uma pessoa', $html);
        $this->assertStringContainsString('fa-headset', $html);
    }

    /** O painel escreve na ficha e o atendente da caixa obedece — inclusive o ícone sem rosto. */
    public function test_os_textos_e_o_icone_do_atendente_vem_da_ficha_editada_no_painel(): void
    {
        Assistente::garanta()->update([
            'nome' => 'Bia',
            'cargo' => 'Consultora de vendas',
            'dica' => 'Conversar com :nome',
            'status' => 'Online agora',
            'placeholder' => 'No que posso ajudar?',
            'humano' => 'Quero um humano',
            'icone' => 'fa-headset',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Conversar com Bia"', $html);
        $this->assertStringContainsString('<b>Bia</b>', $html);
        $this->assertStringContainsString('Consultora de vendas', $html);
        $this->assertStringContainsString('Online agora', $html);
        $this->assertStringContainsString('placeholder="No que posso ajudar?"', $html);
        $this->assertStringContainsString('>Quero um humano</a>', $html);
        $this->assertStringContainsString('fa-headset', $html);
    }

    /** Sem nenhum canal de WhatsApp, o mesmo botão passa a ser a porta do assistente. */
    public function test_sem_canal_de_whatsapp_o_botao_do_canto_vira_a_porta_do_assistente(): void
    {
        foreach (config('golden.whatsapp_box.canais') as $canal) {
            Setting::set('whatsapp_canal_'.$canal['id'].'_ativo', '0');
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="waPopup"', $html);
        $this->assertStringContainsString('wa-float-ia', $html);
        $this->assertStringContainsString('data-ia-abrir', $html);
        $this->assertStringContainsString('title="Falar com Ana"', $html);
        $this->assertStringContainsString('id="iaPainel"', $html);
    }

    /** Caixa desligada no painel: volta o link direto do WhatsApp e o robô perde a porta. */
    public function test_a_caixa_desligada_devolve_o_link_direto_e_tira_o_assistente_da_pagina(): void
    {
        Setting::set('whatsapp_box_ativo', '0');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-cta="float"', $html);

        foreach (['data-ia-abrir', 'golden-assistente.css', 'golden-assistente.js', 'GOLDEN_IA'] as $sobras) {
            $this->assertStringNotContainsString($sobras, $html, "A caixa desligada ainda deixou {$sobras} na página.");
        }
    }

    /** Ficha desligada: nem o atendente, nem a folha, nem o script — a caixa segue só com o WhatsApp. */
    public function test_a_ficha_desligada_tira_o_atendente_a_folha_e_o_script_da_pagina(): void
    {
        Assistente::garanta()->update(['ativo' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="waPopup"', $html);

        foreach (['data-ia-abrir', 'golden-assistente.css', 'golden-assistente.js', 'GOLDEN_IA'] as $sobras) {
            $this->assertStringNotContainsString($sobras, $html, "A ficha desligada ainda deixou {$sobras} na página.");
        }
    }

    public function test_o_funil_anuncia_o_canal_funil_e_o_interruptor_dele_manda_sozinho(): void
    {
        $funil = $this->get('/c/frota-j16')->assertOk()->getContent();
        $this->assertSame('funil', $this->rostoDaBolha($funil)['canal']);

        Assistente::garanta()->update(['no_funil' => false]);

        $this->assertStringNotContainsString('data-ia-abrir', $this->get('/c/frota-j16')->assertOk()->getContent());
        $this->assertStringContainsString('data-ia-abrir', $this->get('/')->assertOk()->getContent(),
            'Desligar o funil também derrubou o atendente do site.');
    }

    public function test_um_indexador_nao_recebe_o_atendente(): void
    {
        $html = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-ia-abrir', $html);
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

    /** A abertura são duas falas: a saudação da casa e, logo depois, a pergunta pelo nome. */
    public function test_a_bolha_abre_a_conversa_na_sessao_e_sauda_uma_unica_vez(): void
    {
        $abertura = $this->postJson('/assistente/abrir', ['pagina' => '/planos'])
            ->assertOk()
            ->assertJson(['ativo' => true, 'status' => 'aberta']);

        $conversa = Conversa::findOrFail((int) $abertura->json('conversa'));
        $this->assertSame('site', $conversa->canal);
        $this->assertSame('/planos', $conversa->pagina);
        $this->assertCount(2, $conversa->mensagens);
        $this->assertSame('assistente', $conversa->mensagens()->first()->autor);
        $this->assertSame(Assistente::paraABolha()->textoPerguntaNome(), $conversa->mensagens()->latest('id')->firstOrFail()->corpo);
        $this->assertSame(1, $conversa->refresh()->nome_pedidos);

        // Voltar à página não repete o "olá" na tela de quem já está no meio da conversa.
        $this->postJson('/assistente/abrir')->assertOk();
        $this->assertSame(1, Conversa::count(), 'A bolha abriu uma conversa nova na segunda visita.');
        $this->assertSame(2, ConversaMensagem::count(), 'A abertura se repetiu na mesma conversa.');
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

    /** Cortesia não pergunta fato: o provedor responde mesmo sem fonte e a conversa não escala. */
    public function test_a_saudacao_e_respondida_sem_fonte_e_nao_escala(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Oi! Tudo bem por aqui. Como posso ajudar você hoje?']]]]],
        ], 200)]);

        $this->postJson('/assistente/fala', ['texto' => 'Oi, tudo bem?'])
            ->assertOk()
            ->assertJson(['status' => 'aberta', 'ativo' => true]);

        Http::assertSentCount(1);
        $this->assertNull(Conversa::firstOrFail()->escala_motivo);

        // A ordem levada ao modelo diz que é saudação: sem isso, a regra "sem fonte, chame a
        // equipe" da persona voltaria a valer e a resposta seria a frase de escalada.
        Http::assertSent(fn ($pedido): bool => str_contains(
            (string) data_get($pedido->data(), 'systemInstruction.parts.0.text'),
            'saudação'
        ));
    }

    /** "Boa tarde" de quem chega é saudação: o modelo responde, a conversa não se despede. */
    public function test_a_saudacao_de_quem_chega_nao_fecha_o_atendimento(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Boa tarde! Como posso ajudar você hoje?']]]]],
        ], 200)]);

        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Boa tarde'])
            ->assertOk()
            ->assertJson(['status' => 'aberta']);

        Http::assertSentCount(1);

        $this->assertNull(Conversa::firstOrFail()->avaliacao_pedida_em, 'Quem chegou recebeu pedido de nota.');
        $this->assertSame(
            'Boa tarde! Como posso ajudar você hoje?',
            collect($resposta->json('falas'))->last()['texto']
        );
    }

    /** Saudação na frente da pergunta não é adeus: a resposta do modelo chega normalmente. */
    public function test_a_saudacao_com_pergunta_atras_nao_e_tratada_como_despedida(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'O rastreador tem o valor publicado na loja; te passo a proposta.']]]]],
        ], 200)]);

        ConhecimentoItem::create([
            'titulo' => 'Preço do rastreador',
            'corpo' => 'O valor do rastreador está publicado na loja; a proposta sai com a instalação inclusa.',
            'tags' => 'rastreador, preco, valor',
            'origem' => 'painel',
            'ativo' => '1',
        ]);

        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Boa tarde, quero saber o preço do rastreador'])
            ->assertOk()
            ->assertJson(['status' => 'aberta']);

        Http::assertSentCount(1);
        $this->assertSame(
            'O rastreador tem o valor publicado na loja; te passo a proposta.',
            collect($resposta->json('falas'))->last()['texto']
        );
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

        // Voltam só as falas novas — a do cliente e a da máquina — mas são quatro no banco (saudação,
        // pergunta pelo nome, pergunta e resposta): sem a última fala certa, a resposta da máquina
        // simplesmente não acontece.
        $this->assertCount(2, $resposta->json('falas'));
        $this->assertSame('assistente', $resposta->json('falas.1.autor'));
        $this->assertSame(4, $conversa->mensagens()->count());
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
     * O cliente encerra a conversa, e o encerramento respeita a hora da pesquisa.
     *
     * A nota é pedida **antes** do fechamento porque a pesquisa só nasce enquanto a conversa ainda
     * é da máquina — fechar primeiro seria enterrar o único atendimento que a casa tinha para
     * avaliar. Depois do fim, a conversa fica na sessão para a tela ler o histórico e receber a nota
     * das estrelas, mas não aceita fala nova: quem escrever numa aba antiga não reabre o chat.
     */
    public function test_o_cliente_encerra_a_conversa_com_a_pesquisa_antes_do_fim(): void
    {
        $this->postJson('/assistente/abrir')->assertOk();
        $conversa = Conversa::firstOrFail();

        // O nome fecha a abertura e dá à casa a terceira fala da máquina — o piso da pesquisa.
        $this->postJson('/assistente/fala', ['texto' => 'Maria Compradora'])->assertOk();

        $resposta = $this->postJson('/assistente/encerrar')
            ->assertOk()
            ->assertJson(['ativo' => true, 'encerrada' => true, 'status' => 'fechada', 'avaliacao' => true]);

        $conversa->refresh();
        $this->assertSame('fechada', $conversa->status);
        $this->assertNotNull($conversa->resolvido_em);
        $this->assertNotNull($conversa->avaliacao_pedida_em);
        $this->assertNull($conversa->avaliacao_nota);

        $falas = $resposta->json('falas');
        $this->assertCount(2, $falas, 'O fim não devolveu a despedida e a fala de sistema.');
        $this->assertSame('assistente', $falas[0]['autor']);
        $this->assertSame(config('assistente.persona.pedido_avaliacao'), (string) $falas[0]['texto']);
        $this->assertSame('sistema', $falas[1]['autor']);
        $this->assertSame('Conversa encerrada pelo cliente.', (string) $falas[1]['texto']);

        // Escrita depois do fim não entra, e a conversa continua fechada.
        $depois = ConversaMensagem::count();
        $this->postJson('/assistente/fala', ['texto' => 'oi, ainda está aí?'])
            ->assertOk()
            ->assertJson(['encerrada' => true, 'falas' => []]);
        $this->assertSame($depois, ConversaMensagem::count(), 'A conversa fechada aceitou uma fala nova.');

        // A nota das estrelas ainda entra: a nota é o que o fim veio colher.
        $this->postJson('/assistente/avaliar', ['nota' => 5])
            ->assertOk()
            ->assertJson(['ativo' => true, 'avaliacao' => false]);
        $this->assertSame(5, (int) $conversa->refresh()->avaliacao_nota);
    }

    /** Depois do fim, "começar outra" abre uma conversa nova — a encerrada fica no histórico. */
    public function test_depois_de_encerrada_a_bolha_comeca_uma_conversa_nova(): void
    {
        $this->postJson('/assistente/abrir')->assertOk();
        $encerrada = Conversa::firstOrFail();
        $this->postJson('/assistente/fala', ['texto' => 'Maria Compradora'])->assertOk();
        $this->postJson('/assistente/encerrar')->assertOk()->assertJson(['encerrada' => true]);

        $resposta = $this->postJson('/assistente/nova')
            ->assertOk()
            ->assertJson(['ativo' => true, 'encerrada' => false, 'status' => 'aberta']);

        $nova = Conversa::findOrFail((int) $resposta->json('conversa'));
        $this->assertNotSame($encerrada->id, $nova->id, 'O recomeço reaproveitou a conversa encerrada.');
        $this->assertCount(2, $nova->mensagens, 'A conversa nova não abriu com saudação e pedido de nome.');
        $this->assertSame(Assistente::paraABolha()->saudacao, $nova->mensagens()->first()->corpo);
        $this->assertSame('fechada', $encerrada->refresh()->status, 'O recomeço reabriu a conversa encerrada.');
        $this->assertSame($nova->id, (int) session(Conversa::SESSAO));
    }

    /** Desligado o documento na tela de chaves, o arquivo é recusado com frase — e sem gastar IA. */
    public function test_o_documento_nao_entra_com_a_permissao_desligada(): void
    {
        config(['assistente.permissoes.documento' => false]);
        Http::fake();
        $this->postJson('/assistente/abrir')->assertOk();

        $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('arquivo');

        $this->assertSame(0, ConversaMensagem::where('tipo', 'arquivo')->count());
        Http::assertNothingSent();
    }

    /** Extensão fora da lista também é recusa da casa, com o nome do formato que falta. */
    public function test_o_arquivo_fora_dos_formatos_da_casa_nao_chega_ao_provedor(): void
    {
        config(['assistente.permissoes.documento' => true]);
        Http::fake();
        $this->postJson('/assistente/abrir')->assertOk();

        $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->create('programa.exe', 40, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('arquivo');

        $this->assertSame(0, ConversaMensagem::where('tipo', 'arquivo')->count());
        Http::assertNothingSent();
    }

    /**
     * Liberado o documento, o arquivo entra na conversa com nome e caminho, e o provedor o lê na
     * capacidade certa — a legenda é a pergunta, e é por ela que a resposta existe.
     */
    public function test_o_documento_e_lido_pelo_provedor_com_a_legenda_como_pergunta(): void
    {
        Storage::fake('public');
        config([
            'assistente.permissoes.documento' => true,
            'assistente.chaves.gemini' => 'chave-de-teste',
            'assistente.chaves.openai' => null,
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'A nota está em aberto, com vencimento em 10/10.']]]]],
            'usageMetadata' => ['promptTokenCount' => 900, 'candidatesTokenCount' => 18],
        ], 200)]);

        $resposta = $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->create('nota-fiscal.pdf', 80, 'application/pdf'),
            'texto' => 'essa nota está paga?',
        ])->assertOk()->assertJson(['ativo' => true, 'status' => 'aberta']);

        $conversa = Conversa::firstOrFail();
        $enviada = $conversa->mensagens()->where('tipo', 'arquivo')->firstOrFail();
        $this->assertSame('essa nota está paga?', $enviada->corpo);
        $this->assertSame('nota-fiscal.pdf', $enviada->midia_nome);
        $this->assertStringStartsWith('assistente/', (string) $enviada->midia);
        Storage::disk('public')->assertExists((string) $enviada->midia);

        $falas = $resposta->json('falas');
        $this->assertCount(2, $falas);
        $this->assertSame('arquivo', $falas[0]['tipo']);
        $this->assertSame('nota-fiscal.pdf', $falas[0]['midia_nome']);
        $this->assertSame('assistente', $falas[1]['autor']);
        $this->assertSame('A nota está em aberto, com vencimento em 10/10.', (string) $falas[1]['texto']);

        Http::assertSent(function ($pedido): bool {
            $partes = $this->partesDoPedido($pedido);

            return in_array('application/pdf', array_column($partes, 'mime'), true)
                && str_contains($this->textoDoPedido($partes), 'essa nota está paga?');
        });

        // O arquivo é a fonte da resposta: o modelo não pode completar o que falta inventando
        // cláusula, valor ou prazo que ninguém escreveu no documento.
        Http::assertSent(fn ($pedido): bool => str_contains(
            (string) data_get($pedido->data(), 'systemInstruction.parts.0.text', ''),
            'tem que sair do que o documento enviado diz'
        ));
    }

    /**
     * Texto puro não vai como anexo: o Gemini ignora `text/plain` em `inlineData` e responde como
     * se nenhum arquivo tivesse chegado — o que fazia o modelo completar um contrato com cláusulas
     * que ninguém escreveu. O conteúdo lido do disco entra na chamada como texto.
     */
    public function test_o_documento_de_texto_vai_ao_provedor_como_texto_e_nao_como_anexo(): void
    {
        Storage::fake('public');
        config([
            'assistente.permissoes.documento' => true,
            'assistente.chaves.gemini' => 'chave-de-teste',
            'assistente.chaves.openai' => null,
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'O contrato vence em 10/10/2027 e a multa é de 30%.']]]]],
        ], 200)]);

        $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->createWithContent(
                'contrato.txt',
                "CONTRATO DE PRESTAÇÃO DE SERVIÇOS\nVigência: 10/10/2027\nMulta por rescisão: 30% do valor restante."
            ),
            'texto' => 'quando vence e qual é a multa?',
        ])->assertOk()->assertJson(['ativo' => true, 'status' => 'aberta']);

        Http::assertSent(function ($pedido): bool {
            $partes = $this->partesDoPedido($pedido);

            return str_contains($this->textoDoPedido($partes), 'Multa por rescisão: 30% do valor restante.')
                && str_contains($this->textoDoPedido($partes), 'contrato.txt')
                && array_filter($partes, fn (array $parte): bool => $parte['mime'] === 'text/plain') === [];
        });
    }

    /** O `.docx` guarda o texto num XML dentro do pacote — e é de lá que ele é lido. */
    public function test_o_docx_e_lido_pelo_texto_de_dentro_do_pacote(): void
    {
        Storage::fake('public');

        $caminho = 'assistente/2026/10/contrato.docx';
        Storage::disk('public')->makeDirectory('assistente/2026/10');

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('public')->path($caminho), \ZipArchive::CREATE));
        $zip->addFromString('word/document.xml',
            '<w:document xmlns:w="urn:w"><w:body><w:p><w:r><w:t>Cláusula 1</w:t></w:r><w:r><w:t> Objeto do contrato.</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $this->assertSame('Cláusula 1 Objeto do contrato.', Midia::textoDe([
            'caminho' => $caminho,
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'nome' => 'contrato.docx',
        ]));
    }

    /** A imagem segue a mesma porta, com a capacidade de visão e o arquivo em `inlineData`. */
    public function test_a_imagem_e_lida_pelo_provedor_de_visao(): void
    {
        Storage::fake('public');
        config(['assistente.chaves.gemini' => 'chave-de-teste', 'assistente.chaves.openai' => null]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Vejo um rastreador instalado no painel do carro.']]]]],
        ], 200)]);

        $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->create('instalacao.png', 60, 'image/png'),
        ])->assertOk()->assertJson(['ativo' => true, 'status' => 'aberta']);

        $conversa = Conversa::firstOrFail();
        $enviada = $conversa->mensagens()->where('tipo', 'imagem')->firstOrFail();
        $this->assertSame('instalacao.png', $enviada->midia_nome);
        $this->assertNull($enviada->corpo, 'A legenda vazia virou texto na fala do cliente.');

        $this->assertSame('Vejo um rastreador instalado no painel do carro.', (string) $conversa->ultimaMensagem()->corpo);
        Http::assertSent(fn ($pedido): bool => in_array('image/png', array_column($this->partesDoPedido($pedido), 'mime'), true));
    }

    /**
     * Áudio não é lido pela conversa: ele é transcrito primeiro, e a transcrição é a fala. Assim a
     * pergunta falada passa pela mesma régua de uma pergunta escrita — nome, fonte e cortesia.
     */
    public function test_o_audio_e_transcrito_e_a_conversa_segue_com_a_transcricao(): void
    {
        Storage::fake('public');
        config(['assistente.chaves.gemini' => 'chave-de-teste', 'assistente.chaves.openai' => null]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Oi, bom dia! Tudo bem?']]]]]], 200)
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Tudo ótimo! Como posso ajudar você hoje?']]]]]], 200),
        ]);

        $this->post('/assistente/arquivo', [
            'arquivo' => UploadedFile::fake()->create('recado.webm', 40, 'audio/webm'),
        ])->assertOk()->assertJson(['ativo' => true, 'status' => 'aberta']);

        $conversa = Conversa::firstOrFail();
        $enviada = $conversa->mensagens()->where('tipo', 'audio')->firstOrFail();
        $this->assertSame('Oi, bom dia! Tudo bem?', (string) $enviada->transcricao);
        $this->assertSame('Tudo ótimo! Como posso ajudar você hoje?', (string) $conversa->ultimaMensagem()->corpo);

        Http::assertSentCount(2);
        Http::assertSent(fn ($pedido): bool => in_array('audio/webm', array_column($this->partesDoPedido($pedido), 'mime'), true));
    }

    /**
     * Conversa de gente não repete o assunto a cada frase: "e a instalação?" continua a pergunta
     * anterior. A ordem levada ao modelo carrega o fio, e a busca ouve a fala anterior quando a
     * atual é curta — sem isso a Ana escalaria no meio de um assunto que já estava respondendo.
     */
    public function test_a_pergunta_curta_continua_o_assunto_e_leva_o_fio_da_conversa(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste', 'assistente.chaves.openai' => null]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'O rastreador custa o valor publicado na loja.']]]]]], 200)
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'A instalação sai inclusa na proposta.']]]]]], 200),
        ]);

        ConhecimentoItem::create([
            'titulo' => 'Preço do rastreador',
            'corpo' => 'O valor do rastreador está publicado na loja e a proposta sai com tudo incluso.',
            'tags' => 'rastreador, preco, valor',
            'origem' => 'painel',
            'ativo' => '1',
        ]);

        $this->postJson('/assistente/fala', ['texto' => 'Quanto custa o rastreador?'])->assertOk();
        $this->postJson('/assistente/fala', ['texto' => 'e a instalação?'])
            ->assertOk()
            ->assertJson(['status' => 'aberta']);

        $pedidos = Http::recorded();
        $this->assertCount(2, $pedidos);

        $segunda = $pedidos[1][0]->data();
        $ordem = (string) data_get($segunda, 'systemInstruction.parts.0.text');
        $this->assertStringContainsString('conversa em andamento', $ordem);

        // A fala curta sozinha não tem palavra que ancore uma fonte; com a pergunta anterior, tem.
        $texto = $this->textoDoPedido($this->partesDoPedido($pedidos[1][0]));
        $this->assertStringContainsString('Preço do rastreador', $texto);

        // E o que já foi dito vai junto: a Ana continua o fio em vez de recomeçar.
        $this->assertStringContainsString('O rastreador custa o valor publicado na loja.', $texto);
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

    /**
     * As partes de uma chamada ao provedor, já decodificadas — a mídia viaja em `inlineData` e o
     * JSON do corpo escapa barras e acentos, então comparar o texto cru da requisição não serve.
     *
     * @return array<int, array{mime: ?string, texto: ?string}>
     */
    private function partesDoPedido($pedido): array
    {
        $falas = (array) data_get($pedido->data(), 'contents', []);

        return collect($falas)
            ->flatMap(fn (array $fala): array => $fala['parts'] ?? [])
            ->map(fn (array $parte): array => [
                'mime' => $parte['inlineData']['mimeType'] ?? null,
                'texto' => $parte['text'] ?? null,
            ])
            ->values()
            ->all();
    }

    /** O texto que foi na chamada, parte por parte — é onde a legenda do arquivo aparece. */
    private function textoDoPedido(array $partes): string
    {
        return implode(' ', array_filter(array_column($partes, 'texto')));
    }
}
