<?php

namespace App\Services\Assistente;

use Illuminate\Support\Str;

/**
 * O nome de quem fala no chat — tirado do que a pessoa diz, nunca inventado.
 *
 * A casa atende pelo nome: é a diferença entre "Prezado cliente" e "Marcelo, vou ver isso agora".
 * O assistente abre a conversa pedindo-o, e as falas seguintes precisam reconhecê-lo quando a
 * pessoa o escreve no meio de uma frase ("me chamo Ana, quero a segunda via") — se a máquina
 * perdesse o nome ali, perguntaria de novo na frase seguinte, que é o jeito mais rápido de o
 * visitante desistir do chat.
 *
 * Duas decisões que não mudam em lugar nenhum:
 *
 * **Só entra o que parece nome de pessoa.** "Sou cliente da Golden", "tudo bem", "123456" e o
 * e-mail não viram nome. Um nome errado gravado na conversa custa mais caro que a falta dele: a
 * equipe passa a chamar alguém errado, e o cliente percebe que ninguém leu nada.
 *
 * **Nada é perguntado para sempre.** `assistente.nome.perguntas_max` é o teto; passada a última
 * recusa, a conversa segue anônima. A casa não tranca atendimento atrás de uma identificação que
 * o visitante não quer dar.
 */
final class Nome
{
    /** O teto de palavras de um nome de pessoa — "Maria de Lourdes Aparecida" já é o fim. */
    private const PALAVRAS_MAX = 4;

    /**
     * O que se diz logo depois de uma destas expressões é o nome.
     *
     * Cada padrão captura até o fim da frase, e o aparador corta no primeiro sinal de pontuação:
     * é assim que "meu nome é Marcelo, quero a via da fatura" devolve "Marcelo" e não a frase
     * inteira. O ponto também encerra a captura, porque "sou a Maria. Meu e-mail é ..." é uma
     * apresentação inteira e o que vem depois do ponto é outra frase — sem isso, o nome engolia
     * o resto do texto e não era reconhecido. A ordem importa — o mais específico vem antes do
     * `sou`, que é o único que aceita assunto pela frente.
     */
    private const PADROES = [
        '/\bmeu nome\s*(?:[ée]h?\s*:?\s*)?([^\n?.]{2,60})/iu',
        '/\bnome\s*[:\-]\s*([^\n?.]{2,60})/iu',
        '/\b(?:me\s+chamo|chamo[-\s]?me|eu\s+chamo)\s+([^\n?.]{2,60})/iu',
        '/\bpod(?:e|em)\s+me\s+chamar\s+de\s+([^\n?.]{2,60})/iu',
        '/\b(?:quem\s+(?:est[aá]\s+)?falando|quem\s+fala)\s+(?:aqui\s+)?[ée]h?\s+([^\n?.]{2,60})/iu',
        '/\b(?:aqui\s+[ée]h?|do\s+outro\s+lado\s+[ée]h?)\s+(?:o|a|os|as)?\s*([^\n?.]{2,60})/iu',
        '/\bsou\s+(?:a|o|uma|um)?\s*([^\n?.]{2,60})/iu',
    ];

    /**
     * Palavras que nunca são nome de ninguém.
     *
     * Comparece a primeira palavra do que sobrou, e a lista existe por causa do `sou`: "sou a
     * Maria" é um nome, "sou cliente da Golden" não é, e o que separa as duas frases é saber que
     * "cliente" não chama pessoa nenhuma. As palavras de resposta ("sou", "estou", "acho") estão
     * aqui pelo mesmo motivo e do outro lado: quando a última fala da máquina foi a pergunta pelo
     * nome, a frase inteira é candidata, e "sou cliente da Golden" viraria vocativo se o "sou" não
     * fosse barrado. Sem acento porque a comparação é sempre pelo nome sem acento — quem digita
     * "não" no celular não é obrigado a acentuar para ser entendido.
     */
    private const NAO_SAO_NOMES = [
        'oi', 'ola', 'alo', 'eae', 'opa', 'bom', 'boa', 'bem', 'tudo', 'sim', 'nao', 'claro',
        'obg', 'obrigado', 'obrigada', 'favor', 'agradeco', 'desculpa', 'beleza', 'certo', 'ok',
        'cliente', 'email', 'telefone', 'whatsapp', 'meu', 'minha', 'seu', 'sua', 'quis', 'quero',
        'preciso', 'gostaria', 'desejo', 'queria', 'vou', 'socorro', 'ajuda', 'segunda', 'fatura',
        'boleto', 'plano', 'rastreador', 'suporte', 'conta', 'senha', 'cartao', 'ninguem', 'todos',
        'voce', 'vc', 'ela', 'ele', 'gente', 'pessoa', 'maquina', 'robo', 'inteligencia', 'como',
        'quando', 'onde', 'quem', 'qual', 'quais', 'quantos', 'porque', 'pois', 'entao', 'depois',
        'agora', 'ainda', 'sempre', 'nunca', 'talvez', 'sei', 'sabia', 'aqui', 'ali', 'la', 'ca',
        'mim', 'eu', 'isso', 'isto', 'aquilo', 'nada', 'tanta', 'tanto', 'mesmo',
        'sou', 'estou', 'somos', 'tenho', 'acho', 'penso',
    ];

    /**
     * A fala é só o nome, sem assunto pendurado?
     *
     * É a pergunta que decide se a máquina responde com o reconhecimento da casa ou se atende o
     * que a pessoa perguntou: "Maria Aparecida" não pergunta nada, "me chamo Ana, quero o preço"
     * pergunta. Tira o nome, tira as palavras de apresentação (as mesmas dos padrões acima, que só
     * servem para anunciar o nome) e o que sobrar é assunto.
     */
    public static function soONome(string $fala): bool
    {
        $nome = self::extrair($fala, respostaDireta: true);

        if ($nome === null) {
            return false;
        }

        $palavrasDoNome = array_map(self::esqueleto(...), explode(' ', $nome));

        $sobram = array_filter(
            preg_split('/\s+/u', trim($fala), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $palavra): bool => ! in_array(self::esqueleto($palavra), array_merge($palavrasDoNome, self::APRESENTACOES), true)
        );

        return $sobram === [];
    }

    /**
     * As palavras que só servem para anunciar o nome — e por isso não são assunto.
     *
     * É o vocabulário dos padrões acima, partido em palavras: em "meu nome é Ana" sobra vazio
     * depois de tirar o nome e estas linhas, e em "meu nome é Ana, quero o preço" sobra "quero o
     * preço". Sem acento porque a comparação é pelo esqueleto.
     */
    private const APRESENTACOES = [
        'meu', 'minha', 'nome', 'e', 'eh', 'chamo', 'me', 'pod', 'em', 'de', 'sou', 'a', 'o',
        'um', 'uma', 'quem', 'falo', 'falando', 'fala', 'estou', 'esta', 'aqui', 'do', 'outro',
        'lado', 'se', 'pra', 'muito', 'prazer', 'obg',
    ];

    /** Pedir o nome é regra da casa, e a casa liga ou desliga na tela de chaves. */
    public static function pedir(): bool
    {
        return (bool) config('assistente.nome.pedir');
    }

    /** Quantas vezes a máquina ainda pergunta antes de seguir sem nome. */
    public static function perguntasMax(): int
    {
        return max(1, (int) config('assistente.nome.perguntas_max', 3));
    }

    /**
     * O nome dito nesta fala, ou `null`.
     *
     * `$respostaDireta` é o estado em que a máquina acabou de perguntar o nome: aí a fala inteira
     * pode ser só o nome ("Marcelo", "Maria de Jesus"), que é como se responde a uma pergunta.
     * Sem ela, só vale nome escrito dentro de frase — ninguém seria chamado de "bom" porque
     * mandou um "bom dia" antes de qualquer pergunta.
     */
    public static function extrair(string $fala, bool $respostaDireta = false): ?string
    {
        $texto = trim($fala);

        if ($texto === '' || mb_strlen($texto) > 200) {
            return null;
        }

        // O e-mail é a resposta mais frequente a "como você se chama?" no chat, e é dado de acesso,
        // não nome: aparado no "@", "maria@exemplo.com.br" deixaria um "Maria" na ficha de alguém.
        if (filter_var($texto, FILTER_VALIDATE_EMAIL) !== false) {
            return null;
        }

        foreach (self::PADROES as $padrao) {
            if (preg_match($padrao, $texto, $parte) === 1) {
                $nome = self::aparar((string) $parte[1]);

                if ($nome !== null) {
                    return $nome;
                }
            }
        }

        return $respostaDireta ? self::aparar(self::primeiraFrase($texto)) : null;
    }

    /**
     * O candidato a nome, no tamanho e no feitio de um.
     *
     * Corta no primeiro sinal que não faz parte de nome (vírgula, dois-pontos, parêntese,
     * número), pede pelo menos duas letras por palavra e recusa as palavras de atendimento da
     * lista acima. Devolve o nome como foi escrito, só com o espaço arrumado — e troca as
     * maiúsculas apenas quando a pessoa digitou tudo em minúsculo ou tudo em caixa alta, que é o
     * jeito de quem escreve no celular, não de quem quer ser lido assim na ficha.
     */
    private static function aparar(string $candidato): ?string
    {
        $nome = trim((string) preg_replace('/[^\p{L}\p{M}\s\'\.\-].*$/su', '', $candidato));
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome), " \t\n\r\0\x0B'-.");

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 90) {
            return null;
        }

        $palavras = explode(' ', $nome);

        if (count($palavras) > self::PALAVRAS_MAX) {
            return null;
        }

        foreach ($palavras as $palavra) {
            if (strlen(self::esqueleto($palavra)) < 2) {
                return null;
            }
        }

        if (in_array(self::esqueleto($palavras[0]), self::NAO_SAO_NOMES, true)) {
            return null;
        }

        // "marcelo" e "MARCELO" são a mesma pessoa mal digitada; "Marcelo" e "marCelO" ficam
        // como a pessoa escreveu.
        if ($nome === mb_strtolower($nome) || $nome === Str::upper($nome)) {
            return Str::ucfirst(mb_strtolower($nome));
        }

        return $nome;
    }

    /** O começo da resposta até o primeiro sinal de pontuação — "Marcelo, e você?" é "Marcelo". */
    private static function primeiraFrase(string $texto): string
    {
        return trim((string) preg_replace('/[,\.;:!?\n].*$/su', '', $texto));
    }

    /** O alfabeto do nome, sem acento e sem pontuação — é assim que a lista compara. */
    private static function esqueleto(string $valor): string
    {
        $semAcento = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        return Str::lower(preg_replace('/[^A-Za-z]/', '', $semAcento) ?? '');
    }
}
