<?php

namespace App\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Uma tarefa da agenda do painel.
 *
 * É a linha que substitui a edição de crontab. A hospedagem tem uma única linha registrada
 * (`* * * * * artisan schedule:run`) e ela não se mexe; o que roda depois disso é decidido
 * aqui, na tela Sistema → Crons, com histórico e com botão de rodar agora.
 *
 * A agenda se guarda em três colunas (`agenda`, `hora`, `dia_semana`) e `expressao` é a
 * resolução delas — espelho, não fonte. Guardar a preset permite reabrir o select da tela na
 * opção certa; guardar a expressão resolvida permite ao painel conferir o que o agendador
 * registrou de fato sem recalcular nada na leitura.
 *
 * `sistema` é a costura de segurança. As quatro tarefas que o sistema traz prontas podem ser
 * pausadas e terem a agenda trocada, mas não apagadas: apagar o worker da fila é o jeito mais
 * silencioso de perder todo e-mail do portal, porque nada quebra — só para de sair.
 */
class CronTarefa extends Model
{
    use HasFactory;

    public const NUNCA = 'nunca';

    public const SUCESSO = 'sucesso';

    public const FALHA = 'falha';

    public const EXECUTANDO = 'executando';

    /** Os quatro estados do batimento, com o rótulo que a tela mostra. */
    public const STATUS = [
        self::NUNCA => 'Nunca rodou',
        self::SUCESSO => 'Rodou bem',
        self::FALHA => 'Falhou',
        self::EXECUTANDO => 'Rodando agora',
    ];

    protected $fillable = [
        'nome', 'descricao', 'comando', 'agenda', 'hora', 'dia_semana', 'expressao',
        'ativa', 'sem_sobreposicao', 'sistema', 'rodou_em', 'status', 'duracao_ms', 'ultima_saida',
    ];

    protected $casts = [
        'ativa' => 'boolean',
        'sem_sobreposicao' => 'boolean',
        'sistema' => 'boolean',
        'dia_semana' => 'integer',
        'rodou_em' => 'datetime',
        'duracao_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        // A expressão é resolvida na escrita, nunca na leitura: é o único jeito de o que o
        // agendador registra e o que a tela mostram forem sempre a mesma linha. Uma tarefa
        // criada por seed traz a expressão pronta na própria linha, e passa por aqui na
        // primeira vez que alguém a edita.
        static::saving(function (self $tarefa): void {
            if ($tarefa->isDirty(['agenda', 'hora', 'dia_semana']) || $tarefa->getAttribute('expressao') === null) {
                $tarefa->setAttribute('expressao', self::resolverExpressao(
                    (string) $tarefa->getAttribute('agenda'),
                    $tarefa->getAttribute('hora'),
                    $tarefa->getAttribute('dia_semana') === null ? null : (int) $tarefa->getAttribute('dia_semana'),
                ));
            }
        });
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(CronExecucao::class)->latest('inicio')->latest('id');
    }

    /** O que o agendador registra: pausada não aparece, porque não tem o que registrar. */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }

    public function scopeComFalha(Builder $query): Builder
    {
        return $query->where('status', self::FALHA);
    }

    /** As tarefas que o sistema trouxe prontas — as únicas que a tela não deixa apagar. */
    public function scopeDoSistema(Builder $query): Builder
    {
        return $query->where('sistema', true);
    }

    /**
     * O nome do comando, separado das flags.
     *
     * É assim que a tela edita uma tarefa sem quebrar a linha de comando: o select oferece os
     * comandos registrados no `artisan` e o que sobra (`--stop-when-empty --max-time=50`) é
     * texto livre num campo próprio. Na tabela os dois voltam a ser um campo só, porque um
     * campo só é o que o agendador executa.
     */
    public function nomeComando(): string
    {
        return self::dividirComando((string) $this->comando)[0];
    }

    public function flags(): string
    {
        return self::dividirComando((string) $this->comando)[1];
    }

    /**
     * A mesma divisão, aberta para a tela: o formulário precisa do nome e das flags em duas
     * caixas, e a régua de "onde termina o comando" é uma só — a mesma que o agendador usa.
     *
     * @return array{0:string,1:string}
     */
    public static function partesDoComando(string $linha): array
    {
        return self::dividirComando($linha);
    }

    /**
     * Nome e flags de uma linha de comando, em dois pedaços.
     *
     * `Str::after` não serve aqui: sem o espaço procurado ele devolve a linha inteira, e um
     * comando sem flags voltaria com o próprio nome no lugar das flags. É explode com teto de
     * dois, que é exatamente a pergunta: o primeiro pedaço é o comando, o resto é argumento.
     */
    private static function dividirComando(string $linha): array
    {
        $partes = explode(' ', trim($linha), 2);

        return [$partes[0] ?? '', trim($partes[1] ?? '')];
    }

    /**
     * Resolve a expressão cron a partir das três colunas de agenda.
     *
     * Um valor inválido devolve a hora padrão em vez de estourar: a validação já barrou a
     * escrita, então chegar aqui com hora torta é bug, não entrada de usuário — e um bug de
     * formato não pode derrubar o agendador inteiro, que registra as outras tarefas na mesma volta.
     */
    public static function resolverExpressao(string $agenda, ?string $hora = null, ?int $diaSemana = null): string
    {
        $preset = (array) (config('cron.agendas')[$agenda] ?? []);
        $modelo = (string) ($preset['expressao'] ?? '* * * * *');

        if (! str_contains($modelo, '{')) {
            return $modelo;
        }

        [$h, $m] = self::horaEmPartes($hora);
        $dias = (array) config('cron.dias_semana', []);

        return str_replace(
            ['{h}', '{m}', '{dow}'],
            [$h, $m, isset($dias[$diaSemana]) ? (string) $diaSemana : '0'],
            $modelo
        );
    }

    /** 'HH:MM' em dois pedaços de dois dígitos, com zero à esquerda. */
    private static function horaEmPartes(?string $hora): array
    {
        $hora = $hora ?: (string) config('cron.hora_padrao', '03:00');
        $partes = explode(':', $hora);

        return [
            str_pad((string) max(0, min(23, (int) ($partes[0] ?? 3))), 2, '0', STR_PAD_LEFT),
            str_pad((string) max(0, min(59, (int) ($partes[1] ?? 0))), 2, '0', STR_PAD_LEFT),
        ];
    }

    /** A preset em palavras: 'Toda semana, terça-feira às 03:30'. */
    public function agendaPorExtenso(): string
    {
        $preset = (array) (config('cron.agendas')[$this->agenda] ?? []);
        $rotulo = (string) ($preset['rotulo'] ?? $this->agenda);
        $hora = $preset['hora'] ?? null;

        if ($hora === 'minuto') {
            return $rotulo.', no minuto '.(int) (substr((string) $this->hora, 3) ?: '0');
        }

        if ($hora !== 'ambas') {
            return $rotulo;
        }

        $dias = (array) config('cron.dias_semana', []);
        $quando = ($preset['dia'] ?? false) ? ($dias[$this->dia_semana] ?? 'domingo').' às ' : 'às ';

        return $rotulo.', '.$quando.$this->hora;
    }

    public function rotuloStatus(): string
    {
        return (string) (self::STATUS[$this->status] ?? $this->status);
    }

    /** A última saída em uma linha — é a prévia que a listagem mostra antes de abrir a ficha. */
    public function resumoUltimaSaida(): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $this->ultima_saida)), 140);
    }

    /**
     * A tarefa pode ser chamada pelo botão "Rodar agora"?
     *
     * O worker da fila é a própria fila: chamá-lo dentro da requisição prende a tela de quem
     * clicou por cinquenta segundos para fazer o que o cron já faz sozinho, e o resultado na
     * tela é uma página girando. A recusa devolve o motivo em palavras, porque "não pode" sem
     * explicação é o jeito certo de fazer a pessoa clicar três vezes.
     */
    public function podeRodarAgora(): bool
    {
        return ! in_array($this->nomeComando(), (array) config('cron.comandos_da_fila', []), true);
    }

    /**
     * O comando escolhido é executável por aqui?
     *
     * Três barreiras, uma mensagem só. A primeira é a lista negra da config — os comandos que
     * apagam dados ou pedem senha na tela não pertencem a uma agenda automática. A segunda é a
     * sintaxe: nada de `;`, `&&`, pipe ou redirecionamento, porque isto transforma o campo de
     * flags em shell e a agenda do painel em porta de entrada. A terceira é o registro do
     * próprio `artisan`: comando que não existe devolve erro de digitação, não tarefa fantasma.
     *
     * Devolve null quando está tudo certo e o texto do erro quando não está, que é o formato
     * que o validador come sem tradução.
     */
    public static function erroDoComando(string $comando): ?string
    {
        $trimado = trim($comando);

        if ($trimado === '') {
            return 'Escolha o comando que a tarefa vai rodar.';
        }

        if (preg_match('/[;&|<>$`\r\n]/', $trimado) === 1) {
            return 'A linha aceita apenas o nome do comando e as flags dele, separados por espaço. '
                .'Ponto e vírgula, "&&", pipe, redirecionamento e crase estão fora.';
        }

        $nome = self::dividirComando($trimado)[0];

        foreach ((array) config('cron.comandos_recusados', []) as $proibido => $motivo) {
            if ($nome === $proibido) {
                return (string) $motivo;
            }
        }

        if (! array_key_exists($nome, Artisan::all())) {
            return 'Não existe comando artisan com esse nome: '.$nome.'.';
        }

        $flags = self::dividirComando($trimado)[1];

        if ($flags !== '' && preg_match('/^[A-Za-z0-9_\-:.\/=\s]+$/', $flags) !== 1) {
            return 'As flags só aceitam letras, números, travessão, dois-pontos, barra e igual.';
        }

        return null;
    }

    /**
     * Abre a execução no histórico e põe a tarefa em "rodando agora".
     *
     * A escrita é do modelo e não do agendador porque as duas pontas — o cron e o botão do
     * painel — precisam deixar o mesmo rastro, mudando só o `acionada_por`. Duas implementações
     * de "começou" é como se descobre, tarde, que uma delas não gravava.
     */
    public function abrirExecucao(string $acionadaPor = 'agendamento', ?int $userId = null): CronExecucao
    {
        // Uma execução parada em "executando" é processo interrompido — restart do servidor,
        // cron matado no meio, deploy em cima da tarefa. Ela não se fecha sozinha, então é
        // fechada aqui, com o motivo escrito: histórico com linha aberta para sempre é histórico
        // que ninguém confia.
        foreach ($this->execucoes()->where('status', self::EXECUTANDO)->get() as $antiga) {
            $antiga->forceFill([
                'fim' => now(),
                'status' => self::FALHA,
                'erro' => 'O processo foi interrompido antes de devolver. Execução encerrada pelo '
                    .'início da execução seguinte.',
                'duracao_ms' => (int) $antiga->inicio->diffInMilliseconds(now()),
            ])->save();
        }

        $execucao = $this->execucoes()->create([
            'inicio' => now(),
            'status' => self::EXECUTANDO,
            'acionada_por' => $acionadaPor,
            'user_id' => $userId,
        ]);

        $this->forceFill(['status' => self::EXECUTANDO])->save();

        return $execucao;
    }

    /**
     * Fecha a execução e espelha o resultado na tarefa.
     *
     * O `ultima_saida` denormalizado é o que a listagem lê; o histórico completo continua em
     * `cron_execucoes`. Sem o espelho, a tela precisaria de um join por linha para dizer o que
     * aconteceu ontem — e é a pergunta mais frequente da tela.
     */
    public function fecharExecucao(CronExecucao $execucao, bool $ok, ?string $saida = null, ?string $erro = null): void
    {
        $teto = (int) config('cron.saida_maxima', 6000);
        $duracao = (int) $execucao->inicio->diffInMilliseconds(now());

        $execucao->forceFill([
            'fim' => now(),
            'status' => $ok ? self::SUCESSO : self::FALHA,
            'duracao_ms' => $duracao,
            'saida' => $saida === null ? null : mb_substr($saida, 0, $teto),
            'erro' => $erro === null ? null : mb_substr($erro, 0, $teto),
        ])->save();

        $this->forceFill([
            'status' => $ok ? self::SUCESSO : self::FALHA,
            'rodou_em' => $execucao->inicio,
            'duracao_ms' => $duracao,
            'ultima_saida' => $ok
                ? (trim((string) $saida) === '' ? null : mb_substr((string) $saida, 0, $teto))
                : mb_substr(trim((string) $erro) !== '' ? (string) $erro : (string) $saida, 0, $teto),
        ])->save();
    }

    /**
     * A janela em palavras que o painel mostra ao lado da expressão crua: "há 40 segundos",
     * "há 3 horas". A diferença entre "parou" e "ainda não era a hora" é toda aí.
     */
    public function batidaEmPalavras(): string
    {
        return $this->rodou_em === null ? 'nunca rodou' : $this->rodou_em->diffForHumans();
    }

    /**
     * Quando a expressão desta tarefa chama de novo.
     *
     * É a resposta que todo mundo procura depois de mexer numa agenda: "mudei para as três da
     * manhã, quando é que isso vale?". A expressão vem do banco e pode estar torta — o painel
     * valida na escrita, mas uma linha antiga não pode derrubar a listagem inteira.
     */
    public function proximaEmPalavras(): string
    {
        if (! $this->ativa) {
            return 'pausada';
        }

        try {
            $proxima = Carbon::instance((new CronExpression((string) $this->expressao))->getNextRunDate());
        } catch (\Throwable) {
            return 'sem previsão';
        }

        return $proxima->diffForHumans();
    }

    /** A duração em palavras, do jeito que a ficha e a listagem escrevem: 1,2 s / 4,3 min. */
    public static function duracaoPorExtenso(?int $duracaoMs): string
    {
        $ms = (int) $duracaoMs;

        if ($ms < 1000) {
            return $ms.' ms';
        }

        if ($ms < 60000) {
            return number_format($ms / 1000, 1, ',', '.').' s';
        }

        return number_format($ms / 60000, 1, ',', '.').' min';
    }

    public function duracaoEmPalavras(): string
    {
        return self::duracaoPorExtenso($this->duracao_ms);
    }

    /**
     * A tarefa deveria ter rodado e não rodou?
     *
     * A tolerância vem da preset (o dobro da janela, com folga) e não de um número fixo: uma
     * tarefa diária sem batimento há duas horas está parada, uma semanal sem batimento há duas
     * horas está no dia certo. Comparar todas com o mesmo teto é o jeito certo de encher o sino
     * de alarme falso — e alarme falso ensina a equipe a ignorar o sino inteiro.
     */
    public function estaSemBatida(): bool
    {
        if (! $this->ativa) {
            return false;
        }

        $tolerancia = (int) (config('cron.agendas')[$this->agenda]['tolerancia'] ?? 0);

        if ($tolerancia <= 0) {
            return false;
        }

        $referencia = $this->rodou_em ?? $this->created_at;

        return $referencia !== null && $referencia->copy()->addSeconds($tolerancia)->isPast();
    }
}
