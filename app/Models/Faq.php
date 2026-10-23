<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['pergunta', 'resposta', 'ordem', 'ativa'];

    protected $casts = ['ativa' => 'boolean', 'ordem' => 'integer'];

    public function scopeAtivas($q)
    {
        return $q->where('ativa', true)->orderBy('ordem');
    }

    /** Retorna as FAQs do banco; se vazias, usa o fallback de config/golden.php. */
    public static function paraSite(): array
    {
        $db = static::ativas()->get()->map(fn ($f) => ['q' => $f->pergunta, 'a' => $f->resposta])->all();
        return $db !== [] ? $db : (config('golden.faq') ?? []);
    }
}
