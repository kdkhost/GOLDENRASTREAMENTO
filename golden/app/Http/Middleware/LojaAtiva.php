<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia toda a loja virtual quando o admin desativa a opção.
 * As rotas continuam registradas, mas respondem 404 enquanto desligada.
 */
class LojaAtiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!loja_ativa()) {
            abort(404, 'A loja virtual está temporariamente fechada.');
        }

        return $next($request);
    }
}
