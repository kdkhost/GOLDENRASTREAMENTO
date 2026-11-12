<?php

namespace App\Http\Middleware;

use App\Services\Security\MilitaryShield;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MilitaryShieldMiddleware
{
    public function __construct(
        protected MilitaryShield $shield
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ameaca = $this->shield->inspecionar($request);

        if ($ameaca !== null) {
            $this->shield->registrarBloqueio($request, $ameaca);

            // Em modo puramente de monitoramento, registra nos logs mas não interrompe
            if ($this->shield->nivelRigor() === MilitaryShield::RIGOR_MONITORAMENTO) {
                return $next($request);
            }

            $incidenteId = 'SEC-'.strtoupper(bin2hex(random_bytes(4)));

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acesso interrompido pelo Escudo de Segurança Militar.',
                    'incident_id' => $incidenteId,
                    'status' => 403,
                ], 403);
            }

            return response()->view('errors.blocked', [
                'motivo' => $ameaca['motivo'] ?? 'Tráfego malicioso interceptado',
                'incidenteId' => $incidenteId,
            ], 403);
        }

        return $next($request);
    }
}

