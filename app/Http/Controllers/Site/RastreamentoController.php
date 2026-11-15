<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\RastreioEncomendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RastreamentoController extends Controller
{
    public function __construct(private readonly RastreioEncomendaService $rastreio) {}

    public function index(Request $request, ?string $codigo = null): View|JsonResponse
    {
        $codigoConsulta = trim((string) ($codigo ?: $request->query('codigo', '')));
        $forcar = $request->boolean('atualizar');
        $resultado = null;

        if ($codigoConsulta !== '') {
            $resultado = $this->rastreio->rastrear($codigoConsulta, $forcar);

            if ($request->wantsJson()) {
                $payload = $resultado;
                if (isset($payload['pedido']) && $payload['pedido'] !== null) {
                    $p = $payload['pedido'];
                    $payload['pedido'] = [
                        'numero' => $p->numero,
                        'nome' => $p->nome,
                        'cidade' => $p->cidade,
                        'uf' => $p->uf,
                    ];
                }

                return response()->json($payload);
            }
        }

        return view('site.rastreio', [
            'codigo' => $codigoConsulta,
            'rastreio' => $resultado,
            'logisticasAtivas' => $this->rastreio->logisticasAtivas(),
        ]);
    }
}
