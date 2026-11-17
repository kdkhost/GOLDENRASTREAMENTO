<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentosSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_todos_os_segmentos_configurados_possuem_pagina_dedicada_com_sucesso(): void
    {
        $segmentos = config('golden.segmentos');

        $this->assertNotEmpty($segmentos);
        $this->assertCount(5, $segmentos);

        foreach ($segmentos as $s) {
            $this->assertArrayHasKey('slug', $s);

            $response = $this->get(route('site.segmento', $s['slug']));

            $response->assertOk();
            $response->assertSee($s['titulo']);
            $response->assertSee($s['eyebrow']);
            $response->assertSee($s['subtitulo']);
            $response->assertSee(e($s['lead']));

            foreach ($s['diferenciais'] as $dif) {
                $response->assertSee($dif['titulo']);
            }
        }
    }

    public function test_segmento_inexistente_retorna_404(): void
    {
        $response = $this->get('/para-quem/segmento-que-nao-existe');

        $response->assertNotFound();
    }

    public function test_home_possui_links_para_cada_pagina_dedicada_de_segmento(): void
    {
        $response = $this->get(route('site.home'));

        $response->assertOk();

        $segmentos = config('golden.segmentos');
        foreach ($segmentos as $s) {
            $link = route('site.segmento', $s['slug']);
            $response->assertSee($link, false);
        }
    }

    public function test_pagina_geral_de_segmentos_possui_links_para_as_paginas_dedicadas(): void
    {
        $response = $this->get(route('site.segmentos'));

        $response->assertOk();

        $segmentos = config('golden.segmentos');
        foreach ($segmentos as $s) {
            $link = route('site.segmento', $s['slug']);
            $response->assertSee($link, false);
        }
    }
}
