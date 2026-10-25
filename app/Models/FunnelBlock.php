<?php

namespace App\Models;

use App\Models\Concerns\CachesContent;
use App\Support\ConteudoSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um trecho da página de funil. O tipo decide quais campos existem e como a linha
 * digitada no painel se transforma em item — a lista de tipos vive em
 * config('golden.funil_blocos') e é a única fonte para o formulário do painel,
 * o parser e a página pública.
 */
class FunnelBlock extends Model
{
    use CachesContent;

    /** Grava na mesma chave da página: mexer num bloco muda o que o site serve. */
    protected array $cacheKeys = ['golden.paginas_funil'];

    protected $fillable = [
        'funnel_page_id', 'tipo', 'titulo', 'texto', 'corpo', 'imagem', 'imagem_alt',
        'itens', 'cta_texto', 'cta_destino', 'cta2_texto', 'cta2_destino', 'limite_em',
        'ordem', 'ativa',
    ];

    protected $casts = [
        'limite_em' => 'datetime',
        'ordem' => 'integer',
        'ativa' => 'boolean',
    ];

    /**
     * Um bloco ainda não gravado — o formulário de bloco novo do painel — precisa ter
     * todas as colunas à vista: sem isto, ler $bloco->itens de um modelo recém-created
     * cai no método itens() em vez da coluna e a tela quebra antes de mostrar o campo.
     */
    protected $attributes = [
        'titulo' => null, 'texto' => null, 'corpo' => null, 'imagem' => null, 'imagem_alt' => null,
        'itens' => null, 'cta_texto' => null, 'cta_destino' => null, 'cta2_texto' => null,
        'cta2_destino' => null, 'limite_em' => null,
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(FunnelPage::class, 'funnel_page_id');
    }

    public static function booted(): void
    {
        static::saving(function (self $b) {
            if ($b->isDirty('corpo')) {
                $b->corpo = Page::sanitizeHtml((string) $b->corpo);
            }
        });
    }

    /** @return array<string, array<string, mixed>> */
    public static function tipos(): array
    {
        return (array) config('golden.funil_blocos');
    }

    /** @return array<string, string> */
    public static function rotulos(): array
    {
        return collect(self::tipos())->map(fn (array $t) => $t['rotulo'])->all();
    }

    public static function rotuloTipo(?string $tipo): string
    {
        return (string) (self::tipos()[$tipo]['rotulo'] ?? $tipo);
    }

    public static function iconeTipo(?string $tipo): string
    {
        return (string) (self::tipos()[$tipo]['icone'] ?? 'fa-puzzle-piece');
    }

    public static function descricaoTipo(?string $tipo): string
    {
        return (string) (self::tipos()[$tipo]['descricao'] ?? '');
    }

    public static function dicaTipo(?string $tipo): ?string
    {
        $dica = self::tipos()[$tipo]['dica'] ?? null;

        return is_string($dica) && trim($dica) !== '' ? $dica : null;
    }

    /**
     * Os campos que este tipo mostra no painel, na ordem, cada um com o controle que
     * o x-admin.field conhece e o rótulo daquele tipo — assim o tipo novo não precisa
     * de view própria.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function camposPara(string $tipo): array
    {
        $registro = self::tipos()[$tipo] ?? [];
        $defs = (array) config('golden.funil_campos');
        $rotulos = (array) ($registro['rotulos'] ?? []);

        $campos = [];

        foreach ((array) ($registro['campos'] ?? []) as $campo) {
            $def = $defs[$campo] ?? null;

            if ($def === null) {
                continue;
            }

            $def['label'] = $rotulos[$campo] ?? $def['label'];
            $campos[$campo] = $def;
        }

        return $campos;
    }

    /** @return array<int, string> */
    public static function colunasDe(?string $tipo): array
    {
        return array_keys((array) (self::tipos()[$tipo]['colunas'] ?? []));
    }

    /** @return array<string, string> */
    public static function rotulosDasColunas(?string $tipo): array
    {
        return (array) (self::tipos()[$tipo]['colunas'] ?? []);
    }

    /** @return array<int, string> */
    private static function listasDe(?string $tipo): array
    {
        return (array) (self::tipos()[$tipo]['listas'] ?? []);
    }

    /**
     * O texto do campo 'itens' transformado em itens: uma linha por item, colunas
     * separadas por |, na ordem declarada pelo tipo. O que falta em linha curta vira
     * null e o que sobra é descartado — a view pública decide o que mostrar.
     *
     * @return array<int, array<string, string|array<int, string>|null>>
     */
    public function itens(): array
    {
        $colunas = self::colunasDe($this->tipo);

        if ($colunas === []) {
            return [];
        }

        $listas = self::listasDe($this->tipo);

        return collect(preg_split('/\R+/', (string) $this->itens))
            ->map(fn (string $linha) => array_map('trim', explode('|', $linha)))
            ->filter(fn (array $partes) => trim((string) ($partes[0] ?? '')) !== '')
            ->map(function (array $partes) use ($colunas, $listas) {
                $item = [];

                foreach ($colunas as $i => $chave) {
                    $valor = trim((string) ($partes[$i] ?? ''));

                    if ($valor === '') {
                        $item[$chave] = null;
                        continue;
                    }

                    $item[$chave] = in_array($chave, $listas, true)
                        ? collect(explode(';', $valor))->map('trim')->filter()->values()->all()
                        : $valor;
                }

                return $item;
            })
            ->values()
            ->all();
    }

    public function imagemUrl(): ?string
    {
        return ConteudoSite::urlCaminho($this->imagem);
    }

    /** Primeira imagem do funil, para a OG da página quando ela não tem cartaz próprio. */
    public static function thumbDaPagina(FunnelPage $pagina): ?string
    {
        foreach ($pagina->blocks as $bloco) {
            if (filled($bloco->imagem)) {
                return $bloco->imagemUrl();
            }
        }

        return null;
    }
}
