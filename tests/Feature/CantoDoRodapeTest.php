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
        $this->assertMatchesRegularExpression('/:root\{[^}]*--flutuante-largura:\d+px/s', $topo,
            'A calha dos flutuantes saiu do :root e cada rodapé voltaria a adivinhar a medida.');

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
        // auto-margin é o que a encosta no canto direito. A calha à direita é a largura dos
        // flutuantes, que pairam sobre o fim da página — sem ela o nome fica atrás deles.
        $css = file_get_contents(public_path('css/golden-site.css'));
        $this->assertMatchesRegularExpression('/\.foot-dev\{[^}]*margin-left:auto/s', $css,
            'A assinatura voltou para o meio da barra em vez do canto direito.');
        $this->assertMatchesRegularExpression('/\.foot-dev\{[^}]*padding-inline-end:var\(--flutuante-largura\)/s', $css,
            'A assinatura deixou de desviar da calha dos botões flutuantes.');
    }

    /**
     * O desenvolvedor creditado na tela é o mesmo creditado no pacote: se um dos dois for
     * editado e o outro não, o site passa a desmentir o próprio composer.json. A agência é
     * a cliente dona do produto e não tem nada a ver com o autor do código.
     */
    public function test_a_assinatura_do_site_e_a_mesma_do_pacote(): void
    {
        $pacote = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $autor = collect($pacote['authors'])->firstWhere('role', 'Desenvolvedor do sistema');
        $credito = config('golden.credito');

        $this->assertNotEmpty($autor, 'O composer.json deixou de creditar o desenvolvedor do sistema.');
        $this->assertStringContainsString($credito['desenvolvedor'], $autor['name'],
            'Site e pacote assinam o desenvolvimento com nomes diferentes.');
        $this->assertSame($credito['site'], $autor['homepage'],
            'Site e pacote apontam para endereços diferentes para quem fez o sistema.');
        $this->assertSame($credito['email'], $autor['email'],
            'Site e pacote dão contatos diferentes para quem fez o sistema.');
    }

    /**
     * Nada na página pública pode ser mais largo que a tela. Quando é, o navegador entende
     * que existe um lado direito para mostrar e habilita a rolagem horizontal: no celular,
     * segurar e arrastar faz o site inteiro tremer. Três coisas garantem que isso não volta,
     * medidas em todas as larguras de 320px a 1440px com zero de transbordo:
     * a assinatura cabe quebrando linha, nenhuma coluna de grade cresce por causa do próprio
     * conteúdo, e uma palavra maior que a coluna quebra em vez de vazar tinta para fora.
     */
    public function test_nada_na_pagina_publica_e_mais_largo_que_a_tela(): void
    {
        $site = file_get_contents(public_path('css/golden-site.css'));

        $this->assertMatchesRegularExpression('/\.foot-dev\{[^}]*flex-wrap:wrap/s', $site,
            'A assinatura do rodapé voltou a ser uma linha só: com a calha dos flutuantes ela não cabe no celular.');
        preg_match('/\.foot-dev\{[^}]*\}/s', $site, $assinatura);
        $this->assertNotEmpty($assinatura, 'O bloco .foot-dev saiu de cena.');
        $this->assertDoesNotMatchRegularExpression('/white-space:nowrap/s', $assinatura[0],
            'A assinatura cravou o texto em uma linha de novo e empurra a página para o lado.');

        // Uma coluna declarada como "1fr" tem piso no conteúdo mínimo: uma palavra longa
        // alarga a coluna, e a grade inteira passa da tela. O teto é o que quebra o ciclo.
        preg_match_all('/grid-template-columns:([^;}]+)/s', $site, $colunas);
        $this->assertNotEmpty($colunas[1], 'Nenhuma grade sobrou no CSS do site.');
        foreach ($colunas[1] as $declaracao) {
            foreach (preg_split('/\s+/', trim($declaracao), -1, PREG_SPLIT_NO_EMPTY) as $coluna) {
                $this->assertDoesNotMatchRegularExpression('/^[0-9.]+fr$/', $coluna,
                    "A coluna '{$coluna}' voltou a crescer com o próprio conteúdo e faz a página ter rolagem horizontal.");
            }
        }

        $this->assertMatchesRegularExpression('/body\{[^}]*overflow-wrap:break-word/s', $site,
            'Uma palavra maior que a coluna voltou a vazar tinta para fora da tela.');

        // A calha reservada aos flutuantes é espaço que tira do texto: se crescer demais, é
        // ela mesma que não cabe numa tela de celular.
        $topo = file_get_contents(public_path('css/golden-to-top.css'));
        preg_match_all('/--flutuante-largura:(\d+)px/s', $topo, $calhas);
        $this->assertNotEmpty($calhas[1], 'A calha dos flutuantes deixou de ter medida no :root.');
        foreach ($calhas[1] as $largura) {
            $this->assertLessThanOrEqual(150, (int) $largura,
                "A calha de {$largura}px come a tela do celular e a assinatura não tem onde caber.");
        }

        // Os dois botões do cartão de produto também não cabem lado a lado no celular.
        $loja = file_get_contents(resource_path('views/site/store/index.blade.php'));
        $this->assertMatchesRegularExpression('/\.prod-actions\{[^}]*flex-wrap:wrap/s', $loja,
            'A linha de ações do cartão de produto voltou a ser uma linha só e alarga a loja.');
    }
}
