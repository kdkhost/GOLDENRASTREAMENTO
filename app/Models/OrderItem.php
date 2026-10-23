<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'nome', 'quantidade', 'preco_unit_cents', 'subtotal_cents'];

    protected $casts = ['quantidade' => 'integer', 'preco_unit_cents' => 'integer', 'subtotal_cents' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
