<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O canto direito do pé da página: os botões flutuantes e a assinatura de quem fez o
 * sistema. Os dois convivem no mesmo espaço, então dividem a mesma régua de posição e a
 * mesma fonte de dados.
 */
class CantoDoRodapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * O selo do reCAPTCHA é fixo, gruda no pé da tela e recebe um z-index que nenhum botão
     * nosso alcança. A casa não briga pelo canto nem esconde o selo (as regras do serviço
     * exigem que ele apareça): quando ele está na página, o stack inteiro sobe pela mesma
     * variável — e só lá, para não deixar órfão o canto de quem não usa captcha.
     */
    public function test_os_flutuantes_sobem_quando_o_selo_do_recaptcha_esta_na_pagina(): void
    {
        $topo = file_get_contents(public_path('css/golden-to-top.css'));

        $this->assertMatchesRegularExpression('/:root\{[^}]*--flutuante-bottom:var\(--flutuante-gap\)/s', $topo,
            'A régua do canto saiu do :root: cada flutuante voltou a ter a sua própria medida.');
        $this->assertMatchesRegularExpression(
            '/body:has\(\.grecaptcha-badge\)\{--flutuante-bottom:calc\([^}]*--selo-recaptcha[^}]*\)\}/s', $topo,
            'Com o selo do reCAPTCHA na página, os flutuantes deixaram de subir acima dele.');

        $site = file_get_contents(public_path('css/golden-site.css'));
        $this->assertMatchesRegularExpression('/\.wa-float\{[^}]*bottom:var\(--flutuante-bottom\)/s', $site,
            'O botão do WhatsApp não obedece mais à régua do canto.');
        $this->assertMatchesRegularExpression('/\.wa-popup\{[^}]*bottom:calc\(var\(--flutuante-bottom\)/s', $site,
            'A caixa multicanal parou de abrir em cima do próprio botão.');

        // Nenhum dos dois pode voltar a fixar número próprio — nem no celular, onde a régua
        // encolhe por token: é assim que o stack continua subindo inteiro.
        foreach (['golden-to-top.css' => '\.to-top', 'golden-site.css' => '\.wa-float'] as $arquivo => $seletor) {
            $css = file_get_contents(public_path('css/' . $arquivo));
            preg_match_all('/' . $seletor . '\s*\{[^}]*\}/s', $css, $blocos);
            $this->assertNotEmpty($blocos[0], "Nenhum bloco {$seletor} sobrou em {$arquivo}.");

            foreach ($blocos[0] as $bloco) {
                $this->assertDoesNotMatchRegularExpression('/bottom:\s*\d+px/s', $bloco,
                    "{$seletor} em {$arquivo} voltou a cravar a própria distância do pé da tela.");
            }
        }
    }

    public function test_o_rodape_assina_o_desenvolvedor_e_a_agencia(): void
    {
        $credito = config('golden.credito');

        foreach (['/', '/c/frota-j16'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Desenvolvido por', $html, "A assinatura sumiu de {$url}.");
            $this->assertStringContainsString($credito['desenvolvedor'], $html, "O nome do desenvolvedor sumiu de {$url}.");
            $this->assertStringContainsString($credito['agencia'], $html, "A agência sumiu de {$url}.");

            foreach (['site', 'agencia_site'] as $chave) {
                $this->assertStringContainsString('href="' . $credito[$chave] . '"', $html,
                    "O link {$chave} da assinatura não está em {$url}.");
            }
        }

        // Uma só assinatura para os dois rodapés, e ela é a última coisa da barra: o
        // auto-margin é o que a encosta no canto direito.
        $css = file_get_contents(public_path('css/golden-site.css'));
        $this->assertMatchesRegularExpression('/\.foot-dev\{[^}]*margin-left:auto/s', $css,
            'A assinatura voltou para o meio da barra em vez do canto direito.');
    }

    /**
     * O mesmo crédito sai do pacote (composer.json) e da tela: se um dos dois for editado e
     * o outro não, o site passa a desmentir o próprio repositório.
     */
    public function test_a_assinatura_do_site_e_a_mesma_do_pacote(): void
    {
        $pacote = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $autor = collect($pacote['authors'])->firstWhere('role', 'Desenvolvedor do sistema');
        $credito = config('golden.credito');

        $this->assertNotEmpty($autor, 'O composer.json deixou de creditar o desenvolvedor do sistema.');
        $this->assertSame($credito['agencia_site'], $autor['homepage'],
            'Site e pacote apontam para agências diferentes.');
        $this->assertSame($credito['email'], $autor['email'],
            'Site e pacote dão contatos diferentes para quem fez o sistema.');
    }
}
