<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\ConteudoSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nenhuma fotografia do site nasce dentro de uma view: toda imagem é um slot de
 * config('golden.imagens'), e o que o painel envia (Admin → Conteúdo → Imagens
 * do site) entra por cima do arquivo do pacote. Este teste trava as três
 * promessas do desenho — a view não cita arquivo, o envio vence, e apagar o
 * envio faz o original voltar.
 */
class ImagensDoSitePeloCmsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_nenhuma_view_cita_arquivo_de_imagem_do_pacote(): void
    {
        $views = glob('resources/views/{layouts,site,site/partials,site/store,admin,admin/layouts,auth,partials,components,components/site}/*.blade.php', GLOB_BRACE);

        foreach ($views as $view) {
            $conteudo = (string) file_get_contents($view);
            $this->assertStringNotContainsString(
                "asset('img/", $conteudo,
                basename($view).' ainda aponta para um arquivo de imagem dentro da view.'
            );
        }
    }

    public function test_cada_slot_tem_o_que_a_tela_do_painel_mostra(): void
    {
        $slots = ConteudoSite::slots();

        foreach ($slots as $slot => $registro) {
            foreach (['pagina', 'titulo', 'alt', 'largura', 'altura'] as $campo) {
                $this->assertArrayHasKey($campo, $registro, "O slot {$slot} não tem '{$campo}'.");
            }

            if (($registro['padrao'] ?? null) !== null) {
                $this->assertFileExists(public_path($registro['padrao']), "O padrão do slot {$slot} não está no projeto.");
            }
        }

        // Toda página fixa pode receber cartaz, e o nome do grupo de cada slot
        // precisa bater com uma página registrada — é assim que a tela agrupa.
        $nomes = collect(config('golden.paginas_site'))->pluck('nome')->all();
        $nomes[] = 'Página inicial';
        $nomes[] = 'Todo o site';

        foreach ($slots as $registro) {
            $this->assertContains(
                \Illuminate\Support\Str::before($registro['pagina'], ' ·'),
                $nomes,
                "O grupo '{$registro['pagina']}' não corresponde a nenhuma página de config('golden.paginas_site')."
            );
        }

        foreach (array_keys(config('golden.paginas_site')) as $chave) {
            $this->assertArrayHasKey(
                ConteudoSite::slotDoCartaz($chave),
                $slots,
                "A página '{$chave}' não tem slot de cartaz."
            );
        }
    }

    public function test_a_foto_enviada_pelo_painel_vence_a_foto_do_pacote(): void
    {
        $pacote = ConteudoSite::slots()['home_hero']['padrao'];
        $this->assertStringContainsString($pacote, $this->get('/')->getContent());

        Setting::set('imagem.home_hero', 'site/heroi-novo.jpg');
        Setting::set('imagem_alt.home_hero', 'A central nova da Golden');

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('storage/site/heroi-novo.jpg', $html);
        $this->assertStringNotContainsString($pacote, $html);
        $this->assertStringContainsString('alt="A central nova da Golden"', $html);
    }

    public function test_o_cartaz_so_aparece_depois_do_envio(): void
    {
        $this->assertStringNotContainsString('com-foto', $this->get('/planos')->getContent());

        Setting::set('imagem.hero_planos', 'site/planos-capa.jpg');

        $html = $this->get('/planos')->getContent();
        $this->assertStringContainsString('page-hero com-foto', $html);
        $this->assertStringContainsString('storage/site/planos-capa.jpg', $html);

        // O cartaz é filho direto da <section>: a regra .page-hero.com-foto>.foto só vale
        // para filho direto, e dentro de .wrap a foto sairia no fluxo, empurrando a página.
        $this->assertMatchesRegularExpression(
            '#<section class="page-hero com-foto[^"]*">\s*<img[^>]*class="foto"#',
            $html,
            'O cartaz não é filho direto da seção, então não fica atrás do título.'
        );
    }

    public function test_a_tela_de_imagens_mostra_cada_slot_do_registro(): void
    {
        $slots = ConteudoSite::slots();
        $html = $this->actingAs($this->admin)
            ->get(route('admin.conteudo.imagens.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            substr_count($html, 'data-campo="site_imagem"'),
            count($slots),
            'A tela não tem um campo de envio por slot registrado.'
        );

        foreach ($slots as $slot => $registro) {
            $this->assertStringContainsString($registro['titulo'], $html, "O card do slot {$slot} não aparece na tela.");
            $this->assertStringContainsString('data-nome="imagem['.$slot.']"', $html, "O slot {$slot} não tem campo de envio.");
            $this->assertStringContainsString('name="alt['.$slot.']"', $html);
        }
    }

    public function test_a_tela_divide_as_fotos_por_area_e_diz_onde_cada_uma_aparece(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.conteudo.imagens.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('#data-aba-painel="([a-z]+)"(.*?)(?=data-aba-painel=|<div class="abas-salvar|\z)#s', $html, $paneis);

        $porArea = array_combine($paneis[1], $paneis[2]);

        $this->assertSame(['identidade', 'home', 'cartazes'], array_keys($porArea));

        foreach (ConteudoSite::slots() as $slot => $registro) {
            $area = match (true) {
                $registro['pagina'] === 'Todo o site' => 'identidade',
                str_starts_with($registro['pagina'], 'Página inicial') => 'home',
                default => 'cartazes',
            };

            $this->assertStringContainsString(
                'data-nome="imagem['.$slot.']"',
                $porArea[$area],
                "O slot {$slot} ({$registro['pagina']}) caiu na aba errada."
            );

            // Os cartazes têm todos o mesmo título, então quem diz qual é qual é o nome da página.
            if ($area === 'cartazes') {
                $this->assertStringContainsString(
                    '<div class="slot-onde">'.$registro['pagina'].'</div>',
                    $porArea[$area],
                    "O card do cartaz {$slot} não diz em qual página a foto entra."
                );
            }
        }
    }

    public function test_salvar_uma_foto_apaga_a_anterior_do_disco(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/cta-antiga.jpg', 'antiga');
        Storage::disk('public')->put('site/cta-nova.jpg', 'nova');
        Setting::set('imagem.home_cta', 'site/cta-antiga.jpg');

        $this->actingAs($this->admin)->post(route('admin.conteudo.imagens.update'), [
            'imagem' => ['home_cta' => 'site/cta-nova.jpg'],
            'alt' => ['home_cta' => ConteudoSite::slots()['home_cta']['alt']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('site/cta-nova.jpg', Setting::get('imagem.home_cta'));
        Storage::disk('public')->assertMissing('site/cta-antiga.jpg');
    }

    public function test_voltar_ao_padrao_devolve_a_foto_e_o_texto_do_pacote(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/cta-enviada.jpg', 'enviada');
        Setting::set('imagem.home_cta', 'site/cta-enviada.jpg');
        Setting::set('imagem_alt.home_cta', 'Descrição escrita para a foto enviada');

        $this->actingAs($this->admin)->post(route('admin.conteudo.imagens.update'), [
            'voltar_ao_padrao' => ['home_cta' => '1'],
            'alt' => ['home_cta' => 'Descrição escrita para a foto enviada'],
        ])->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('imagem.home_cta'));
        Storage::disk('public')->assertMissing('site/cta-enviada.jpg');

        // O texto alternativo pertence à foto: sem envio, volta o alt do registro.
        $this->assertNull(Setting::get('imagem_alt.home_cta'));
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString(ConteudoSite::slots()['home_cta']['padrao'], $html);
        $this->assertStringNotContainsString('Descrição escrita para a foto enviada', $html);
    }

    public function test_um_caminho_que_nao_e_desse_campo_e_recusado(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('produtos/foto-de-outro-campo.jpg', 'intrusa');

        $this->actingAs($this->admin)->post(route('admin.conteudo.imagens.update'), [
            'imagem' => ['home_cta' => 'produtos/foto-de-outro-campo.jpg'],
        ])->assertSessionHasErrors('imagem.home_cta');

        $this->assertNull(Setting::get('imagem.home_cta'));
    }

    public function test_os_textos_de_abertura_das_paginas_sao_editaveis(): void
    {
        $pacote = config('golden.paginas_site.faq.titulo');
        $this->assertStringContainsString($pacote, $this->get('/faq')->getContent());

        $this->actingAs($this->admin)->post(route('admin.conteudo.textos.update'), [
            'paginas_site' => ['faq' => ['titulo' => 'O que todo mundo pergunta antes de instalar']],
        ])->assertSessionHasNoErrors();

        $this->assertStringContainsString(
            'O que todo mundo pergunta antes de instalar',
            $this->get('/faq')->getContent()
        );

        // Em branco = volta ao padrão, e a chave sai do banco.
        $this->actingAs($this->admin)->post(route('admin.conteudo.textos.update'), [
            'paginas_site' => ['faq' => ['titulo' => '']],
        ])->assertRedirect();

        $this->assertNull(Setting::get('paginas_site.faq.titulo'));
        $this->assertStringContainsString($pacote, $this->get('/faq')->getContent());
    }

    public function test_cada_pagina_do_cms_tem_a_propria_foto_de_capa(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/capa-pagina.jpg', 'capa');

        $pagina = Page::firstOrFail();
        $html = $this->get('/p/'.$pagina->slug)->getContent();
        $this->assertStringNotContainsString('com-foto', $html);

        $this->actingAs($this->admin)->post(route('admin.conteudo.paginas.update', $pagina), $this->dadosDaPagina($pagina, ['imagem' => 'site/capa-pagina.jpg', 'imagem_alt' => 'Fachada da Golden'])
        )->assertSessionHasNoErrors();

        $this->assertSame('site/capa-pagina.jpg', $pagina->fresh()->imagem);

        $html = $this->get('/p/'.$pagina->slug)->getContent();
        $this->assertStringContainsString('page-hero com-foto', $html);
        $this->assertStringContainsString('storage/site/capa-pagina.jpg', $html);
        $this->assertStringContainsString('alt="Fachada da Golden"', $html);
        $this->assertMatchesRegularExpression(
            '#<section class="page-hero com-foto[^"]*">\s*<img[^>]*class="foto"#',
            $html,
            'A capa não é filho direto da seção, então não fica atrás do título.'
        );
    }

    public function test_a_capa_da_pagina_rejeita_arquivo_de_outro_campo(): void
    {
        Storage::fake('public');
        $pagina = Page::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.conteudo.paginas.update', $pagina), $this->dadosDaPagina($pagina, ['imagem' => 'produtos/qualquer-coisa.jpg'])
        )->assertSessionHasErrors('imagem');

        $this->assertNull($pagina->fresh()->imagem);
    }

    /** O formulário da página inteiro, com os campos de capa por cima. */
    private function dadosDaPagina(Page $pagina, array $extras = []): array
    {
        return array_merge([
            'titulo' => $pagina->titulo,
            'slug' => $pagina->slug,
            'eyebrow' => $pagina->eyebrow,
            'intro' => $pagina->intro,
            'corpo' => $pagina->corpo,
            'publicada' => '1',
            'ordem_menu' => $pagina->ordem_menu,
        ], $extras);
    }
}
