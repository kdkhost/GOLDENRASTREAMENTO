<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * O passo a passo de cada item configurável — a leitura de `config/guia_configuracao.php`.
 *
 * É a ajuda que aparece no ícone ao lado do rótulo de um campo ou de um cartão: uma frase
 * dizendo o que aquilo é, a ordem em que se faz, e para onde ir buscar a credencial. O texto
 * todo vive no config; esta classe só resolve as três coisas que a view não tem como saber
 * sozinha — o endereço por trás de um nome de rota, o que `{host}`, `{whmcs}` e `{whatsapp}`
 * significam nesta instalação, e em que id de gaveta cada item se apresenta.
 *
 * Um item sem entrada no config não mostra ícone nenhum. É assim que o mecanismo vale para
 * todas as telas do painel sem obrigar nenhuma delas a falar de ajuda.
 */
class GuiaConfiguracao
{
    /**
     * Todos os itens documentados, pela chave de registro (nome do campo ou nome do cartão).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function itens(): array
    {
        return (array) config('guia_configuracao.itens', []);
    }

    /** A entrada de um item; nula quando ele não tem ajuda escrita. */
    public static function item(string $chave): ?array
    {
        $item = self::itens()[$chave] ?? null;

        return is_array($item) ? $item : null;
    }

    /**
     * O conteúdo da gaveta para um item: título, blocos para o partial do guia desenhar e o id
     * que liga o ícone ao `<template>` da página. Nulo quando não há ajuda — e a view não desenha
     * botão nenhum nesse caso.
     */
    public static function gaveta(string $chave, ?string $tituloAlternativo = null): ?array
    {
        $item = self::item($chave);

        if ($item === null) {
            return null;
        }

        $blocos = self::blocos($item);

        if ($blocos === []) {
            return null;
        }

        return [
            'id' => self::id($chave),
            'titulo' => ($item['titulo'] ?? '') !== '' ? $item['titulo'] : ($tituloAlternativo ?? 'Como configurar'),
            'blocos' => $blocos,
        ];
    }

    /** O id da gaveta — o mesmo no `data-ajuda` do botão e no `<template>` que guarda o corpo. */
    public static function id(string $chave): string
    {
        return 'ajuda-'.preg_replace('/[^a-z0-9]+/i', '-', $chave);
    }

    /**
     * A entrada virada nos tipos que o partial do guia já sabe desenhar: uma frase, os passos,
     * os endereços e o cuidado final.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function blocos(array $item): array
    {
        $blocos = [];

        if (($item['onde'] ?? '') !== '') {
            $blocos[] = ['tipo' => 'texto', 'conteudo' => $item['onde']];
        }

        $passos = $item['passos'] ?? [];

        if ($passos !== []) {
            $blocos[] = ['tipo' => 'passos', 'itens' => $passos];
        }

        $links = self::links($item['links'] ?? []);

        if ($links !== []) {
            $blocos[] = ['tipo' => 'links', 'itens' => $links];
        }

        if (($item['nota'] ?? '') !== '') {
            $blocos[] = ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => $item['nota']];
        }

        return $blocos;
    }

    /**
     * Os endereços que esta instalação consegue preencher agora.
     *
     * Um link dependente de algo que ninguém informou ainda (o endereço do WHMCS, o número de
     * WhatsApp) some da gaveta, junto com o placeholder que ele carregaria: é melhor a pessoa não
     * ver o botão do que ver um botão que não leva a lugar nenhum.
     *
     * @return array<int, array{rotulo: string, url: string}>
     */
    private static function links(array $links): array
    {
        $resolvidos = [];

        foreach ($links as $link) {
            $rotulo = (string) ($link['rotulo'] ?? '');

            if ($rotulo === '') {
                continue;
            }

            $url = isset($link['rota']) ? self::rota((string) $link['rota']) : self::endereco((string) ($link['url'] ?? ''));

            if ($url === null) {
                continue;
            }

            $resolvidos[] = ['rotulo' => $rotulo, 'url' => $url];
        }

        return $resolvidos;
    }

    /** O endereço de uma rota deste sistema; nulo se o nome não existir. */
    private static function rota(string $nome): ?string
    {
        try {
            return route($nome);
        } catch (Throwable) {
            return null;
        }
    }

    /** O endereço externo com os marcadores da casa trocados pelo que o painel sabe. */
    private static function endereco(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $marcadores = ['{host}' => self::host(), '{whmcs}' => self::whmcs(), '{whatsapp}' => self::whatsapp()];

        foreach ($marcadores as $marca => $valor) {
            if (! str_contains($url, $marca)) {
                continue;
            }

            // O marcador pede algo que a casa ainda não informou: sem valor, o endereço não é um
            // endereço, e a gaveta fica sem aquele botão.
            if ($valor === '') {
                return null;
            }

            $url = str_replace($marca, $valor, $url);
        }

        return $url;
    }

    /** O domínio desta instalação, para os endereços do painel de controle da hospedagem. */
    private static function host(): string
    {
        $app = (string) config('app.url');
        $host = $app !== '' ? (parse_url($app, PHP_URL_HOST) ?: '') : '';

        return $host !== '' ? $host : (string) (request()?->getHost() ?? '');
    }

    /** A raiz do financeiro preenchida no painel, sem barra no fim. Vazia, não há endereço. */
    private static function whmcs(): string
    {
        return rtrim((string) config('whmcs.url'), '/');
    }

    /** O número principal, no formato que o link de conversa entende. */
    private static function whatsapp(): string
    {
        try {
            $salvo = (string) Setting::get('contato_whatsapp', '');
        } catch (Throwable) {
            return '';
        }

        return preg_replace('/\D/', '', $salvo) ?: (string) config('golden.whatsapp');
    }
}
