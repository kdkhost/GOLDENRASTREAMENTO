<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'titulo', 'slug', 'eyebrow', 'intro', 'corpo',
        'meta_title', 'meta_description', 'exibir_menu', 'ordem_menu', 'publicada',
    ];

    protected $casts = ['exibir_menu' => 'boolean', 'publicada' => 'boolean', 'ordem_menu' => 'integer'];

    public function scopePublicadas($q)
    {
        return $q->where('publicada', true)->orderBy('ordem_menu');
    }

    public function scopeMenu($q)
    {
        return $q->where('publicada', true)->where('exibir_menu', true)->orderBy('ordem_menu');
    }

    public static function booted(): void
    {
        static::saving(function (self $p) {
            if (empty($p->slug)) {
                $p->slug = slugify_pt($p->titulo);
            }
            if ($p->isDirty('corpo')) {
                $p->corpo = self::sanitizeHtml((string) $p->corpo);
            }
        });
    }

    /** Remove tags/execução de script do corpo editado no painel. */
    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#i', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(javascript|data|vbscript)\s*:#i', '', $html);
        return trim($html);
    }
}
