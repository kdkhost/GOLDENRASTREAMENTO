<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O registro de uma execução: quando começou, quanto durou, o que devolveu.
 *
 * É a caixa-preta da agenda. A tarefa guarda apenas o último batimento denormalizado para a
 * listagem ler barato; tudo o que aconteceu antes mora aqui, e é por esta tabela que a ficha
 * da tarefa pode dizer "falhou três vezes desde a uma da manhã" em vez de "está com erro".
 *
 * A linha nasce em `executando` quando o processo começa e morre em `sucesso` ou `falha`
 * quando ele devolve. Uma linha parada em `executando` depois de a volta do cron já ter
 * passado é a marca de um processo morto no meio — o processo do servidor, não o da tarefa.
 */
class CronExecucao extends Model
{
    use HasFactory;

    /** Quem pôs a tarefa para rodar. Muda o aviso: cron falho notifica, botão só mostra. */
    public const POR_AGENDAMENTO = 'agendamento';

    public const POR_PAINEL = 'painel';

    // O inflector da framework faz "cron_execucaos" com o plural inglês. Nome de tabela se
    // declara, não se espera que alguém adivinhe.
    protected $table = 'cron_execucoes';

    protected $fillable = [
        'inicio', 'fim', 'status', 'duracao_ms', 'saida', 'erro', 'acionada_por', 'user_id',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
        'duracao_ms' => 'integer',
    ];

    public function tarefa(): BelongsTo
    {
        return $this->belongsTo(CronTarefa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A duração em palavras, do jeito que a ficha escreve: 1,2 s / 4,3 min. */
    public function duracaoEmPalavras(): string
    {
        return CronTarefa::duracaoPorExtenso($this->duracao_ms);
    }

    public function rotuloStatus(): string
    {
        return (string) (CronTarefa::STATUS[$this->status] ?? $this->status);
    }

    /** Quem apertou o botão, em palavras: a equipe da noite quer saber quem mexeu na agenda. */
    public function acionadaPorEmPalavras(): string
    {
        return $this->acionada_por === self::POR_PAINEL
            ? 'Painel'.($this->usuario ? ' — '.$this->usuario->name : '')
            : 'Agendamento';
    }
}
