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
        'resources/views/portal/layout-auth.blade.php',
    ];

    /** As quatro telas de acesso do cliente: entrada, cadastro e a dupla de recuperação. */
    private const TELAS_DE_ACESSO = [
        'resources/views/portal/entrar.blade.php',
        'resources/views/portal/cadastro.blade.php',
        'resources/views/portal/esquecer.blade.php',
        'resources/views/portal/redefinir.blade.php',
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

            // A cor é `#` seguido de hex e terminada ali. O `#` de uma âncora ou de um
            // `data-bs-target="#aba-contato"` não conta: vem grudado em letras e hífen.
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,8}(?![0-9A-Za-z_-])/',
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

    public function test_o_acesso_do_portal_entra_pela_carcaca_do_painel(): void
    {
        $carcaca = $this->ler('resources/views/portal/layout-auth.blade.php');

        $this->assertStringContainsString('golden-login.css', $carcaca,
            'As telas de acesso do cliente precisam da mesma folha da entrada do painel.');
        $this->assertStringContainsString("@include('partials.favicon')", $carcaca);
        $this->assertStringContainsString("@include('admin.partials.theme-init')", $carcaca,
            'O tema escolhido no painel tem que valer para a tela de entrada do cliente.');

        foreach (self::TELAS_DE_ACESSO as $arquivo) {
            $this->assertStringContainsString("@extends('portal.layout-auth')", $this->ler($arquivo),
                "{$arquivo} não entra pela carcaça compartilhada.");
        }
    }

    public function test_toda_classe_do_acesso_do_portal_existe_na_folha(): void
    {
        $folha = $this->ler('public/css/golden-login.css');

        foreach (self::TELAS_DE_ACESSO as $arquivo) {
            preg_match_all('/class="([^"]*)"/', $this->ler($arquivo), $grupos);

            foreach ($grupos[1] as $linha) {
                foreach (preg_split('/\s+/', trim($linha)) as $classe) {
                    if ($classe === '' || str_starts_with($classe, 'fa-')) {
                        continue;
                    }

                    $this->assertStringContainsString('.'.$classe, $folha,
                        "{$arquivo} usa .{$classe}, que não existe em golden-login.css — a tela sairia sem estilo.");
                }
            }
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

    /**
     * A lista suspensa do <select> é desenhada pelo sistema, não pela página: sem esquema de
     * cor declarado e sem fundo e texto na própria option, o Windows claro abre a lista com
     * fundo branco herdando o texto claro do painel escuro — a lista existe e ninguém lê.
     */
    public function test_a_lista_do_select_tem_fundo_e_texto_declarados_nos_dois_temas(): void
    {
        $tema = $this->ler('public/css/golden-theme.css');
        $inicio = (int) strpos($tema, '[data-bs-theme="dark"]');
        $claro = substr($tema, 0, $inicio);
        $escuro = substr($tema, $inicio);

        $this->assertMatchesRegularExpression('/(^|[\s;{])color-scheme:light;/', $claro,
            'O tema claro não declara o esquema nativo; o pop-up do select fica nas cores do sistema.');
        $this->assertMatchesRegularExpression('/(^|[\s;{])color-scheme:dark;/', $escuro,
            'O tema escuro não declara o esquema nativo.');

        foreach (['select-surface', 'select-text', 'select-marcado'] as $token) {
            $this->assertMatchesRegularExpression("/--{$token}:\s*#[0-9a-fA-F]{6};/", $claro,
                "O token --{$token} falta no tema claro, ou não é uma cor opaca.");
            $this->assertMatchesRegularExpression("/--{$token}:\s*#[0-9a-fA-F]{6};/", $escuro,
                "O token --{$token} não tem par opaco no tema escuro.");
        }

        $this->assertMatchesRegularExpression(
            '/option[^{]*\{[^}]*(?:background|background-color):var\(--select-surface\)[^}]*color:var\(--select-text\)/',
            $tema,
            'A option não herda o par declarado da paleta; a lista volta a abrir invisível.');
        $this->assertStringContainsString('optgroup{font-weight:600}', $tema,
            'O rótulo de grupo do select precisa das mesmas cores da lista.');
    }

    public function test_nenhuma_outra_folha_escreve_cor_de_option(): void
    {
        foreach (glob(base_path('public/css/*.css')) ?: [] as $caminho) {
            if (basename($caminho) === 'golden-theme.css') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/\boption\b[^{]*\{/', (string) file_get_contents($caminho),
                basename($caminho).' pinta a option por conta própria; a lista do select vive na paleta única.');
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
