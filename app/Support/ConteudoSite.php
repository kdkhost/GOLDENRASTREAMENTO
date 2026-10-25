<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Imagens e textos que o site mostra, todos administráveis pelo painel.
 *
 * Duas fontes, uma só regra: o registro de slots de config('golden.imagens')
 * (o arquivo empacotado, o alt, a dimensão recomendada) e o que o operador
 * enviou em Admin → Conteúdo → Imagens e textos, guardado em `settings` como
 * `imagem.<slot>` / `imagem_alt.<slot>`. O que o painel define vence; o pacote
 * é apenas o fallback — por isso apagar o envio do painel faz a foto original
 * voltar sem precisar republicar nada.
 *
 * Os textos de abertura das páginas fixas seguem o mesmo desenho:
 * config('golden.paginas_site') dá o padrão e `paginas_site.<chave>.<campo>`
 * guarda a edição do painel.
 */
class ConteudoSite
{
    /** Prefixo da pasta de envio no disco público (config/envios.php: site_imagem). */
    public const PASTA = 'site';

    /** @return array<string, array<string, mixed>> */
    public static function slots(): array
    {
        return config('golden.imagens', []);
    }

    /** @return array<string, mixed> */
    public static function slot(string $slot): array
    {
        $registro = self::slots()[$slot] ?? null;

        if (! is_array($registro)) {
            throw new RuntimeException("Slot de imagem desconhecido: {$slot}. Cadastre em config('golden.imagens').");
        }

        return $registro;
    }

    /** Slots na ordem do registro, agrupados pela página onde aparecem. */
    public static function porPagina(): array
    {
        $grupos = [];

        foreach (self::slots() as $slot => $registro) {
            $grupos[$registro['pagina']][$slot] = $registro;
        }

        return $grupos;
    }

    /** O caminho enviado pelo painel para este slot, se houver. */
    public static function enviada(string $slot): ?string
    {
        return Setting::get('imagem.'.$slot) ?: null;
    }

    /** O caminho que vale agora: o do painel quando existe, senão o do pacote. */
    public static function caminho(string $slot): ?string
    {
        return self::enviada($slot) ?: (self::slot($slot)['padrao'] ?? null);
    }

    /** URL pública da imagem, ou null quando a página ainda não tem nenhuma. */
    public static function url(?string $slot): ?string
    {
        return $slot === null ? null : self::urlCaminho(self::caminho($slot));
    }

    /**
     * URL de um caminho de arquivo do site que não veio de um slot — a foto que o
     * bloco de funil recebeu no editor. O que está em storage/ é envio do painel,
     * o resto é arquivo empacotado; a régua é a mesma do slot.
     */
    public static function urlCaminho(?string $caminho): ?string
    {
        if ($caminho === null || trim($caminho) === '') {
            return null;
        }

        return str_starts_with($caminho, self::PASTA.'/')
            ? asset('storage/'.$caminho)
            : asset($caminho);
    }

    /** Descrição alternativa do <img>: a edição do painel, ou a do registro. */
    public static function alt(string $slot): string
    {
        return (string) Setting::get('imagem_alt.'.$slot, (string) (self::slot($slot)['alt'] ?? ''));
    }

    /** O arquivo do pacote realmente existe? A tela do painel avisa quando não. */
    public static function padraoExiste(string $slot): bool
    {
        $padrao = self::slot($slot)['padrao'] ?? null;

        return $padrao === null || File::exists(public_path($padrao));
    }

    /**
     * Texto de abertura de uma página fixa (rotulo, eyebrow, titulo, subtitulo),
     * com o que foi editado no painel por cima do padrão do pacote.
     */
    public static function pagina(string $chave, string $campo): ?string
    {
        $padrao = config("golden.paginas_site.{$chave}.{$campo}");

        if (! is_string($padrao) && $padrao !== null) {
            throw new RuntimeException("Texto de página inexistente: paginas_site.{$chave}.{$campo}.");
        }

        $editado = Setting::get("paginas_site.{$chave}.{$campo}");

        if ($editado === null) {
            return $padrao;
        }

        return trim((string) $editado) === '' ? $padrao : (string) $editado;
    }

    /** Slot do cartaz de uma página fixa, quando a página tem um. */
    public static function slotDoCartaz(string $chave): ?string
    {
        $slot = 'hero_'.str_replace('-', '_', $chave);

        return isset(self::slots()[$slot]) ? $slot : null;
    }
}
