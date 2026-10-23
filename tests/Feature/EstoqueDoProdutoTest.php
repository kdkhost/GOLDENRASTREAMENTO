<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * O ajuste de estoque feito pelo formulário do produto: o movimento registra a diferença
 * entre o saldo novo e o anterior, e o cadastro continua respondendo (um clone do modelo
 * para calcular o delta perdia a chave primária e derrubava a gravação em 404).
 */
class EstoqueDoProdutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        Cache::flush();
    }

    private function salvar(Product $produto, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->post(route('admin.catalogo.produtos.update', $produto), array_merge([
                'nome' => $produto->nome,
                'preco' => $produto->preco_cents / 100,
                'estoque' => $produto->estoque,
                'controle_estoque' => '1',
            ], $extra));
    }

    public function test_ajustar_o_estoque_no_cadastro_registra_a_diferenca_e_nao_quebra_a_gravacao(): void
    {
        $produto = Product::firstOrFail();
        $antes = $produto->estoque;

        $this->salvar($produto, ['estoque' => $antes + 7])->assertRedirect(route('admin.catalogo.produtos.index'));

        $movimento = StockMovement::where('product_id', $produto->id)->latest('id')->firstOrFail();
        $this->assertSame('ajuste', $movimento->tipo);
        $this->assertSame(7, $movimento->quantidade);
        $this->assertSame($antes + 7, $produto->refresh()->estoque);
        $this->assertSame($antes + 7, $movimento->saldo_after);
    }

    public function test_reduzir_o_estoque_no_cadastro_registra_saida(): void
    {
        $produto = Product::firstOrFail();
        $produto->update(['estoque' => 20]);
        StockMovement::where('product_id', $produto->id)->delete();

        $this->salvar($produto, ['estoque' => 12])->assertRedirect();

        $movimento = StockMovement::where('product_id', $produto->id)->latest('id')->firstOrFail();
        $this->assertSame(-8, $movimento->quantidade);
        $this->assertSame(12, $produto->refresh()->estoque);
    }

    public function test_alterar_outros_dados_nao_gera_movimento_de_estoque(): void
    {
        $produto = Product::firstOrFail();

        $this->salvar($produto, ['nome' => 'Nome novo do produto'])->assertRedirect();

        $this->assertSame('Nome novo do produto', $produto->refresh()->nome);
        $this->assertSame(0, StockMovement::where('product_id', $produto->id)->where('tipo', 'ajuste')->count());
    }
}
