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

    public function test_desativar_produto_e_remover_categoria_salva_corretamente(): void
    {
        $admin = User::first();
        $produto = Product::first();

        // O navegador quando desmarca switches não envia ativo, destaque ou controle_estoque.
        // E quando seleciona "Sem categoria", envia category_id = ""
        $payload = [
            'nome' => 'Produto Desativado',
            'category_id' => '', // Sem categoria
            'preco' => '100,00',
            'imagem' => $produto->imagem,
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
        $this->assertFalse($produto->ativo, 'Produto deve ser desativado quando o switch ativo for desmarcado.');
        $this->assertFalse($produto->destaque);
        $this->assertFalse($produto->controle_estoque);
        $this->assertNull($produto->category_id);
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
}
