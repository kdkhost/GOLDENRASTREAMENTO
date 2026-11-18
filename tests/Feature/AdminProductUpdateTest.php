<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_edicao_de_produto_com_precos_em_formato_brasileiro(): void
    {
        $admin = User::first();
        $produto = Product::first();

        $payload = [
            'nome' => 'Rastreador J16 Atualizado',
            'sku' => 'J16-MOD',
            'category_id' => (string) $produto->category_id,
            'resumo' => 'Resumo atualizado com sucesso',
            'descricao' => 'Descrição detalhada do rastreador',
            'preco' => '349,90', // Formato brasileiro com vírgula
            'preco_promocional' => 'R$ 299,00', // Formato com R$
            'mensalidade' => '39,90',
            'estoque' => '25',
            'estoque_minimo' => '3',
            'ordem' => '1',
            'ativo' => '1',
            'destaque' => '1',
            'controle_estoque' => '1',
            'imagem' => $produto->imagem,
            'galeria' => [],
            'video' => null,
        ];

        $res = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.catalogo.produtos.update', $produto), $payload);

        $res->assertOk();
        $this->assertTrue($res->json('ok'));

        $produto->refresh();
        $this->assertSame('Rastreador J16 Atualizado', $produto->nome);
        $this->assertSame('J16-MOD', $produto->sku);
        $this->assertSame(34990, $produto->preco_cents);
        $this->assertSame(29900, $produto->preco_promocional_cents);
        $this->assertSame(3990, $produto->mensalidade_cents);
        $this->assertSame(25, $produto->estoque);
        $this->assertTrue($produto->ativo);
        $this->assertTrue($produto->destaque);
    }

    public function test_salvar_mensalidade_especifica_produto_10(): void
    {
        $admin = User::first();
        $p = Product::find(10);

        // Testa salvar nova mensalidade de 79,90 no produto 10
        $payload = [
            'nome' => $p->nome,
            'sku' => $p->sku,
            'category_id' => (string) $p->category_id,
            'resumo' => $p->resumo,
            'descricao' => $p->descricao,
            'preco' => '',
            'preco_promocional' => '',
            'mensalidade' => '79,90',
            'estoque' => '999',
            'estoque_minimo' => '5',
            'ordem' => '0',
            'ativo' => '1',
            'destaque' => '1',
            'controle_estoque' => '0',
            'imagem' => $p->imagem,
        ];

        $res = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertOk();
        $p->refresh();
        $this->assertSame(7990, $p->mensalidade_cents);
        $this->assertSame(0, $p->preco_cents);
    }

    public function test_salvar_mensalidade_com_switch_sob_consulta_ativo(): void
    {
        $admin = User::first();
        $p = Product::find(10);

        // Se o operador marca sob_consulta, os preços e mensalidade são zerados para exibir "Consulte"
        $payload = [
            'nome' => $p->nome,
            'sku' => $p->sku,
            'category_id' => (string) $p->category_id,
            'resumo' => $p->resumo,
            'descricao' => $p->descricao,
            'preco' => '199',
            'preco_promocional' => '149',
            'mensalidade' => '89,90',
            'sob_consulta' => '1',
            'estoque' => '999',
            'estoque_minimo' => '5',
            'ordem' => '0',
            'ativo' => '1',
            'destaque' => '1',
            'controle_estoque' => '0',
            'imagem' => $p->imagem,
        ];

        $res = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertOk();
        $p->refresh();
        $this->assertNull($p->mensalidade_cents);
        $this->assertSame(0, $p->preco_cents);
    }

    public function test_adicionar_mensalidade_ao_rastreador_j16(): void
    {
        $admin = User::first();
        $p = Product::where('slug', 'rastreador-j16-4g')->firstOrFail();

        // Rastreador J16: R$ 299,00 à vista + R$ 49,90/mês
        $payload = [
            'nome' => $p->nome,
            'sku' => $p->sku,
            'category_id' => (string) $p->category_id,
            'resumo' => $p->resumo,
            'descricao' => $p->descricao,
            'preco' => '299,00',
            'preco_promocional' => '249,00',
            'mensalidade' => '49,90',
            'estoque' => '50',
            'estoque_minimo' => '5',
            'ordem' => '0',
            'ativo' => '1',
            'destaque' => '1',
            'controle_estoque' => '1',
            'imagem' => $p->imagem,
        ];

        $res = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertOk();
        $p->refresh();
        $this->assertSame(24900, $p->preco_promocional_cents);
        $this->assertSame(29900, $p->preco_cents);
        $this->assertSame(4990, $p->mensalidade_cents);
    }

    public function test_todos_os_produtos_do_catalogo_podem_ser_editados_e_salvos(): void
    {
        $admin = User::first();
        $produtos = Product::all();

        foreach ($produtos as $p) {
            $preco = $p->preco_cents ? number_format($p->preco_cents / 100, 2, ',', '') : '';
            $precoPromocional = $p->preco_promocional_cents ? number_format($p->preco_promocional_cents / 100, 2, ',', '') : '';
            $mensalidade = $p->mensalidade_cents ? number_format($p->mensalidade_cents / 100, 2, ',', '') : '';

            $payload = [
                'nome' => $p->nome.' (OK)',
                'sku' => $p->sku,
                'category_id' => $p->category_id ? (string) $p->category_id : '',
                'resumo' => $p->resumo,
                'descricao' => $p->descricao,
                'preco' => $preco,
                'preco_promocional' => $precoPromocional,
                'mensalidade' => $mensalidade,
                'sob_consulta' => ($p->preco_cents === 0 && ! $p->mensalidade_cents) ? '1' : null,
                'estoque' => (string) $p->estoque,
                'estoque_minimo' => (string) $p->estoque_minimo,
                'ordem' => (string) $p->ordem,
                'ativo' => $p->ativo ? '1' : null,
                'destaque' => $p->destaque ? '1' : null,
                'controle_estoque' => $p->controle_estoque ? '1' : null,
                'imagem' => $p->imagem,
                'galeria' => $p->galeria ?? [],
                'video' => $p->video,
            ];

            $payload = array_filter($payload, fn ($v) => $v !== null);

            $res = $this->actingAs($admin)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(route('admin.catalogo.produtos.update', $p), $payload);

            $this->assertEquals(200, $res->status(), "Falha ao salvar produto ID {$p->id} ({$p->nome}): ".json_encode($res->json()));
            $this->assertTrue($res->json('ok'));
        }
    }

    public function test_salvar_campos_completos_de_rastreador_e_frete(): void
    {
        $admin = User::first();
        $p = Product::find(10);

        $payload = [
            'nome' => 'Plano Frota Completo 4G',
            'sku' => 'FROTA-4G-VIVO',
            'category_id' => (string) $p->category_id,
            'resumo' => 'Monitoramento completo de frotas com telemetria e bloqueio remoto.',
            'descricao' => 'Acesso total à plataforma web e app móvel, com alertas e cercas virtuais.',
            'tipo_cobranca' => 'combo',
            'preco' => '199,00',
            'preco_promocional' => '149,00',
            'mensalidade' => '59,90',
            'chip_operadora' => 'Multi-operadora 4G (Vivo/Claro/TIM)',
            'garantia' => 'Vitalícia no plano',
            'anatel_homologado' => 'Homologado Anatel',
            'recursos' => ['app', 'web', 'bloqueio', 'cercas', 'rotas', 'ignicao'],
            'peso_gramas' => '220',
            'comprimento_cm' => '16',
            'largura_cm' => '12',
            'altura_cm' => '5',
            'estoque' => '999',
            'estoque_minimo' => '5',
            'ordem' => '1',
            'ativo' => '1',
            'destaque' => '1',
            'controle_estoque' => '0',
            'imagem' => $p->imagem,
        ];

        $res = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertOk();
        $this->assertTrue($res->json('ok'));

        $p->refresh();
        $this->assertSame('Plano Frota Completo 4G', $p->nome);
        $this->assertSame('FROTA-4G-VIVO', $p->sku);
        $this->assertSame('combo', $p->tipo_cobranca);
        $this->assertSame(14900, $p->preco_promocional_cents);
        $this->assertSame(19900, $p->preco_cents);
        $this->assertSame(5990, $p->mensalidade_cents);
        $this->assertSame('Multi-operadora 4G (Vivo/Claro/TIM)', $p->chip_operadora);
        $this->assertSame('Vitalícia no plano', $p->garantia);
        $this->assertSame('Homologado Anatel', $p->anatel_homologado);
        $this->assertEquals(['app', 'web', 'bloqueio', 'cercas', 'rotas', 'ignicao'], $p->recursos);
        $this->assertSame(220, $p->peso_gramas);
        $this->assertSame(16, $p->comprimento_cm);
        $this->assertSame(12, $p->largura_cm);
        $this->assertSame(5, $p->altura_cm);
    }

    public function test_salvamento_nativo_formulario_html_redireciona_com_sucesso(): void
    {
        $admin = User::first();
        $p = Product::find(10);

        $payload = [
            'nome' => 'Plano Frota Salvo Nativo',
            'category_id' => (string) $p->category_id,
            'preco' => '199,00',
            'mensalidade' => '59,90',
            'imagem' => $p->imagem,
            'ativo' => '1',
        ];

        // Simula POST padrão de navegador (sem Ajax / sem header Accept: json)
        $res = $this->actingAs($admin)
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertRedirect(route('admin.catalogo.produtos.index'));
        $res->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('Plano Frota Salvo Nativo', $p->nome);
        $this->assertSame(19900, $p->preco_cents);
        $this->assertSame(5990, $p->mensalidade_cents);
    }

    public function test_salvar_e_continuar_editando_nativo_redireciona_para_edicao(): void
    {
        $admin = User::first();
        $p = Product::find(10);

        $payload = [
            'nome' => 'Plano Frota Salvar e Continuar',
            'category_id' => (string) $p->category_id,
            'preco' => '249,00',
            'mensalidade' => '69,90',
            'salvar_e_continuar' => '1',
            'imagem' => $p->imagem,
            'ativo' => '1',
        ];

        $res = $this->actingAs($admin)
            ->post(route('admin.catalogo.produtos.update', $p), $payload);

        $res->assertRedirect(route('admin.catalogo.produtos.edit', $p));
        $res->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('Plano Frota Salvar e Continuar', $p->nome);
        $this->assertSame(24900, $p->preco_cents);
        $this->assertSame(6990, $p->mensalidade_cents);
    }
}
