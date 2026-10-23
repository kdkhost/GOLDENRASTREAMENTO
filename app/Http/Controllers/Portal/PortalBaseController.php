<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Setting;
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
