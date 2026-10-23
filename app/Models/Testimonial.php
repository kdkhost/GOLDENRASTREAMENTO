<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Testimonial extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.depoimentos_site'];

    protected $fillable = ['nome', 'cidade', 'papel', 'texto', 'nota', 'aprovado', 'ordem'];

    protected $casts = ['aprovado' => 'boolean', 'nota' => 'integer', 'ordem' => 'integer'];

    public function scopeAprovados($q)
    {
        return $q->where('aprovado', true)->orderBy('ordem');
    }

    /** Depoimentos aprovados do carrossel, servidos do cache. */
    public static function aprovadosParaSite(int $limite = 6): array
    {
        $todos = Cache::rememberForever('golden.depoimentos_site', function () {
            return static::aprovados()->get(['nome', 'cidade', 'papel', 'texto', 'nota'])
                ->map(fn (self $t) => [
                    'nome' => $t->nome,
                    'cidade' => $t->cidade,
                    'papel' => $t->papel,
                    'texto' => $t->texto,
                    'nota' => $t->nota,
                ])
                ->all();
        });

        return array_slice($todos, 0, max(1, $limite));
    }
}
