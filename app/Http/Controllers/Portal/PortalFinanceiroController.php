<?php

namespace App\Http\Controllers\Portal;

use App\Services\Whmcs\Cliente as Financeiro;
use App\Services\Whmcs\Veiculacao;
use Illuminate\View\View;

/**
 * Serviços e faturas do cliente, lidos do WHMCS na hora em que a tela abre. A tela de detalhes é
 * a mesma leitura, recortada no serviço que a pessoa escolheu.
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
     * O serviço sozinho, com o que veio junto dele: os campos que a equipe preencheu no cadastro
     * (veículo, placa, chip, IMEI), as opções do plano e os adicionais pendurados nele.
     *
     * O número da URL nunca é perguntado ao financeiro. Lê-se a lista da conta autenticada e o id
     * só escolhe qual linha daquela lista abrir — número de outra pessoa é 404, sem pista de que
     * existe. É a mesma trava da tela de chamado e do pedido.
     */
    public function detalhe(int $servico): View
    {
        $leitura = $this->ler(
            'servicos',
            fn (Financeiro $financeiro, int $id): ?array => $financeiro->servicos($id)
        );

        if ($leitura['situacao'] !== 'ok') {
            return view('portal.servico', $leitura + ['servico' => null, 'adicionais' => null]);
        }

        $achado = collect($leitura['itens'])
            ->first(fn (array $item): bool => $item['id'] === $servico);

        abort_unless($achado !== null, 404);

        return view('portal.servico', [
            'situacao' => 'ok',
            'itens' => $leitura['itens'],
            'servico' => $achado,
            'adicionais' => $this->adicionaisDo($achado, $this->idNoFinanceiro()),
        ]);
    }

    /**
     * Os adicionais deste serviço, recortados da lista da conta. `null` é "o financeiro não
     * respondeu" — a tela diz isso em vez de deixar o cliente ler "nenhum", que seria mentira.
     *
     * O vínculo é o número do serviço. Quando o financeiro devolve o adicional sem número, sobra
     * o nome do serviço a que ele pertence; sem nenhum dos dois, ele não entra nesta tela — fica
     * na ficha da equipe, onde a conta inteira é lida.
     *
     * @param  array<string, mixed>  $servico
     * @return array<int, array<string, mixed>>|null
     */
    private function adicionaisDo(array $servico, ?int $id): ?array
    {
        $todos = $id === null ? null : $this->financeiro()->adicionais($id);

        if ($todos === null) {
            return null;
        }

        return collect($todos)
            ->filter(fn (array $addon): bool => $addon['servico_id'] === $servico['id']
                || ($addon['servico_id'] === 0 && $addon['servico'] !== '' && $addon['servico'] === $servico['nome']))
            ->values()
            ->all();
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
