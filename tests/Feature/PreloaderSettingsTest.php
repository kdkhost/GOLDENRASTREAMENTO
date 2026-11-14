<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreloaderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_salva_configuracoes_do_preloader_e_alterna_site_e_admin_individualmente(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                '_aba' => 'preloader',
                'preloader_site_ativo' => '1',
                'preloader_estilo' => 'orbita',
                'preloader_titulo' => 'Golden Teste',
                'preloader_subtitulo' => 'Carregando sistema...',
                'preloader_cor' => '#e6b800',
                // preloader_admin_ativo omitido = desativado no admin
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('1', Setting::get('preloader_site_ativo'));
        $this->assertSame('0', Setting::get('preloader_admin_ativo'));
        $this->assertSame('orbita', Setting::get('preloader_estilo'));
        $this->assertSame('Golden Teste', Setting::get('preloader_titulo'));
        $this->assertSame('#e6b800', Setting::get('preloader_cor'));

        // Site público exibe o preloader customizado
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('id="golden-preloader"', false)
            ->assertSee('golden-preloader--orbita', false)
            ->assertSee('Golden Teste')
            ->assertSee('golden-tooltips.js', false);

        // Admin não exibe o preloader pois foi desativado individualmente, mas carrega tooltips
        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('id="golden-preloader"', false)
            ->assertSee('golden-tooltips.js', false);
    }
}
