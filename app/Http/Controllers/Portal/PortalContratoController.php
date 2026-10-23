<?php

namespace App\Http\Controllers\Portal;

use App\Models\Contract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contratos publicados para o cliente ler.
 *
 * Rascunho fica fora da lista: documento ainda em feitura da equipe não é informação do
 * cliente. E o arquivo nunca sai por URL pública chutada — o download passa por esta rota,
 * que só entrega o PDF de um contrato da ficha logada.
 */
class PortalContratoController extends PortalBaseController
{
    public function index(): View
    {
        $contratos = $this->cliente()->contracts()->where('status', '!=', 'rascunho')->get();

        return view('portal.contratos', [
            'contratos' => $contratos,
            'vigentes' => $contratos->whereIn('status', ['assinado', 'vigente'])->count(),
        ]);
    }

    public function arquivo(Contract $contrato): StreamedResponse
    {
        abort_unless($contrato->customer_id === $this->cliente()->id, 404);
        abort_unless($contrato->status !== 'rascunho', 404);
        abort_unless($contrato->temArquivo(), 404);

        return Storage::disk('public')->download(
            $contrato->arquivo,
            $contrato->numero.'-'.Str::slug($contrato->titulo).'.pdf'
        );
    }
}
