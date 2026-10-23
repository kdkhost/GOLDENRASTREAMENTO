<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Promessa de leveza do site: com o cache do servidor preenchido, as páginas públicas
 * não visitam o banco. O primeiro acesso de cada página (cache frio) é limitado a poucas
 * consultas — o suficiente para montar menu, configurações e conteúdo.
 */
class LevezaDasPaginasTest extends TestCase
{
    use RefreshDatabase;

    /** Consultas aceitas em uma requisição com cache frio. */
    private const LIMITE_CACHE_FRIO = 14;

    protected function setUp(): void
    {
        parent::setUp();

        // Memórias estáticas sobrevivem ao desmontar a aplicação entre testes.
        Setting::esquecerMemoria();
        Cache::flush();
        config(['cache.default' => 'file']);
        Cache::flush();

        $this->seed();
        Setting::esquecerMemoria();
        Cache::flush();
    }

    public static function pages(): array
    {
        return [
            '/',
            '/solucoes',
            '/para-quem',
            '/planos',
            '/como-funciona',
            '/faq',
            '/contato',
            '/privacidade',
            '/p/sobre',
        ];
    }

    public function test_cache_frio_carrega_cada_pagina_com_poucas_consultas(): void
    {
        foreach ($this->pages() as $url) {
            $consultas = $this->consultasDaRequisicao('GET', $url);
            $this->assertLessThanOrEqual(
                self::LIMITE_CACHE_FRIO,
                count($consultas),
                "{$url} fez ".count($consultas).' consultas com cache frio: '.implode(' | ', $consultas)
            );
        }
    }

    public function test_cache_quente_nao_consultam_o_banco(): void
    {
        foreach ($this->pages() as $url) {
            $this->get($url)->assertOk();

            $this->assertSame(
                [],
                $this->consultasDaRequisicao('GET', $url),
                "{$url} voltou ao banco mesmo com o cache já preenchido."
            );
        }
    }

    public function test_gravacao_no_painel_invalida_o_cache_do_site(): void
    {
        $this->get('/faq')->assertOk();

        Faq::create(['pergunta' => 'Pergunta nova?', 'resposta' => 'Resposta nova.', 'ordem' => 99]);

        $this->assertStringContainsString('Pergunta nova?', $this->get('/faq')->assertOk()->getContent());
    }

    public function test_pagina_inedita_do_cms_responde_404(): void
    {
        $this->get('/p/nao-existe')->assertNotFound();
    }

    /**
     * Roda a requisição e devolve as consultas feitas por ela, ignorando o que o próprio
     * teste executa fora do ciclo HTTP.
     */
    private function consultasDaRequisicao(string $metodo, string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->{$metodo === 'GET' ? 'get' : 'post'}($url);

        $consultas = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $consultas;
    }
}
