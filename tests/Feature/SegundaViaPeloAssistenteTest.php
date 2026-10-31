<?php

namespace Tests\Feature;

use App\Mail\ConversaEscalada;
use App\Mail\SegundaViaFatura;
use App\Models\Conversa;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Assistente\Verificacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequisicaoHttp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A segunda via da fatura saindo pelo chat do assistente — e a conferência que protege o envio.
 *
 * Esta é a primeira vez que a máquina *faz* alguma coisa com o dinheiro de alguém, então o que estes
 * testes fecham não é a frase bonita: é a régua de segurança combinada antes de escrever o código.
 *
 * 1. **Nenhuma decisão de envio passa por uma IA.** Sem chave de provedor nenhuma, o atendimento
 *    normal escalaria; se a conversa continua `aberta` e a máquina pergunta um dado da ficha, é
 *    porque a ação atendeu aquela fala — não o modelo.
 * 2. **O destino é a coluna da ficha, nunca o texto do chat.** O endereço digitado serve só para
 *    localizar a conta; o e-mail sai para `emailDeAcesso()`.
 * 3. **Errar não vaza.** A resposta errada ouve "não bateu com o cadastro" e nunca o valor certo,
 *    e nada do que a pessoa informou fica gravado na mesa da ação.
 * 4. **Sem prova, sem envio.** Ficha sem dado conferível, tentativas esgotadas, financeiro desligado
 *    ou sem resposta: os quatro caminhos chamam a equipe e nenhum e-mail de fatura sai.
 * 5. **O interruptor manda.** Desligada a veiculação, o gatilho não existe e nenhuma requisição chega
 *    ao WHMCS por causa do chat — inclusive no meio de uma conferência já começada.
 */
class SegundaViaPeloAssistenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();

        // Sem provedor com chave, qualquer fala que sobrasse para o `Motor` seria escalada. É isso
        // que torna estes testes uma prova e não uma coincidência: conversa que segue `aberta` foi
        // atendida pela ação, e não por uma IA que aqui não existe.
        config(['assistente.chaves.gemini' => null, 'assistente.chaves.openai' => null]);

        // A pesquisa de satisfação muda a última fala devolvida pelo endpoint no exato momento em que
        // a ação se encerra — e é dela que estes testes tratam. O pedido de nota tem teste próprio
        // (`IdentificacaoEAvaliacaoPeloAssistenteTest`); aqui ele sai do caminho.
        config(['assistente.avaliacao.pedir' => false]);
    }

    /** A ficha de um cliente com os três dados que a casa escolheu conferir. */
    private function ficha(array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'nascimento' => '1990-10-27',
            'placas' => 'ABC1D23, XYZ9A01',
            'tipo' => 'fisica',
            'telefone' => '(35) 99888-7766',
            'email' => 'comercial@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br',
            'password' => 'senha-portal-123',
            'status' => 'ativo',
            'whmcs_id' => 77,
        ], $extra));
    }

    private function ligaFinanceiro(): void
    {
        config([
            'whmcs.url' => 'https://financeiro.exemplo.com.br',
            'whmcs.identifier' => 'identificador-da-api',
            'whmcs.secret' => 'segredo-da-api',
            'whmcs.timeout' => 5,
        ]);
    }

    /** @param  array<string, mixed>  $respostas */
    private function fingirWhmcs(array $respostas = []): void
    {
        Http::fake(function (RequisicaoHttp $requisicao) use ($respostas) {
            $acao = (string) ($requisicao['action'] ?? '');

            return $respostas[$acao] ?? Http::response([
                'result' => 'error',
                'message' => 'ação não prevista no teste: '.$acao,
            ]);
        });
    }

    /** A fatura em aberto do financeiro, no formato que a API devolve. */
    private function faturas(): array
    {
        return [
            'GetInvoices' => Http::response([
                'result' => 'success',
                'invoices' => ['invoice' => [
                    [
                        'id' => 501, 'invoicenum' => 'GR-501', 'date' => '2026-10-01',
                        'duedate' => '2026-10-10', 'datepaid' => '', 'total' => '59.90',
                        'balance' => '59.90', 'status' => 'Unpaid', 'paymentmethod' => 'pix',
                    ],
                    [
                        'id' => 500, 'invoicenum' => 'GR-500', 'date' => '2026-09-01',
                        'duedate' => '2026-09-10', 'datepaid' => '2026-09-09 10:00:00',
                        'total' => '59.90', 'balance' => '0.00', 'status' => 'Paid',
                        'paymentmethod' => 'pix',
                    ],
                ]],
            ]),
        ];
    }

    /** Abre a bolha na sessão e devolve a conversa. */
    private function conversa(): Conversa
    {
        $this->postJson('/assistente/abrir')->assertOk()->assertJson(['ativo' => true]);

        return Conversa::firstOrFail();
    }

    /** Uma fala do cliente, devolvendo o texto que a máquina respondeu. */
    private function diga(Conversa $conversa, string $texto): string
    {
        $resposta = $this->postJson('/assistente/fala', ['texto' => $texto])
            ->assertOk()
            ->assertJson(['status' => $conversa->refresh()->status]);

        $fala = collect($resposta->json('falas'))->last(fn (array $fala): bool => $fala['autor'] === 'assistente');

        // A ficha do cliente achada pelo e-mail deixa uma nota de sistema no fio antes da
        // confirmação, e é por isso que a resposta da máquina não está num índice fixo.
        $this->assertIsArray($fala, 'A fala não foi respondida pela máquina.');

        return (string) $fala['texto'];
    }

    /**
     * O procedimento da casa antes de mexer na conta de alguém: nome e e-mail na conversa. Os testes
     * desta classe provam a régua de segurança do envio, e a porta da identificação é provada em
     * `IdentificacaoEAvaliacaoPeloAssistenteTest` — aqui ela se atravessa uma vez, no começo.
     */
    private function identifica(Conversa $conversa): void
    {
        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'maria@exemplo.com.br');

        $this->assertSame('maria@exemplo.com.br', $conversa->refresh()->email);
    }

    public function test_o_pedido_de_segunda_via_abre_a_conferencia_sem_tocar_no_financeiro(): void
    {
        Mail::fake();
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        $conversa = $this->conversa();
        $this->identifica($conversa);

        $texto = $this->diga($conversa, 'Quero a segunda via da fatura, por favor.');

        $conversa->refresh();
        // O e-mail já dado no atendimento não é pedido de novo: a ação começa direto na conferência.
        $this->assertStringContainsString('ncontrei a conta', $texto);
        $this->assertSame('conferir', $conversa->acao_passo);
        $this->assertSame('segunda_via', $conversa->acao);
        $this->assertSame('aberta', $conversa->status, 'Sem IA disponível, a ação teria que virar equipe.');
        $this->assertSame(1, $conversa->nome_pedidos, 'A conferência começou sem a abertura ter pedido o nome.');

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    /** "2ª via", "via do boleto" e "manda a fatura" são o mesmo pedido — a máquina não pede IA para isso. */
    public function test_o_gatilho_reconhece_o_pedido_sem_acento_e_em_formas_diferentes(): void
    {
        $this->ficha();
        $conversa = $this->conversa();
        $this->identifica($conversa);

        foreach (['Pode mandar a fatura no meu email?', '2ª via do boleto', 'preciso da via da minha conta'] as $pedido) {
            $conversa->encerrarAcao();
            $this->diga($conversa, $pedido);

            $this->assertSame('segunda_via', $conversa->refresh()->acao, 'O pedido não abriu a ação: '.$pedido);
        }
    }

    /** Uma frase sobre a plataforma não abre conferência de dados: gatilho largo demais é porta aberta. */
    public function test_um_assunto_qualsquer_nao_abre_a_conferencia(): void
    {
        $conversa = $this->conversa();

        $this->diga($conversa, 'Meu rastreador aparece offline na plataforma.');

        $this->assertNull($conversa->refresh()->acao);
        $this->assertSame('sem_fonte', $conversa->escala_motivo);
    }

    public function test_o_email_digitado_no_chat_localiza_a_conta_mas_nunca_e_o_destino(): void
    {
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->diga($conversa, 'Maria Compradora');
        // A identificação aceita o endereço que a pessoa deu; a ação é que sabe se ele é de alguém.
        $this->diga($conversa, 'intruso@exemplo.com.br');
        $this->diga($conversa, 'Quero a segunda via da fatura.');

        // Primeiro um endereço que não é de ninguém: a máquina diz que não achou e nada sai.
        $this->assertStringContainsString('não localizei', $this->diga($conversa, 'intruso@exemplo.com.br'));
        Mail::assertNothingSent();

        $this->assertStringContainsString('conta encontrada', $this->diga($conversa, 'maria@exemplo.com.br'));

        $conversa->refresh();
        $this->assertSame('conferir', $conversa->acao_passo);
        $this->assertSame(Verificacao::escolhidos(), $conversa->acao_dados_json['conferindo']);
        $this->assertSame(2, $conversa->acao_dados_json['necessarios']);
        // A mesa de trabalho guarda estado, não resposta: nem o e-mail digitado nem o dado ficam lá.
        $this->assertStringNotContainsString('maria@exemplo.com.br', json_encode($conversa->acao_dados_json, JSON_THROW_ON_ERROR));

        $this->assertStringContainsString('isso mesmo', $this->diga($conversa, '123.456.789-01'));
        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('GR-501', $texto);
        $this->assertStringContainsString('10/10/2026', $texto, 'O vencimento da fatura em aberto não foi dito.');

        // O destino é a coluna da ficha: o comercial da empresa e o intruso do chat ficam de fora.
        Mail::assertSent(SegundaViaFatura::class, fn (SegundaViaFatura $m): bool => $m->hasTo('maria@exemplo.com.br')
            && ! $m->hasTo('comercial@exemplo.com.br')
            && ! $m->hasTo('intruso@exemplo.com.br')
            && $m->fatura['numero'] === 'GR-501');

        $conversa->refresh();
        $this->assertNull($conversa->acao, 'A ação não foi encerrada depois do envio.');
        $this->assertSame($this->fichaId(), (int) $conversa->customer_id);
        $this->assertDatabaseHas('activities', ['action' => 'segunda_via_enviada']);
    }

    private function fichaId(): int
    {
        return (int) Customer::where('email_portal', 'maria@exemplo.com.br')->value('id');
    }

    /** A placa errada ouve "não bateu" e nunca a placa certa — conferir que corrige é consulta que vaza. */
    public function test_a_resposta_errada_nunca_devolve_o_valor_certo(): void
    {
        config(['assistente.verificacao.campos' => ['placas']]);
        $this->ficha();
        $this->ligaFinanceiro();
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');

        $texto = $this->diga($conversa, 'BRQ9999');

        $this->assertStringContainsString('não bateu com o cadastro', $texto);
        $this->assertStringNotContainsString('ABC1D23', $texto);
        $this->assertStringNotContainsString('BRQ9999', $texto, 'A máquina repetiu o que a pessoa errou.');
        $this->assertSame(1, $conversa->refresh()->acao_tentativas);
        $this->assertSame('Maria Compradora', $conversa->nome, 'A placa digitada virou o nome da pessoa.');

        Mail::assertNothingSent();
    }

    public function test_esgotar_as_tentativas_chama_a_equipe_e_nenhum_e_mail_sai(): void
    {
        config(['assistente.verificacao.tentativas' => 2]);
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '000.000.000-00');
        $texto = $this->diga($conversa, '111.111.111-11');

        $this->assertStringContainsString('chamei a equipe', $texto);
        $conversa->refresh();
        $this->assertSame('aguardando_equipe', $conversa->status);
        $this->assertSame('verificacao_sem_sucesso', $conversa->escala_motivo);
        $this->assertNull($conversa->acao);

        Mail::assertNothingSent();
        Mail::assertQueued(ConversaEscalada::class);
    }

    /** Ficha sem nenhum dos dados escolhidos não pode provar nada: a casa não envia, e a equipe assume. */
    public function test_ficha_sem_dado_para_conferir_nao_recebe_envio(): void
    {
        $this->ficha(['documento' => null, 'nascimento' => null, 'placas' => null]);
        $this->ligaFinanceiro();
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        // Sem e-mail para achar a ficha, a ação teria que pedir o endereço antes; identificada a
        // pessoa, ela já sabe de quem é a conta e descobre na hora que não há o que conferir.
        $texto = $this->diga($conversa, 'segunda via da fatura');

        $this->assertStringContainsString('chamei a equipe', $texto);
        $this->assertSame('verificacao_impossivel', $conversa->refresh()->escala_motivo);
        Mail::assertNothingSent();
    }

    public function test_financeiro_desligado_ou_sem_resposta_vira_recado_da_equipe(): void
    {
        $this->ficha();
        Mail::fake();

        // 1. Sem credencial nenhuma: a conferência passa e o envio não tem para onde ir.
        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '123.456.789-01');
        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('sistema financeiro está desligado', $texto);
        $this->assertSame('envio_nao_concluido', $conversa->refresh()->escala_motivo);
        Mail::assertNothingSent();

        // 2. Credencial no ar e o servidor não responde à consulta de faturas.
        $conversa->update(['escala_motivo' => null, 'status' => 'aberta']);
        $this->ligaFinanceiro();
        $this->fingirWhmcs(['GetInvoices' => Http::response(['result' => 'error', 'message' => 'boom'], 500)]);

        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '123.456.789-01');
        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('chamei a equipe', $texto);
        $this->assertSame('envio_nao_concluido', $conversa->refresh()->escala_motivo);
        Mail::assertNothingSent();
    }

    /** Sem fatura em aberto a máquina diz que não há o que mandar — e não chama ninguém à toa. */
    public function test_conta_sem_fatura_em_aberto_recebe_a_noticia_sem_escalada(): void
    {
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs([
            'GetInvoices' => Http::response([
                'result' => 'success',
                'invoices' => ['invoice' => [[
                    'id' => 500, 'invoicenum' => 'GR-500', 'date' => '2026-09-01',
                    'duedate' => '2026-09-10', 'datepaid' => '2026-09-09 10:00:00',
                    'total' => '59.90', 'balance' => '0.00', 'status' => 'Paid',
                ]]],
            ]),
        ]);
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '123.456.789-01');
        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('não há fatura em aberto', $texto);
        $this->assertNull($conversa->refresh()->escala_motivo);
        $this->assertNull($conversa->acao);
        Mail::assertNothingSent();
    }

    /**
     * Conferência parada é outra pessoa na frente do computador: vencida a janela, a mesa é montada
     * de novo do zero — os acertos da rodada vencida não servem de atalho para ninguém.
     */
    public function test_a_conferencia_vencida_recomeca_do_zero(): void
    {
        config(['assistente.verificacao.minutos' => 20]);
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '123.456.789-01');

        $conversa->refresh();
        $this->assertSame(1, (int) $conversa->acao_dados_json['acertos'], 'A conferência não anotou o primeiro acerto.');

        $this->travel(21)->minutes();
        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('não vale mais', $texto);
        $conversa->refresh();
        // A ficha não é pedida de novo — ela é o e-mail que a identificação já gravou. O que morre é
        // a rodada: a mesa volta a listar os três dados e o placar zera.
        $this->assertSame('conferir', $conversa->acao_passo);
        $this->assertSame(Verificacao::escolhidos(), $conversa->acao_dados_json['conferindo']);
        $this->assertSame(0, (int) $conversa->acao_dados_json['acertos'],
            'Sobrou acerto da conferência vencida na mesa de trabalho.');
        $this->assertSame(0, (int) $conversa->acao_tentativas);
        Mail::assertNothingSent();
    }

    /** Desligado o interruptor, nem a conferência já começada sobrevive: o estado morre com ela. */
    public function test_o_interruptor_desligado_derruba_ate_a_conferencia_ja_comecada(): void
    {
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->assertSame('conferir', $conversa->refresh()->acao_passo);

        config(['whmcs.veiculacao.segunda_via' => false]);
        $this->diga($conversa, '123.456.789-01');

        $conversa->refresh();
        $this->assertNull($conversa->acao);
        $this->assertNull($conversa->acao_passo);
        $this->assertSame('sem_fonte', $conversa->escala_motivo, 'A fala da conferência ainda pertencia à ação.');
        Mail::assertNothingSent();
    }

    /** O pedido antes da conferência: um endereço malformado não gasta uma consulta ao financeiro. */
    public function test_um_texto_que_nao_e_email_nao_vira_busca_de_conta(): void
    {
        // A tranca da identificação é provada na outra classe; aqui ela sai do caminho de propósito,
        // porque o que se testa é o passo `email` da própria ação — o único em que ela ainda pergunta.
        config(['assistente.contato.exigir_para_personalizado' => false]);
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());

        $conversa = $this->conversa();
        $this->diga($conversa, 'Maria Compradora');
        $this->diga($conversa, 'segunda via da fatura');
        $texto = $this->diga($conversa, 'maria sem aroba');

        $this->assertStringContainsString('não parece um endereço', $texto);
        $this->assertSame(1, $conversa->refresh()->acao_tentativas);
        $this->assertSame('email', $conversa->acao_passo);

        Http::assertSentCount(0);
    }

    /** O nome dito na conversa entra na boca da máquina até nos passos fixos da conferência. */
    public function test_a_conferencia_chama_a_pessoa_pelo_nome(): void
    {
        $this->ficha();
        $conversa = $this->conversa();

        $this->diga($conversa, 'Meu nome é Marcelo');
        $this->assertSame('Marcelo', $conversa->refresh()->nome);

        $this->diga($conversa, 'maria@exemplo.com.br');

        $texto = $this->diga($conversa, 'segunda via da fatura');

        $this->assertStringStartsWith('Marcelo,', $texto);
    }

    /**
     * Enquanto a conferência dura, a fala é dela — e um dado digitado ali não vira nome de ninguém.
     * "ABC1D23" tem três letras e é a primeira palavra da frase: sem esta trava, a máquina passaria
     * a chamar o cliente de "Abc" e ainda gravaria a placa no campo do nome.
     */
    public function test_dado_de_conferencia_nunca_aproveitado_como_nome(): void
    {
        config(['assistente.verificacao.campos' => ['placas']]);
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $texto = $this->diga($conversa, 'ABC1D23');

        $this->assertStringContainsString('enviei a fatura', $texto);
        $this->assertSame('Maria Compradora', $conversa->refresh()->nome, 'A placa conferida virou o nome da pessoa.');
        $this->assertSame($this->fichaId(), (int) $conversa->customer_id);
    }

    /**
     * A única escrita que a conferência faz: quem não tem data de nascimento é perguntado depois de
     * provar que é dono da conta, e a resposta é gravada. Antes da prova, nada é perguntado — provar
     * identidade com o dado que se está coletando seria entregar a conta a quem sabe a data.
     */
    public function test_a_data_de_nascimento_em_branco_e_pedida_depois_da_prova_e_gravada(): void
    {
        config(['assistente.verificacao.campos' => ['documento'], 'assistente.verificacao.minimo' => 1]);
        $this->ficha(['nascimento' => null]);
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');

        $pedido = $this->diga($conversa, '123.456.789-01');

        $this->assertStringContainsString('identidade confirmada', $pedido);
        $this->assertStringContainsString('dia/mês/ano', $pedido);
        $this->assertSame('conferir', $conversa->refresh()->acao_passo, 'A ação parou antes de completar a ficha.');
        Mail::assertNothingSent();

        $texto = $this->diga($conversa, '27/10/1990');

        $this->assertStringContainsString('anotei sua data de nascimento', $texto);
        $this->assertStringContainsString('GR-501', $texto, 'A fatura não saiu depois de a data ser registrada.');
        $this->assertSame('1990-10-27', $this->fichaDoBanco()->nascimento->format('Y-m-d'));
        $this->assertDatabaseHas('activities', ['action' => 'cliente_nascimento_pelo_chat']);
        Mail::assertSent(SegundaViaFatura::class, fn (SegundaViaFatura $m): bool => $m->hasTo('maria@exemplo.com.br'));
    }

    /** O interruptor manda: desligado, o chat não pergunta a data e não escreve nada na ficha. */
    public function test_com_o_interruptor_desligado_a_data_nao_e_pedida_nem_gravada(): void
    {
        config([
            'assistente.verificacao.campos' => ['documento'],
            'assistente.verificacao.minimo' => 1,
            'assistente.verificacao.completar' => false,
        ]);
        $this->ficha(['nascimento' => null]);
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');

        $texto = $this->diga($conversa, '123.456.789-01');

        $this->assertStringContainsString('enviei a fatura', $texto);
        $this->assertStringNotContainsString('data de nascimento', $texto);
        $this->assertNull($this->fichaDoBanco()->nascimento);
        $this->assertDatabaseMissing('activities', ['action' => 'cliente_nascimento_pelo_chat']);
        Mail::assertSent(SegundaViaFatura::class);
    }

    /** Data já escrita não é perguntada nem trocada: o cadastro da casa não se reescreve pelo chat. */
    public function test_data_ja_escrita_nao_e_perguntada_nem_sobrescrita(): void
    {
        config(['assistente.verificacao.campos' => ['documento'], 'assistente.verificacao.minimo' => 1]);
        $this->ficha();
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');

        $texto = $this->diga($conversa, '123.456.789-01');

        $this->assertStringContainsString('enviei a fatura', $texto);
        $this->assertStringNotContainsString('data de nascimento', $texto);
        $this->assertSame('1990-10-27', $this->fichaDoBanco()->nascimento->format('Y-m-d'));
    }

    /**
     * A fatura já estava liberada antes da pergunta: recusa, "depois" ou texto que não é data
     * encerram a coleta e o envio acontece do mesmo jeito. Coleta de dado não é refém de envio.
     */
    public function test_data_incompreendida_ou_recusada_nao_segura_a_segunda_via(): void
    {
        config(['assistente.verificacao.campos' => ['documento'], 'assistente.verificacao.minimo' => 1]);
        $this->ficha(['nascimento' => null]);
        $this->ligaFinanceiro();
        $this->fingirWhmcs($this->faturas());
        Mail::fake();

        $conversa = $this->conversa();
        $this->identifica($conversa);
        $this->diga($conversa, 'segunda via da fatura');
        $this->diga($conversa, '123.456.789-01');

        $this->assertStringContainsString('não entendi como data', $this->diga($conversa, 'ontem à tarde'));

        $texto = $this->diga($conversa, 'depois');

        $this->assertStringContainsString('deixei a data de nascimento em branco', $texto);
        $this->assertStringContainsString('GR-501', $texto);
        $this->assertNull($this->fichaDoBanco()->nascimento);
        $this->assertNull($conversa->refresh()->acao, 'A ação não foi encerrada depois do envio.');
        Mail::assertSent(SegundaViaFatura::class);
    }

    /** A ficha que o chat acabou de completar, lida do banco. */
    private function fichaDoBanco(): Customer
    {
        return Customer::where('email_portal', 'maria@exemplo.com.br')->firstOrFail();
    }

    /** O Blade da fatura: renderizar de verdade, porque `Mail::fake()` intercepta antes do template. */
    public function test_o_html_da_segunda_via_mostra_a_fatura_e_o_caminho_do_pagamento(): void
    {
        $cliente = $this->ficha();
        $fatura = [
            'id' => 501, 'numero' => 'GR-501', 'emitida' => Carbon::parse('2026-10-01'),
            'vencimento' => Carbon::parse('2026-10-10'), 'paga_em' => null,
            'total_cents' => 5990, 'saldo_cents' => 5990, 'status' => 'Unpaid',
            'metodo' => 'pix', 'url' => 'https://financeiro.exemplo.com.br/viewinvoice.php?id=501',
        ];

        $html = (string) (new SegundaViaFatura($cliente, $fatura))->render();

        $this->assertStringContainsString('GR-501', $html);
        $this->assertStringContainsString('R$ 59,90', $html);
        $this->assertStringContainsString('10/10/2026', $html);
        $this->assertStringContainsString('https://financeiro.exemplo.com.br/viewinvoice.php?id=501', $html);
        // O e-mail do cliente nunca é impresso no corpo: quem recebe a mensagem já sabe para onde ela
        // foi, e um dado de cadastro escrito num e-mail é dado que se espalha em cópia e encaminhamento.
        $this->assertStringNotContainsString('maria@exemplo.com.br', $html);
    }
}
