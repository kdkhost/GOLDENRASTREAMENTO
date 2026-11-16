<?php

namespace Tests\Feature;

use App\Models\Customer;
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
                'preloader_fundo' => 'oled_black',
                'preloader_titulo' => 'Golden Teste',
                'preloader_subtitulo' => 'Carregando sistema...',
                'preloader_cor' => '#e6b800',
                'preloader_cor_secundaria' => '#f59e0b',
                'preloader_icone_tipo' => 'icone',
                'preloader_icone' => 'fa-satellite',
                'preloader_badge_ativo' => '1',
                'preloader_badge_texto' => 'Sinal GPS Ativo',
                'preloader_barra_ativa' => '1',
                'preloader_barra_estilo' => 'laser',
                'preloader_velocidade' => 'rapida',
                'preloader_tempo_saida' => 'cinema',
                'preloader_timeout_max' => 3500,
                // preloader_admin_ativo e portal omitidos = 0
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('1', Setting::get('preloader_site_ativo'));
        $this->assertSame('0', Setting::get('preloader_admin_ativo'));
        $this->assertSame('0', Setting::get('preloader_portal_ativo'));
        $this->assertSame('orbita', Setting::get('preloader_estilo'));
        $this->assertSame('oled_black', Setting::get('preloader_fundo'));
        $this->assertSame('Golden Teste', Setting::get('preloader_titulo'));
        $this->assertSame('#e6b800', Setting::get('preloader_cor'));
        $this->assertSame('#f59e0b', Setting::get('preloader_cor_secundaria'));
        $this->assertSame('fa-satellite', Setting::get('preloader_icone'));
        $this->assertSame('1', Setting::get('preloader_badge_ativo'));
        $this->assertSame('Sinal GPS Ativo', Setting::get('preloader_badge_texto'));
        $this->assertSame('laser', Setting::get('preloader_barra_estilo'));
        $this->assertSame('rapida', Setting::get('preloader_velocidade'));
        $this->assertSame('cinema', Setting::get('preloader_tempo_saida'));
        $this->assertSame('3500', (string) Setting::get('preloader_timeout_max'));

        // Site público exibe o preloader customizado com tema e classes apuradas
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('id="golden-preloader"', false)
            ->assertSee('golden-preloader--orbita', false)
            ->assertSee('golden-preloader--fundo-oled_black', false)
            ->assertSee('golden-preloader--vel-rapida', false)
            ->assertSee('golden-preloader--saida-cinema', false)
            ->assertSee('golden-preloader__bar--laser', false)
            ->assertSee('Sinal GPS Ativo')
            ->assertSee('Golden Teste');

        // Admin não exibe o preloader pois foi desativado individualmente
        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('id="golden-preloader"', false);
    }

    public function test_site_renderiza_novos_estilos_como_telemetria_e_satelite(): void
    {
        $this->seed();
        Setting::set('preloader_site_ativo', '1');
        Setting::set('preloader_estilo', 'telemetria');
        Setting::set('preloader_fundo', 'glass_blur');
        Setting::set('preloader_cor', '#06b6d4');
        Setting::set('preloader_badge_ativo', '1');
        Setting::set('preloader_badge_texto', 'HUD Militar Conectado');

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('golden-preloader--telemetria', false)
            ->assertSee('golden-preloader--fundo-glass_blur', false)
            ->assertSee('gp-hud-bracket', false)
            ->assertSee('HUD Militar Conectado');
    }

    public function test_portal_do_cliente_respeita_ativacao_independente_do_preloader(): void
    {
        $this->seed();
        $cliente = Customer::create([
            'nome' => 'Cliente Teste Preloader',
            'email' => 'cliente.preloader@teste.com',
            'telefone' => '31999990000',
            'cpf' => '12345678909',
            'status' => 'ativo',
            'email_portal' => 'cliente.preloader@teste.com',
            'senha_portal' => bcrypt('senha123'),
        ]);

        // 1. Quando preloader_portal_ativo = '0', não exibe no portal
        Setting::set('preloader_portal_ativo', '0');

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.inicio'))
            ->assertOk()
            ->assertDontSee('id="golden-preloader"', false);

        // 2. Quando preloader_portal_ativo = '1', exibe perfeitamente no portal
        Setting::set('preloader_portal_ativo', '1');
        Setting::set('preloader_estilo', 'circuito');

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee('id="golden-preloader"', false)
            ->assertSee('golden-preloader--circuito', false);
    }
}
