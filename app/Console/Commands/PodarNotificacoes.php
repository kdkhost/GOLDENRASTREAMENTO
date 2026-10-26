<?php

namespace App\Console\Commands;

use App\Models\CronExecucao;
use App\Models\CronTarefa;
use App\Models\Notificacao;
use App\Support\Agendador;
use App\Support\Notifica;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * A poda das duas centrais: notificação que fechou o ciclo e histórico que ninguém vai ler.
 *
 * As duas tabelas novas do painel crescem sozinhas, e cada uma estraga a tela de um jeito
 * próprio quando ninguém encosta nela. Notificação que não morre vira sino ignorado — a pessoa
 * aprende a fechar o que nunca vai ler. Histórico de cron sem teto vira a tabela grande que
 * ninguém consulta porque consultar custa.
 *
 * A terceira parte do comando não é limpeza, é vigia: uma tarefa pausada não é falha, mas uma
 * tarefa que *deveria* ter rodado e não rodou é, e só existe um jeito de descobrir — alguém, de
 * tempos em tempos, olha a data do último batimento de cada uma. O próprio cron não pode se
 * vigiar: se a crontab da hospedagem sumir, não há quem avise. Por isso a falta da
 * batida-cardíaco é mostrada na tela, onde quem vê é quem está com o painel aberto.
 */
class PodarNotificacoes extends Command
{
    protected $signature = 'notificacoes:apodrecer
                            {--dias= : Retenção das notificações lidas, em dias (o padrão é o do painel)}';

    protected $description = 'Aposentar notificações lidas e vencidas, aparar o histórico da agenda e avisar tarefa sem batimento';

    public function handle(): int
    {
        $this->podarNotificacoes();
        $this->apararHistorico();
        $this->vigiarBatimento();

        return self::SUCCESS;
    }

    /**
     * Tira o que a equipe já leu, depois da retenção, e o que ninguém abriu quando venceu.
     *
     * A retenção vem do banco antes do config porque é escolha do painel, não do código: muda na
     * tela de preferências e vale para a próxima poda, sem deploy.
     */
    private function podarNotificacoes(): void
    {
        if (! Schema::hasTable('notificacoes')) {
            $this->warn('Sem tabela de notificações neste banco: nada a aposentar.');

            return;
        }

        $dias = (int) ($this->option('dias') ?: Notificacao::diasDeRetencao());
        $apareadas = Notificacao::paraAposentar($dias)->delete();

        $this->line("Notificações aposentadas: {$apareadas} (lidas há mais de {$dias} dias ou vencidas).");
    }

    /**
     * Mantém o histórico da agenda no tamanho que a tela consegue ler.
     *
     * Um cron de minuto escreve 1.440 linhas por dia por tarefa. O teto da config preserva as
     * últimas centenas — o suficiente para responder "o que aconteceu hoje" — e joga o resto
     * fora, porque histórico que ninguém consulta é só tabela grande.
     */
    private function apararHistorico(): void
    {
        if (! Agendador::agendaPronta()) {
            return;
        }

        $teto = (int) config('cron.historico_por_tarefa', 500);
        $removidas = 0;

        foreach (CronTarefa::pluck('id') as $id) {
            $velhas = CronExecucao::where('cron_tarefa_id', $id)
                ->orderByDesc('inicio')
                ->orderByDesc('id')
                ->skip($teto)
                ->take(2000)
                ->pluck('id');

            if ($velhas->isNotEmpty()) {
                $removidas += CronExecucao::whereIn('id', $velhas)->delete();
            }
        }

        $this->line("Execuções fora do histórico: {$removidas} (teto de {$teto} por tarefa).");
    }

    /**
     * A tarefa ativa que não dá sinal de vida.
     *
     * O aviso é uma linha por tarefa por dia, garantido pela própria chave do disparo: a verificação
     * roda de hora em hora, e sem chave a equipe receberia vinte e quatro avisos do mesmo problema
     * antes do café da manhã.
     *
     * Antes de olhar tarefa alguma, o comando olha o relógio que as vigia. Se a batida-cardíaco
     * faltou, não é a tarefa que parou — foi o `schedule:run` inteiro, e acusar as quatro linhas de
     * uma vez seria barulho sobre uma causa só. Nesse caso a tela é quem avisa, porque este comando
     * também não rodou: quem está morto não emite atestado.
     */
    private function vigiarBatimento(): void
    {
        if (! Agendador::agendaPronta()) {
            return;
        }

        $segundos = Agendador::segundosSemBatida();

        if ($segundos === null) {
            $this->line('Agenda sem batida-cardíaco: o schedule:run ainda não passou por aqui. '
                .'A falta da crontab é mostrada na tela, não notificada daqui.');

            return;
        }

        if (! Agendador::batidaViva()) {
            $this->line("Agenda sem batida-cardíaco há ".number_format($segundos).' segundos. '
                .'Nenhuma tarefa é acusada enquanto o agendador estiver em falta.');

            return;
        }

        $paradas = 0;

        foreach (CronTarefa::ativas()->get() as $tarefa) {
            if (! $tarefa->estaSemBatida()) {
                continue;
            }

            $paradas++;

            Notifica::disparar(
                'cron_pausado_demais',
                'A tarefa "'.$tarefa->nome.'" está sem batimento',
                'A última execução registrada foi '.$tarefa->batidaEmPalavras()
                    .', e a agenda dela ('.$tarefa->agendaPorExtenso().') já devia ter chamado de novo. '
                    .'Ou a tarefa quebrou antes de escrever no histórico, ou o cron da hospedagem parou.',
                (string) $tarefa->id,
                [
                    'tarefa_id' => $tarefa->id,
                    'comando' => $tarefa->comando,
                    'expressao' => $tarefa->expressao,
                    'rodou_em' => $tarefa->rodou_em?->toDateTimeString(),
                ],
                'cron_pausado:'.$tarefa->id.':'.now()->toDateString(),
            );
        }

        $this->line($paradas === 0 ? 'Todas as tarefas ativas deram sinal.' : "Tarefas sem batimento: {$paradas}.");
    }
}
