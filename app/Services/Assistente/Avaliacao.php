<?php

namespace App\Services\Assistente;

use App\Models\Conversa;
use App\Models\ConversaMensagem;
use Illuminate\Support\Str;

/**
 * A nota do atendimento da Ana — quem pede, como se lê e onde fica gravada.
 *
 * A casa só sabe se o robô atende bem por uma coisa que a máquina não pode medir sozinha: o que a
 * pessoa achou. Por isso o fim de um atendimento tem duas saídas — a ação que se encerrou e a
 * despedida dita no chat — e as duas passam por esta classe, que é o único lugar onde se decide
 * quando pedir e onde a resposta é gravada.
 *
 * Três decisões que não mudam em lugar nenhum:
 *
 * **Se pede uma vez só.** `conversas.avaliacao_pedida_em` é a prova. Pesquisa que insiste é a
 * maneira mais rápida de estragar a nota que ela ia colher.
 *
 * **Nada de IA aqui.** O pedido e o agradecimento são texto da ficha, e a nota é lida por regra,
 * não por modelo: uma frase digitada numa conversa que já teve 40 chamadas de IA não precisa de
 * mais uma.
 *
 * **A nota da máquina não confunde com a da equipe.** Se um humano assumiu o fio, a pesquisa não
 * abre — a pessoa avaliaria o atendente errado, e a casa tomaria decisão sobre quem não atendeu.
 */
final class Avaliacao
{
    public const NOTA_MAX = 5;

    /**
     * O que encerra a conversa na boca de quem escreve no celular.
     *
     * Lidas sem acento e em minúsculas, do mesmo jeito dos gatilhos de ação: "obrigado, era isso",
     * "valeu, era só isso" e "até mais" são despedida, e é nelas que a casa quer a nota. A lista é
     * estreita de propósito — "valeu" sozinho no meio de um assunto não pode virar pesquisa.
     */
    private const DESPEDIDAS = [
        '/\b(?:era|eh)\s+(?:so|isso|apenas|só)\s+(?:isso|que\s+isso)?\b/',
        '/\bpor\s+hoje\s+eh\s+(?:isso|so\s+isso)\b/',
        '/\b(?:obrigad[ao]|obg|valeu|brigado)\s*[.,!]?\s*(?:era|por\s+agora|tanto|mesmo)?\b.{0,12}\b(?:isso|agora|mais)\b/',
        '/\b(?:ate\s+(?:mais|logo|um\s+momento)|tenha\s+um\s+otimo\s+dia|boa\s+(?:noite|tarde|manha))\b/',
        '/\b(?:pode\s+(?:encerrar|fechar)|encerra\s+(?:ai|por)\s+favou?|acabou\s+por\s+aqui)\b/',
    ];

    /** Os números por extenso, porque nem todo mundo digita 5 num celular. */
    private const EXTENSO = [
        'um' => 1, 'uma' => 1, 'dois' => 2, 'duas' => 2, 'tres' => 3,
        'quatro' => 4, 'cinco' => 5,
    ];

    /** Pedir a nota é regra da casa, e a casa liga ou desliga na tela de chaves. */
    public static function pedir(): bool
    {
        return (bool) config('assistente.avaliacao.pedir');
    }

    /** Quantas falas da máquina antes de incomodar com pesquisa. */
    public static function minimoDeFalas(): int
    {
        return max(1, (int) config('assistente.avaliacao.minimo_de_falas', 3));
    }

    /** A fala é uma despedida? */
    public static function eDespedida(string $fala): bool
    {
        $texto = self::semAcento($fala);

        foreach (self::DESPEDIDAS as $padrao) {
            if (preg_match($padrao, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ainda é hora de pedir? Uma vez só, com conversa de verdade, e só enquanto o atendimento é da
     * máquina — a régua inteira está numa linha, porque as duas saídas (ação encerrada e despedida)
     * precisam bater com exatamente o mesmo critério.
     */
    public static function horaDePedir(Conversa $conversa): bool
    {
        if (! self::pedir() || $conversa->avaliacao_pedida_em !== null) {
            return false;
        }

        if ($conversa->respondido_por_equipe_em !== null || $conversa->status === 'aguardando_equipe') {
            return false;
        }

        return $conversa->mensagens()->where('autor', 'assistente')->count() >= self::minimoDeFalas();
    }

    /**
     * A nota escrita no chat, e o que a pessoa disse junto — ou `null` quando a fala não é uma
     * resposta à pesquisa.
     *
     * Só é lida como nota depois de a máquina ter perguntado: "4" no meio de uma conferência é dado
     * de cadastro, e interpretá-lo como nota roubaria a resposta do passo certo. Aceita o número
     * sozinho, o `nota 4`, o `4`, o `quatro` por extenso e o número pendurado num comentário
     * ("5, parabéns pelo atendimento") — o que sobra da frase vira o texto da avaliação.
     *
     * @return ?array{nota: int, texto: string}
     */
    public static function leia(string $fala): ?array
    {
        $texto = trim($fala);

        if ($texto === '' || mb_strlen($texto) > 400) {
            return null;
        }

        $alvo = self::semAcento($texto);
        $nota = null;

        if (preg_match('/^(?:nota\s*(?:de\s*)?)?([1-5])(?![\d])/', $alvo, $parte) === 1) {
            $nota = (int) $parte[1];
        } elseif (preg_match('/^(?:uma?\s+)?([1-5])\s*estrela/', $alvo, $parte) === 1) {
            $nota = (int) $parte[1];
        } else {
            foreach (self::EXTENSO as $palavra => $valor) {
                if (preg_match('/^(?:uma?\s+)?' . $palavra . '(?:\s+estrelas?)?$/', $alvo) === 1) {
                    $nota = $valor;

                    break;
                }
            }
        }

        if ($nota === null) {
            return null;
        }

        $resto = trim((string) preg_replace(
            '/^\s*(?:nota\s*(?:de\s*)?)?(?:[1-5](?:\s*(?:de|\/)?\s*' . self::NOTA_MAX . ')?|\w+)(?:\s*estrelas?)?[\s.,;:!-]*/iu',
            '',
            $texto,
            1
        ));

        return [
            'nota' => $nota,
            'texto' => Str::limit($resto, 500, ''),
        ];
    }

    /**
     * A pesquisa foi pedida e ainda não tem nota? É o que diz à bolha acender as estrelas: a
     * pergunta já está na tela, e o toque é uma resposta tão válida quanto a nota escrita.
     */
    public static function pedidaSemNota(Conversa $conversa): bool
    {
        return $conversa->avaliacao_pedida_em !== null && $conversa->avaliacao_nota === null;
    }

    /**
     * Grava a nota na conversa e deixa a fala de sistema no histórico.
     *
     * A coluna é o que o painel soma; a fala é o que a equipe lê quando abre a conversa. Uma das
     * duas sem a outra deixa um atendimento avaliado que ninguém consegue revisar.
     */
    public static function grave(Conversa $conversa, int $nota, ?string $texto = null): ConversaMensagem
    {
        $nota = max(1, min(self::NOTA_MAX, $nota));
        $texto = Str::limit(trim((string) $texto), 500, '');

        $conversa->forceFill([
            'avaliacao_nota' => $nota,
            'avaliacao_texto' => $texto !== '' ? $texto : null,
            'avaliada_em' => now(),
            'avaliacao_pedida_em' => $conversa->avaliacao_pedida_em ?? now(),
        ])->save();

        return ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'sistema',
            'tipo' => 'texto',
            'corpo' => 'Avaliação do atendimento: ' . $nota . '/' . self::NOTA_MAX
                . ($texto !== '' ? ' — ' . $texto : ''),
        ]);
    }

    /** O texto do chat pronto para as regex: minúsculo, sem acento, sem pontuação grudada. */
    private static function semAcento(string $texto): string
    {
        $semAcento = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return Str::lower(trim(preg_replace('/[^A-Za-z0-9\s]+/', ' ', $semAcento) ?? ''));
    }
}
