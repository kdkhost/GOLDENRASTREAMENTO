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

    /** Estados de fatura que ainda são uma cobrança em andamento. */
    public const ESTADOS_EM_ABERTO = ['Unpaid', 'Payment Pending', 'Collections'];

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

    /**
     * Esta fatura ainda é uma cobrança em aberto?
     *
     * A pergunta existe numa fonte só porque ela é feita de dois lugares que não podem discordar: a
     * soma do aviso do portal ("R$ 59,90 em aberto") e a segunda via do chat, que só pode sair com
     * fatura em aberto — mandar uma já quitada seria cobrar de novo quem pagou. O que decide é o
     * estado quando o financeiro o devolve; sem estado, o saldo é a única prova de que ainda há o
     * que pagar, e saldo zero não é dívida de ninguém.
     *
     * @param  array<string, mixed>  $fatura
     */
    public static function faturaEmAberto(array $fatura): bool
    {
        $status = (string) ($fatura['status'] ?? '');

        if ($status !== '') {
            return in_array($status, self::ESTADOS_EM_ABERTO, true);
        }

        return (int) ($fatura['saldo_cents'] ?? 0) > 0;
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

    /**
     * A equipe pode entrar na conta de um cliente, pela janela de supervisão?
     *
     * É a pergunta que a ficha do cliente faz antes de desenhar o botão e que o middleware faz
     * em cada requisição do portal. Desligar no meio de uma supervisão aberta fecha a janela na
     * hora — não adianta um interruptor que só vale para a próxima sessão.
     */
    public static function supervisao(): bool
    {
        return (bool) config('whmcs.veiculacao.supervisao');
    }

    /** Por quantos minutos a janela fica aberta. */
    public static function minutosDeSupervisao(): int
    {
        return max(1, (int) config('whmcs.veiculacao.supervisao_minutos'));
    }

    /**
     * O assistente do site pode entregar a segunda via da fatura pelo chat?
     *
     * É a veiculação mais delicada das lista: as anteriores decidem o que o cliente *vê* dentro do
     * portal, e esta decide se o financeiro sai daqui para um e-mail sem que ninguém faça login.
     * Desligada, o gatilho do chat nem chega a existir — a conversa segue normal, e nenhuma
     * requisição é feita ao WHMCS a partir do atendimento público.
     */
    public static function segundaVia(): bool
    {
        return (bool) config('whmcs.veiculacao.segunda_via') && self::abre('faturas');
    }
}
