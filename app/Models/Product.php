<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.loja_catalogo'];

    protected $fillable = [
        'category_id', 'nome', 'slug', 'sku', 'resumo', 'descricao',
        'preco_cents', 'preco_promocional_cents', 'mensalidade_cents',
        'estoque', 'estoque_minimo', 'imagem', 'video', 'destaque', 'ativo', 'controle_estoque', 'ordem',
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
        return $this->ativo && (! $this->controle_estoque || $this->estoque > 0);
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

    /**
     * A vitrine inteira, já resolvida, servida de uma única chave de cache. Loja,
     * página de produto, carrinho e checkout montam a tela a partir deste array — a
     * requisição pública não emite SELECT e o pico de tráfego de uma campanha não
     * chega ao banco. Gravações no catálogo (painel ou estoque) invalidam a chave.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalogoParaSite(): array
    {
        return Cache::rememberForever('golden.loja_catalogo', function () {
            return static::with('category:id,nome,slug')
                ->where('ativo', true)
                ->orderByDesc('destaque')
                ->orderBy('ordem')
                ->get()
                ->map(fn (self $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'slug' => $p->slug,
                    'sku' => $p->sku,
                    'resumo' => $p->resumo,
                    'descricao' => $p->descricao,
                    'preco_cents' => $p->preco_cents,
                    'preco' => $p->preco(),
                    'promocao' => $p->temPromocao(),
                    'mensalidade' => $p->mensalidade_cents,
                    'imagem' => $p->imagem,
                    'video' => $p->video,
                    'destaque' => (bool) $p->destaque,
                    'estoque' => (int) $p->estoque,
                    'controle_estoque' => (bool) $p->controle_estoque,
                    'disponivel' => $p->disponivel(),
                    'categoria' => $p->category?->nome,
                    'categoria_slug' => $p->category?->slug,
                ])
                ->all();
        });
    }

    /** Um produto da vitrine pelo slug (mesmo blob de cache, sem consulta). */
    public static function paraSite(string $slug): ?array
    {
        foreach (static::catalogoParaSite() as $produto) {
            if ($produto['slug'] === $slug) {
                return $produto;
            }
        }

        return null;
    }

    /** O mesmo produto, quando a referência que se tem é o id (linhas do carrinho). */
    public static function doCatalogo(int $id): ?array
    {
        foreach (static::catalogoParaSite() as $produto) {
            if ($produto['id'] === $id) {
                return $produto;
            }
        }

        return null;
    }

    /**
     * Categorias com contagem de produtos ativos, derivadas do catálogo em cache —
     * é o que alimenta os filtros da loja e o menu.
     */
    public static function categoriasDaVitrine(): array
    {
        $grupos = [];

        foreach (static::catalogoParaSite() as $produto) {
            $slug = $produto['categoria_slug'] ?: '';
            $grupos[$slug] ??= ['nome' => $produto['categoria'] ?: 'Outros', 'slug' => $slug, 'total' => 0];
            $grupos[$slug]['total']++;
        }

        return array_values($grupos);
    }
}
