<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Um tutorial em vídeo da galeria do site. A identidade do registro é o código do
 * YouTube — é por ele que o painel evita cadastrar o mesmo vídeo duas vezes e é ele
 * que vira miniatura, link de assistir e endereço do player embutido.
 */
class Video extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.videos_site'];

    protected $fillable = ['titulo', 'codigo', 'categoria', 'descricao', 'ordem', 'ativa'];

    protected $casts = ['ativa' => 'boolean', 'ordem' => 'integer'];

    /** O player vem sem cookies: a galeria abre depois do consentimento do visitante. */
    private const EMBED = 'https://www.youtube-nocookie.com/embed/';

    public static function categorias(): array
    {
        return (array) config('golden.video_categorias');
    }

    /** Chave => rótulo, na ordem do registro: é o que os seletores do painel mostram. */
    public static function rotulos(): array
    {
        return collect(self::categorias())->map(fn (array $c) => $c['rotulo'])->all();
    }

    public static function rotuloDaCategoria(string $categoria): string
    {
        return (string) (self::categorias()[$categoria]['rotulo'] ?? $categoria);
    }

    /**
     * Aceita o que o operador cola no campo: um link do YouTube (watch, youtu.be,
     * shorts, embed ou live) ou os onze caracteres do código. Devolve null quando
     * não há código nenhum ali — é assim que o painel aponta o erro em português.
     */
    public static function codigoDe(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('#^(?:https?://)?(?:www\.)?(?:m\.)?(?:youtube\.com/(?:watch?\?(?:.*&)?v=|shorts/|embed/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $valor, $achados)) {
            return $achados[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $valor) ? $valor : null;
    }

    public static function thumb(?string $codigo): ?string
    {
        return $codigo === null || $codigo === '' ? null : 'https://i.ytimg.com/vi/'.$codigo.'/hqdefault.jpg';
    }

    public static function embed(?string $codigo): ?string
    {
        return $codigo === null || $codigo === '' ? null : self::EMBED.$codigo.'?autoplay=1&rel=0';
    }

    public function scopeAtivas($q)
    {
        return $q->where('ativa', true)->orderBy('ordem')->orderBy('id');
    }

    /** A galeria inteira, servida do cache: a página pública não visita o banco. */
    public static function paraSite(): array
    {
        return Cache::rememberForever('golden.videos_site', function () {
            return static::ativas()->get()
                ->map(fn (self $v) => [
                    'id' => $v->id,
                    'titulo' => $v->titulo,
                    'codigo' => $v->codigo,
                    'categoria' => $v->categoria,
                    'categoria_rotulo' => static::rotuloDaCategoria($v->categoria),
                    'descricao' => $v->descricao,
                    'thumb' => static::thumb($v->codigo),
                    'assistir' => 'https://www.youtube.com/watch?v='.$v->codigo,
                ])
                ->all();
        });
    }
}
