<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * O retorno do sistema é sempre um toastr e a confirmação é sempre SweetAlert2. Enquanto
 * cada tela pintou o próprio aviso (caixa no topo do painel, span no site, div na entrada
 * do cliente), a mesma falha aparecia de quatro jeitos diferentes — e sobrava tela que
 * chamava confirm() do navegador, que o tema escuro não alcança.
 */
class RetornoEConfirmacaoTest extends TestCase
{
    /** Toda janela que o usuário abre: ou carrega a folha de avisos, ou ninguém responde. */
    private const DOCUMENTOS = [
        'resources/views/layouts/site.blade.php',
        'resources/views/admin/layouts/app.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/portal/layout.blade.php',
        'resources/views/portal/layout-auth.blade.php',
    ];

    private const AVISOS = 'resources/views/partials/avisos.blade.php';

    private const SCRIPTS_DO_PAINEL = 'resources/views/admin/partials/scripts.blade.php';

    public function test_todo_documento_carrega_a_folha_de_avisos(): void
    {
        $scripts = $this->ler(self::SCRIPTS_DO_PAINEL);
        $this->assertStringContainsString("@include('partials.avisos')", $scripts,
            'O painel responde pela folha compartilhada; sem ela, o include abaixo não vale.');

        foreach (self::DOCUMENTOS as $arquivo) {
            $conteudo = $this->ler($arquivo);

            $this->assertTrue(
                str_contains($conteudo, "@include('partials.avisos'") || str_contains($conteudo, "@include('admin.partials.scripts')"),
                "{$arquivo} não carrega partials.avisos — quem usa esta tela fica sem retorno."
            );
        }
    }

    public function test_o_aviso_de_sessao_sai_como_toastr_e_nunca_como_caixa_na_pagina(): void
    {
        foreach ($this->views() as $arquivo) {
            if ($arquivo === self::AVISOS) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\{\{\s*(?:session\(|\$errors->)|role="alert"/',
                $this->ler($arquivo),
                "{$arquivo} imprime retorno no próprio HTML; o retorno vive em partials.avisos."
            );
        }
    }

    public function test_a_folha_de_avisos_e_a_unica_que_carrega_toastr_e_sweetalert(): void
    {
        foreach ($this->views() as $arquivo) {
            if ($arquivo === self::AVISOS) {
                continue;
            }

            $conteudo = $this->ler($arquivo);

            $this->assertStringNotContainsString('toastr', $conteudo, "{$arquivo} carrega toastr por conta própria.");
            $this->assertStringNotContainsString('sweetalert', strtolower($conteudo), "{$arquivo} carrega SweetAlert2 por conta própria.");
        }

        $avisos = $this->ler(self::AVISOS);
        $this->assertStringContainsString('toastr.min.js', $avisos);
        $this->assertStringContainsString('sweetalert2@11', $avisos);
        $this->assertStringContainsString('golden-avisos.css', $avisos);
    }

    public function test_o_sprite_de_icone_do_toastr_nunca_volta(): void
    {
        $avisos = $this->ler(self::AVISOS);
        $link = strpos($avisos, 'toastr.min.css');
        $trava = strpos($avisos, '#toast-container>.toast{background-image:none!important}');

        $this->assertGreaterThan(0, (int) $link);
        $this->assertGreaterThan((int) $link, (int) $trava,
            'A trava do sprite precisa vir depois da folha do toastr: com a mesma especificidade e !important, só ganha quem chega por último no documento.');

        $folha = $this->ler('public/css/golden-avisos.css');
        $this->assertStringNotContainsString('background-image', $folha,
            'O sprite se desliga num lugar só, na folha partial/avisos — duplicar na stylesheet deixa uma das duas para trás.');
    }

    public function test_nenhum_script_chama_alert_ou_confirm_do_navegador(): void
    {
        $arquivos = array_merge($this->views(), $this->javascripts());

        foreach ($arquivos as $arquivo) {
            $this->assertDoesNotMatchRegularExpression('/\balert\s*\(/', $this->ler($arquivo),
                "{$arquivo} usa alert() do navegador; o retorno é toastr.");
            $this->assertDoesNotMatchRegularExpression('/(?<!\w)confirm\s*\(/', $this->ler($arquivo),
                "{$arquivo} usa confirm() do navegador; a confirmação é SweetAlert2.");
        }
    }

    public function test_excluir_do_painel_ped_confirmacao_sem_sair_do_tema(): void
    {
        $componente = $this->ler('resources/views/components/admin/delete.blade.php');

        $this->assertStringContainsString('data-confirmar="{{ $confirm }}"', $componente,
            'A exclusão precisa sair pelo interceptor de confirmação de partials.avisos.');
        $this->assertStringNotContainsString('onsubmit', $componente,
            'onsubmit com confirm() do navegador ignora o tema e não tem botões próprios.');

        $avisos = $this->ler(self::AVISOS);
        $this->assertStringContainsString("hasAttribute('data-confirmar')", $avisos);
        $this->assertStringContainsString('GoldenAvisos.confirmar', $avisos);
    }

    public function test_o_desligamento_das_confirmacoes_so_vale_para_quem_nao_pede_nada(): void
    {
        $site = $this->ler('resources/views/layouts/site.blade.php');
        $this->assertStringContainsString("@include('partials.avisos', ['confirmacoes' => false])", $site,
            'O site público não confirma nada: levar SweetAlert2 em toda página seria peso morto.');

        foreach (['resources/views/site', 'resources/views/portal'] as $pasta) {
            foreach ($this->viewsDe($pasta) as $arquivo) {
                $this->assertStringNotContainsString('data-confirmar', $this->ler($arquivo),
                    "{$arquivo} pede confirmação numa tela que não carrega SweetAlert2.");
            }
        }
    }

    public function test_a_folha_de_avisos_so_usa_tokens_e_o_escuro_redefine_cada_um(): void
    {
        $avisos = $this->ler('public/css/golden-avisos.css');
        $this->assertMatchesRegularExpression('/#toast-container/', $avisos);
        $this->assertMatchesRegularExpression('/\.swal2-popup/', $avisos);

        preg_match_all('/var\(--([a-z0-9-]+)[),]/', $avisos, $grupos);
        $tokens = array_unique($grupos[1]);
        $this->assertNotEmpty($tokens);

        $tema = $this->ler('public/css/golden-theme.css');
        $inicio = (int) strpos($tema, '[data-bs-theme="dark"]');
        $claro = substr($tema, 0, $inicio);
        $escuro = substr($tema, $inicio);

        foreach ($tokens as $token) {
            $this->assertStringContainsString("--{$token}", $claro, "O aviso usa --{$token}, que não está na paleta clara.");

            // só os tokens de superfície e de sombra precisam do par no escuro; tom e forma já vêm prontos
            if (preg_match('/^(?:aviso|login)-[a-z]+$/', $token)) {
                $this->assertStringContainsString("--{$token}:", $escuro, "--{$token} não foi ajustado para o tema escuro.");
            }
        }

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $avisos,
            'golden-avisos.css escreve cor própria; a paleta vive em golden-theme.css.');
    }

    public function test_o_site_publico_le_a_mesma_paleta_do_painel(): void
    {
        $site = $this->ler('resources/views/layouts/site.blade.php');
        $tema = strpos($site, 'golden-theme.css');
        $folha = strpos($site, 'golden-site.css');

        $this->assertGreaterThan(0, (int) $tema, 'O site não carrega golden-theme.css: o aviso sairia sem cor.');
        $this->assertLessThan((int) $folha, (int) $tema,
            'golden-theme.css precisa vir antes de golden-site.css, para o site continuar dono da própria paleta.');
    }

    /** @return list<string> */
    private function views(): array
    {
        return $this->viewsDe('resources/views');
    }

    /** @return list<string> */
    private function viewsDe(string $pasta): array
    {
        $itens = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($pasta), FilesystemIterator::SKIP_DOTS));
        $arquivos = [];
        foreach ($itens as $item) {
            if ($item->isFile() && str_ends_with($item->getFilename(), '.blade.php')) {
                $arquivos[] = $pasta.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen(base_path($pasta).DIRECTORY_SEPARATOR)));
            }
        }
        sort($arquivos);

        return $arquivos;
    }

    /** @return list<string> */
    private function javascripts(): array
    {
        $arquivos = [];
        foreach (scandir(public_path('js')) as $arquivo) {
            if (str_ends_with((string) $arquivo, '.js')) {
                $arquivos[] = 'public/js/'.$arquivo;
            }
        }

        return $arquivos;
    }

    private function ler(string $arquivo): string
    {
        $caminho = base_path($arquivo);
        $this->assertFileExists($caminho);

        return (string) file_get_contents($caminho);
    }
}
