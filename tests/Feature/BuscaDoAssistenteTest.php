<?php

namespace Tests\Feature;

use App\Models\ConhecimentoItem;
use App\Services\Assistente\Busca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A busca que decide se o atendimento tem com o que responder.
 *
 * Duas promessas moram aqui, e as duas nasceram de um cliente real ficando sem resposta:
 *
 * 1. **A pergunta comprida não pode esconder a fonte.** Cobrir metade de um item cujo título é
 *    exatamente o assunto ainda é ter fonte; o piso de cobertura continua valendo para quem só
 *    achou a palavra no corpo, onde toda palavra aparece.
 * 2. **Cortesia não é pergunta.** "Oi, tudo bem?" não tem fato a conferir na base, e escalar por
 *    isso mandava para a equipe quem só cumprimentou. "Tem como?", feita só de palavras vazias,
 *    não é cortesia e continua devendo fonte.
 */
class BuscaDoAssistenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** A palavra no título é sinal curado: vence o piso mesmo quando a pergunta dilui a cobertura. */
    public function test_a_palavra_do_titulo_ancora_o_item_mesmo_com_a_cobertura_abaixo_do_piso(): void
    {
        $item = $this->item([
            'titulo' => 'Plataforma de monitoramento',
            'corpo' => 'O acesso é feito pelo site com o e-mail e a senha do cadastro.',
            'tags' => 'app, login',
        ]);

        $cobertura = $this->cobertura('quero testar a plataforma tem como?', $item);

        $this->assertLessThan((float) config('assistente.resposta.minimo_de_fonte'), $cobertura);

        $fontes = Busca::fontes('quero testar a plataforma tem como?');
        $achada = collect($fontes)->firstWhere('id', $item->id);

        $this->assertNotNull($achada, 'O item ancorado pelo título ficou de fora.');
        $this->assertTrue($achada['ancorado']);
    }

    /** A palavra achada só no corpo não ancora: ali toda palavra aparece, e o piso continua valendo. */
    public function test_a_palavra_do_corpo_ainda_precisa_da_cobertura_minima(): void
    {
        $item = $this->item([
            'titulo' => 'Condições do serviço',
            'corpo' => 'O prazo de instalação é de três dias úteis no estado de São Paulo.',
            'tags' => 'contrato',
        ]);

        $pergunta = 'quero testar a instalação em São Paulo?';

        $this->assertLessThan(
            (float) config('assistente.resposta.minimo_de_fonte'),
            $this->cobertura($pergunta, $item)
        );

        $this->assertNotContains($item->id, array_column(Busca::fontes($pergunta, 50), 'id'));
    }

    public function test_a_saudacao_e_cortesia_e_a_pergunta_de_conteudo_nao(): void
    {
        $this->assertTrue(Busca::eCortesia('Oi, tudo bem?'));
        $this->assertTrue(Busca::eCortesia('Bom dia!'));
        $this->assertTrue(Busca::eCortesia('olá, boa tarde'));
        $this->assertTrue(Busca::eCortesia('Obrigado!'));

        $this->assertFalse(Busca::eCortesia('tem como?'), 'Palavra vazia não é cortesia.');
        $this->assertFalse(Busca::eCortesia('Oi, quanto custa o rastreador?'));
        $this->assertFalse(Busca::eCortesia('tudo bem, quero cancelar'));
    }

    /** A nota do item como a busca a calcula, sem depender da ordem do banco nem do piso. */
    private function cobertura(string $pergunta, ConhecimentoItem $item): float
    {
        $piso = config('assistente.resposta.minimo_de_fonte');
        config(['assistente.resposta.minimo_de_fonte' => 0]);

        $achada = collect(Busca::fontes($pergunta, 50))->firstWhere('id', $item->id);
        $nota = $achada['pontuacao'] ?? 0.0;

        config(['assistente.resposta.minimo_de_fonte' => $piso]);

        return (float) $nota;
    }

    private function item(array $dados): ConhecimentoItem
    {
        return ConhecimentoItem::create($dados + [
            'origem' => 'painel',
            'ativo' => '1',
        ]);
    }
}
