<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante um identificador anônimo de visita persistido em cookie,
 * para atribuir leads à jornada do visitante sem expor dados pessoais.
 */
class IdentificadorVisita
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->hasCookie('golden_visitor')) {
            $response->cookie(
                'golden_visitor',
                Str::random(24),
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
