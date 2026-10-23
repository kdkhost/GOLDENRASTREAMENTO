<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.categorias_ativas', 'golden.loja_catalogo'];

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

    /** Categorias do menu da loja e dos filtros, servidas do cache. */
    public static function ativasParaSite(): array
    {
        return Cache::rememberForever('golden.categorias_ativas', function () {
            return static::ativas()->get(['nome', 'slug'])
                ->map(fn (self $c) => ['nome' => $c->nome, 'slug' => $c->slug])
                ->all();
        });
    }
}
