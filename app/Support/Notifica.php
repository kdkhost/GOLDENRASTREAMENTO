<?php

namespace App\Support;

use App\Models\Notificacao;
use App\Models\NotificacaoPreferencia;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A única porta de entrada da central de notificações.
 *
 * Um evento do sistema avisa por aqui e não precisa saber quem é a equipe, quantas pessoas têm
 * acesso ao painel, o que cada uma delas desligou no sino, nem quando o aviso vence. Tudo isso é
 * regra desta camada, escrita uma vez: os pontos de disparo ficam com uma linha só, e a
 * consequência — "mudou o catálogo, mudou quem é avisado" — vale para todos ao mesmo tempo.
 *
 * A decisão de design é que notificar nunca quebra o que notificou. Cada disparo devolve o
 * número de avisos criados em vez de atirar exceção: um aviso que falha é um incômodo, e o
 * cadastro do cliente que gerou aquele aviso é a receita de uma tela quebrada no meio do
 * formulário por causa de um sino cheio.
 *
 * O e-mail continua sendo responsabilidade de quem dispara. Esta camada não envia nada — ela é o
 * canal de dentro do painel, e um canal não substitui o outro.
 */
class Notifica
{
    /**
     * Abre o aviso para a audiência do tipo e devolve quantas linhas entraram.
     *
     * `chave` é o freio de repetição: o mesmo fato para a mesma pessoa não entra duas vezes.
     * Quem passa a chave decide o que é "o mesmo fato" — o lead 44 é sempre o mesmo lead; uma
     * falha de cron só é a mesma falha dentro do mesmo dia.
     */
    public static function disparar(
        string $tipo,
        string $titulo,
        ?string $texto = null,
        ?string $rotaParam = null,
        array $dados = [],
        ?string $chave = null,
    ): int {
        if (! static::centralPronta()) {
            return 0;
        }

        $item = (array) (config("notificacoes.tipos.{$tipo}") ?? []);

        if ($item === []) {
            return 0;
        }

        $teto = (int) config('notificacoes.texto_maximo', 500);
        $expiraEm = static::expiracaoDoTipo($tipo, $item);
        $criadas = 0;

        foreach (static::audiencia((string) ($item['audiencia'] ?? 'equipe')) as $usuario) {
            if (! NotificacaoPreferencia::emPainel($usuario, $tipo)) {
                continue;
            }

            $atributos = [
                'tipo' => $tipo,
                'titulo' => mb_substr($titulo, 0, 190),
                'texto' => $texto === null ? null : mb_substr($texto, 0, $teto),
                'rota' => $item['rota'] ?? null,
                'rota_param' => $rotaParam,
                'dados_json' => $dados === [] ? null : $dados,
                'user_id' => $usuario->id,
                'chave' => $chave,
                'expira_em' => $expiraEm,
            ];

            // Com chave, a escrita é "se ainda não existe"; sem chave, é sempre uma linha nova.
            // O try/catch é a última barreira: duas requisições concorrentes podem chegar juntas
            // no mesmo `exists`, e quem perde leva o erro de unicidade do banco.
            try {
                $aviso = $chave === null
                    ? Notificacao::create($atributos)
                    : Notificacao::firstOrCreate(
                        ['user_id' => $usuario->id, 'chave' => $chave],
                        $atributos
                    );

                // A contagem é do que entrou agora: o aviso que já estava lá não é um aviso novo,
                // e devolver 1 para ele faria o chamador achar que notificou de novo.
                if ($aviso->wasRecentlyCreated) {
                    $criadas++;
                }
            } catch (\Throwable $falha) {
                report($falha);
            }
        }

        return $criadas;
    }

    /**
     * Para quem este tipo de aviso vai.
     *
     * `equipe` são os papéis de painel listados na config; `admin` é só quem configura a
     * plataforma. A conta inativa fica de fora: notificar quem não pode entrar no painel é
     * encher o banco de aviso que ninguém vai ler — e a poda ainda teria que varrer depois.
     */
    public static function audiencia(string $audiencia): Collection
    {
        $papeis = $audiencia === 'admin'
            ? ['admin']
            : (array) config('notificacoes.papeis_equipe', ['admin', 'vendedor']);

        return User::query()
            ->whereIn('role', $papeis)
            ->where('status', 'ativo')
            ->orderBy('id')
            ->get();
    }

    /**
     * Até quando um aviso deste tipo aguenta sem ser lido. O painel manda (a chave geral fica no
     * banco); o catálogo define a exceção por tipo; o config é o plano de fundo de instalação.
     */
    private static function expiracaoDoTipo(string $tipo, array $item): ?\Carbon\CarbonInterface
    {
        $dias = (int) ($item['expiracao_dias']
            ?? Setting::get('notificacoes_expiracao_dias', config('notificacoes.expiracao_dias_padrao', 15)));

        return $dias > 0 ? now()->addDays($dias) : null;
    }

    /**
     * A central existe neste banco?
     *
     * O `hasTable` não é preciosismo: todo disparo desta camada acontece em caminho de pedido —
     * captura de lead, checkout, chamado do portal — e numa instalação que acabou de clonar o
     * código e ainda não rodou `migrate` esses caminhos existem antes das tabelas. Cair fora em
     * silêncio é o comportamento certo: o cadastro do cliente não pode falhar porque o sino ainda
     * não foi construído.
     */
    public static function centralPronta(): bool
    {
        try {
            return Schema::hasTable('notificacoes');
        } catch (\Throwable) {
            return false;
        }
    }
}
