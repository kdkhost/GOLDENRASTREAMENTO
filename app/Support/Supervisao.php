<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use App\Services\Whmcs\Veiculacao;
use Illuminate\Support\Facades\Auth;

/**
 * A janela de supervisão: o painel dentro da conta de um cliente, sem ser o cliente.
 *
 * Não existe espelho das telas do portal feito à mão. Um segundo jogo de telas "como as do
 * cliente" divergiria do original na primeira mudança de regra — e é exatamente o que este
 * recurso promete não fazer. Em vez disso o painel **entra** no portal da pessoa pelo guard
 * `cliente`, e as telas que ela vê são as mesmas que a equipe passa a ver, obedecendo aos
 * mesmos interruptores de `Veiculacao`. O isolamento de dados deixa de ser uma promessa: é o
 * guard que já separa as duas casas.
 *
 * O que distingue a visita de um dono é o marcador abaixo, gravado na sessão no instante em que
 * o botão é apertado. Ele é a única coisa que transforma o portal em vitrine: presente e válido,
 * nenhuma escrita do portal passa (`AcessoSupervisionado`), e a barra na tela diz quem está
 * olhando por cima do ombro de quem. Válido quer dizer quatro coisas — a ficha logada é a da
 * janela, o painel aberto é o de quem abriu, o interruptor continua ligado e o prazo não venceu.
 * Vencido, o middleware derruba a sessão em vez de deixá-la virar conta normal: janela que
 * expira e continua aberta não é janela com horário, é porta.
 */
class Supervisao
{
    /** A chave da sessão que carrega a janela. */
    public const SESSAO = 'golden.supervisao';

    /** As duas escritas que a supervisão pode atravessar: as saídas, que só desfazem a si mesmas. */
    public const PODE_ESCREVER = ['portal.supervisao.sair', 'portal.sair'];

    /**
     * Abre a janela: registra na sessão quem supervisiona, a partir de quando, e de quem é a
     * conta. O login do guard é feito antes, pelo controller — aqui só a prova de que quem está
     * do outro lado não é o dono.
     */
    public static function abrir(User $admin, Customer $cliente): void
    {
        session([
            self::SESSAO => [
                'cliente_id' => (int) $cliente->id,
                'admin_id' => (int) $admin->id,
                'admin_nome' => (string) $admin->name,
                'desde' => now()->timestamp,
            ],
        ]);
    }

    /** A janela aberta agora, ou `null` quando o portal está nas mãos do dono (ou de ninguém). */
    public static function dados(): ?array
    {
        $bruto = session(self::SESSAO);

        if (! is_array($bruto)) {
            return null;
        }

        $clienteId = $bruto['cliente_id'] ?? null;
        $adminId = $bruto['admin_id'] ?? null;
        $nome = $bruto['admin_nome'] ?? null;
        $desde = $bruto['desde'] ?? null;

        if (! is_int($clienteId) || ! is_int($adminId) || ! is_string($nome) || ! is_int($desde)) {
            return null;
        }

        // A ficha logada tem que ser a da janela: alguém trocou de conta no meio e a barra
        // passaria a mentir sobre de quem é o que está na tela.
        if (Auth::guard('cliente')->id() !== $clienteId) {
            return null;
        }

        // Outro login no painel, ou a janela fechada na tela de Configurações — nas duas a
        // supervisão deixou de ser verdade, e o que sobra é uma sessão de cliente indevida.
        if (Auth::guard('web')->id() !== $adminId || ! Veiculacao::supervisao()) {
            return null;
        }

        if ($desde + Veiculacao::minutosDeSupervisao() * 60 < now()->timestamp) {
            return null;
        }

        return ['cliente_id' => $clienteId, 'admin_id' => $adminId, 'admin_nome' => $nome, 'desde' => $desde];
    }

    /** Há marcador na sessão — válido ou não. É o que distingue uma visita de uma intrusão. */
    public static function haMarcador(): bool
    {
        return is_array(session(self::SESSAO));
    }

    /** Apaga o marcador. Chamado em todo login de verdade: a sessão de um dono nunca o carrega. */
    public static function limpar(): void
    {
        session()->forget(self::SESSAO);
    }
}
