<?php

namespace App\Http\Middleware;

use App\Services\Rastreio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota a visualização de página de toda entrega pública bem-sucedida do site.
 * Painel, autenticação e o próprio beacon ficam de fora: aqui só entra o que um
 * visitante viu.
 */
class RegistrarRastreio
{
    public function __construct(private readonly Rastreio $rastreio) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->deveRegistrar($request, $response)) {
            $visita = $this->rastreio->registrarVisita($request);
            $this->rastreio->registrarNaVisita(
                $visita,
                'view',
                '/'.ltrim($request->path(), '/'),
                null,
                $request->query('utm_content') ?: null
            );
        }

        return $response;
    }

    private function deveRegistrar(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('site.*') || $response->getStatusCode() !== 200) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
