<?php

namespace App\Http\Controllers\Portal;

use App\Services\Whmcs\Cliente as Financeiro;
use App\Services\Whmcs\Veiculacao;
use Illuminate\View\View;

/**
 * Serviços e faturas do cliente, lidos do WHMCS na hora em que a tela abre.
 *
 * Toda tela devolve uma das cinco situações, e cada uma tem uma resposta diferente: a
 * veiculação desligada não consulta ninguém; sem credenciais o financeiro está desligado; com
 * credenciais mas sem conta localizada a pessoa ainda não tem ficha lá; com conta e API fora do
 * ar a página não pode mentir dizendo que não há nada; e só no último caso a lista é a verdade.
 * É essa distinção que separa "não tem" de "não consegui perguntar" — sem ela, um cliente com
 * fatura em aberto leria uma tela vazia.
 */
class PortalFinanceiroController extends PortalBaseController
{
    public function servicos(): View
    {
        return view('portal.servicos', $this->ler(
            'servicos',
            fn (Financeiro $financeiro, int $id): ?array => $financeiro->servicos($id)
        ));
    }

    public function faturas(): View
    {
        return view('portal.faturas', $this->ler(
            'faturas',
            fn (Financeiro $financeiro, int $id): ?array => $financeiro->faturas($id)
        ));
    }

    /**
     * @param  callable(Financeiro, int): ?array  $buscar
     * @return array<string, mixed>
     */
    private function ler(string $tela, callable $buscar): array
    {
        // Primeiro o interruptor: fechado, a tela nem pergunta — o financeiro não recebe
        // requisição de uma área que a casa decidiu não abrir.
        if (! Veiculacao::abre($tela)) {
            return ['situacao' => 'desligado', 'itens' => []];
        }

        $financeiro = $this->financeiro();

        if (! $financeiro->configurado()) {
            return ['situacao' => 'sem_configuracao', 'itens' => []];
        }

        $id = $this->idNoFinanceiro();

        if ($id === null) {
            return ['situacao' => 'sem_conta', 'itens' => []];
        }

        $itens = $buscar($financeiro, $id);

        return $itens === null
            ? ['situacao' => 'fora_do_ar', 'itens' => []]
            : ['situacao' => 'ok', 'itens' => $itens];
    }
}
