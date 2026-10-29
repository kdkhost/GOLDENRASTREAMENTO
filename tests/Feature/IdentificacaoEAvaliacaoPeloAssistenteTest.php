<?php

namespace Tests\Feature;

use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Assistente\Avaliacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A porta do atendimento personalizado e a nota no fim dele.
 *
 * O combinado com a casa tem duas frases, e cada uma delas tem uma régua que a máquina não pode
 * afrouxar sozinha:
 *
 * **Nada da conta da pessoa se mexe antes de saber de quem é a conta.** Nome e e-mail vêm primeiro,
 * um por vez, com teto de pergunta (é a tela de chaves que manda) — e o pedido que ficou na fila
 * retoma sozinho assim que a identificação fecha, sem que a pessoa precise repeti-lo. Passado o teto
 * sem identificação, a conversa vai para a equipe em vez de virar formulário infinito. O que a tranca
 * segura é a ação personalizada; a dúvida geral continua sendo respondida na hora.
 *
 * **No fim, a Ana pede a avaliação do atendimento dela.** Uma única vez, só enquanto a conversa é da
 * máquina, e a nota entra por dois caminhos que gravam as mesmas colunas: o número escrito no chat ou
 * o toque nas estrelas da bolha.
 */
class IdentificacaoEAvaliacaoPeloAssistenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();

        // Sem provedor com chave, o que sobrasse para o `Motor` seria escalado. Aqui isso é
        // instrumento: as falas que estes testes leem são as falas fixas da casa.
        config(['assistente.chaves.gemini' => null, 'assistente.chaves.openai' => null]);

        Mail::fake();
    }

    private function ficha(): Customer
    {
        return Customer::create([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'nascimento' => '1990-10-27',
            'placas' => 'ABC1D23',
            'tipo' => 'fisica',
            'telefone' => '(35) 99888-7766',
            'email' => 'comercial@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
            'whmcs_id' => 77,
        ]);
    }

    /** Abre a bolha na sessão e devolve a conversa. */
    private function conversa(): Conversa
    {
        $this->postJson('/assistente/abrir')->assertOk()->assertJson(['ativo' => true]);

        return Conversa::firstOrFail();
    }

    /** Uma fala do cliente, devolvendo a resposta inteira para quem precisa ler mais de uma fala. */
    private function fala(Conversa $conversa, string $texto): TestResponse
    {
        return $this->postJson('/assistente/fala', ['texto' => $texto])
            ->assertOk()
            ->assertJson(['status' => $conversa->refresh()->status]);
    }

    /**
     * Uma fala do cliente, devolvendo o texto da última fala da máquina nesta resposta.
     *
     * É a última e não a segunda porque o atendimento pode botar duas falas de uma vez na tela (o
     * envio da fatura e o pedido de nota, por exemplo) — e o que importa aqui é o que a pessoa lê
     * por último.
     */
    private function diga(Conversa $conversa, string $texto): string
    {
        $ultima = collect($this->fala($conversa, $texto)->json('falas'))->last();

        $this->assertSame('assistente', $ultima['autor'], 'A fala não terminou com a máquina respondendo.');

        return (string) $ultima['texto'];
    }

    /** O pedido de segunda via na boca de quem escreve no celular. */
    private function pidaVia(Conversa $conversa): string
    {
        return $this->diga($conversa, 'quero a segunda via da fatura');
    }

    /** A porta fechada: o que a casa pede antes de olhar a conta da pessoa. */
    public function test_o_pedido_personalizado_pede_o_nome_e_nao_abre_nada(): void
    {
        $this->ficha();
        Http::fake();
        $conversa = $this->conversa();

        $texto = $this->pidaVia($conversa);

        $conversa->refresh();
        $this->assertSame(config('assistente.persona.pergunta_nome'), $texto);
        $this->assertNull($conversa->acao, 'A ação abriu antes de a casa saber de quem é a conta.');
        $this->assertSame('segunda_via', $conversa->acao_pendente);
        $this->assertNotNull($conversa->acao_pendente_em);
        $this->assertSame('aberta', $conversa->status);
        $this->assertSame(2, (int) $conversa->nome_pedidos, 'A porta não contou a pergunta que fez.');

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    /** O e-mail que veio dentro de uma frase é identificação, não assunto — e não gasta IA. */
    public function test_o_email_dado_no_meio_da_frase_e_aproveitado_na_hora(): void
    {
        Http::fake();
        $conversa = $this->conversa();

        $texto = $this->diga($conversa, 'meu email é maria@exemplo.com.br');

        $this->assertSame(config('assistente.persona.confirmacao_email'), $texto);
        $this->assertSame('maria@exemplo.com.br', $conversa->refresh()->email);

        Http::assertNothingSent();
    }

    /**
     * O pedido que ficou na fila não precisa ser repetido: fechada a identificação, a casa retoma
     * sozinha — e retoma na conferência, não na pergunta pelo e-mail que a pessoa já deu.
     */
    public function test_o_pedido_da_fila_retoma_sozinho_quando_a_identificacao_fecha(): void
    {
        $this->ficha();
        Http::fake();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Maria Compradora');
        $this->pidaVia($conversa);
        $conversa->refresh();
        $this->assertSame('segunda_via', $conversa->acao_pendente);
        $this->assertNull($conversa->acao);

        $texto = $this->diga($conversa, 'maria@exemplo.com.br');

        $conversa->refresh();
        $this->assertStringContainsString('contrei a conta', $texto);
        $this->assertSame('segunda_via', $conversa->acao);
        $this->assertSame('conferir', $conversa->acao_passo);
        $this->assertNull($conversa->acao_pendente, 'A fila ficou pendurada depois de atendida.');
        // Retomar não é consultar: a ação abre na conferência e o financeiro não é chamado antes de
        // os dados baterem.
        Http::assertNothingSent();
    }

    /** Vencida a janela da conferência, o pedido que estava na fila vence junto: nada de ação fantasma. */
    public function test_o_pedido_na_fila_vence_com_a_conferencia(): void
    {
        config(['assistente.verificacao.minutos' => 20]);
        $this->ficha();
        $conversa = $this->conversa();

        $this->pidaVia($conversa);
        $this->diga($conversa, 'tá bom');

        $this->travel(21)->minutes();
        $this->diga($conversa, 'e aí?');

        $conversa->refresh();
        $this->assertNull($conversa->acao_pendente);
        $this->assertNull($conversa->acao);
    }

    /**
     * A casa pergunta até o teto e para: um cliente que não conseguiu ser identificado é cliente sem
     * atendimento, e a conversa inteira vai para a equipe — com a nota fora do caminho, porque quem
     * não atendeu não é a máquina.
     */
    public function test_esgotado_o_teto_de_perguntas_a_equipe_assume_sem_pesquisa(): void
    {
        config(['assistente.contato.perguntas_max' => 2]);
        $this->ficha();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Maria Compradora');
        $this->pidaVia($conversa);
        $this->diga($conversa, '???');

        $texto = $this->diga($conversa, '???');

        $conversa->refresh();
        $this->assertStringContainsString('chamei a equipe', $texto);
        $this->assertSame('aguardando_equipe', $conversa->status);
        $this->assertSame('sem_identificacao', $conversa->escala_motivo);
        $this->assertSame(2, (int) $conversa->email_pedidos, 'A casa passou do teto sem parar de perguntar.');
        $this->assertNull($conversa->acao_pendente);
        $this->assertNull($conversa->acao);
        $this->assertNull($conversa->avaliacao_pedida_em);
        $this->assertFalse(Avaliacao::horaDePedir($conversa));
    }

    /** Desligada a exigência na tela de chaves, a ação abre com o que a conversa tiver. */
    public function test_desligada_a_exigencia_a_acao_abre_na_hora(): void
    {
        config(['assistente.contato.exigir_para_personalizado' => false]);
        $this->ficha();
        $conversa = $this->conversa();

        $this->pidaVia($conversa);

        $conversa->refresh();
        $this->assertSame('segunda_via', $conversa->acao);
        $this->assertSame('email', $conversa->acao_passo);
        $this->assertNull($conversa->acao_pendente);
    }

    /** A tranca segura o que olha a conta da pessoa — a dúvida geral continua sendo respondida. */
    public function test_a_duvida_geral_nao_espera_identificacao(): void
    {
        config(['assistente.chaves.gemini' => 'chave-de-teste']);
        ConhecimentoItem::create([
            'titulo' => 'Tempo de instalação',
            'corpo' => 'A instalação do rastreador leva 40 minutos e é feita no endereço do cliente.',
            'tags' => 'instalacao, tempo',
            'origem' => 'painel',
            'ativo' => '1',
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'A instalação leva cerca de 40 minutos.']]]]],
            'usageMetadata' => ['promptTokenCount' => 180, 'candidatesTokenCount' => 12],
        ], 200)]);

        $conversa = $this->conversa();
        $texto = $this->diga($conversa, 'Quanto tempo leva a instalação?');

        $this->assertSame('A instalação leva cerca de 40 minutos.', $texto);
        $conversa->refresh();
        $this->assertNull($conversa->acao_pendente);
        $this->assertNull($conversa->escala_motivo);
    }

    /**
     * O atendimento personalizado termina, e é nele que a casa quer a nota — a última fala da bolha
     * passa a ser a pesquisa, e o payload avisa a tela para acender as estrelas.
     */
    public function test_a_nota_e_pedida_no_fim_do_atendimento_da_acao(): void
    {
        $this->ligaFinanceiro();
        $this->ficha();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'maria@exemplo.com.br');
        $this->pidaVia($conversa);
        $this->diga($conversa, '123.456.789-01');
        $resposta = $this->fala($conversa, '27/10/1990');

        $falas = $resposta->json('falas');
        $this->assertCount(3, $falas, 'A fatura saiu e a pesquisa não veio junto.');
        $this->assertStringContainsString('enviei a fatura', (string) $falas[1]['texto']);
        $this->assertSame(config('assistente.persona.pedido_avaliacao'), (string) $falas[2]['texto']);
        $this->assertTrue($resposta->json('avaliacao'));

        $conversa->refresh();
        $this->assertNotNull($conversa->avaliacao_pedida_em);
        $this->assertNull($conversa->avaliacao_nota);
        $this->assertSame('aberta', $conversa->status);
    }

    /** A nota escrita no chat é lida por regra, sem chamada de IA, e o agradecimento é da casa. */
    public function test_a_nota_escrita_no_chat_e_gravada_com_agradecimento(): void
    {
        $conversa = $this->conversaComPesquisaAberta();

        $texto = $this->diga($conversa, '5, parabéns pelo atendimento');

        $conversa->refresh();
        $this->assertSame(str_replace(':nome', 'Maria', config('assistente.persona.agradecimento_avaliacao')), $texto);
        $this->assertSame(5, (int) $conversa->avaliacao_nota);
        $this->assertStringContainsString('parabéns', (string) $conversa->avaliacao_texto);
        $this->assertNotNull($conversa->avaliada_em);
        $this->postJson('/assistente/avaliar', ['nota' => 3])->assertOk()->assertJson(['ativo' => false]);
        $this->assertSame(5, (int) $conversa->refresh()->avaliacao_nota, 'A nota foi sobrescrita na conversa.');
    }

    /** O toque na estrela grava as mesmas colunas do número digitado — e não se repete. */
    public function test_o_toque_na_estrela_grava_a_nota_e_agradece(): void
    {
        $conversa = $this->conversaComPesquisaAberta();

        $resposta = $this->postJson('/assistente/avaliar', ['nota' => 4])
            ->assertOk()
            ->assertJson(['ativo' => true, 'avaliacao' => false]);

        $conversa->refresh();
        $this->assertSame(4, (int) $conversa->avaliacao_nota);
        $this->assertNotNull($conversa->avaliada_em);
        $this->assertDatabaseHas('conversas_mensagens', [
            'conversa_id' => $conversa->id, 'autor' => 'sistema',
            'corpo' => 'Avaliação do atendimento: 4/5',
        ]);
        $this->assertSame(
            str_replace(':nome', 'Maria', config('assistente.persona.agradecimento_avaliacao')),
            (string) collect($resposta->json('falas'))->last()['texto']
        );

        // Pesquisa respondida não se responde duas vezes: o toque repetido devolve a conversa como
        // está, e a bolha apaga as estrelas.
        $this->postJson('/assistente/avaliar', ['nota' => 1])->assertOk()->assertJson(['ativo' => false]);
        $this->assertSame(4, (int) $conversa->refresh()->avaliacao_nota);
    }

    /** Fora da pesquisa aberta o toque não tem valor nenhum, e nota fora da escala não passa da validação. */
    public function test_o_toque_fora_de_hora_ou_fora_da_escala_nao_grava_nada(): void
    {
        $conversa = $this->conversa();

        $this->postJson('/assistente/avaliar', ['nota' => 5])->assertOk()->assertJson(['ativo' => false]);
        $this->assertNull($conversa->refresh()->avaliacao_nota);

        $pesquisa = $this->conversaComPesquisaAberta();

        $this->postJson('/assistente/avaliar', ['nota' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nota');
        $this->assertNull($pesquisa->refresh()->avaliacao_nota);
    }

    /**
     * A pesquisa se pede uma vez só: enquanto a conversa é da máquina ela é a última fala, e depois
     * dela a conversa volta a ser conversa.
     */
    public function test_a_pesquisa_nao_se_repete_em_outro_fim_de_conversa(): void
    {
        config(['assistente.verificacao.campos' => ['placas'], 'assistente.verificacao.minimo' => 1]);
        $this->ligaFinanceiro();
        $this->ficha();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'maria@exemplo.com.br');

        $this->pidaVia($conversa);
        $this->diga($conversa, 'ABC1D23');
        $this->assertNotNull($conversa->refresh()->avaliacao_pedida_em);

        // Um segundo atendimento de verdade na mesma conversa: a nota já foi pedida, e pesquisa que
        // insiste é o jeito mais rápido de estragar a nota que ela ia colher.
        $this->pidaVia($conversa);
        $texto = $this->diga($conversa, 'ABC1D23');

        $this->assertStringContainsString('enviei a fatura', $texto);
        $this->assertSame(1, ConversaMensagem::where('corpo', config('assistente.persona.pedido_avaliacao'))->count(),
            'A casa pediu a nota duas vezes na mesma conversa.');
    }

    /**
     * Cada endereço da bolha tem seu próprio teto — e não o mesmo contador.
     *
     * Sem prefixo no `throttle`, a chave é só o IP com o navegador: a varredura de `falas`, que roda a
     * cada cinco segundos com o balão aberto, come o teto da `fala` e o visitante fica sem escrever no
     * meio da própria conversa. É o tipo de travamento que só aparece em uso de verdade, depois de dois
     * minutos de balão aberto — por isso a régua aqui é o consumo de um endereço não bloquear o outro.
     */
    /**
     * A despedida é o fim de conversa, e o fim é regra.
     *
     * "Obrigado, era isso" não é pergunta: é a pessoa encerrando. Mandada ao provedor sem chave
     * ligada — ou a um provedor fora do ar — ela voltaria como "não sei responder" com a equipe
     * chamada no fim de um atendimento concluído, que é exatamente a hora em que a casa quer a nota.
     */
    public function test_a_despedida_pede_a_nota_e_nao_chama_a_equipe(): void
    {
        $conversa = $this->conversa();
        $this->diga($conversa, 'Maria Compradora');

        $texto = $this->diga($conversa, 'obrigado, era isso');

        $conversa->refresh();
        $this->assertSame(config('assistente.persona.pedido_avaliacao'), $texto);
        $this->assertSame('aberta', $conversa->status, 'A despedida virou chamado para a equipe.');
        $this->assertNull($conversa->respondido_por_equipe_em);
        $this->assertNotNull($conversa->avaliacao_pedida_em);
    }

    /**
     * Dada a nota, a despedida que vem depois se despede — e não pergunta nada de novo.
     *
     * A pesquisa é uma vez só por conversa; o que sobra é o adeus, e ele é a fala da ficha, não o
     * texto de escalada. Uma segunda pesquisa na mesma conversa é o jeito mais rápido de estragar a
     * nota que a pessoa acabou de dar.
     */
    public function test_a_despedida_depois_da_nota_se_despede_sem_repetir_a_pesquisa(): void
    {
        $conversa = $this->conversa();
        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'obrigado, era isso');
        $this->diga($conversa, '5');

        $texto = $this->diga($conversa, 'valeu, era só isso');

        $conversa->refresh();
        $this->assertStringContainsString('Foi um prazer ajudar', $texto);
        $this->assertStringContainsString('Maria', $texto, 'A despedida não chamou a pessoa pelo nome.');
        $this->assertSame('aberta', $conversa->status);
        $this->assertSame(1, ConversaMensagem::where('corpo', config('assistente.persona.pedido_avaliacao'))->count(),
            'A casa pediu a nota de novo depois de já ter recebido a nota.');
    }

    public function test_os_enderecos_da_bolha_nao_dividem_o_mesmo_teto(): void
    {
        $this->conversa();

        // Trinta varreduras: o teto de `falas` é 120 por minuto, então nenhuma delas estoura nada.
        for ($vezes = 0; $vezes < 30; $vezes++) {
            $this->getJson(route('site.assistente.falas'))->assertSuccessful();
        }

        // A fala tem teto 20. Se as duas dividirem o contador, esta requisição chega em 429 — e o
        // atendimento morre na frente de quem acabou de escrever.
        $this->postJson(route('site.assistente.fala'), ['texto' => 'quanto custa o rastreador?'])
            ->assertSuccessful();

        $this->postJson(route('site.assistente.avaliar'), ['nota' => 5])->assertSuccessful();
    }

    /**
     * O e-mail dito no chat acha a ficha do cliente — e a casa reconhece a pessoa pelo cadastro.
     *
     * É a promessa do atendimento personalizado: o endereço não é só anotado, é a chave que liga o
     * fio à ficha que o vendedor cuida. A conversa ganha o cliente, o nome que ela ainda não tinha e
     * a fala de sistema que explica à equipe por que aquela ficha está ali.
     */
    public function test_o_email_dito_na_conversa_localiza_o_cadastro_do_cliente(): void
    {
        $cliente = $this->ficha();
        Http::fake();
        $conversa = $this->conversa();

        $texto = $this->diga($conversa, 'meu email é maria@exemplo.com.br');

        $conversa->refresh();
        $this->assertSame($cliente->id, $conversa->customer_id, 'O e-mail não ligou a conversa ao cadastro.');
        $this->assertSame('maria@exemplo.com.br', $conversa->email);
        $this->assertSame('Maria Compradora', $conversa->nome, 'A ficha não completou o nome que a conversa não tinha.');
        $this->assertSame(
            str_replace(':nome', 'Maria', config('assistente.persona.confirmacao_cadastro')),
            $texto
        );
        $this->assertDatabaseHas('conversas_mensagens', [
            'conversa_id' => $conversa->id,
            'autor' => 'sistema',
            'corpo' => 'Cadastro localizado pelo e-mail: cliente #'.$cliente->id.' — '.$cliente->nome.'.',
        ]);

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    /**
     * Quem se apresenta e dá o e-mail na mesma frase também é reconhecido, e sem chamar a IA.
     *
     * "Oi, sou o João. Meu e-mail é joao@..." é uma frase inteira de identificação: não há pergunta
     * nenhuma ali. Mandá-la ao provedor terminava em "isso eu não sei responder, já chamei a
     * equipe" — a máquina escalando o atendimento de quem só chegou e se apresentou, que é a
     * primeira impressão que a casa dá.
     */
    public function test_a_apresentacao_com_email_acha_o_cadastro_sem_chamar_o_provedor(): void
    {
        $cliente = $this->ficha();
        Http::fake();
        $conversa = $this->conversa();

        $texto = $this->diga($conversa, 'Oi, sou a Maria. Meu email é maria@exemplo.com.br');

        $conversa->refresh();
        $this->assertSame($cliente->id, $conversa->customer_id, 'A apresentação não ligou a conversa ao cadastro.');
        $this->assertSame('maria@exemplo.com.br', $conversa->email);
        $this->assertSame(
            str_replace(':nome', 'Maria', config('assistente.persona.confirmacao_cadastro')),
            $texto
        );

        Http::assertNothingSent();
    }

    /**
     * O endereço no meio de um assunto também acha o cadastro — e, achando, destranca o pedido.
     *
     * "meu email é ..., quero a segunda via" chega numa fala só no celular. O e-mail é aproveitado
     * sem esperar a casa perguntar, e o pedido que já vinha na frase abre na hora: pedir de novo o
     * endereço que a pessoa acabou de escrever seria o formulário que ela não quer.
     */
    public function test_o_email_no_meio_do_assunto_destranca_o_pedido_personalizado(): void
    {
        $cliente = $this->ficha();
        Http::fake();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Maria Compradora');
        $texto = $this->diga($conversa, 'oi, meu email é comercial@exemplo.com.br, quero a segunda via');

        $conversa->refresh();
        $this->assertSame($cliente->id, $conversa->customer_id);
        $this->assertSame('comercial@exemplo.com.br', $conversa->email);
        $this->assertSame('segunda_via', $conversa->acao, 'O pedido não abriu com o cadastro localizado.');
        $this->assertSame('conferir', $conversa->acao_passo);
        $this->assertStringContainsString('contrei a conta', $texto);

        Http::assertNothingSent();
    }

    /** A conta do financeiro, no ar — só o que a ação consulta depois de os dados baterem. */
    private function ligaFinanceiro(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador-da-api',
            'whmcs.secret' => 'segredo-da-api',
            'whmcs.timeout' => 5,
        ]);

        Http::fake(function (RequisicaoHttp $requisicao) {
            $acao = (string) ($requisicao['action'] ?? '');

            if ($acao !== 'GetInvoices') {
                return Http::response(['result' => 'error', 'message' => 'ação não prevista: '.$acao]);
            }

            return Http::response([
                'result' => 'success',
                'invoices' => ['invoice' => [[
                    'id' => 501, 'invoicenum' => 'GR-501', 'date' => '2026-10-01',
                    'duedate' => '2026-10-10', 'datepaid' => '', 'total' => '59.90',
                    'balance' => '59.90', 'status' => 'Unpaid', 'paymentmethod' => 'pix',
                ]]],
            ]);
        });
    }

    /**
     * Uma conversa que já teve atendimento de verdade e chegou à pesquisa: um envio de segunda via
     * concluído. Os dois caminhos da nota (o número digitado e o toque) partem do mesmo estado.
     */
    private function conversaComPesquisaAberta(): Conversa
    {
        config(['assistente.verificacao.campos' => ['documento', 'nascimento'], 'assistente.verificacao.minimo' => 2]);
        $this->ligaFinanceiro();
        $this->ficha();

        $conversa = $this->conversa();
        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'maria@exemplo.com.br');
        $this->pidaVia($conversa);
        $this->diga($conversa, '123.456.789-01');
        $this->diga($conversa, '27/10/1990');

        $conversa->refresh();
        $this->assertNotNull($conversa->avaliacao_pedida_em, 'A preparação não chegou à pesquisa.');

        return $conversa;
    }
}
