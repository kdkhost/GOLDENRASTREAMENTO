<?php

namespace Tests\Feature;

use App\Models\ErrorPage;
use App\Models\User;
use Database\Seeders\ErrorPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginasDeErroPersonalizadasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'ativo',
        ]);
    }

    public function test_admin_consegue_listar_paginas_de_erro(): void
    {
        $this->seed(ErrorPageSeeder::class);

        $response = $this->actingAs($this->admin)->get(route('admin.erros.index'));

        $response->assertOk();
        $response->assertSee('Páginas de erro personalizadas');
        $response->assertSee('HTTP 404');
        $response->assertSee('HTTP 500');
        $response->assertSee('HTTP 503');
    }

    public function test_admin_consegue_editar_e_atualizar_pagina_de_erro_404(): void
    {
        $this->seed(ErrorPageSeeder::class);

        $response = $this->actingAs($this->admin)->get(route('admin.erros.edit', 404));
        $response->assertOk();
        $response->assertSee('Editar página do erro HTTP 404');

        $updateResponse = $this->actingAs($this->admin)->put(route('admin.erros.update', 404), [
            'title' => 'Rastreador Não Localizado',
            'subtitle' => 'Esta coordenada não existe no mapa.',
            'message' => 'O endereço que você digitou não foi encontrado em nossos servidores de rastreamento.',
            'primary_button_text' => 'Retornar ao Radar',
            'primary_button_url' => '/',
            'show_whatsapp_button' => 1,
            'whatsapp_custom_text' => 'Preciso de ajuda para achar a página certa.',
            'icon' => 'fa-compass',
            'active' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.erros.index'));
        $updateResponse->assertSessionHas('success');

        $pagina = ErrorPage::where('code', 404)->first();
        $this->assertNotNull($pagina);
        $this->assertSame('Rastreador Não Localizado', $pagina->title);
        $this->assertSame('Esta coordenada não existe no mapa.', $pagina->subtitle);
    }

    public function test_preview_renderiza_o_layout_de_erro_com_marca_golden(): void
    {
        $this->seed(ErrorPageSeeder::class);

        $response = $this->actingAs($this->admin)->get(route('admin.erros.preview', 404));

        $response->assertOk();
        $response->assertSee('Golden');
        $response->assertSee('Rastreamento');
        $response->assertSee('404');
    }

    public function test_admin_consegue_restaurar_padrao_de_fabrica(): void
    {
        $this->seed(ErrorPageSeeder::class);

        // Modifica primeiro
        ErrorPage::where('code', 404)->update(['title' => 'Texto Totalmente Modificado']);

        $response = $this->actingAs($this->admin)->post(route('admin.erros.restaurar', 404));

        $response->assertRedirect(route('admin.erros.edit', 404));
        $response->assertSessionHas('success');

        $pagina = ErrorPage::where('code', 404)->first();
        $this->assertSame('Página Não Encontrada', $pagina->title);
    }
}
