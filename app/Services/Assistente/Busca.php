<?php

namespace App\Services\Assistente;

use App\Models\Assistente;
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
        'como', 'quando', 'onde', 'quem', 'e', 'ou', 'se', 'sua', 'seu', 'suas', 'seus', 'meu', 'minha',
        'meus', 'minhas', 'nosso', 'nossa', 'nossos', 'nossas',
        'você', 'voces', 'voce', 'eu', 'ele', 'ela', 'eles', 'elas', 'isto', 'isso', 'aquilo',
        'tem', 'tenho', 'tiene', 'possui', 'sobre', 'é', 'eh', 'sou', 'somos', 'ser', 'está', 'esta',
        'estao', 'sao', 'fazer', 'faz', 'mais', 'menos', 'muito', 'pouco', 'tambem', 'também',
        'ja', 'já', 'sim', 'nao', 'não', 'oi', 'ola', 'olá', 'bom', 'boa', 'por favor',
        'pra', 'pras', 'pros', 'aqui', 'ali', 'la', 'dele', 'dela', 'deles', 'delas',
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
        'agradecida', 'agradecido', 'grata', 'grato', 'vlw', 'brigada', 'blz', 'joia', 'td',
        'otimo', 'otima', 'maravilha', 'excelente', 'tranquilo', 'tranquila', 'perfeito', 'perfeita',
        'show', 'top', 'massa', 'prazer', 'feliz',
        'gentil', 'gentileza', 'simpatica', 'simpatico', 'simpatia', 'atenciosa', 'atencioso',
        'atencao', 'carinho', 'carinhosa', 'carinhoso', 'educada', 'educado', 'educacao',
        'querida', 'querido', 'linda', 'lindo', 'maravilhosa', 'maravilhoso', 'incrivel',
        'sensacional', 'prestativa', 'prestativo', 'amavel', 'humana', 'humanizada',
        'fofa', 'fofo', 'anjo', 'amor', 'parabens', 'adorei', 'amei', 'gostei',
        'abraco', 'abracos', 'beijo', 'beijos', 'bjs', 'coracao', 'deus', 'abencoe', 'amem',
        'licenca', 'desculpa', 'desculpe',
    ];

    /**
     * Vocativos, pronomes e verbos afetivos comuns em cumprimentos ("oi Ana, tudo bem com você?",
     * "como vai?", "você é muito gentil") — mantidos fora de `PARADAS` para não enfraquecer a busca.
     */
    private const COMPLEMENTOS_DE_CORTESIA = [
        'ana', 'voce', 'vc', 'moca', 'atendente', 'consultora', 'amiga', 'amigo',
        'vai', 'anda', 'esta', 'estao', 'estou', 'estamos', 'to', 'ta', 'vao', 'passa',
        'muito', 'muita', 'muitissimo', 'super', 'tao', 'demais', 'bastante',
        'hoje', 'agora', 'aqui', 'sempre', 'tambem', 'tbm', 'sim',
        'falar', 'conversar', 'tratou', 'trata', 'atendeu', 'ajudou',
        'pessoa', 'gente', 'menina', 'senhora', 'senhor', 'doutora',
        'te', 'ti', 'me', 'mim', 'comigo', 'contigo', 'nos', 'vcs',
        'pelo', 'pela', 'seu', 'sua',
        'rs', 'rsrs', 'rsrsrs', 'haha', 'hahaha', 'kkk', 'kkkk', 'kkkkk', 'hehe',
    ];

    /** Palavras que elogiam ou agradecem — desbloqueiam termos como "atendimento", "ajuda" e "trabalho". */
    private const ELOGIOS_E_GRATIDAO = [
        'obrigado', 'obrigada', 'obg', 'valeu', 'brigado', 'brigada', 'agradeco', 'agradecida',
        'agradecido', 'grata', 'grato', 'vlw', 'parabens', 'adorei', 'amei', 'gostei',
        'excelente', 'otimo', 'otima', 'maravilhoso', 'maravilhosa', 'incrivel', 'sensacional',
        'gentil', 'atencioso', 'atenciosa', 'simpatico', 'simpatica', 'perfeito', 'perfeita',
        'humanizada', 'carinho', 'carinhosa', 'carinhoso',
    ];

    private const SUBSTANTIVOS_DE_ELOGIO = [
        'atendimento', 'ajuda', 'trabalho',
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

        $query = ConhecimentoItem::query()
            ->ativos()
            ->latest('updated_at');

        if (! Assistente::permissaoAtiva('consultar_catalogo')) {
            $query->where(function ($q): void {
                $q->whereNull('referencia')->orWhere('referencia', '!=', 'golden:catalogo');
            });
        }

        $escored = $query
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

        $limpo = implode(' ', $palavras);
        $saudou = preg_match('/\bcomo\s+(?:voce\s+|vc\s+)?(?:vai|esta|anda|ta)\b/', $limpo) === 1;
        $elogiou = array_intersect($palavras, self::ELOGIOS_E_GRATIDAO) !== []
            || preg_match('/\b(?:bom|otimo|excelente)\s+(?:atendimento|trabalho)\b/', $limpo) === 1;
        $pediuDisponibilidade = in_array('tem', $palavras, true) || in_array('ha', $palavras, true);

        foreach ($palavras as $palavra) {
            if (in_array($palavra, self::CORTESIAS, true)) {
                $saudou = true;

                continue;
            }

            if (in_array($palavra, self::COMPLEMENTOS_DE_CORTESIA, true)) {
                continue;
            }

            if (in_array($palavra, self::SUBSTANTIVOS_DE_ELOGIO, true) && $elogiou && ! $pediuDisponibilidade) {
                continue;
            }

            if (! in_array($palavra, self::PARADAS, true)) {
                return false;
            }
        }

        return $saudou;
    }

    /**
     * Detecta sinais explícitos de saudação, pergunta de bem-estar, carinho/elogio ou agradecimento
     * na fala do cliente (mesmo quando vêm junto de uma pergunta sobre produtos ou serviços).
     *
     * @return array{saudacao: ?string, perguntou_bem_estar: bool, carinho_ou_elogio: bool, agradecimento: bool}
     */
    public static function sinaisDeCordialidade(string $texto): array
    {
        $limpo = trim((string) preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower($texto))));

        $saudacao = null;

        if (preg_match('/\bbom\s+dia\b/', $limpo) === 1) {
            $saudacao = 'bom dia';
        } elseif (preg_match('/\bboa\s+tarde\b/', $limpo) === 1) {
            $saudacao = 'boa tarde';
        } elseif (preg_match('/\bboa\s+noite\b/', $limpo) === 1) {
            $saudacao = 'boa noite';
        } elseif (preg_match('/\b(?:ola+|saudacoes)\b/', $limpo) === 1) {
            $saudacao = 'olá';
        } elseif (preg_match('/\b(?:oi+|opa+|e\s*ai)\b/', $limpo) === 1) {
            $saudacao = 'oi';
        }

        $perguntouBemEstar = preg_match('/\bcomo\s+(?:voce\s+|vc\s+)?(?:vai|esta|anda|ta)\b/', $limpo) === 1
            || preg_match('/\b(?:tudo|td)\s+(?:bem|bom|joia|certo|otimo|beleza)\s*(?:\?|com\s+(?:voce|vc)|e\s+(?:voce|vc|ai)|por\s+ai)/', $limpo) === 1
            || ($saudacao !== null && preg_match('/\b(?:tudo|td)\s+(?:bem|bom|joia|certo|otimo|beleza)\b/', $limpo) === 1);

        $carinhoOuElogio = preg_match(
            '/\b(?:querid[ao]|lind[ao]|gentil(?:eza)?|simpatic[ao]|simpatia|atencios[ao]|carinh(?:o|os[ao])|maravilhos[ao]|incrivel|sensacional|prestativ[ao]|amavel|human[ao]|humanizada|educad[ao]|fof[ao]|anjo|meu\s+bem|parabens|adorei|amei|gostei\s+muito|deus\s+(?:te\s+)?abencoe|bom\s+trabalho|muito\s+obrigad[ao]|obrigad[ao]\s+(?:pelo\s+carinho|pela\s+atencao|de\s+coracao))\b/',
            $limpo
        ) === 1;

        $agradecimento = preg_match('/\b(?:obrigad[ao]|obg|valeu|brigad[ao]|agradec(?:o|id[ao])|grat[ao]|vlw)\b/', $limpo) === 1;

        return [
            'saudacao' => $saudacao,
            'perguntou_bem_estar' => $perguntouBemEstar,
            'carinho_ou_elogio' => $carinhoOuElogio,
            'agradecimento' => $agradecimento,
        ];
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

        $titulosRadicais = array_map(self::radical(...), $titulos);
        $assuntosRadicais = array_map(self::radical(...), $assuntos);
        $corpoRadicais = array_map(self::radical(...), $corpo);

        $alcance = 0;
        $nota = 0.0;
        $ancorado = false;

        foreach ($palavras as $palavra) {
            $alcance += 3;
            $rad = self::radical($palavra);

            if (in_array($palavra, $titulos, true) || in_array($rad, $titulosRadicais, true)) {
                $nota += 3;
                $ancorado = true;
            } elseif (in_array($palavra, $assuntos, true) || in_array($rad, $assuntosRadicais, true)) {
                $nota += 2;
                $ancorado = true;
            } elseif (in_array($palavra, $corpo, true) || in_array($rad, $corpoRadicais, true)) {
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

    /**
     * O radical da palavra para casar plurais e flexões em português (rastreadores -> rastreador/rastre).
     */
    public static function radical(string $palavra): string
    {
        $p = Str::ascii(mb_strtolower(trim($palavra)));

        if (mb_strlen($p) <= 3) {
            return $p;
        }

        if (str_ends_with($p, 'res') && mb_strlen($p) >= 5) {
            $p = substr($p, 0, -2);
        } elseif (str_ends_with($p, 'oes') && mb_strlen($p) >= 5) {
            $p = substr($p, 0, -3).'ao';
        } elseif (str_ends_with($p, 'ais') && mb_strlen($p) >= 5) {
            $p = substr($p, 0, -3).'al';
        } elseif (str_ends_with($p, 'eis') && mb_strlen($p) >= 5) {
            $p = substr($p, 0, -3).'el';
        } elseif (str_ends_with($p, 'is') && mb_strlen($p) >= 4) {
            $p = substr($p, 0, -2).'il';
        } elseif (str_ends_with($p, 'ns') && mb_strlen($p) >= 4) {
            $p = substr($p, 0, -2).'m';
        } elseif (str_ends_with($p, 's') && mb_strlen($p) >= 4) {
            $p = substr($p, 0, -1);
        }

        if (str_starts_with($p, 'rastre') && mb_strlen($p) >= 6) {
            return 'rastre';
        }
        if (str_starts_with($p, 'instal') && mb_strlen($p) >= 6) {
            return 'instal';
        }
        if (str_starts_with($p, 'bloque') && mb_strlen($p) >= 6) {
            return 'bloque';
        }
        if (str_starts_with($p, 'localiz') && mb_strlen($p) >= 7) {
            return 'localiz';
        }
        if (str_starts_with($p, 'monitor') && mb_strlen($p) >= 7) {
            return 'monitor';
        }

        return $p;
    }

    /** O texto em palavras nuas: sem acento, sem sinal, sem espaço sobrando. */
    private static function dividir(string $texto): array
    {
        $limpo = Str::ascii(mb_strtolower($texto));
        $limpo = preg_replace('/[^a-z0-9]+/u', ' ', $limpo) ?? '';

        return array_values(array_filter(explode(' ', $limpo), static fn (string $p): bool => $p !== ''));
    }
}
