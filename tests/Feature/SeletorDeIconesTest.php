<?php

namespace Tests\Feature;

use App\Models\ErrorPage;
use App\Models\User;
use App\Support\Icones;
use Database\Seeders\ErrorPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeletorDeIconesTest extends TestCase
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

    public function test_catalogo_de_icones_possui_categorias_e_itens_validos(): void
    {
        $catalogo = Icones::catalogo();

        $this->assertNotEmpty($catalogo);
        $this->assertArrayHasKey('alertas', $catalogo);
        $this->assertArrayHasKey('servidor', $catalogo);
        $this->assertArrayHasKey('rastreamento', $catalogo);
        $this->assertArrayHasKey('comunicacao', $catalogo);
        $this->assertArrayHasKey('comercio', $catalogo);
        $this->assertArrayHasKey('gerais', $catalogo);

        foreach ($catalogo as $cat) {
            $this->assertNotEmpty($cat['nome']);
            $this->assertNotEmpty($cat['itens']);
            foreach ($cat['itens'] as $item) {
                $this->assertNotEmpty($item['codigo']);
                $this->assertNotEmpty($item['nome']);
                $this->assertNotEmpty($item['tags']);
            }
        }
    }

    public function test_icones_resolvem_nome_amigavel_e_classe_completa(): void
    {
        $this->assertSame('Alerta / Atenção', Icones::nomeAmigavel('fa-triangle-exclamation'));
        $this->assertSame('WhatsApp Oficial', Icones::nomeAmigavel('fa-brands fa-whatsapp'));
        $this->assertSame('Carro Particular', Icones::nomeAmigavel('fa-car'));

        $this->assertSame('fa-solid fa-triangle-exclamation', Icones::classeCompleta('fa-triangle-exclamation'));
        $this->assertSame('fa-solid fa-triangle-exclamation', Icones::classeCompleta('triangle-exclamation'));
        $this->assertSame('fa-brands fa-whatsapp', Icones::classeCompleta('fa-brands fa-whatsapp'));
        $this->assertSame('fa-solid fa-server', Icones::classeCompleta('fa-server'));
    }

    public function test_tela_de_edicao_de_erro_renderiza_seletor_visual_de_icones(): void
    {
        $this->seed(ErrorPageSeeder::class);

        $response = $this->actingAs($this->admin)->get(route('admin.erros.edit', 404));

        $response->assertOk();
        $response->assertSee('Selecione o Ícone Visual');
        $response->assertSee('Escolher Ícone');
        $response->assertSee('fa-triangle-exclamation');
        $response->assertSee('icon-card-btn');
    }

    public function test_admin_consegue_atualizar_icone_da_pagina_de_erro_selecionado(): void
    {
        $this->seed(ErrorPageSeeder::class);

        $response = $this->actingAs($this->admin)->put(route('admin.erros.update', 404), [
            'title' => 'Página Perdida no Espaço',
            'subtitle' => 'Não conseguimos localizar o satélite.',
            'message' => 'O endereço solicitado não foi encontrado.',
            'primary_button_text' => 'Início',
            'primary_button_url' => '/',
            'icon' => 'fa-satellite',
            'active' => 1,
        ]);

        $response->assertRedirect(route('admin.erros.index'));

        $pagina = ErrorPage::where('code', 404)->first();
        $this->assertSame('fa-satellite', $pagina->icon);
    }
}
