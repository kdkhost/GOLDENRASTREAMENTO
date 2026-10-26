<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Whmcs\Cliente as Financeiro;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Base das telas do portal: quem é o cliente e para onde vai o aviso da equipe.
 *
 * O `cliente()` é a única porta para a ficha. Todo controller de área logada parte dele
 * para carregar pedidos, chamados e contratos — é assim que a lista de um cliente nunca
 * enxerga a de outro, sem depender de lembrar de um `where` em cada query.
 */
abstract class PortalBaseController extends Controller
{
    protected function cliente(): Customer
    {
        return Auth::guard('cliente')->user();
    }

    /** A mesma porta do WHMCS para todas as telas do portal. */
    protected function financeiro(): Financeiro
    {
        return app(Financeiro::class);
    }

    /**
     * O id desta pessoa no financeiro. Quem manda é a ficha autenticada, nunca um número de
     * requisição: a primeira descoberta é pelo e-mail de acesso e fica gravada em `whmcs_id`,
     * então as visitas seguintes não gastam uma chamada só para se apresentar.
     */
    protected function idNoFinanceiro(): ?int
    {
        $cliente = $this->cliente();

        if ($cliente->whmcs_id) {
            return (int) $cliente->whmcs_id;
        }

        $email = $cliente->email_portal ?: $cliente->email;

        if (blank($email)) {
            return null;
        }

        $id = $this->financeiro()->idDoCliente((string) $email);

        if ($id !== null) {
            $cliente->forceFill(['whmcs_id' => $id])->saveQuietly();
        }

        return $id;
    }

    /**
     * Espelha no financeiro o que o portal já gravou. `null` quando não há o que espelhar
     * (integração desligada ou conta não localizada lá) e `false` quando havia e a chamada
     * falhou — é o `false` que merece um aviso na tela. A gravação local nunca é desfeita por
     * causa do financeiro: a ficha do CRM e a senha do portal são a verdade daqui.
     *
     * @param  callable(Financeiro, int): bool  $acao
     */
    protected function espelharNoFinanceiro(callable $acao): ?bool
    {
        $financeiro = $this->financeiro();

        if (! $financeiro->configurado()) {
            return null;
        }

        $id = $this->idNoFinanceiro();

        return $id === null ? null : $acao($financeiro, $id);
    }

    /**
     * E-mail para a equipe (mesmo endereço de contato do site). Como no pedido, um SMTP
     * fora do ar nunca pode barrar a ação do cliente: o envio é tentado e seguido em frente.
     */
    protected function avisarEquipe(mixed $mensagem): void
    {
        $destino = Setting::get('contato_email', config('golden.email'));

        if ($destino === '' || $destino === null) {
            return;
        }

        try {
            Mail::to($destino)->queue($mensagem);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * E-mail para o próprio cliente — pelo endereço de acesso à conta, caindo no da ficha.
     * Mesma regra de acima: sem SMTP configurado o aviso se perde, a ação do cliente não.
     */
    protected function avisarCliente(mixed $mensagem): void
    {
        $destino = $this->cliente()->email_portal ?: $this->cliente()->email;

        if ($destino === null || $destino === '') {
            return;
        }

        try {
            Mail::to($destino)->queue($mensagem);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
