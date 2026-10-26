<?php

namespace App\Support;

/**
 * O guia de uso do painel — a leitura de `config/guia_painel.php`.
 *
 * O texto vive todo no config e esta classe só resolve três coisas que a view não tem como
 * saber sozinha: qual seção documenta a tela aberta (o link discreto do cabeçalho), como agrupar
 * as seções na coluna da esquerda e como desenhar o pouco de formatação que um parágrafo usa.
 *
 * Os passos do guia não têm número nenhum — a ordem já está na lista. Um passo é ícone, título
 * e texto, igual ao resto do sistema.
 */
class GuiaPainel
{
    /**
     * O ícone de cada grupo do guia. A view não escolhe: um cartão sem ícone seria o único do
     * painel sem, e a coluna da esquerda já usa o ícone da seção para o leitor se situar.
     *
     * @var array<string, string>
     */
    private const ICONES_GRUPO = [
        'Começando' => 'fa-house',
        'Comercial' => 'fa-briefcase',
        'Vendas' => 'fa-store',
        'Atendimento' => 'fa-life-ring',
        'Conteúdo do site' => 'fa-newspaper',
        'Portal do cliente' => 'fa-user-tie',
        'Sistema' => 'fa-sliders',
    ];

    /**
     * As seções, na ordem em que aparecem na tela.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function secoes(): array
    {
        return array_values(config('guia_painel.secoes', []));
    }

    /** O ícone do grupo; nulo para um grupo novo, que a view desenha sem ícone. */
    public static function iconeDoGrupo(string $grupo): ?string
    {
        return self::ICONES_GRUPO[$grupo] ?? null;
    }

    /** A seção de um id; nula se o link apontou para algo que não existe. */
    public static function secao(string $id): ?array
    {
        foreach (self::secoes() as $secao) {
            if (($secao['id'] ?? '') === $id) {
                return $secao;
            }
        }

        return null;
    }

    /**
     * Seções agrupadas pela barra da esquerda, na ordem em que os grupos surgem no config.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function porGrupo(): array
    {
        $grupos = [];

        foreach (self::secoes() as $secao) {
            $grupos[$secao['grupo'] ?? 'Guia'][] = $secao;
        }

        return $grupos;
    }

    /**
     * A seção que documenta uma rota. Vence o prefixo mais longo, para que
     * `admin.google-ads.criativo` tenha a sua própria seção dentro do grupo do Google Ads.
     *
     * Rota fora do mapa — ou nenhuma rota — devolve nulo e o cabeçalho não mostra link algum.
     */
    public static function secaoDaRota(?string $rota): ?array
    {
        if ($rota === null || $rota === '') {
            return null;
        }

        $melhor = '';
        $id = null;

        foreach (config('guia_painel.rotas', []) as $prefixo => $identificador) {
            if (str_starts_with($rota, $prefixo) && strlen($prefixo) > strlen($melhor)) {
                $melhor = $prefixo;
                $id = $identificador;
            }
        }

        return $id === null ? null : self::secao($id);
    }

    /**
     * O parágrafo em HTML: escapa tudo e devolve duas coisas que o texto precisa — código em
     * crase e ênfase em asteriscos. Escapar primeiro é o que torna o `{!! !!}` da view seguro:
     * nada do config vira marca, só o que a própria função desenha.
     */
    public static function inline(string $texto): string
    {
        $escapado = e($texto);

        return preg_replace(
            ['/(?<!\*)\*\*(.+?)\*\*(?!\*)/u', '/`([^`]+)`/u'],
            ['<strong>$1</strong>', '<code>$1</code>'],
            $escapado
        );
    }
}
