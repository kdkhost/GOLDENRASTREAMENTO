<?php

namespace App\Services\GoogleAds;

/**
 * O plano que o copiloto devolve, conferido contra as travas do Google antes de virar campanha.
 *
 * A IA escreve JSON; este serviço é o que transforma texto de modelo em plano que a API aceita.
 * Nada aqui confia no que veio: título com 34 caracteres é descartado e contado, descrição a
 * menos vira problema, orçamento fora da faixa da casa é preso no limite com aviso. O resultado
 * tem duas listas — `problemas`, que impedem aprovar e aplicar, e `avisos`, que a pessoa lê e
 * decide se importa.
 *
 * A normalização é o único lugar que conhece os limites do anúncio responsivo. A tela de
 * criativo e o editor já leem `config('google_ads.rsa')`; aqui os mesmos números valem para o
 * que a IA escreveu, senão um anúncio passaria na tela e seria recusado pelo Google.
 */
final class PlanoDeCampanha
{
    /** Os valores de correspondência que a API entende, por chave em português. */
    private const CORRESPONDENCIA = [
        'ampla' => 'BROAD', 'broad' => 'BROAD',
        'frase' => 'PHRASE', 'phrase' => 'PHRASE',
        'exata' => 'EXACT', 'exact' => 'EXACT',
    ];

    /** As estratégias que o copiloto pode pedir; o resto cai no padrão da casa. */
    private const ESTRATEGIAS = ['MAXIMIZE_CLICKS', 'MANUAL_CPC'];

    /**
     * Confere e normaliza um plano bruto.
     *
     * O briefing entra junto porque é a fonte de último recurso: se o modelo esqueceu a URL, a
     * região ou o orçamento, o que a pessoa escreveu na tela ainda vale — e a tela avisa de onde
     * veio o valor em vez de inventar.
     *
     * @return array{plano: array<string, mixed>, problemas: array<int, string>, avisos: array<int, string>, forca: array{nota:int, rotulo:string, chave:string}}
     */
    public static function conferir(array $bruto, array $briefing = []): array
    {
        $problemas = [];
        $avisos = [];

        $plano = [
            'nome' => self::texto($bruto['nome'] ?? $briefing['nome'] ?? '', 120),
            'objetivo' => (string) ($bruto['objetivo'] ?? $briefing['objetivo'] ?? 'leads'),
            'estrategia_lance' => strtoupper(self::texto($bruto['estrategia_lance'] ?? 'MAXIMIZE_CLICKS', 30)),
            'cpc_maximo_centavos' => self::inteiro($bruto['cpc_maximo_centavos'] ?? 200),
            'url' => self::urlDe($bruto['url'] ?? $briefing['url'] ?? ''),
            'regioes' => self::lista($bruto['regioes'] ?? $briefing['regioes'] ?? []),
            'idiomas' => self::lista($bruto['idiomas'] ?? []),
            'grupos' => [],
            'anuncios' => [
                'titulos' => [],
                'descricoes' => [],
                'caminhos' => [],
            ],
            'justificativa' => self::texto($bruto['justificativa'] ?? '', 2000),
        ];

        if ($plano['nome'] === '') {
            $plano['nome'] = 'Campanha de rastreamento';
            $avisos[] = 'O plano não trouxe nome de campanha; a tela usou um nome padrão.';
        }

        if (! array_key_exists($plano['objetivo'], (array) config('google_ads.copiloto.objetivos'))) {
            $plano['objetivo'] = 'leads';
            $avisos[] = 'O objetivo informado não é um dos da casa; o plano foi tratado como "mais contatos".';
        }

        if (! in_array($plano['estrategia_lance'], self::ESTRATEGIAS, true)) {
            $plano['estrategia_lance'] = 'MAXIMIZE_CLICKS';
            $avisos[] = 'A estratégia de lance pedida não é oferecida; o plano ficou em "maximizar cliques".';
        }

        $plano['orcamento_diario_centavos'] = self::orcamento($bruto, $briefing, $avisos);

        if ($plano['url'] === '') {
            $problemas[] = 'Falta o endereço de destino do anúncio (uma página do site, começando com https://).';
        }

        if ($plano['regioes'] === []) {
            $problemas[] = 'Falta pelo menos uma região para a campanha atingir.';
        }

        if ($plano['idiomas'] === []) {
            $plano['idiomas'] = [(string) config('google_ads.copiloto.idioma_rotulo', 'Português')];
        }

        [$plano['grupos'], $problemas, $avisos] = self::grupos($bruto['grupos'] ?? [], $problemas, $avisos);
        [$plano['anuncios'], $problemas, $avisos] = self::anuncios($bruto['anuncios'] ?? [], $problemas, $avisos);

        $forca = self::forca($plano);

        return [
            'plano' => $plano,
            'problemas' => $problemas,
            'avisos' => $avisos,
            'forca' => $forca,
        ];
    }

    /**
     * O orçamento diário em centavos, preso entre o mínimo e o máximo da casa.
     *
     * O modelo pode devolver `orcamento_diario` em reais (o formato do formulário) ou
     * `orcamento_diario_centavos` (o formato do banco). Os dois são aceitos; o que não pode é
     * um valor absurdo virar gasto — fora da faixa, ele é preso no limite e a tela avisa.
     */
    private static function orcamento(array $bruto, array $briefing, array &$avisos): int
    {
        $minimo = (int) config('google_ads.copiloto.orcamento_minimo_centavos', 1000);
        $maximo = (int) config('google_ads.copiloto.orcamento_maximo_centavos', 50000);

        if (array_key_exists('orcamento_diario_centavos', $bruto)) {
            $centavos = self::inteiro($bruto['orcamento_diario_centavos']);
        } elseif (array_key_exists('orcamento_diario', $bruto)) {
            $centavos = self::reaisParaCentavos($bruto['orcamento_diario']);
        } else {
            $centavos = self::reaisParaCentavos($briefing['orcamento'] ?? 0);
        }

        if ($centavos <= 0) {
            $centavos = $minimo;
            $avisos[] = 'O plano não informou orçamento diário; a tela partiu do mínimo da casa, '.brl($minimo).'.';
        }

        if ($centavos < $minimo) {
            $avisos[] = 'O orçamento do plano ('.brl($centavos).') ficou abaixo do mínimo da casa; foi ajustado para '.brl($minimo).'.';
            $centavos = $minimo;
        }

        if ($centavos > $maximo) {
            $avisos[] = 'O orçamento do plano ('.brl($centavos).') passou do teto da casa; foi ajustado para '.brl($maximo).'.';
            $centavos = $maximo;
        }

        return $centavos;
    }

    /**
     * Os grupos de anúncios, com palavras-chave e negativas.
     *
     * Um grupo sem palavra-chave não pode existir no Google, e um plano sem grupo não tem
     * anúncio. Palavra longa demais ou fora do padrão é descartada e contada — a frase que
     * sobra é a que a pessoa lê, sem precisar abrir o JSON.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, string>}
     */
    private static function grupos(mixed $brutos, array $problemas, array $avisos): array
    {
        $grupos = [];
        $descartadas = 0;
        $maximo = (int) config('google_ads.copiloto.grupos_max', 8);
        $porGrupo = (int) config('google_ads.copiloto.palavras_por_grupo_max', 25);
        $negativasMax = (int) config('google_ads.copiloto.negativas_max', 60);

        foreach (self::itens($brutos) as $bruto) {
            if (! is_array($bruto)) {
                continue;
            }

            $nome = self::texto($bruto['nome'] ?? '', 60);

            if ($nome === '') {
                continue;
            }

            $palavras = [];
            $vistas = [];

            foreach (self::itens($bruto['palavras_chave'] ?? []) as $item) {
                [$texto, $correspondencia] = self::palavra($item);

                if ($texto === '' || mb_strlen($texto) > 80 || count(preg_split('/\s+/u', $texto) ?: []) > 10) {
                    $descartadas++;

                    continue;
                }

                $chave = mb_strtolower($texto).'|'.$correspondencia;

                if (isset($vistas[$chave])) {
                    continue;
                }

                $vistas[$chave] = true;
                $palavras[] = ['texto' => $texto, 'correspondencia' => $correspondencia];

                if (count($palavras) >= $porGrupo) {
                    break;
                }
            }

            if ($palavras === []) {
                $problemas[] = "O grupo \"{$nome}\" ficou sem palavra-chave; nenhum grupo pode existir sem uma.";

                continue;
            }

            $negativas = [];

            foreach (self::lista($bruto['negativas'] ?? []) as $negativa) {
                $negativa = self::texto($negativa, 80);

                if ($negativa !== '' && ! in_array(mb_strtolower($negativa), array_map('mb_strtolower', $negativas), true)) {
                    $negativas[] = $negativa;
                }

                if (count($negativas) >= $negativasMax) {
                    break;
                }
            }

            $grupos[] = [
                'nome' => $nome,
                'tema' => self::texto($bruto['tema'] ?? '', 160),
                'palavras_chave' => $palavras,
                'negativas' => $negativas,
            ];

            if (count($grupos) >= $maximo) {
                $avisos[] = "O plano trouxe mais de {$maximo} grupos; os excedentes foram deixados de fora.";
                break;
            }
        }

        if ($descartadas > 0) {
            $avisos[] = "{$descartadas} palavra(s)-chave foram descartadas por formato ou comprimento fora do que o Google aceita.";
        }

        if ($grupos === []) {
            $problemas[] = 'O plano não trouxe nenhum grupo de anúncios com palavra-chave.';
        }

        return [$grupos, $problemas, $avisos];
    }

    /**
     * O anúncio responsivo: títulos, descrições e rotas de exibição, nas travas do Google.
     *
     * As contagens mínimas são as da criação de um RSA — três títulos e duas descrições —, e
     * não as do editor solto. Faltando qualquer uma, o plano não pode ser aplicado: o Google
     * recusaria o anúncio inteiro, e é melhor saber aqui do que no meio da escrita.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: array<int, string>}
     */
    private static function anuncios(mixed $bruto, array $problemas, array $avisos): array
    {
        $bruto = is_array($bruto) ? $bruto : [];

        $titulos = self::textos($bruto['titulos'] ?? [], (int) config('google_ads.rsa.caracteres_titulo', 30), 15, 'título', $avisos);
        $descricoes = self::textos($bruto['descricoes'] ?? [], (int) config('google_ads.rsa.caracteres_descricao', 90), 4, 'descrição', $avisos);

        $caminhos = [];

        foreach (self::lista($bruto['caminhos'] ?? []) as $caminho) {
            $caminho = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', self::texto($caminho, 15))) ?? '';

            if ($caminho !== '' && ! in_array($caminho, $caminhos, true)) {
                $caminhos[] = mb_substr($caminho, 0, (int) config('google_ads.rsa.caracteres_rota', 15));
            }

            if (count($caminhos) >= 2) {
                break;
            }
        }

        if (count($titulos) < max(3, (int) config('google_ads.rsa.titulos_min', 3))) {
            $problemas[] = 'O anúncio precisa de pelo menos 3 títulos válidos (até 30 caracteres cada); o plano trouxe '.count($titulos).'.';
        }

        if (count($descricoes) < max(2, (int) config('google_ads.rsa.descricoes_min', 1))) {
            $problemas[] = 'O anúncio precisa de pelo menos 2 descrições válidas (até 90 caracteres cada); o plano trouxe '.count($descricoes).'.';
        }

        return [['titulos' => $titulos, 'descricoes' => $descricoes, 'caminhos' => $caminhos], $problemas, $avisos];
    }

    /**
     * A régua da casa para a força do anúncio — uma estimativa, não o Índice de qualidade.
     *
     * O Google calcula o próprio índice com histórico de leilão, e nenhuma conta local chega
     * lá. O que dá para medir antes de publicar é a variedade e o preenchimento: quantos
     * títulos, quantas descrições, se as rotas foram usadas, se a palavra-chave aparece no
     * texto. A tela mostra a nota como estimativa e nunca como garantia de desempenho.
     */
    private static function forca(array $plano): array
    {
        $nota = 30;
        $titulos = $plano['anuncios']['titulos'];
        $descricoes = $plano['anuncios']['descricoes'];

        $nota += min(25, (int) round(count($titulos) * 2.5));
        $nota += min(20, count($descricoes) * 5);

        if ($plano['anuncios']['caminhos'] !== []) {
            $nota += 5;
        }

        $palavras = [];

        foreach ($plano['grupos'] as $grupo) {
            foreach ($grupo['palavras_chave'] as $palavra) {
                $palavras[] = mb_strtolower($palavra['texto']);
            }
        }

        $texto = mb_strtolower(implode(' ', array_merge($titulos, $descricoes)));

        foreach (array_unique($palavras) as $palavra) {
            if ($palavra !== '' && str_contains($texto, $palavra)) {
                $nota += 15;

                break;
            }
        }

        if ($titulos !== []) {
            $media = array_sum(array_map('mb_strlen', $titulos)) / count($titulos);

            if ($media >= 15) {
                $nota += 5;
            }
        }

        $nota = max(0, min(100, $nota));
        $limites = (array) config('google_ads.copiloto.forca');

        [$chave, $rotulo] = match (true) {
            $nota >= (int) ($limites['forte'] ?? 85) => ['forte', 'Forte'],
            $nota >= (int) ($limites['boa'] ?? 65) => ['boa', 'Boa'],
            $nota >= (int) ($limites['regular'] ?? 45) => ['regular', 'Regular'],
            default => ['fraca', 'Fraca'],
        };

        return ['nota' => $nota, 'rotulo' => $rotulo, 'chave' => $chave];
    }

    /**
     * Uma lista de títulos ou descrições já limpa: texto aparado, duplicata fora e o excedente
     * ao limite do Google contado num aviso. O que passa do comprimento é descartado, nunca
     * cortado no meio de uma palavra — anúncio com frase mutilada é pior que anúncio menor.
     *
     * @return array<int, string>
     */
    private static function textos(mixed $brutos, int $limite, int $maximo, string $tipo, array &$avisos): array
    {
        $textos = [];
        $longos = 0;

        foreach (self::lista($brutos) as $texto) {
            $texto = self::texto($texto, $limite + 1);

            if ($texto === '') {
                continue;
            }

            if (mb_strlen($texto) > $limite) {
                $longos++;

                continue;
            }

            if (! in_array(mb_strtolower($texto), array_map('mb_strtolower', $textos), true)) {
                $textos[] = $texto;
            }
        }

        if ($longos > 0) {
            $avisos[] = "{$longos} {$tipo}(s) foram descartados por passar de {$limite} caracteres.";
        }

        if (count($textos) > $maximo) {
            $avisos[] = 'O anúncio trouxe mais de '.$maximo.' '.$tipo.'s; os excedentes foram deixados de fora.';
            $textos = array_slice($textos, 0, $maximo);
        }

        return array_values($textos);
    }

    /** Uma palavra-chave em texto e correspondência; aceita string solta ou objeto do JSON. */
    private static function palavra(mixed $item): array
    {
        if (is_array($item)) {
            $texto = self::texto($item['texto'] ?? $item['palavra'] ?? '', 80);
            $correspondencia = mb_strtolower(self::texto($item['correspondencia'] ?? $item['tipo'] ?? 'frase', 10));

            return [$texto, self::CORRESPONDENCIA[$correspondencia] ?? 'PHRASE'];
        }

        return [self::texto($item, 80), 'PHRASE'];
    }

    /** Reais como o modelo escreve ("50", "50.00", "50,00") → centavos. */
    private static function reaisParaCentavos(mixed $valor): int
    {
        if (! is_numeric($valor)) {
            $valor = str_replace(['R$', ' ', '.'], '', (string) $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (int) round((float) $valor * 100) : 0;
    }

    /** Uma lista vinda do JSON: array, texto com quebras de linha ou item solto. */
    private static function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = preg_split('/\r\n|\r|\n|;/', $valor) ?: [];
        }

        if (! is_array($valor)) {
            return $valor === null || $valor === '' ? [] : [self::texto($valor, 200)];
        }

        $lista = [];

        foreach ($valor as $item) {
            if (is_scalar($item)) {
                $item = self::texto($item, 200);
            }

            if (is_string($item) && $item !== '') {
                $lista[] = $item;
            }
        }

        return array_values(array_unique($lista));
    }

    /** Itens de uma coleção do JSON, sem confundir objeto com lista. */
    private static function itens(mixed $valor): array
    {
        return is_array($valor) ? array_values($valor) : [];
    }

    /** Texto de planilha: sem quebra de linha, sem espaço dobrado, aparado. */
    private static function texto(mixed $valor, int $limite = 200): string
    {
        if (! is_scalar($valor)) {
            return '';
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? ''), 0, $limite);
    }

    private static function inteiro(mixed $valor): int
    {
        return is_numeric($valor) ? (int) round((float) $valor) : 0;
    }

    private static function urlDe(mixed $valor): string
    {
        $url = trim((string) $valor);

        if ($url === '') {
            return '';
        }

        // O modelo às vezes devolve "goldenrastreamento.com.br/planos": sem protocolo, o Google
        // não aceita, e a tela não tem por que recusar o que é só uma abreviação.
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $partes = parse_url($url);

        return in_array($partes['scheme'] ?? '', ['http', 'https'], true) && filled($partes['host'] ?? null)
            ? $url
            : '';
    }
}
