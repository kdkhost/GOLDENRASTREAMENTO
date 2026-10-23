<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'tipo', 'quantidade', 'saldo_after', 'motivo', 'user_id', 'order_id'];

    protected $casts = ['quantidade' => 'integer', 'saldo_after' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function registrar(Product $produto, string $tipo, int $quantidade, ?string $motivo = null, ?int $userId = null, ?int $orderId = null): self
    {
        $delta = match ($tipo) {
            'entrada' => abs($quantidade),
            'saida' => -abs($quantidade),
            default => $quantidade - $produto->estoque, // ajuste
        };

        $produto->estoque = max(0, $produto->estoque + $delta);
        $produto->save();
        $produto->refresh();

        return static::create([
            'product_id' => $produto->id,
            'tipo' => $tipo,
            'quantidade' => $delta,
            'saldo_after' => $produto->estoque,
            'motivo' => $motivo,
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }
}
