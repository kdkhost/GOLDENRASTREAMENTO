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

    /** Os tipos que deslizam na tela: é por eles que a página carrega o Swiper. */
    public const CARROSSEIS = ['beneficios', 'prova'];

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

    /**
     * A prova social da faixa. Vêm primeiro os depoimentos aprovados da plataforma — eles
     * trazem a foto, a nota e o selo do Google — e depois os digitados aqui que ainda não
     * estão na lista. O nome decide a duplicidade, para a mesma pessoa não aparecer duas
     * vezes na mesma campanha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function depoimentos(int $limite = 12): array
    {
        $saindo = [];
        $nomes = [];

        foreach (Testimonial::aprovadosParaSite($limite) as $d) {
            $nome = trim((string) $d['nome']);
            $chave = self::nomeChave($nome);

            // A mesma pessoa pode estar aprovada duas vezes na plataforma (pelo painel e
            // pelo portal dela); na faixa ela aparece uma vez só, a que veio primeiro.
            if ($nome === '' || trim((string) $d['texto']) === '' || in_array($chave, $nomes, true)) {
                continue;
            }

            $nomes[] = $chave;
            $saindo[] = [
                'nome' => $nome,
                'papel' => $d['papel'] ?: $d['cidade'],
                'texto' => $d['texto'],
                'nota' => $d['nota'],
                'avatar' => $d['avatar'],
                'iniciais' => $d['iniciais'],
                'google' => ($d['fonte'] ?? 'site') === 'google',
            ];

            if (count($saindo) >= $limite) {
                return $saindo;
            }
        }

        foreach ($this->itens() as $item) {
            $nome = trim((string) ($item['nome'] ?? ''));
            $chave = self::nomeChave($nome);

            if ($nome === '' || in_array($chave, $nomes, true)) {
                continue;
            }

            $nomes[] = $chave;
            $saindo[] = [
                'nome' => $nome,
                'papel' => $item['papel'] ?? null,
                'texto' => (string) ($item['texto'] ?? ''),
                'nota' => $item['nota'] ?? null,
                'avatar' => self::fotoDigitada($item['avatar'] ?? null),
                'iniciais' => self::iniciais($nome),
                'google' => false,
            ];

            if (count($saindo) >= $limite) {
                break;
            }
        }

        return $saindo;
    }

    /** O nome sem espaço, acento nem pontuação: serve para comparar quem já foi citado. */
    private static function nomeChave(string $nome): string
    {
        return (string) preg_replace('/\P{L}/u', '', mb_strtolower(trim($nome)));
    }

    private static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];

        return mb_strtoupper(mb_substr($partes[0] ?? 'G', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    /** O que foi digitado no campo pode ser um endereço completo ou um caminho do painel. */
    private static function fotoDigitada(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return preg_match('#^https?://#i', $valor) === 1
            ? $valor
            : ConteudoSite::urlCaminho($valor);
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
