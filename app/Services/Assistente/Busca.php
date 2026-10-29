<?php

namespace App\Services\Assistente;

use App\Models\ConhecimentoItem;
use Illuminate\Support\Str;

/**
 * A busca que decide se o assistente tem com o que responder.
 *
 * É léxica, feita em PHP sobre `conhecimento_itens`, e não sem motivo: a hospedagem é
 * compartilhada — não há banco vetorial nem servidor de busca, e um atendimento que depende de
 * índice vetorial para funcionar é um atendimento que para. O que se perde em sutileza ganha-se
 * em duas coisas que importam mais aqui: a resposta cabe em qualquer servidor e a equipe consegue
 * ler *por que* aquele item foi escolhido, porque a pontuação é contagem de palavra, não distância
 * numérica de um vetor que ninguém audita.
 *
 * A pontuação é cobertura ponderada: para cada palavra da pergunta, vale 3 se ela aparece no
 * título, 2 se está nos assuntos e 1 se está no corpo. O resultado é um número entre zero e um, e
 * `assistente.resposta.minimo_de_fonte` é o piso a partir do qual se pode chamar aquilo de fonte.
 * Abaixo do piso, a máquina não responde: escala.
 *
 * A cobertura sozinha puniria quem escreve uma pergunta comprida: "como acesso a plataforma de
 * monitoramento?" cobre metade de um item cujo título é exatamente "plataforma" e fica abaixo do
 * piso. Por isso existe a âncora — uma palavra que o administrador pôs no título ou nos assuntos
 * daquele item é sinal curado de que o item trata do assunto, e vence o piso. Palavra achada só no
 * corpo continua devendo a cobertura: o corpo é texto corrido, e ali toda palavra aparece.
 */
final class Busca
{
    /** Palavras que não distinguem nada e só diluem a conta. */
    private const PARADAS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'no', 'na',
        'nos', 'nas', 'ao', 'aos', 'em', 'por', 'para', 'pro', 'com', 'sem', 'que', 'qual', 'quais',
        'como', 'quando', 'onde', 'quem', 'e', 'ou', 'se', 'sua', 'seu', 'suas', 'meu', 'minha',
        'você', 'voces', 'voce', 'eu', 'ele', 'ela', 'eles', 'elas', 'isto', 'isso', 'aquilo',
        'tem', 'tenho', 'tiene', 'possui', 'sobre', 'é', 'eh', 'sou', 'somos', 'ser', 'está', 'esta',
        'estao', 'sao', 'fazer', 'faz', 'mais', 'menos', 'muito', 'pouco', 'tambem', 'também',
        'ja', 'já', 'sim', 'nao', 'não', 'oi', 'ola', 'olá', 'bom', 'boa', 'por favor',
    ];

    /**
     * Saudações e agradecimentos: as palavras que abrem ou fecham uma conversa sem perguntar fato
     * nenhum. Ficam separadas das `PARADAS` porque o atendimento precisa distingui-las — uma fala
     * feita só disto é cortesia, e cortesia se responde mesmo sem fonte; "tem como?" é feito só de
     * palavras vazias, mas não é cortesia, e continua devendo uma fonte para ser respondida.
     */
    private const CORTESIAS = [
        'oi', 'ola', 'opa', 'eai', 'ai', 'bom', 'boa', 'dia', 'tarde', 'noite', 'tudo', 'bem',
        'beleza', 'saudacoes', 'favor', 'obrigado', 'obrigada', 'obg', 'valeu', 'brigado', 'agradeco',
    ];

    /** Quantos itens a varredura lê por pergunta. Acima disto, a lista ativa está fora de uso. */
    private const LIMIAR_DE_VARREDURA = 800;

    /**
     * As fontes que respondem à pergunta, na ordem em que servem para a IA.
     *
     * @return array<int, array{id: int, titulo: string, texto: string, procedencia: string, pontuacao: float, ancorado: bool}>
     */
    public static function fontes(string $pergunta, ?int $limite = null): array
    {
        $palavras = self::palavrasDaPergunta($pergunta);

        if ($palavras === []) {
            return [];
        }

        $limite ??= (int) config('assistente.resposta.itens_de_fonte');
        $piso = (float) config('assistente.resposta.minimo_de_fonte');

        $escored = ConhecimentoItem::query()
            ->ativos()
            ->latest('updated_at')
            ->limit(self::LIMIAR_DE_VARREDURA)
            ->get()
            ->map(fn (ConhecimentoItem $item): array => self::comPontuacao($item, $palavras))
            ->filter(fn (array $item): bool => $item['pontuacao'] >= $piso || $item['ancorado'])
            ->sortByDesc('pontuacao')
            ->take($limite)
            ->values()
            ->all();

        return $escored;
    }

    /** Há fonte confiável para esta pergunta? É a pergunta que decide responder ou escalar. */
    public static function temFonte(string $pergunta): bool
    {
        return self::fontes($pergunta, 1) !== [];
    }

    /**
     * A fala é só cortesia — "oi", "bom dia", "tudo bem?" — e não pergunta fato nenhum? Nela não há
     * o que o conhecimento da casa responda: a máquina cumprimenta de volta, e é o único caso em
     * que o atendimento pode abrir a boca sem fonte.
     */
    public static function eCortesia(string $texto): bool
    {
        $palavras = self::dividir($texto);

        if ($palavras === []) {
            return false;
        }

        $saudou = false;

        foreach ($palavras as $palavra) {
            if (in_array($palavra, self::CORTESIAS, true)) {
                $saudou = true;

                continue;
            }

            if (! in_array($palavra, self::PARADAS, true)) {
                return false;
            }
        }

        return $saudou;
    }

    /**
     * As palavras que contam numa pergunta: sem acento, sem pontuação, com pelo menos três letras
     * e fora da lista de palavras vazias. O mesmo tratamento se aplica aos itens, e é isso que faz
     * "quanto custa o rastreador?" encontrar um texto escrito "valor do rastreador".
     *
     * @return array<int, string>
     */
    public static function palavrasDaPergunta(string $texto): array
    {
        return array_values(array_unique(array_filter(
            self::dividir($texto),
            static fn (string $palavra): bool => mb_strlen($palavra) >= 3 && ! in_array($palavra, self::PARADAS, true)
        )));
    }

    /**
     * A nota de um item: para cada palavra da pergunta, o melhor lugar em que ela foi achada
     * (título pesa 3, assuntos 2, corpo 1) sobre o máximo possível. Dividir pelo total de palavras
     * é o que torna a nota comparável entre perguntas de três e de quinze palavras.
     *
     * `ancorado` nasce verdadeiro quando uma palavra da pergunta está no título ou nos assuntos —
     * os dois campos que o administrador escreve à mão para dizer do que o item trata.
     */
    private static function comPontuacao(ConhecimentoItem $item, array $palavras): array
    {
        $titulos = self::dividir($item->titulo);
        $assuntos = self::dividir((string) $item->tags);
        $corpo = self::dividir($item->corpo);

        $alcance = 0;
        $nota = 0.0;
        $ancorado = false;

        foreach ($palavras as $palavra) {
            $alcance += 3;

            if (in_array($palavra, $titulos, true)) {
                $nota += 3;
                $ancorado = true;
            } elseif (in_array($palavra, $assuntos, true)) {
                $nota += 2;
                $ancorado = true;
            } elseif (in_array($palavra, $corpo, true)) {
                $nota += 1;
            }
        }

        return [
            'id' => (int) $item->id,
            'titulo' => (string) $item->titulo,
            'texto' => $item->textoParaIA(),
            'procedencia' => $item->procedencia(),
            'pontuacao' => $alcance === 0 ? 0.0 : round($nota / $alcance, 4),
            'ancorado' => $ancorado,
        ];
    }

    /** O texto em palavras nuas: sem acento, sem sinal, sem espaço sobrando. */
    private static function dividir(string $texto): array
    {
        $limpo = Str::ascii(mb_strtolower($texto));
        $limpo = preg_replace('/[^a-z0-9]+/u', ' ', $limpo) ?? '';

        return array_values(array_filter(explode(' ', $limpo), static fn (string $p): bool => $p !== ''));
    }
}
