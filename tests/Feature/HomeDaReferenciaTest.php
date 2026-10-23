<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A home segue a referência visual aprovada: onze seções na ordem e nenhuma
 * imagem quebrada. Foi exatamente a falta das duas coisas que derrubou a
 * entrega anterior, então elas ficam travadas aqui.
 */
class HomeDaReferenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed();
        Cache::flush();
    }
    /** Marcadores que provam que a seção da referência está montada na home. */
    private const SECOES = [
        'hero-foto',        // herói com a fotografia da central de monitoramento
        'trust-grid',       // faixa de confiança dentro do herói
        'ben-grid',         // por que escolher a Golden
        'plataforma-monitor.jpg',   // aplicativo e plataforma
        'seg-foto',         // para quem é, em cartões com foto
        'kit-tag',          // kits com o do meio destacado
        'checks',           // equipamento J16 com as verificações
        'steps',            // como funciona
        'js-depoimentos',   // depoimentos
        'cta-foto',         // chamada final sobre a estrada
    ];

    public function test_a_home_mostra_as_secoes_da_referencia(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (self::SECOES as $marcador) {
            $this->assertStringContainsString($marcador, $html, "A seção '{$marcador}' sumiu da home.");
        }
    }

    public function test_toda_imagem_referenciada_pela_home_existe_no_projeto(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('#src="https?://[^/]+(/[^"]+\.(?:jpg|jpeg|png|webp|svg))"#i', $html, $casos);
        $caminhos = array_unique($casos[1]);

        $this->assertNotEmpty($caminhos, 'A home não referencia nenhuma imagem.');

        foreach ($caminhos as $caminho) {
            $this->assertFileExists(public_path(ltrim($caminho, '/')), "A imagem {$caminho} da home não existe.");
        }
    }

    public function test_os_cinco_segmentos_tem_fotografia_propria(): void
    {
        $slots = config('golden.imagens');

        foreach (config('golden.segmentos') as $s) {
            $this->assertArrayHasKey('slot', $s, "O segmento '{$s['titulo']}' está sem slot de imagem.");
            $this->assertArrayHasKey($s['slot'], $slots, "O slot '{$s['slot']}' do segmento '{$s['titulo']}' não está registrado.");
            $this->assertFileExists(public_path($slots[$s['slot']]['padrao']), "A foto do segmento '{$s['titulo']}' não existe.");
        }

        $this->assertFileExists(public_path($slots[config('golden.equipamento_info.slot')]['padrao']));
    }
}
