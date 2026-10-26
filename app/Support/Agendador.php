<?php

namespace App\Support;

use App\Models\CronExecucao;
use App\Models\CronTarefa;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Quem traduz a tabela `cron_tarefas` em agendamento de verdade.
 *
 * A hospedagem tem uma linha só de crontab, e ela não se mexe: `* * * * * artisan
 * schedule:run`. Tudo o que roda depois disso é decidido na tela Sistema → Crons e registrado
 * aqui, a cada vez que o agendador é montado. É isto que faz o painel ser o dono da agenda sem
 * que ninguém precise tocar em crontab — e é também o motivo pelo qual uma tarefa pausada
 * simplesmente não aparece: ela não é registrada, em vez de registrada e ignorada.
 *
 * O histórico é o que transforma "o cron rodou?" em pergunta respondível. Cada tarefa abre uma
 * execução quando começa e fecha em sucesso ou falha quando devolve, com a saída e o erro
 * guardados. Sem isso, a única evidência de um cron seria a linha de log do `schedule:run`, que
 * ninguém no painel lê às três da manhã.
 *
 * A batida-cardíaco é separada das tarefas: ela não roda nada, só marca que o `schedule:run`
 * passou. É o que permite à tela dizer "cron vivo há 40 segundos" — e, quando ela falta, "a
 * crontab da hospedagem sumiu", que é um diagnóstico completamente diferente de "a tarefa falhou".
 */
class Agendador
{
    /** Registra no schedule tudo o que a tabela manda rodar. Chamado por `bootstrap/app.php`. */
    public static function registrar(Schedule $schedule): void
    {
        static::registrarBatida($schedule);

        foreach (static::tarefas() as $tarefa) {
            static::registrarTarefa($schedule, $tarefa);
        }
    }

    /**
     * O coração: um minuto sem esta linha é um minuto sem agenda.
     *
     * Vai para o cache e não para o banco porque é informação de estado, não de histórico: o
     * painel só quer saber se o cron está vivo agora, e uma escrita por minuto em tabela por
     * causa de um timestamp é custo sem pergunta correspondente.
     */
    private static function registrarBatida(Schedule $schedule): void
    {
        $chave = (string) config('cron.batida.chave_no_cache', 'golden.cron_batida');

        $schedule->call(fn (): bool => Cache::put($chave, now()->timestamp, now()->addDay()))
            ->everyMinute()
            ->name('golden:batida-do-agendador')
            ->withoutOverlapping();
    }

    /**
     * A linha da agenda virou evento do `schedule:run`.
     *
     * O `name()` carrega o id porque é a âncora do mutex do `withoutOverlapping` e o rótulo que
     * o `schedule:list` mostra: sem nome, duas tarefas com o mesmo comando disputam a mesma
     * trava e uma delas nunca roda.
     */
    private static function registrarTarefa(Schedule $schedule, CronTarefa $tarefa): void
    {
        $execucao = null;

        /** @var Event $evento */
        $evento = $schedule->command($tarefa->comando)
            ->cron($tarefa->expressao)
            ->name("golden:tarefa-{$tarefa->id}")
            ->description($tarefa->nome);

        if ($tarefa->sem_sobreposicao) {
            $evento->withoutOverlapping();
        }

        $evento->before(function () use ($tarefa, &$execucao): void {
            $execucao = $tarefa->abrirExecucao(CronExecucao::POR_AGENDAMENTO);
        });

        $evento->onSuccess(function ($saida) use ($tarefa, &$execucao): void {
            if ($execucao !== null) {
                $tarefa->fecharExecucao($execucao, true, (string) $saida);
            }
        });

        $evento->onFailure(function ($saida) use ($tarefa, &$execucao): void {
            if ($execucao !== null) {
                $tarefa->fecharExecucao($execucao, false, (string) $saida, (string) $saida);
            }

            static::avisarFalha($tarefa, (string) $saida);
        });
    }

    /**
     * O aviso de falha. Uma linha por tarefa por dia, e não por execução: um cron quebrado a cada
     * cinco minutos gera 288 avisos idênticos por dia, e no terceiro a pessoa para de ler o sino
     * inteiro — inclusive o que não era do cron.
     */
    public static function avisarFalha(CronTarefa $tarefa, string $saida): void
    {
        Notifica::disparar(
            'cron_falhou',
            'A tarefa "'.$tarefa->nome.'" falhou',
            trim($saida) !== '' ? trim($saida) : 'O comando devolveu erro sem escrever saída.',
            (string) $tarefa->id,
            [
                'tarefa_id' => $tarefa->id,
                'comando' => $tarefa->comando,
                'expressao' => $tarefa->expressao,
                'rodou_em' => now()->toDateTimeString(),
            ],
            'cron_falhou:'.$tarefa->id.':'.now()->toDateString(),
        );
    }

    /**
     * As tarefas que vão ser registradas.
     *
     * Uma consulta por batida do cron é o custo, e é o preço de a agenda morar no banco: sem
     * cache, a pessoa muda o horário na tela e o efeito é imediato. Com cache, o efeito é quando
     * o cache resolver — e "mudei e não mudou" é a discussão mais cara que existe.
     */
    public static function tarefas(): iterable
    {
        if (! static::agendaPronta()) {
            return [];
        }

        try {
            return CronTarefa::ativas()->orderBy('id')->get();
        } catch (\Throwable $falha) {
            report($falha);

            return [];
        }
    }

    /**
     * A agenda existe neste banco?
     *
     * `schedule:run` roda de minuto em minuto, inclusive nos segundos em que um deploy está no
     * meio de uma migration. Sem esta barreira, o cron derrubado por tabela ausente para de
     * drenar a fila de e-mail também — e aí um problema de esquema vira dois problemas de
     * produção, sendo o segundo silencioso.
     */
    public static function agendaPronta(): bool
    {
        try {
            return Schema::hasTable('cron_tarefas');
        } catch (\Throwable) {
            return false;
        }
    }

    /** O último minuto em que o agendador passou por aqui, em segundos. Null se ele nunca veio. */
    public static function segundosSemBatida(): ?int
    {
        $batida = Cache::get((string) config('cron.batida.chave_no_cache', 'golden.cron_batida'));

        return $batida === null ? null : max(0, (int) (now()->timestamp - (int) $batida));
    }

    /** O cron da hospedagem está vivo? A tolerância é curta de propósito: um minuto perdido é visível. */
    public static function batidaViva(): bool
    {
        $segundos = static::segundosSemBatida();

        return $segundos !== null && $segundos <= (int) config('cron.batida.tolerancia_segundos', 120);
    }

    /**
     * A batida em palavras para a tela: "há 40 segundos", "há 2 horas". A frase é a mesma da
     * ficha da tarefa, porque a diferença entre "parou" e "está devagar" é justamente o tempo.
     */
    public static function batidaEmPalavras(): string
    {
        $segundos = static::segundosSemBatida();

        return $segundos === null
            ? 'não há registro de batida'
            : now()->subSeconds($segundos)->diffForHumans();
    }

    /** Quantos registros a agenda tem, incluindo as pausadas — é o número do cartão da tela. */
    public static function totalDeTarefas(): int
    {
        return static::agendaPronta() ? CronTarefa::count() : 0;
    }

    public static function totalDeAtivas(): int
    {
        return static::agendaPronta() ? CronTarefa::ativas()->count() : 0;
    }
}
