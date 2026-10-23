<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante um identificador anônimo de visita persistido em cookie, para atribuir
 * leads e cliques à jornada do visitante sem expor dados pessoais. O id entra já na
 * requisição (não só na resposta) para o RegistrarRastreio da mesma visita ler o valor.
 */
class IdentificadorVisita
{
    public function handle(Request $request, Closure $next): Response
    {
        $nome = config('golden.rastreio.cookie');
        $novo = $request->hasCookie($nome) ? null : Str::random(24);

        if ($novo !== null) {
            $request->cookies->set($nome, $novo);
        }

        $response = $next($request);

        if ($novo !== null) {
            Cookie::queue(
                $nome,
                $novo,
                60 * 24 * 90, // 90 dias
                '/',
                null,
                $request->secure(),
                true,
                false,
                'Lax'
            );
        }

        return $response;
    }
}
