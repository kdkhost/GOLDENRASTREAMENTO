<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Uma diretiva Blade colada no texto anterior (`Nota@else`, `@csrf@method('DELETE')`) não é
 * compilada: ela sai na tela como texto puro e o formulário deixa de funcionar. O bug ficou
 * escondido em nove listagens do painel, onde todo botão de excluir virou POST.
 */
class DiretivasBladeTest extends TestCase
{
    private const DIRETIVAS = [
        'auth', 'bail', 'can', 'cannot', 'checked', 'class', 'component', 'directive', 'each',
        'else', 'elseif', 'empty', 'enderror', 'endauth', 'endcan', 'endcomponent', 'endempty',
        'endenumerate', 'endif', 'endisset', 'endonce', 'endpush', 'endsection', 'endslot',
        'endfor', 'endforeach', 'endwhile', 'enumerate', 'error', 'extends', 'for', 'foreach',
        'guest', 'if', 'include', 'includes', 'isset', 'js', 'lang', 'method', 'once', 'php',
        'prepend', 'push', 'readonly', 'required', 'section', 'selected', 'slot', 'style',
        'switch', 'case', 'break', 'default', 'endswitch', 'trans', 'unless', 'use', 'vite',
        'while', 'yield', 'dd', 'dump', 'props', 'class', 'disabled',
    ];

    public static function arquivos(): array
    {
        $lista = [];
        $pasta = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($pasta as $arquivo) {
            if ($arquivo->isFile() && $arquivo->getExtension() === 'php' && str_ends_with($arquivo->getFilename(), '.blade.php')) {
                $lista[] = [$arquivo->getPathname()];
            }
        }

        if (count($lista) < 30) {
            throw new \RuntimeException('A varredura encontrou poucas telas ('.count($lista).'): o caminho de resources/views mudou?');
        }

        return $lista;
    }

    #[DataProvider('arquivos')]
    public function test_diretivas_nao_ficam_coladas_no_texto_anterior(string $caminho): void
    {
        $conteudo = file_get_contents($caminho);
        $erradas = [];

        foreach (self::DIRETIVAS as $diretiva) {
            if (preg_match_all('/\w@'.$diretiva.'\b/', $conteudo, $achados)) {
                $erradas[] = implode(', ', array_unique($achados[0]));
            }
        }

        $this->assertSame(
            [],
            $erradas,
            basename($caminho).': diretiva colada na palavra anterior não compila ('.implode(' | ', $erradas).').'
        );
    }
}
