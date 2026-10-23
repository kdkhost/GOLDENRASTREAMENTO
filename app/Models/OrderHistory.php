<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marco da jornada de um pedido. Toda mudança de status escreve uma linha — é o que
 * alimenta a linha do tempo da página pública e o histórico visto no painel.
 */
class OrderHistory extends Model
{
    protected $fillable = ['order_id', 'status', 'observacao', 'user_id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return Order::STATUS[$this->status] ?? ucfirst($this->status);
    }
}
