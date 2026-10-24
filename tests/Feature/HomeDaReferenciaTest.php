<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A home segue a referência visual aprovada: onze seções na ordem, nenhuma imagem
 * quebrada e as faixas escuras com a fotografia cobrindo a seção. Foi a falta
 * dessas coisas que derrubou as entregas anteriores, então ficam travadas aqui.
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
        'sec-foto-esq',     // aplicativo e plataforma: fotografia de fundo em largura total
        'sec-foto-dir',     // equipamento J16: mesmo tratamento, do outro lado
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

    /**
     * Aplicativo e plataforma, o Rastreador J16 e a chamada final nasceram como faixa escura
     * com a imagem encaixada ao lado do texto; a referência aprovada mostra as três com a
     * fotografia cobrindo a seção inteira. A imagem precisa ser a camada de fundo da própria
     * seção, então ela vem antes do conteúdo — não dentro dele.
     */
    public function test_as_tres_faixas_escuras_tem_a_fotografia_como_fundo_da_secao(): void
    {
        $css = file_get_contents(public_path('css/golden-site.css'));
        $this->assertMatchesRegularExpression('/\.sec-foto\s*>\s*\.foto\{[^}]*object-fit:\s*cover/s', $css,
            'A classe .sec-foto perdeu o recorte em largura total: a foto voltou a ser um quadro.');

        // A chamada final divide o tratamento com as outras duas; se ela recuperar o quadro
        // arredondado, vira cartão dentro do miolo de novo — foi o que a referência desmentiu.
        $this->assertMatchesRegularExpression('/\.cta-final\.cta-foto\{[^}]*border-radius:\s*0\s*[;}]/s', $css,
            'A chamada final voltou a ser quadro arredondado em vez de faixa de fundo total.');

        $html = $this->get('/')->getContent();

        foreach (['sec-foto-esq' => 'Aplicativo e plataforma', 'sec-foto-dir' => 'Rastreador J16', 'cta-foto' => 'chamada final'] as $classe => $rotulo) {
            $tem = preg_match('#<section class="([^"]*' . $classe . '[^"]*)">(.*?)<div class="wrap#s', $html, $faixa);
            $this->assertSame(1, $tem, "A faixa '{$rotulo}' não é mais uma .{$classe}.");
            $this->assertStringContainsString('sec-foto', $faixa[1], "A faixa '{$rotulo}' saiu do tratamento de fundo total.");
            $this->assertStringContainsString('class="foto"', $faixa[2], "A fotografia da faixa '{$rotulo}' saiu do fundo da seção.");
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
