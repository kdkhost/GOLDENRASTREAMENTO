<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Barreira do portal do cliente.
 *
 * É a guarda do guard `cliente`, separado do painel: quem está logado no /portal não tem
 * nenhuma rota de admin, e quem está no painel não é cliente. Ao desativar a ficha a saída
 * é só deste guard — a sessão da equipe, que mora na mesma cookie, continua intacta.
 */
class AutenticarCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('cliente');

        if ($guard->guest()) {
            return redirect()->route('portal.login')->with('warning', 'Entre no portal para continuar.');
        }

        if ($guard->user()->status !== 'ativo') {
            $guard->logout();

            return redirect()->route('portal.login')->with('error', 'Sua conta está inativa. Fale com o atendimento Golden.');
        }

        return $next($request);
    }
}
