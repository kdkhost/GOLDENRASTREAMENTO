<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Atrasa o relógio do que já estava gravado adiantado.
 *
 * Até a versão 2.48.0 o fuso da aplicação era UTC, enquanto o MySQL da hospedagem vive em −3.
 * Toda data escrita pelo sistema saiu, portanto, três horas à frente da hora de Brasília: a fala
 * da Ana gravada às 22:30 aparecia como 01:30. Corrigir o fuso conserta o que se grava de agora
 * em diante e deixa o histórico onde estava — é esta classe que fecha a janela inteira.
 *
 * A seleção das linhas é a parte delicada, e ela tem duas metades:
 *
 * - **Antes da virada** (`< VIRADA`): só uma linha do regime velho pode ter valor tão baixo,
 *   porque nenhuma linha é gravada antes de existir. Mexer é seguro.
 * - **Na faixa duvidosa** (entre a virada e a virada + 3h): o valor de uma linha velha já
 *   adiantada cai exatamente na mesma hora do valor de uma linha nova. Aqui o relógio não decide,
 *   decide a sequência de ids: ela é a ordem cronológica real de gravação, então a volta de três
 *   horas no meio da sequência é o instante em que o código novo assumiu. Corte provado, impresso
 *   no relatório, nunca chutado.
 *
 * Só entram aqui colunas de **instante escritas pelo próprio sistema**. Data digitada por gente
 * (celebração de contrato, vencimento, aniversário, próxima ação, contagem em `expira_em`) nunca
 * foi adiantada pelo fuso — mexeria em algo que está certo.
 */
class FusoHistorico
{
    /** O instante em que o 2.48.0 assumiu a produção, na hora de Brasília. */
    public const VIRADA = '2026-10-27 22:46:45';

    /** Quantas horas o relógio da hospedagem adiantava. */
    public const HORAS = 3;

    /**
     * Os carimbos que o Eloquent escreve sozinho, em todas as tabelas, mais as colunas de instante
     * que o sistema grava a partir de `now()`. A tabela ao lado de cada coluna é a prova de que o
     * autor é o código, não a tela: `customers.ultimo_acesso_em` é `forceFill(now())`, `orders
     .* pago_em` é o modelo que marca a entrega, `visits.aberta_em` é o `Rastreio`.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const COLUNAS_DO_SISTEMA = [
        ['visits', 'aberta_em'],
        ['visits', 'ultima_em'],
        ['leads', 'primeiro_toque_em'],
        ['leads', 'won_at'],
        ['leads', 'lost_at'],
        ['customers', 'ultimo_acesso_em'],
        ['orders', 'pago_em'],
        ['orders', 'enviado_em'],
        ['conversas', 'ultima_fala_em'],
        ['conversas', 'respondido_por_equipe_em'],
        ['conversas', 'resolvido_em'],
        ['conversas', 'avaliacao_pedida_em'],
        ['conversas', 'avaliada_em'],
        ['conversas', 'acao_em'],
        ['conhecimento_itens', 'lido_em'],
        ['notificacoes', 'lida_em'],
        ['tickets', 'respondido_em'],
        ['tickets', 'resolvido_em'],
        ['cron_execucoes', 'inicio'],
        ['cron_execucoes', 'fim'],
        ['cron_tarefas', 'rodou_em'],
        ['users', 'email_verified_at'],
        ['google_ads_accounts', 'sincronizado_em'],
    ];

    /**
     * As colunas que o sistema lê e escreve por conta própria, em tabelas que não são do framework.
     * Os carimbos do Eloquent entram em todas, mas `jobs.created_at` é inteiro e `failed_jobs` é
     * caixa-preta de fila: atrasar relógio de fila não corrige nada e ainda embaralha a poda.
     *
     * @var array<int, string>
     */
    public const FORA_DA_CORRECAO = [
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'migrations',
    ];

    /**
     * O que a correção tem ordem de não encostar, mesmo parecendo data: digitado por gente ou
     * calculado para a frente. Está listado para que um teste o vigie, não para filtrar em runtime.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const INTACTAVEIS = [
        ['contracts', 'celebrado_em'],
        ['contracts', 'vigencia_ate'],
        ['customers', 'nascimento'],
        ['interactions', 'proxima_acao_em'],
        ['funnel_blocks', 'limite_em'],
        ['notificacoes', 'expira_em'],
        ['conversas', 'acao_pendente_em'],
        ['failed_jobs', 'failed_at'],
    ];

    /**
     * Atrasa o histórico e devolve o relatório do que foi mexido, linha por coluna.
     *
     * @return array{virada: string, faixa_ate: string, horas: int, colunas: array<int, array<string, mixed>>}
     */
    public static function corrigir(bool $simular = false, ?string $virada = null, int $horas = self::HORAS): array
    {
        $virada = $virada ?? self::VIRADA;
        // A faixa é a largura do atraso, nunca o sinal: desfazer o passe (`horas` negativo) precisa
        // percorrer exatamente as mesmas linhas que a correção percorreu.
        $fimDaFaixa = date('Y-m-d H:i:s', (int) strtotime($virada) + abs($horas) * 3600);

        $relatorio = [];

        foreach (self::alvos() as [$tabela, $coluna]) {
            $antes = self::selecionar($tabela, $coluna, $virada, $fimDaFaixa, false);
            $corte = self::corteDaFaixa($tabela, $coluna, $virada, $fimDaFaixa);
            $naFaixa = self::selecionar($tabela, $coluna, $virada, $fimDaFaixa, true, $corte);

            if (($mexidas = array_merge($antes, $naFaixa)) !== []) {
                self::atrasar($tabela, $coluna, $mexidas, $horas, $simular);
            }

            $relatorio[] = [
                'tabela' => $tabela,
                'coluna' => $coluna,
                'adiantadas' => count($antes),
                'corte' => $corte,
                'na_faixa' => count($naFaixa),
                'sobram_na_faixa' => $corte === null ? self::contarNaFaixa($tabela, $coluna, $virada, $fimDaFaixa) : 0,
            ];
        }

        return ['virada' => $virada, 'faixa_ate' => $fimDaFaixa, 'horas' => $horas, 'colunas' => $relatorio];
    }

    /**
     * As colunas que de fato existem no banco agora e são de instante.
     *
     * O carimbo `created_at` existe até em tabela de fila, onde é inteiro: a checagem de tipo é o
     * que impede a correção de transformar um `1730000000` em 1969. A checagem de existência é o
     * que segura uma instalação pela metade, em que ainda não nasceu a tabela do assistente.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function alvos(): array
    {
        $alvos = self::COLUNAS_DO_SISTEMA;

        foreach (self::tabelas() as $tabela) {
            $alvos[] = [$tabela, 'created_at'];
            $alvos[] = [$tabela, 'updated_at'];
        }

        $deInstante = [];

        foreach ($alvos as [$tabela, $coluna]) {
            if (in_array($tabela, self::FORA_DA_CORRECAO, true) || ! self::eInstante($tabela, $coluna)) {
                continue;
            }

            $deInstante[] = [$tabela, $coluna];
        }

        return $deInstante;
    }

    /** As tabelas do banco de agora, menos as do framework, que o `alvos()` já recusa. */
    private static function tabelas(): array
    {
        try {
            return array_map(
                fn (array $t): string => $t['name'],
                Schema::getTables()
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * O tipo da coluna na parede do banco: só `timestamp` e `datetime` são instante de relógio.
     *
     * Data pura (`date`) é dia de calendário — aniversário, celebração de contrato — e não tem
     * hora para atrasar.
     */
    private static function eInstante(string $tabela, string $coluna): bool
    {
        static $memoria = [];

        $chave = $tabela.'.'.$coluna;

        if (isset($memoria[$chave])) {
            return $memoria[$chave];
        }

        try {
            $tipos = array_column(
                array_filter(Schema::getColumns($tabela), fn (array $c): bool => $c['name'] === $coluna),
                'type_name'
            );

            $memoria[$chave] = in_array(strtolower((string) ($tipos[0] ?? '')), ['timestamp', 'datetime'], true);
        } catch (Throwable) {
            $memoria[$chave] = false;
        }

        return $memoria[$chave];
    }

    /**
     * O id da primeira linha do regime novo dentro da faixa duvidosa, ou null se não há volta.
     *
     * Id é auto-incremento: percorrer a faixa por id é percorrer a ordem real de gravação. Enquanto
     * o código velho manda, cada valor é três horas à frente do anterior e a sequência sobe; no
     * instante em que o código novo assume, o relógio volta. A maior volta de pelo menos uma hora é
     * o corte. Sem volta clara, nada é mexido na faixa — e as linhas ficam no relatório para um
     * humano decidir, porque chute aqui custa dado certo.
     */
    private static function corteDaFaixa(string $tabela, string $coluna, string $de, string $ate): ?int
    {
        if (! self::eInstante($tabela, $coluna)) {
            return null;
        }

        $anterior = null;
        $melhorVolta = 0.0;
        $corte = null;

        foreach (DB::table($tabela)->select(['id', $coluna])->whereBetween($coluna, [$de, $ate])->orderBy('id')->get() as $linha) {
            if ($anterior !== null) {
                $volta = (strtotime($anterior) - strtotime((string) $linha->$coluna)) / 3600;

                if ($volta >= 1 && $volta > $melhorVolta) {
                    $melhorVolta = $volta;
                    $corte = (int) $linha->id;
                }
            }

            $anterior = (string) $linha->$coluna;
        }

        return $corte;
    }

    /**
     * Os ids e valores adiantados de uma coluna: ou tudo antes da virada, ou a faixa duvidosa até
     * o corte. Null no corte significa "ainda não se sabe", e nesse caso nada é selecionado.
     *
     * @return array<int, array{id: int, valor: string}>
     */
    private static function selecionar(string $tabela, string $coluna, string $virada, string $fimDaFaixa, bool $naFaixa, ?int $corte = null): array
    {
        if (! self::eInstante($tabela, $coluna) || ($naFaixa && $corte === null)) {
            return [];
        }

        $consulta = DB::table($tabela);

        if ($naFaixa) {
            $consulta->whereBetween($coluna, [$virada, $fimDaFaixa])->where('id', '<', $corte);
        } else {
            $consulta->where($coluna, '<', $virada);
        }

        $selecionados = [];

        foreach ($consulta->select(['id', $coluna])->orderBy('id')->get() as $linha) {
            $selecionados[] = ['id' => (int) $linha->id, 'valor' => (string) $linha->$coluna];
        }

        return $selecionados;
    }

    /** Quantas linhas caem na faixa duvidosa desta coluna — o que fica para um humano quando não há corte. */
    private static function contarNaFaixa(string $tabela, string $coluna, string $de, string $ate): int
    {
        if (! self::eInstante($tabela, $coluna)) {
            return 0;
        }

        return DB::table($tabela)->whereBetween($coluna, [$de, $ate])->count();
    }

    /**
     * Escreve o valor três horas mais cedo, na parede da mesma forma como foi lido.
     *
     * Vai pelo query builder, e não pelo modelo, porque salvar em Eloquent recarimbaria
     * `updated_at` e faria o histórico parecer atividade de hoje.
     *
     * @param  array<int, array{id: int, valor: string}>  $linhas
     */
    private static function atrasar(string $tabela, string $coluna, array $linhas, int $horas, bool $simular): void
    {
        if ($simular) {
            return;
        }

        $atraso = $horas * 3600;

        foreach ($linhas as $linha) {
            DB::table($tabela)
                ->where('id', $linha['id'])
                ->update([$coluna => date('Y-m-d H:i:s', (int) strtotime($linha['valor']) - $atraso)]);
        }
    }

}
