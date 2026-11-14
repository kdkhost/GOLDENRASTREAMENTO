<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Correios\CorreiosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RastreamentoController extends Controller
{
    public function __construct(private readonly CorreiosService $correios) {}

    public function index(Request $request, ?string $codigo = null): View|JsonResponse
    {
        $codigoConsulta = trim((string) ($codigo ?: $request->query('codigo', '')));
        $resultado = null;

        if ($codigoConsulta !== '') {
            $resultado = $this->correios->rastrear($codigoConsulta);

            if ($request->wantsJson()) {
                return response()->json($resultado);
            }
        }

        return view('site.rastreio', [
            'codigo' => $codigoConsulta,
            'rastreio' => $resultado,
        ]);
    }
}
