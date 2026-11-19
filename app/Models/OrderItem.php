<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'nome', 'sku', 'quantidade', 'preco_unit_cents', 'mensalidade_cents', 'chip_operadora', 'pacotes_alertas', 'pacotes_alertas_cents', 'cobrar_mensalidade_hoje', 'subtotal_cents'];

    protected $casts = [
        'quantidade' => 'integer',
        'preco_unit_cents' => 'integer',
        'mensalidade_cents' => 'integer',
        'pacotes_alertas' => 'array',
        'pacotes_alertas_cents' => 'integer',
        'cobrar_mensalidade_hoje' => 'boolean',
        'subtotal_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
