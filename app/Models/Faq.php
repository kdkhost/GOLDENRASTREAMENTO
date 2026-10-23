<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Faq extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.faqs_site'];

    protected $fillable = ['pergunta', 'resposta', 'ordem', 'ativa'];

    protected $casts = ['ativa' => 'boolean', 'ordem' => 'integer'];

    public function scopeAtivas($q)
    {
        return $q->where('ativa', true)->orderBy('ordem');
    }

    /** Retorna as FAQs do banco (cacheadas); se vazias, usa o fallback de config/golden.php. */
    public static function paraSite(): array
    {
        $db = Cache::rememberForever('golden.faqs_site', function () {
            return static::ativas()->get()->map(fn (self $f) => ['q' => $f->pergunta, 'a' => $f->resposta])->all();
        });

        return $db !== [] ? $db : (config('golden.faq') ?? []);
    }
}
