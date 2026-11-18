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

    public const OPERADORAS_CHIP = [
        'algar' => [
            'id' => 'algar',
            'nome' => 'Chip Multioperadora',
            'tipo' => 'multioperadora',
            'rotulo' => 'Chip Multioperadora (Claro, Vivo e TIM)',
            'descricao' => 'Conecta automaticamente na melhor rede entre Claro, Vivo e TIM com máxima cobertura nacional.',
            'destaque' => 'Recomendado · Maior Cobertura',
            'icone' => 'fa-satellite-dish',
        ],
        'vivo' => [
            'id' => 'vivo',
            'nome' => 'Vivo M2M',
            'tipo' => 'individual',
            'rotulo' => 'Vivo M2M (Individual)',
            'descricao' => 'Conexão dedicada na rede móvel da Vivo com cobertura 4G/2G.',
            'destaque' => 'Operadora Individual',
            'icone' => 'fa-sim-card',
        ],
        'claro' => [
            'id' => 'claro',
            'nome' => 'Claro M2M',
            'tipo' => 'individual',
            'rotulo' => 'Claro M2M (Individual)',
            'descricao' => 'Conexão dedicada na rede móvel da Claro com cobertura 4G/2G.',
            'destaque' => 'Operadora Individual',
            'icone' => 'fa-sim-card',
        ],
        'tim' => [
            'id' => 'tim',
            'nome' => 'TIM M2M',
            'tipo' => 'individual',
            'rotulo' => 'TIM M2M (Individual)',
            'descricao' => 'Conexão dedicada na rede móvel da TIM com cobertura 4G/2G.',
            'destaque' => 'Operadora Individual',
            'icone' => 'fa-sim-card',
        ],
    ];

    protected $fillable = [
        'category_id', 'nome', 'slug', 'sku', 'resumo', 'descricao',
        'preco_cents', 'preco_promocional_cents', 'mensalidade_cents', 'sob_consulta', 'cobrar_primeira_mensalidade',
        'tipo_cobranca', 'chip_operadora', 'beneficio_chip_texto', 'permite_escolha_chip', 'operadoras_chip', 'precos_chip', 'chip_padrao', 'garantia', 'anatel_homologado', 'recursos',
        'peso_gramas', 'comprimento_cm', 'largura_cm', 'altura_cm',
        'estoque', 'estoque_minimo', 'imagem', 'galeria', 'video', 'destaque', 'ativo', 'controle_estoque', 'ordem',
    ];

    protected $casts = [
        'destaque' => 'boolean',
        'ativo' => 'boolean',
        'controle_estoque' => 'boolean',
        'sob_consulta' => 'boolean',
        'cobrar_primeira_mensalidade' => 'boolean',
        'permite_escolha_chip' => 'boolean',
        'operadoras_chip' => 'array',
        'precos_chip' => 'array',
        'preco_cents' => 'integer',
        'preco_promocional_cents' => 'integer',
        'mensalidade_cents' => 'integer',
        'estoque' => 'integer',
        'estoque_minimo' => 'integer',
        'galeria' => 'array',
        'recursos' => 'array',
        'peso_gramas' => 'integer',
        'comprimento_cm' => 'integer',
        'largura_cm' => 'integer',
        'altura_cm' => 'integer',
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

    /**
     * Retorna a lista unificada de fotos do produto (foto principal + fotos adicionais da galeria).
     *
     * @return array<int, string>
     */
    public function todasImagens(): array
    {
        $imagens = [];
        if (! empty($this->imagem)) {
            $imagens[] = (string) $this->imagem;
        }
        foreach ((array) ($this->galeria ?? []) as $foto) {
            if (! empty($foto) && is_string($foto) && ! in_array($foto, $imagens, true)) {
                $imagens[] = $foto;
            }
        }

        if (empty($imagens)) {
            $padrao = static::imagemPadraoPorSlug($this->slug, $this->category?->slug);
            if ($padrao) {
                $imagens[] = $padrao;
            }
        }

        return $imagens;
    }

    public static function imagemPadraoPorSlug(string $slug, ?string $categoriaSlug = null): ?string
    {
        return match ($slug) {
            'plano-frota' => 'img/home/segmento-frota.jpg',
            'rastreador-j16-4g' => 'img/home/rastreador-j16.jpg',
            'rastreador-moto-j16' => 'img/home/rastreador-j6.jpg',
            'botao-de-panico', 'rele-de-bloqueio', 'kit-chicote' => 'img/home/rastreador-j16.jpg',
            default => str_starts_with($slug, 'chip-m2m')
                ? 'img/home/plataforma-monitor.jpg'
                : ($categoriaSlug === 'planos' ? 'img/home/segmento-frota.jpg' : 'img/home/rastreador-j16.jpg'),
        };
    }

    /**
     * Dicionário de recursos comuns de rastreamento veicular para seleção no admin.
     *
     * @return array<string, string>
     */
    public static function recursosDisponiveis(): array
    {
        return [
            'chip_incluso' => 'Chip M2M Incluso no Benefício do Plano (Sem Custo de Ativação do Chip)',
            'app' => 'Aplicativo Celular (Android e iOS)',
            'web' => 'Plataforma Web para Computador',
            'bloqueio' => 'Bloqueio e Desbloqueio Remoto',
            'cercas' => 'Cercas Virtuais com Alerta de Saída',
            'rotas' => 'Histórico e Reprodução de Trajetos (90 dias)',
            'ignicao' => 'Alerta de Ignição Ligada / Desligada',
            'bateria' => 'Alerta de Bateria Desconectada ou Baixa',
            'velocidade' => 'Alerta de Excesso de Velocidade',
            'movimento' => 'Sensor de Movimento Indevido / Reboque',
            'panico' => 'Compatível com Botão de Pânico SOS',
            'suporte' => 'Suporte Técnico Humanizado via WhatsApp',
            'central24h' => 'Central de Alerta e Emergência 24h',
        ];
    }

    public static function operadorasDisponiveis(): array
    {
        return static::OPERADORAS_CHIP;
    }

    public function mensalidadeParaOperadora(?string $operadora = null): int
    {
        $op = $operadora ?: $this->operadoraPadrao();
        $precos = (array) ($this->precos_chip ?? []);
        if (isset($precos[$op]) && is_numeric($precos[$op]) && (int) $precos[$op] > 0) {
            return (int) $precos[$op];
        }

        return (int) ($this->mensalidade_cents ?? 0);
    }

    public function operadorasConfiguradas(): array
    {
        $selecionadas = (array) ($this->operadoras_chip ?? []);
        $todas = static::OPERADORAS_CHIP;
        $chaves = ! empty($selecionadas) ? $selecionadas : array_keys($todas);
        $precos = (array) ($this->precos_chip ?? []);
        $mensalidadeBase = (int) ($this->mensalidade_cents ?? 0);

        $resultado = [];
        foreach ($chaves as $chave) {
            if (isset($todas[$chave])) {
                $dados = $todas[$chave];
                $precoOp = (isset($precos[$chave]) && is_numeric($precos[$chave]) && (int) $precos[$chave] > 0)
                    ? (int) $precos[$chave]
                    : $mensalidadeBase;
                $dados['mensalidade_cents'] = $precoOp;
                $dados['mensalidade_formatada'] = $precoOp > 0 ? brl($precoOp).'/mês' : null;
                $dados['chip_incluso'] = true;
                $resultado[$chave] = $dados;
            }
        }

        return $resultado ?: $todas;
    }

    public function permiteEscolhaChip(): bool
    {
        if ($this->permite_escolha_chip !== null && (bool) $this->permite_escolha_chip) {
            return true;
        }

        $temMensalidade = ! empty($this->mensalidade_cents) && $this->mensalidade_cents > 0;
        $ehRastreadorOuPlano = in_array((int) $this->category_id, [1, 2, 4], true)
            || str_contains(strtolower($this->nome), 'rastreador')
            || str_contains(strtolower($this->nome), 'plano')
            || str_contains(strtolower($this->nome), 'chip');

        return $temMensalidade || $ehRastreadorOuPlano;
    }

    public function operadoraPadrao(): string
    {
        if (! empty($this->chip_padrao) && isset(static::OPERADORAS_CHIP[$this->chip_padrao])) {
            return $this->chip_padrao;
        }

        $configuradas = array_keys($this->operadorasConfiguradas());

        return $configuradas[0] ?? 'algar';
    }

    public function tipoCobrancaRotulo(): string
    {
        return match ($this->tipo_cobranca) {
            'unico' => 'Venda Única (Equipamento / Sem mensalidade)',
            'combo' => 'Combo (Equipamento + Mensalidade por veículo)',
            default => 'Plano Mensal (Mensalidade recorrente por veículo)',
        };
    }

    /**
     * Estado do estoque em uma palavra: 'livre' (sem controle), 'zerado', 'baixo'
     * (no mínimo ou abaixo) ou 'ok'. É a régua que decide a cor do selo no painel e
     * o aviso na vitrine, para as duas telas não discordarem sobre o mesmo saldo.
     */
    public static function estado(bool $controle, int $estoque, int $minimo): string
    {
        return match (true) {
            ! $controle => 'livre',
            $estoque <= 0 => 'zerado',
            $estoque <= $minimo => 'baixo',
            default => 'ok',
        };
    }

    public function estadoEstoque(): string
    {
        return self::estado((bool) $this->controle_estoque, (int) $this->estoque, (int) $this->estoque_minimo);
    }

    public function scopeAtivos($q)
    {
        return $q->where('ativo', true)->orderBy('ordem');
    }

    public function scopeVitrine($q)
    {
        return $q->where('ativo', true)->orderByDesc('destaque')->orderBy('ordem');
    }

    /**
     * A régua do alarme de estoque, nos mesmos termos de estado(): só conta quem tem
     * controle de estoque; 'baixo' é o saldo no mínimo ou abaixo dele (zerado incluso).
     */
    public function scopeEstoqueBaixo($q)
    {
        return $q->where('controle_estoque', true)->whereColumn('estoque', '<=', 'estoque_minimo');
    }

    public function scopeEstoqueZerado($q)
    {
        return $q->where('controle_estoque', true)->where('estoque', '<=', 0);
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
                    'sob_consulta' => (bool) ($p->sob_consulta ?? false),
                    'cobrar_primeira_mensalidade' => (bool) ($p->cobrar_primeira_mensalidade ?? true),
                    'tipo_cobranca' => $p->tipo_cobranca ?: 'mensal',
                    'chip_operadora' => $p->chip_operadora,
                    'beneficio_chip_texto' => $p->beneficio_chip_texto,
                    'category_id' => $p->category_id,
                    'permite_escolha_chip' => $p->permiteEscolhaChip(),
                    'operadoras_chip' => (array) ($p->operadoras_chip ?? []),
                    'precos_chip' => (array) ($p->precos_chip ?? []),
                    'operadoras_detalhes' => $p->operadorasConfiguradas(),
                    'chip_padrao' => $p->operadoraPadrao(),
                    'garantia' => $p->garantia,
                    'anatel_homologado' => $p->anatel_homologado,
                    'recursos' => (array) ($p->recursos ?? []),
                    'peso_gramas' => $p->peso_gramas,
                    'comprimento_cm' => $p->comprimento_cm,
                    'largura_cm' => $p->largura_cm,
                    'altura_cm' => $p->altura_cm,
                    'imagem' => $p->imagem ?: static::imagemPadraoPorSlug($p->slug, $p->category?->slug),
                    'galeria' => (array) ($p->galeria ?? []),
                    'imagens' => $p->todasImagens(),
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
