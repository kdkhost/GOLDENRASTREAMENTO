<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Customer;
use App\Services\Whmcs\Cliente as Financeiro;
use Illuminate\Console\Command;

/**
 * A varredura que completa, de uma vez, as fichas que já estão ligadas ao financeiro.
 *
 * O botão da ficha resolve uma pessoa por vez; esta rotina existe para o dia em que a base
 * inteira está incompleta — cadastros que nasceram no portal e ganharam vínculo depois, sem
 * ninguém digitar endereço. Ela usa o mesmo caminho da tela (`Customer::absorverDoFinanceiro()`),
 * inclusive a regra de só tocar no que está em branco; a diferença é o padrão de execução.
 *
 * Sem `--aplicar` ela não grava nada: lista o que entraria em cada ficha e sai. É o que permite
 * conferir o tamanho do estrago antes de escrever em centenas de registros de produção.
 */
class PuxarDadosDoFinanceiro extends Command
{
    protected $signature = 'clientes:puxar-do-financeiro
                            {--aplicar : Grava de verdade; sem esta opção só mostra o que entraria}
                            {--ficha= : Uma ficha só, pelo número do painel}';

    protected $description = 'Completa as fichas ligadas ao financeiro com os dados que estão em branco';

    public function handle(): int
    {
        $financeiro = app(Financeiro::class);

        if (! $financeiro->configurado()) {
            $this->error('O financeiro não tem endereço, identificador e segredo. Preencha em Sistema → Configurações → Chaves de integração → WHMCS.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');
        $fichas = Customer::query()
            ->whereNotNull('whmcs_id')
            ->when($this->option('ficha'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($fichas->isEmpty()) {
            $this->warn('Nenhuma ficha ligada ao financeiro'.($this->option('ficha') ? ' com este número' : '').'.');

            return self::SUCCESS;
        }

        $this->line(($aplicar ? 'Gravando' : 'Simulando').' '.$fichas->count().' ficha(s) ligada(s) ao financeiro.');

        $preenchidas = 0;
        $jaCompletas = 0;
        $mudas = 0;

        foreach ($fichas as $ficha) {
            $dados = $financeiro->ficha((int) $ficha->whmcs_id);

            if ($dados === null) {
                $mudas++;
                $this->warn("#{$ficha->id} {$ficha->nome}: o financeiro não respondeu — nada alterado.");

                continue;
            }

            $resultado = $ficha->absorverDoFinanceiro($dados, $aplicar);

            if ($resultado['preenchidos'] === []) {
                $jaCompletas++;

                continue;
            }

            $preenchidas++;
            $this->line("#{$ficha->id} {$ficha->nome}: ".implode(', ', $resultado['preenchidos']).'.');
        }

        $this->newLine();
        $this->line(($aplicar ? 'Preenchidas' : 'Preencheriam').": {$preenchidas}. Sem nada a puxar: {$jaCompletas}. Financeiro mudo: {$mudas}.");

        if (! $aplicar && $preenchidas > 0) {
            $this->comment('Nada foi gravado. Rode com --aplicar para gravar.');
        }

        if ($aplicar && $preenchidas > 0) {
            Activity::log('cliente_dados_do_financeiro', "A rotina completou {$preenchidas} ficha(s) com o cadastro do financeiro.");
        }

        return self::SUCCESS;
    }
}
