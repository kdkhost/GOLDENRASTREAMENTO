<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.paginas_publicadas'];

    protected $fillable = [
        'titulo', 'slug', 'eyebrow', 'intro', 'corpo',
        'meta_title', 'meta_description', 'exibir_menu', 'ordem_menu', 'publicada',
    ];

    protected $casts = ['exibir_menu' => 'boolean', 'publicada' => 'boolean', 'ordem_menu' => 'integer'];

    public function scopePublicadas($q)
    {
        return $q->where('publicada', true)->orderBy('ordem_menu');
    }

    /**
     * Páginas do menu, já na ordem do painel. Vem do mesmo blob de
     * self::paraSite() — uma única leitura de `pages` por ciclo de cache.
     */
    public static function menuParaSite(): array
    {
        return collect(self::publicadasParaSite())
            ->filter(fn (array $p) => $p['exibir_menu'])
            ->map(fn (array $p) => ['titulo' => $p['titulo'], 'slug' => $p['slug']])
            ->values()
            ->all();
    }

    /** Página do CMS servida do cache: exibir /p/{slug} não consulta o banco. */
    public static function paraSite(string $slug): ?self
    {
        $pagina = self::publicadasParaSite()[$slug] ?? null;

        return $pagina === null ? null : (new static)->newFromBuilder($pagina);
    }

    private static function publicadasParaSite(): array
    {
        return Cache::rememberForever('golden.paginas_publicadas', function () {
            return static::publicadas()->get()
                ->keyBy('slug')
                ->map(fn (self $p) => $p->getAttributes())
                ->all();
        });
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
