<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\Conversa;
use App\Models\Setting;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O nome de quem fala no chat, pedido antes de qualquer assunto.
 *
 * A casa atende pelo nome — é a diferença entre "Prezado cliente" e "Marcelo, vou ver isso agora" —
 * e o pedido vem antes da primeira dúvida porque, perguntado na terceira frase, metade das
 * conversas já morreu. O que estes testes travam é o custo de errar esse caminho: um nome errado
 * gravado na conversa faz a equipe chamar alguém que não é, e uma pergunta repetida faz o
 * visitante desistir do atendimento.
 *
 * Nenhuma fala aqui tem IA disponível: as duas chaves de provedor zeradas no `setUp` deixam
 * evidente que a saudação, o pedido e o nome gravado são código da casa, não resposta de modelo —
 * e que a régua vale mesmo quando o provedor está fora do ar.
 */
class NomeNaConversaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        config(['assistente.chaves.gemini' => null, 'assistente.chaves.openai' => null]);
    }

    /**
     * A cópia em memória de `Setting` é estática e sobrevive ao rollback do banco: se este arquivo
     * desligar uma chave e sair sem desfazer a escrita, o `ChavesDeIntegracao::aplicar()` da próxima
     * teste lê aquela cópia no boot — antes de qualquer `esquecerMemoria()` do corpo — e o teste
     * seguinte herda um config que não existe mais no banco.
     */
    protected function tearDown(): void
    {
        Setting::forget('assistente_pedir_nome');
        Setting::esquecerMemoria();

        parent::tearDown();
    }

    /** Abre a bolha na sessão do visitante e devolve a conversa recém-criada. */
    private function abreBolha(): Conversa
    {
        $this->postJson('/assistente/abrir')->assertOk()->assertJson(['ativo' => true]);

        return Conversa::firstOrFail();
    }

    /** Manda uma fala e devolve o texto que a máquina respondeu. */
    private function diga(string $texto): string
    {
        $falas = $this->postJson('/assistente/fala', ['texto' => $texto])
            ->assertOk()
            ->json('falas');

        $ultima = collect($falas)->last();

        $this->assertSame('assistente', $ultima['autor'], 'A fala não foi respondida pela máquina.');

        return (string) $ultima['texto'];
    }

    public function test_a_primeira_resposta_da_maquina_ja_pede_o_nome(): void
    {
        $conversa = $this->abreBolha();
        $ficha = Assistente::paraABolha();

        $falas = $conversa->mensagens()->reorder('id')->get();

        $this->assertCount(2, $falas, 'A abertura é a saudação e o pedido de nome, nesta ordem.');
        $this->assertSame('assistente', $falas[0]->autor);
        $this->assertSame($ficha->textoPerguntaNome(), $falas[1]->corpo);
        $this->assertSame(1, $conversa->refresh()->nome_pedidos);
        $this->assertNull($conversa->nome, 'A máquina pediu o nome e já escreveu um na ficha.');
    }

    public function test_a_resposta_solta_depois_da_pergunta_e_o_nome_e_a_maquina_para_de_perguntar(): void
    {
        $conversa = $this->abreBolha();

        $this->diga('Marcelo');

        $conversa->refresh();
        $this->assertSame('Marcelo', $conversa->nome);
        $this->assertSame(1, $conversa->nome_pedidos, 'Com o nome já gravado, a máquina perguntou de novo.');
    }

    /** "me chamo Ana, quero saber o preço" é a forma mais comum de se apresentar no chat. */
    public function test_o_nome_dito_dentro_da_frase_e_aproveitado_na_mesma_fala(): void
    {
        $conversa = $this->abreBolha();

        $this->diga('me chamo Ana, quero saber o preço do rastreador');

        $this->assertSame('Ana', $conversa->refresh()->nome);
    }

    /**
     * Quem responde ao pedido de nome não fez pergunta nenhuma: a máquina reconhece a pessoa com a
     * frase da ficha e espera o assunto. Mandar um vocativo ao provedor custaria uma chamada por
     * conversa e, sem chave, entregaria à equipe quem apenas se apresentou.
     */
    public function test_a_fala_que_e_so_o_nome_recebe_o_reconhecimento_da_casa(): void
    {
        $conversa = $this->abreBolha();

        $resposta = $this->diga('Maria Aparecida');

        $this->assertSame(
            Assistente::paraABolha()->textoConfirmacaoNome('Maria Aparecida'),
            $resposta,
            'A fala de reconhecimento não é a que a casa escreveu na ficha.'
        );
        $this->assertStringContainsString('Prazer, Maria!', $resposta, 'O :nome não virou o primeiro nome dito.');
        $this->assertNotSame('aguardando_equipe', $conversa->refresh()->status, 'Quem só disse o nome foi escalado à equipe.');
    }

    /**
     * Quem se apresenta cumprimentando — "Boa noite, meu nome é Marcelo" — também recebe o
     * reconhecimento da casa. Sem isso, a saudação grudada no nome virava assunto sem fonte e
     * levava à equipe uma conversa que só tinha começado.
     */
    public function test_o_nome_dito_junto_com_o_cumprimento_tambem_e_reconhecido(): void
    {
        $conversa = $this->abreBolha();

        $resposta = $this->diga('Boa noite, meu nome é Marcelo');

        $this->assertSame('Marcelo', $conversa->refresh()->nome);
        $this->assertSame(
            Assistente::paraABolha()->textoConfirmacaoNome('Marcelo'),
            $resposta,
            'O cumprimento junto com o nome não recebeu o reconhecimento da ficha.'
        );
        $this->assertNotSame('aguardando_equipe', $conversa->refresh()->status, 'Quem se apresentou foi escalado à equipe.');
    }

    /** O reconhecimento é texto da ficha, e o que a pessoa escreveu junto com o nome é assunto. */
    public function test_a_fala_com_assunto_pela_frente_nao_e_trocada_por_reconhecimento(): void
    {        $conversa = $this->abreBolha();

        $resposta = $this->diga('Maria Aparecida, quero a segunda via da minha fatura');

        $this->assertSame('Maria Aparecida', $conversa->refresh()->nome);
        $this->assertStringNotContainsString('Prazer, Maria', $resposta, 'A máquina cumprimentou em vez de abrir a ação.');
        $this->assertStringContainsString('e-mail', $resposta, 'O pedido de segunda via não abriu a conferência.');
    }

    /**
     * O nome que a pessoa deu vale para o atendimento inteiro, inclusive depois que ela desiste de
     * responder ao assunto: a conversa é que carrega o nome, não a fala.
     */
    public function test_o_nome_dito_uma_vez_fica_na_conversa_ate_o_fim(): void
    {
        $conversa = $this->abreBolha();

        $this->diga('Bruna');
        $this->diga('Quero saber se o rastreador funciona em moto.');

        $conversa->refresh();
        $this->assertSame('Bruna', $conversa->nome);
        $this->assertSame(1, $conversa->nome_pedidos);
    }

    /**
     * Nada que não parece nome de pessoa entra na ficha. O caso mais traiçoeiro é o e-mail: é a
     * resposta que muita gente dá ao pedido de nome, e aparado no "@" ele deixaria um vocativo
     * inventado no cadastro.
     */
    public function test_o_que_nao_e_nome_nunca_e_aproveitado(): void
    {
        foreach (['bom dia', '123456', 'maria@exemplo.com.br', 'sou cliente da Golden', ' Qual o telefone?'] as $fala) {
            $conversa = Conversa::abrir('site');
            $this->session([Conversa::SESSAO => $conversa->id]);
            $this->postJson('/assistente/abrir')->assertOk();
            $this->postJson('/assistente/fala', ['texto' => $fala])->assertOk();

            $this->assertNull($conversa->refresh()->nome, 'A máquina aceitou como nome a fala: '.$fala);
        }
    }

    /** Passado o teto, a conversa segue anônima: insistir é o jeito mais rápido de perder o lead. */
    public function test_esgotado_o_teto_de_perguntas_a_conversa_segue_sem_nome(): void
    {
        config(['assistente.nome.perguntas_max' => 1]);
        $conversa = $this->abreBolha();

        $this->diga('não vou dizer');
        $this->diga('prefiro ficar anônimo');

        $conversa->refresh();
        $this->assertNull($conversa->nome);
        $this->assertSame(1, $conversa->nome_pedidos, 'A máquina perguntou o nome uma vez a mais que o teto.');
    }

    /** Desligado o interruptor do painel, a abertura é só a saudação — e ninguém é perguntado. */
    public function test_a_casa_pode_desligar_o_pedido_de_nome_pela_tela_de_chaves(): void
    {
        Setting::set('assistente_pedir_nome', '0');
        ChavesDeIntegracao::aplicar();

        $conversa = $this->abreBolha();

        $conversa->refresh();
        $this->assertSame(0, $conversa->nome_pedidos);
        $this->assertCount(1, $conversa->mensagens, 'A abertura pediu o nome com o interruptor desligado.');
    }
}
