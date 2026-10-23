<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'nome', 'slug', 'sku', 'resumo', 'descricao',
        'preco_cents', 'preco_promocional_cents', 'mensalidade_cents',
        'estoque', 'estoque_minimo', 'imagem', 'destaque', 'ativo', 'controle_estoque', 'ordem',
    ];

    protected $casts = [
        'destaque' => 'boolean',
        'ativo' => 'boolean',
        'controle_estoque' => 'boolean',
        'preco_cents' => 'integer',
        'preco_promocional_cents' => 'integer',
        'mensalidade_cents' => 'integer',
        'estoque' => 'integer',
        'estoque_minimo' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /** Preço efetivo de venda (considera promoção). */
    public function preco(): int
    {
        return $this->preco_promocional_cents !== null && $this->preco_promocional_cents > 0
            ? $this->preco_promocional_cents
            : $this->preco_cents;
    }

    public function temPromocao(): bool
    {
        return $this->preco_promocional_cents !== null
            && $this->preco_promocional_cents > 0
            && $this->preco_promocional_cents < $this->preco_cents;
    }

    public function disponivel(): bool
    {
        return $this->ativo && (!$this->controle_estoque || $this->estoque > 0);
    }

    public function scopeAtivos($q)
    {
        return $q->where('ativo', true)->orderBy('ordem');
    }

    public function scopeVitrine($q)
    {
        return $q->where('ativo', true)->orderByDesc('destaque')->orderBy('ordem');
    }

    public static function booted(): void
    {
        static::creating(function (self $p) {
            if (empty($p->slug)) {
                $p->slug = slugify_pt($p->nome);
            }
        });
    }
}
