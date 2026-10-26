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

    /** Quantos itens a varredura lê por pergunta. Acima disto, a lista ativa está fora de uso. */
    private const LIMIAR_DE_VARREDURA = 800;

    /**
     * As fontes que respondem à pergunta, na ordem em que servem para a IA.
     *
     * @return array<int, array{id: int, titulo: string, texto: string, procedencia: string, pontuacao: float}>
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
            ->filter(fn (array $item): bool => $item['pontuacao'] >= $piso)
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
     */
    private static function comPontuacao(ConhecimentoItem $item, array $palavras): array
    {
        $titulos = self::dividir($item->titulo);
        $assuntos = self::dividir((string) $item->tags);
        $corpo = self::dividir($item->corpo);

        $alcance = 0;
        $nota = 0.0;

        foreach ($palavras as $palavra) {
            $alcance += 3;

            if (in_array($palavra, $titulos, true)) {
                $nota += 3;
            } elseif (in_array($palavra, $assuntos, true)) {
                $nota += 2;
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
