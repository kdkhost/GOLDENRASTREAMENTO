<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Str;

/**
 * O que a conexão com o financeiro veicula no portal.
 *
 * As credenciais abrem a porta; esta classe decide o que passa por ela. Cada pergunta aqui tem
 * uma resposta única, lida de `config('whmcs.veiculacao.*')` — que já vem sobreposta pelo que
 * está gravado no banco (`ChavesDeIntegracao::aplicar()`), então ninguém precisa saber se o
 * interruptor foi ligado na tela ou no `.env`. É essa fonte única que permite ao controller, à
 * sidebar e ao `Cliente` discordarem zero sobre "a tela de faturas está aberta agora?".
 *
 * Desligar uma veiculação não é esconder um botão: é não gastar a chamada. Quem fecha
 * `faturas` não vê requisição ao financeiro, e o menu do portal perde o item — a pessoa não cai
 * numa porta trancada.
 */
class Veiculacao
{
    /** Estados de serviço que não são mais um contrato em andamento. */
    public const ESTADOS_ENCERRADOS = ['Terminated', 'Cancelled', 'Fraud'];

    /** Estados de fatura que já foram quitadas — o passado da conta. */
    public const ESTADOS_PAGAS = ['Paid', 'Refunded'];

    /** As duas telas de financeiro do portal. */
    public const TELAS = ['servicos', 'faturas'];

    /** A tela do portal está aberta para o cliente? */
    public static function abre(string $tela): bool
    {
        return (bool) config("whmcs.veiculacao.{$tela}");
    }

    /** O serviço deste estado entra na lista do cliente? */
    public static function mostraServico(string $status): bool
    {
        if ($status === '') {
            return true;
        }

        return (bool) config('whmcs.veiculacao.servicos_encerrados')
            || ! in_array($status, self::ESTADOS_ENCERRADOS, true);
    }

    /** A fatura deste estado entra na lista do cliente? */
    public static function mostraFatura(string $status): bool
    {
        if ($status === '') {
            return true;
        }

        return (bool) config('whmcs.veiculacao.faturas_pagas')
            || ! in_array($status, self::ESTADOS_PAGAS, true);
    }

    /** O grupo de serviços está na vitrine? Lista vazia é "todos", porque foi assim que a
     * integração nasceu e é o que uma instalação com um único grupo precisa.
     */
    public static function mostraGrupo(string $grupo): bool
    {
        $permitidos = self::grupos();

        if ($permitidos === []) {
            return true;
        }

        return in_array(Str::lower(trim($grupo)), $permitidos, true);
    }

    /** Os grupos autorizados, já normalizados: minúsculos, sem espaço, sem vazio, sem repetido. */
    public static function grupos(): array
    {
        $valor = config('whmcs.veiculacao.grupos');
        $itens = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_unique(array_filter(
            array_map(fn ($grupo): string => Str::lower(trim((string) $grupo)), $itens),
            fn (string $grupo): bool => $grupo !== ''
        )));
    }

    /** O cliente pode saltar do portal para o financeiro? */
    public static function mostraLink(): bool
    {
        return (bool) config('whmcs.veiculacao.link_externo');
    }

    /** O portal ainda escreve no financeiro (`dados` = cadastro, `senha` = troca de senha)? */
    public static function espelha(string $oQue): bool
    {
        return (bool) config("whmcs.veiculacao.espelhar_{$oQue}");
    }
}
