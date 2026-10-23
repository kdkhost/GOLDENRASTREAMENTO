<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Todo nome de rota escrito entre aspas no código e nas views precisa existir no roteador.
 * Um route() com nome errado só explode quando alguém clica (redirecionamento para 404),
 * que é exatamente como o cadastro de produto ficou quebrado por várias versões.
 */
class NomesDeRotaTest extends TestCase
{
    public function test_todos_os_nomes_de_rota_referenciados_estao_registrados(): void
    {
        $registradas = array_keys(Route::getRoutes()->getRoutesByName());
        $desconhecidas = [];

        foreach ($this->arquivos() as $caminho) {
            $conteudo = file_get_contents($caminho);

            // Só o helper route('nome'); request()->route('param') é o objeto da rota atual.
            if (! preg_match_all("/(?<!->)\broute\(\s*'([A-Za-z0-9._-]+)'/", $conteudo, $achados)) {
                continue;
            }

            foreach (array_unique($achados[1]) as $nome) {
                if (! in_array($nome, $registradas, true)) {
                    $desconhecidas[] = basename($caminho).' → '.$nome;
                }
            }
        }

        $this->assertSame([], $desconhecidas, 'Nomes de rota citados no código que não existem no roteador.');
    }

    /** @return array<int, string> */
    private function arquivos(): array
    {
        $raizes = [app_path(), resource_path('views'), base_path('routes'), config_path()];
        $arquivos = [];

        foreach ($raizes as $raiz) {
            $itens = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));

            foreach ($itens as $item) {
                if ($item->isFile() && in_array($item->getExtension(), ['php', 'blade.php'], true)) {
                    $arquivos[] = $item->getPathname();
                }
            }
        }

        return $arquivos;
    }
}
