<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um passo da jornada: cada visualização de página, clique em chamada, ligação,
 * WhatsApp, kit escolhido, formulário iniciado, cadastro enviado ou pedido criado.
 * Os tipos possíveis vivem em config('golden.rastreio.eventos') — uma régua só para
 * o beacon do site, a linha do tempo do painel e os rótulos.
 */
class VisitEvent extends Model
{
    protected $fillable = ['visit_id', 'visitor_id', 'tipo', 'path', 'destino', 'detalhe'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** As chaves válidas para o beacon; o validador do controller usa esta lista. */
    public static function tipos(): array
    {
        return array_keys((array) config('golden.rastreio.eventos'));
    }

    public function rotulo(): string
    {
        return config('golden.rastreio.eventos.'.$this->tipo.'.rotulo', $this->tipo);
    }

    public function icone(): string
    {
        return config('golden.rastreio.eventos.'.$this->tipo.'.icone', 'fa-circle');
    }
}
