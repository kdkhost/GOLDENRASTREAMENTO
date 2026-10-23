<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * O painel tem dois temas, então nenhuma cor pode nascer fora de golden-theme.css —
 * foi assim que claro e escuro começaram a divergir. A favicon também é uma só.
 */
class TemasEIconesDoPainelTest extends TestCase
{
    /** Documentos HTML que o navegador abre: todos precisam da mesma favicon. */
    private const DOCUMENTOS = [
        'resources/views/layouts/site.blade.php',
        'resources/views/admin/layouts/app.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/portal/layout.blade.php',
    ];

    public function test_todo_documento_abre_com_a_mesma_favicon(): void
    {
        foreach (self::DOCUMENTOS as $arquivo) {
            $conteudo = $this->ler($arquivo);

            $this->assertStringContainsString("@include('partials.favicon')", $conteudo, "{$arquivo} não inclui o partial da favicon.");
            $this->assertStringNotContainsString('rel="icon"', $conteudo, "{$arquivo} escreve uma tag de ícone própria em vez de usar o partial.");
        }
    }

    public function test_o_arquivo_da_favicon_existe(): void
    {
        foreach (['public/favicon.ico', 'public/img/favicon.png'] as $arquivo) {
            $this->assertFileExists($arquivo);
            $this->assertGreaterThan(0, filesize($arquivo), "{$arquivo} está vazio.");
        }
    }

    public function test_as_paginas_do_painel_nao_escrevem_cor_propria(): void
    {
        foreach ($this->viewsDoPainel() as $arquivo) {
            $conteudo = $this->ler($arquivo);

            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,6}\b/',
                $conteudo,
                "{$arquivo} traz cor em hexadecimal; use um token de golden-theme.css."
            );

            foreach (['bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-secondary', 'btn-outline-dark'] as $classe) {
                $this->assertStringNotContainsString($classe, $conteudo, "{$arquivo} usa {$classe}, que não acompanha o tema escuro.");
            }
        }
    }

    public function test_os_css_do_painel_so_usam_tokens(): void
    {
        foreach (['public/css/admin-golden.css', 'public/css/golden-login.css'] as $arquivo) {
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,6}\b/',
                $this->ler($arquivo),
                "{$arquivo} define cor própria; a paleta vive em golden-theme.css."
            );
        }
    }

    public function test_o_tema_escuro_redefine_cada_tom_do_tema_claro(): void
    {
        $tema = $this->ler('public/css/golden-theme.css');
        $inicio = (int) strpos($tema, '[data-bs-theme="dark"]');
        $this->assertGreaterThan(0, $inicio, 'O arquivo de tema não define o bloco escuro.');

        $tokens = fn (string $trecho) => array_values(array_unique($this->todos($trecho, '/(--t-[a-z]+-(?:bg|fg)):/')));
        $claro = $tokens(substr($tema, 0, $inicio));
        $escuro = $tokens(substr($tema, $inicio));

        $this->assertNotEmpty($claro);
        foreach ($claro as $token) {
            $this->assertContains($token, $escuro, "O tom {$token} não foi ajustado para o tema escuro.");
        }
    }

    /** @return list<string> */
    private function todos(string $texto, string $regex): array
    {
        preg_match_all($regex, $texto, $grupos);

        return $grupos[1];
    }

    private function ler(string $arquivo): string
    {
        $caminho = base_path($arquivo);
        $this->assertFileExists($caminho);

        return (string) file_get_contents($caminho);
    }

    /** @return list<string> */
    private function viewsDoPainel(): array
    {
        $itens = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/views/admin')));
        $arquivos = [];
        foreach ($itens as $item) {
            if ($item->isFile() && str_ends_with($item->getFilename(), '.blade.php')) {
                $arquivos[] = 'resources/views/admin/'.$this->relative($item->getPathname());
            }
        }
        sort($arquivos);
        $this->assertNotEmpty($arquivos);

        return $arquivos;
    }

    private function relative(string $caminho): string
    {
        return str_replace('\\', '/', substr($caminho, strlen(base_path('resources/views/admin').DIRECTORY_SEPARATOR)));
    }
}
