<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['nome', 'slug', 'descricao', 'ordem', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeAtivas($q)
    {
        return $q->where('ativa', true)->orderBy('ordem');
    }
}
