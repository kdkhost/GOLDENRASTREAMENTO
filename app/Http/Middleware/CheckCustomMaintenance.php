<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomMaintenance
{
    public function __construct(
        protected MaintenanceService $maintenance
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->maintenance->podeAcessar($request)) {
            $response = $next($request);

            // Se usou token de bypass pela URL, grava o cookie de 7 dias na resposta
            if ($request->query('golden_bypass') && $request->query('golden_bypass') === $this->maintenance->tokenBypass()) {
                $response->headers->setCookie(
                    cookie(
                        MaintenanceService::COOKIE_BYPASS,
                        $this->maintenance->tokenBypass(),
                        60 * 24 * 7,
                        '/',
                        null,
                        $request->isSecure(),
                        true
                    )
                );
            }

            return $response;
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'O sistema está temporariamente em manutenção programada.',
                'previsao_fim' => $this->maintenance->previsaoFim()?->toIso8601String(),
            ], 503)->header('Retry-After', '300');
        }

        return response()
            ->view('errors.503', [], 503)
            ->header('Retry-After', '300');
    }
}

