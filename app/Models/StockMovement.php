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

    /**
     * Registra a movimentação e devolve o produto com o novo saldo.
     * $saldoAnterior é para o ajuste vindo do cadastro, em que o produto já carrega o
     * estoque novo: sem ele a diferença sairia errada (e copiar o modelo antigo, id a menos).
     */
    public static function registrar(Product $produto, string $tipo, int $quantidade, ?string $motivo = null, ?int $userId = null, ?int $orderId = null, ?int $saldoAnterior = null): self
    {
        $antes = $saldoAnterior ?? $produto->estoque;

        $delta = match ($tipo) {
            'entrada' => abs($quantidade),
            'saida' => -abs($quantidade),
            default => $quantidade - $antes, // ajuste: a diferença entre o novo e o que estava
        };

        $produto->estoque = max(0, $antes + $delta);
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
