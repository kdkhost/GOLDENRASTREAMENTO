<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Envio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Destino único de todos os campos de arrastar-e-soltar do painel (imagens,
 * vídeos e documentos). O navegador envia o arquivo + a chave do campo; a
 * pasta de destino e as regras vêm de config/envios.php, nunca do pedido —
 * assim ninguém escreve fora de uma pasta de envio.
 *
 * Responde JSON porque o campo mostra o progresso real do upload e o tempo que
 * ele levou; nada disso existiria num POST comum de formulário.
 */
class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $campo = (string) $request->input('campo');

        if (! array_key_exists($campo, Envio::campos())) {
            return $this->erro('Campo de envio inválido.');
        }

        if (! $request->hasFile('arquivo')) {
            // Corpo cortado pelo servidor (post_max_size) chega sem nenhum campo.
            return $this->erro($request->getContentLength() > 0
                ? 'O arquivo passou do limite de envio do servidor ('.ltrim((string) ini_get('post_max_size')).').'
                : 'Escolha um arquivo para enviar.');
        }

        $regra = Envio::regra($campo);

        $validou = validator(['arquivo' => $request->file('arquivo')], [
            'arquivo' => [
                'required', 'file',
                'mimes:'.implode(',', $regra['formatos']),
                'max:'.intdiv(Envio::maxBytes($campo), 1024),
            ],
        ]);

        if ($validou->fails()) {
            return $this->erro(sprintf(
                'Esse arquivo não atende ao campo: use %s, no máximo %d MB.',
                strtoupper(implode(', ', $regra['formatos'])),
                (int) $regra['max_mb'],
            ));
        }

        try {
            $descritor = Envio::guardar($request->file('arquivo'), $campo);
        } catch (ValidationException $e) {
            return $this->erro(collect($e->errors())->flatten()->first()
                ?: 'Esse arquivo não atende ao campo.');
        }

        return response()->json(['ok' => true, 'arquivo' => $descritor]);
    }

    /** Descarta um arquivo recém-enviado (o operador trocou ou removeu antes de salvar). */
    public function destroy(Request $request): JsonResponse
    {
        $campo = (string) $request->input('campo');
        $caminho = (string) $request->input('caminho');

        if (! array_key_exists($campo, Envio::campos()) || ! Envio::caminhoConfere($caminho, $campo)) {
            return $this->erro('Esse arquivo não pertence a este campo.');
        }

        Storage::disk('public')->delete($caminho);

        return response()->json(['ok' => true]);
    }

    private function erro(string $mensagem): JsonResponse
    {
        return response()->json(['ok' => false, 'erro' => $mensagem], 422);
    }
}
