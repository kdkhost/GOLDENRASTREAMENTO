<?php

namespace Tests\Feature;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Excecoes\ChamadaFalhou;
use App\Services\Assistente\Provedores\Gemini;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O que o Gemini devolve — e o que não pode chegar ao cliente.
 *
 * Os modelos Gemini 3 pensam antes de responder, e o rascunho do pensamento é cobrado no mesmo
 * teto de tokens da fala. Numa resposta de documento isso já custou o atendimento: o orçamento
 * acabou no meio do raciocínio e o cliente recebeu um começo de frase em inglês. O pedido agora
 * desliga o pensamento longo, e a leitura da resposta recusa o que não é fala.
 */
class RespostaDoGeminiTest extends TestCase
{
    use RefreshDatabase;

    /** Um pedido ao Gemini com a resposta que o provedor devolveria. */
    private function pedir(array $resposta, string $modelo = 'gemini-3-flash-preview'): string
    {
        Http::fake(['*' => Http::response($resposta)]);

        return (new Gemini('chave-de-teste', $modelo))
            ->responder(new Chamada(pergunta: 'o que é rastreamento veicular?', capacidade: 'conversa'))
            ->texto;
    }

    private function respostaCom(string $texto, string $motivo = 'STOP', bool $pensamento = false): array
    {
        $partes = [];

        if ($pensamento) {
            $partes[] = ['thought' => true, 'text' => 'The user asked a simple question. I should answer. '];
        }

        $partes[] = ['text' => $texto];

        return [
            'candidates' => [['content' => ['role' => 'model', 'parts' => $partes], 'finishReason' => $motivo]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20],
        ];
    }

    /** O Gemini 3 recebe o pedido de pensar pouco, e o orçamento fica com a resposta. */
    public function test_o_gemini_3_recebe_o_pedido_de_pensar_pouco(): void
    {
        $texto = $this->pedir($this->respostaCom('O rastreamento mostra o veículo em tempo real.'));

        $this->assertSame('O rastreamento mostra o veículo em tempo real.', $texto);

        Http::assertSent(fn (Request $requisicao): bool => data_get($requisicao->data(), 'generationConfig.thinkingConfig.thinkingLevel') === 'minimal');
    }

    /** Modelo que não conhece o campo não recebe o campo — um chat ruim é melhor que um 400. */
    public function test_modelo_antigo_nao_recebe_o_campo_de_pensamento(): void
    {
        $this->pedir($this->respostaCom('Resposta qualquer.'), 'gemini-2.5-flash');

        Http::assertSent(fn (Request $requisicao): bool => ! isset($requisicao->data()['generationConfig']['thinkingConfig']));
    }

    /** O rascunho marcado como pensamento é descartado: ao cliente só vai a fala. */
    public function test_o_rascunho_do_pensamento_nao_vira_fala_da_ana(): void
    {
        $texto = $this->pedir($this->respostaCom('O contrato está assinado.', 'STOP', pensamento: true));

        $this->assertSame('O contrato está assinado.', $texto);
    }

    /** Resposta cortada é falha, não frase pela metade na tela de quem perguntou. */
    public function test_resposta_cortada_vira_falha_em_vez_de_frase_pela_metade(): void
    {
        $this->expectException(ChamadaFalhou::class);

        $this->pedir($this->respostaCom('" or just the text. *Self-Correction:*', 'MAX_TOKENS'));
    }
}
