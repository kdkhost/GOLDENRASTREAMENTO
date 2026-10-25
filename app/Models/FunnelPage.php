<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use App\Support\ConteudoSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Página de campanha: funil de vendas completo que não entra no menu, no rodapé nem
 * na busca — só chega aqui quem recebeu o link. O texto mora nos blocos filhos; esta
 * linha guarda o endereço, o cabeçalho de SEO, os pixels de anúncio e a situação.
 *
 * O endereço é independente do nome: trocar o slug não apaga o histórico do contador,
 * porque todo caminho já servido fica registrado em `caminhos`.
 */
class FunnelPage extends Model
{
    use CachesContent;

    protected array $cacheKeys = ['golden.paginas_funil'];

    protected $fillable = [
        'nome', 'slug', 'status', 'visibilidade', 'pele',
        'titulo', 'meta_description', 'meta_keywords', 'og_imagem', 'og_imagem_alt', 'canonical',
        'pixel_ga4', 'pixel_google_ads', 'conversao_google_ads', 'pixel_meta', 'pixel_tiktok',
        'codigo_head', 'codigo_body',
        'preco_cents', 'nota_media', 'num_avaliacoes', 'nota_legal',
    ];

    protected $casts = [
        'preco_cents' => 'integer',
        'nota_media' => 'float',
        'num_avaliacoes' => 'integer',
    ];

    /** Prefixo do endereço: página de campanha, não página institucional. */
    public const ROTA = '/c/';

    public function blocks(): HasMany
    {
        return $this->hasMany(FunnelBlock::class)->orderBy('ordem')->orderBy('id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopePublicadas(Builder $q): Builder
    {
        return $q->where('status', 'publicada')->orderBy('nome');
    }

    /**
     * A página servida no site, com os blocos ativos já montados no relacionamento:
     * /c/{slug} aquecido não emite nenhum SELECT. O blob guarda a página e os blocos
     * na mesma leitura, porque as duas tabelas mudam juntas no painel.
     */
    public static function paraSite(string $slug): ?self
    {
        return self::peloCaminho(self::ROTA.$slug);
    }

    private static function publicadasParaSite(): array
    {
        return Cache::rememberForever('golden.paginas_funil', function () {
            return static::publicadas()->with(['blocks' => fn ($q) => $q->where('ativa', true)])
                ->get()
                ->mapWithKeys(function (self $p) {
                    return [$p->slug => [
                        'pagina' => $p->getAttributes(),
                        'blocos' => $p->blocks->map(fn (FunnelBlock $b) => $b->getAttributes())->values()->all(),
                    ]];
                })
                ->all();
        });
    }

    /**
     * A página publicada que atende por este caminho, incluindo os endereços antigos.
     * É como o lead e o contador descobrem a campanha a partir de /c/{slug} — o cache
     * do site já tem tudo isso, então a consulta não emite SELECT nenhum.
     */
    public static function peloCaminho(string $caminho): ?self
    {
        $procurado = '/'.ltrim(trim($caminho), '/');

        foreach (self::publicadasParaSite() as $registro) {
            $pagina = (new static)->newFromBuilder($registro['pagina'])
                ->setRelation('blocks', FunnelBlock::hydrate($registro['blocos']));

            if (in_array($procurado, $pagina->caminhos(), true)) {
                return $pagina;
            }
        }

        return null;
    }

    public static function idPeloCaminho(string $caminho): ?int
    {
        return self::peloCaminho($caminho)?->id;
    }

    /**
     * Valida o id que veio do formulário: só uma página publicada pode receber um
     * cadastro, senão qualquer POST atribuiria lead a um rascunho do painel.
     */
    public static function idPublicado(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }

        foreach (self::publicadasParaSite() as $registro) {
            if ((int) ($registro['pagina']['id'] ?? 0) === $id) {
                return $id;
            }
        }

        return null;
    }

    public static function booted(): void
    {
        static::saving(function (self $p) {
            if (empty($p->slug)) {
                $p->slug = slugify_pt($p->nome);
            }

            // O endereço antigo continua contando visitas: entra no histórico antes
            // de a mudança ir para o banco.
            $anterior = $p->getOriginal('slug');

            if ($p->isDirty('slug') && filled($anterior)) {
                $p->caminhos = json_encode(
                    collect($p->caminhosHist())->push(self::ROTA.$anterior)->unique()->values()->all(),
                    JSON_UNESCAPED_SLASHES
                );
            }
        });
    }

    /** O caminho atual — é o que o rastreador compara com `Visit.landing_path`. */
    public function caminho(): string
    {
        return self::ROTA.$this->slug;
    }

    public function url(): string
    {
        return route('site.funil', ['slug' => $this->slug]);
    }

    /**
     * Todos os caminhos por onde esta página já foi publicada. O contador soma as
     * visitas de cada um, senão o relatório ficasse mudo na primeira troca de nome.
     *
     * @return array<int, string>
     */
    public function caminhos(): array
    {
        return collect($this->caminhosHist())
            ->push($this->caminho())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function caminhosHist(): array
    {
        return array_values(array_filter((array) json_decode((string) $this->caminhos, true)));
    }

    /**
     * Os blocos que a página pública monta: na ordem do painel, ativos, e só dos tipos
     * que ainda existem na config — um tipo aposentado não derruba a campanha no ar.
     *
     * @return array<int, FunnelBlock>
     */
    public function blocosParaSite(): array
    {
        $tipos = array_keys(FunnelBlock::tipos());

        return $this->blocks->filter(fn (FunnelBlock $b) => $b->ativa && in_array($b->tipo, $tipos, true))->values()->all();
    }

    /**
     * Alguma faixa desta página desliza. É o que decide carregar o Swiper: o arquivo tem
     * corpo, e a campanha que abre no clique do anúncio precisa chegar leve.
     */
    public function temCarrossel(): bool
    {
        foreach ($this->blocosParaSite() as $bloco) {
            if (in_array($bloco->tipo, FunnelBlock::CARROSSEIS, true)) {
                return true;
            }
        }

        return false;
    }

    /** O primeiro bloco deste tipo — é o que a página usa para o que não vem preenchido. */
    public function bloco(string $tipo): ?FunnelBlock
    {
        $bloco = $this->blocks->first(fn (FunnelBlock $b) => $b->ativa && $b->tipo === $tipo);

        return $bloco instanceof FunnelBlock ? $bloco : null;
    }

    /** O `<title>` e o `<h1>`: o título da campanha, senão o nome interno do painel. */
    public function tituloSite(): string
    {
        return trim((string) $this->titulo) !== '' ? (string) $this->titulo : (string) $this->nome;
    }

    /** O resumo que o buscador e o compartilhamento mostram, sempre preenchido. */
    public function resumoSite(): string
    {
        $descrito = trim((string) $this->meta_description);

        if ($descrito !== '') {
            return $descrito;
        }

        $hero = $this->bloco('hero');

        return trim(Str::limit((string) ($hero?->texto ?: 'Consulte as condições com a equipe da Golden.'), 200, ''));
    }

    /** O que vai no `<meta name="robots">`: a visibilidade escolhida no painel. */
    public function metaRobots(): string
    {
        return match ($this->visibilidade) {
            'index' => 'index,follow',
            'noindex_nofollow' => 'noindex,nofollow',
            default => 'noindex,follow',
        };
    }

    public function robotsConvidamBusca(): bool
    {
        return $this->visibilidade === 'index';
    }

    public function imageUrl(): ?string
    {
        return ConteudoSite::urlCaminho($this->og_imagem);
    }

    /**
     * O JSON-LD da página: o produto com a oferta e a nota, as dúvidas do bloco de FAQ
     * e a empresa. É o que vira resultado enriquecido na busca — e o que o assistente
     * de IA lê antes de recomendar a Golden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function esquemas(): array
    {
        $esquemas = [];

        if ($this->preco_cents) {
            $produto = ['@context' => 'https://schema.org', '@type' => 'Product'] + [
                'name' => $this->tituloSite(),
                'description' => $this->resumoSite(),
                'brand' => ['@type' => 'Brand', 'name' => (string) config('golden.marca.nome')],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format($this->preco_cents / 100, 2, '.', ''),
                    'priceCurrency' => 'BRL',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $this->url(),
                ],
            ];

            if (($cartaz = $this->imageUrl()) !== null) {
                $produto['image'] = $cartaz;
            }

            if ($this->nota_media && $this->num_avaliacoes) {
                $produto['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => $this->nota_media,
                    'reviewCount' => $this->num_avaliacoes,
                    'bestRating' => 5,
                ];
            }

            $esquemas[] = $produto;
        }

        $duvidas = collect($this->blocosParaSite())
            ->filter(fn (FunnelBlock $b) => $b->tipo === 'faq')
            ->flatMap(fn (FunnelBlock $b) => $b->itens())
            ->filter(fn (array $item) => filled($item['pergunta'] ?? null) && filled($item['resposta'] ?? null))
            ->map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['pergunta'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $item['resposta'])],
            ])
            ->values()
            ->all();

        if ($duvidas !== []) {
            $esquemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $duvidas];
        }

        [$localidade, $regiao] = array_pad(
            explode(' - ', (string) Setting::get('empresa_cidade', config('golden.contato.cidade_uf')), 2),
            2,
            ''
        );

        $esquemas[] = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness'] + [
            'name' => (string) config('golden.marca.nome'),
            'url' => route('site.home'),
            'telephone' => (string) Setting::get('contato_telefone', implode(', ', (array) config('golden.contato.telefones'))),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => (string) Setting::get('empresa_endereco', config('golden.contato.endereco')),
                'addressLocality' => $localidade,
                'addressRegion' => $regiao,
                'postalCode' => (string) Setting::get('empresa_cep', config('golden.contato.cep')),
                'addressCountry' => 'BR',
            ],
        ];

        return $esquemas;
    }

    public static function statusRotulos(): array
    {
        return (array) config('golden.funil_statuses');
    }

    public function statusRotulo(): string
    {
        return (string) (self::statusRotulos()[$this->status] ?? $this->status);
    }

    public function tomStatus(): string
    {
        return tom_por_status('pagina_funil', $this->status);
    }

    public static function peleRotulo(?string $pele): string
    {
        return (string) (config('golden.funil_peles')[$pele] ?? $pele);
    }

    public static function visibilidadeRotulo(?string $visibilidade): string
    {
        return (string) (config('golden.funil_visibilidade')[$visibilidade] ?? $visibilidade);
    }
}
