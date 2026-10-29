<?php

namespace App\Services\Assistente\Provedores;

use App\Services\Assistente\Contratos\PodeConversar;
use App\Services\Assistente\Contratos\PodeFalar;
use App\Services\Assistente\Contratos\PodeTranscrever;
use App\Services\Assistente\Excecoes\CapacidadeNaoOferecida;
use App\Services\Assistente\Excecoes\CredenciaisAusentes;
use App\Support\ChavesDeIntegracao;

/**
 * Quem atende cada capacidade, agora.
 *
 * A resposta vem do banco (o painel escolheu), depois do `.env` (o plano de fundo), depois do
 * padrão de `config/assistente.php`. Nessa ordem, e só nessa: é assim que a mesma instalação
 * troca de provedor no meio do mês sem tocar no servidor, e é assim que uma instalação recém-
 * migrada já atende sozinha, com o padrão da casa.
 *
 * A fábrica é também o porteiro: um modelo que não está na lista daquela capacidade, ou um
 * provedor que não implementa o contrato, nunca chega a virar chamada de HTTP. Custar uma
 * exceção barata é melhor que cobrar uma caro no erro do provedor.
 */
final class Fabrica
{
    /** Os provedores que este sistema sabe usar, por código. */
    private const CLASSES = [
        'gemini' => Gemini::class,
        'openai' => OpenAi::class,
    ];

    /**
     * `provedor/modelo` vigente para a capacidade, já resolvido o padrão quando ninguém escolheu.
     *
     * @return array{provedor: string, modelo: string}
     */
    public static function rodada(string $capacidade): array
    {
        $escolhida = self::escolha($capacidade);

        // Um modelo que o provedor aposentou (a troca de geração acontece sem aviso: o Gemini 2.5
        // passou a responder 404 "no longer available to new users" com a chave certa na mão) não
        // pode calar o atendimento. A casa volta ao padrão da capacidade, e a tela de chaves diz
        // que a escolha ficou no passado — em vez de escalar todo cliente para a equipe.
        if (! self::oferecida($capacidade, $escolhida)) {
            $escolhida = trim((string) config("assistente.capacidades.{$capacidade}.padrao"));
        }

        return self::partir($escolhida);
    }

    /** A escolha como o painel a gravou (`provedor/modelo`), ou o padrão da casa quando não há. */
    public static function escolha(string $capacidade): string
    {
        $escolhida = trim((string) config("assistente.rodada.{$capacidade}"));

        return $escolhida !== '' ? $escolhida : trim((string) config("assistente.capacidades.{$capacidade}.padrao"));
    }

    public static function rotuloDe(string $provedor): string
    {
        return (string) (config('assistente.provedores')[$provedor]['rotulo'] ?? $provedor);
    }

    /** A chave vigente do provedor — banco, depois ambiente — ou nulo quando não existe. */
    public static function chaveDe(string $provedor): ?string
    {
        $chave = config("assistente.chaves.{$provedor}");

        return filled($chave) ? (string) $chave : null;
    }

    public static function temChave(string $provedor): bool
    {
        return self::chaveDe($provedor) !== null;
    }

    /** Alguma capacidade está ligada? É o que a tela lê para dizer "o assistente atende" ou não. */
    public static function ligado(): bool
    {
        foreach (array_keys((array) config('assistente.capacidades')) as $capacidade) {
            if (self::pronta($capacidade)) {
                return true;
            }
        }

        return false;
    }

    /** A capacidade está escolhida, com provedor conhecido e chave gravada. */
    public static function pronta(string $capacidade): bool
    {
        $rodada = self::rodada($capacidade);

        if (! isset(self::CLASSES[$rodada['provedor']]) || ! self::temChave($rodada['provedor'])) {
            return false;
        }

        return self::modeloOferecido($capacidade, $rodada['provedor'], $rodada['modelo']);
    }

    /**
     * As capacidades que o painel ainda não consegue usar, pelo motivo em português. É o que a
     * tela de chaves e a do assistente mostram em vez de deixar o operador descobri na primeira
     * mensagem de um cliente.
     *
     * @return array<int, string>
     */
    public static function faltando(): array
    {
        $faltando = [];

        foreach ((array) config('assistente.capacidades') as $capacidade => $definicao) {
            $escolhida = self::escolha($capacidade);
            $rodada = self::rodada($capacidade);

            if (! self::oferecida($capacidade, $escolhida)) {
                $faltando[] = $definicao['rotulo'].': o modelo escolhido ('.$escolhida.') saiu do catálogo; '
                    .'o atendimento está no padrão '.$rodada['provedor'].'/'.$rodada['modelo']
                    .' até alguém escolher outro em '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.';

                continue;
            }

            if (! self::temChave($rodada['provedor'])) {
                $faltando[] = $definicao['rotulo'].': falta a chave de '.self::rotuloDe($rodada['provedor']).'.';

                continue;
            }
        }

        return $faltando;
    }

    /**
     * O provedor escolhido para a capacidade, já com a chave e o modelo que o painel gravou.
     *
     * A capacidade chega pelo nome que a chamada pediu: `conversa`, `visao` ou `documento` caem no
     * contrato de conversa, porque os três são a mesma rota do provedor com mídias diferentes.
     */
    public static function conversante(string $capacidade): PodeConversar
    {
        return self::resolver($capacidade, PodeConversar::class);
    }

    public static function transcritor(): PodeTranscrever
    {
        return self::resolver('transcricao', PodeTranscrever::class);
    }

    public static function falante(): PodeFalar
    {
        return self::resolver('voz', PodeFalar::class);
    }

    /**
     * A lista que a tela de chaves oferece para uma capacidade, no formato `provedor/modelo`.
     * É daqui, e de nenhum outro lugar, que saem as opções do seletor — inclusive as do rótulo,
     * que é o texto que o administrador lê para escolher.
     *
     * @return array<string, string>
     */
    public static function opcoesPara(string $capacidade): array
    {
        $opcoes = [];

        foreach ((array) (config('assistente.capacidades')[$capacidade]['modelos'] ?? []) as $provedor => $modelos) {
            foreach ($modelos as $modelo => $rotulo) {
                $opcoes["{$provedor}/{$modelo}"] = self::rotuloDe($provedor).' — '.$rotulo;
            }
        }

        return $opcoes;
    }

    /** As vozes oferecidas no seletor, no formato `provedor:voz`. */
    public static function opcoesDeVoz(): array
    {
        $opcoes = [];

        foreach ((array) config('assistente.vozes') as $provedor => $vozes) {
            foreach ($vozes as $voz => $rotulo) {
                $opcoes["{$provedor}:{$voz}"] = self::rotuloDe($provedor).' — '.$rotulo;
            }
        }

        return $opcoes;
    }

    /** A escolha tem provedor conhecido e modelo na lista desta capacidade? */
    private static function oferecida(string $capacidade, string $escolhida): bool
    {
        $rodada = self::partir($escolhida);

        return isset(self::CLASSES[$rodada['provedor']])
            && self::modeloOferecido($capacidade, $rodada['provedor'], $rodada['modelo']);
    }

    /** @return array{provedor: string, modelo: string} */
    private static function partir(string $escolhida): array
    {
        $partes = explode('/', $escolhida, 2);

        return ['provedor' => $partes[0] ?? '', 'modelo' => $partes[1] ?? ''];
    }

    private static function modeloOferecido(string $capacidade, string $provedor, string $modelo): bool
    {
        $oferecidos = (array) (config('assistente.capacidades')[$capacidade]['modelos'] ?? []);

        return isset($oferecidos[$provedor][$modelo]);
    }

    /**
     * Monta o provedor da capacidade e exige o contrato que ela pede. As três recusas possíveis
     * têm nomes próprios — provedor desconhecido, chave ausente, modelo fora da lista — porque a
     * tela precisa dizer **qual** falta, em vez de quebrar.
     */
    private static function resolver(string $capacidade, string $contrato): object
    {
        $rodada = self::rodada($capacidade);
        $provedor = $rodada['provedor'];

        if (! isset(self::CLASSES[$provedor])) {
            throw new CapacidadeNaoOferecida($provedor ?: '(não escolhido)', $capacidade);
        }

        $rotulo = self::rotuloDe($provedor);
        $chave = self::chaveDe($provedor);

        if ($chave === null) {
            throw new CredenciaisAusentes($rotulo, 'ASSISTENTE_'.strtoupper($provedor).'_KEY');
        }

        if (! self::modeloOferecido($capacidade, $provedor, $rodada['modelo'])) {
            throw new CapacidadeNaoOferecida($rotulo, $capacidade);
        }

        $classe = self::CLASSES[$provedor];
        $instancia = new $classe($chave, $rodada['modelo']);

        if (! $instancia instanceof $contrato) {
            throw new CapacidadeNaoOferecida($rotulo, $capacidade);
        }

        return $instancia;
    }
}
