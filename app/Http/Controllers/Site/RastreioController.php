<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\VisitEvent;
use App\Services\Rastreio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O farol do site: cada clique que o visitante dá numa chamada, num kit ou no
 * formulário chega aqui e entra na jornada da visita. Visualização de página não
 * passa por aqui — o middleware já anotou no HTML, e aceitar 'view' por POST
 * duplicaria a contagem.
 */
class RastreioController extends Controller
{
    public function evento(Request $request, Rastreio $rastreio): JsonResponse
    {
        $aceitos = array_values(array_diff(VisitEvent::tipos(), ['view']));

        $data = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', $aceitos)],
            // Os campos são aparados pelo Rastreio no tamanho da coluna; o teto acima só
            // barra o absurdo. Perder o clique por causa de URL longa custaria mais caro.
            'path' => ['nullable', 'string', 'max:2000'],
            'destino' => ['nullable', 'string', 'max:2000'],
            'detalhe' => ['nullable', 'string', 'max:2000'],
        ]);

        $rastreio->registrarEvento(
            $request,
            $data['tipo'],
            $data['path'] ?? null,
            $data['destino'] ?? null,
            $data['detalhe'] ?? null,
        );

        return response()->json(status: 204);
    }
}
