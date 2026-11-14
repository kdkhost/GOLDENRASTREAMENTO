<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuponsDeDescontoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $categoria = Category::firstOrCreate(['slug' => 'rastreadores'], ['nome' => 'Rastreadores']);
        $this->produto = Product::create([
            'category_id' => $categoria->id,
            'nome' => 'Rastreador J16',
            'slug' => 'rastreador-j16-cupom',
            'preco_cents' => 30000,
            'estoque' => 20,
            'ativo' => true,
            'controle_estoque' => true,
        ]);
        $this->produto->esquecerCache();

        Setting::set('loja_frete_cents', 2500);
        Setting::set('loja_frete_gratis_acima_cents', 0);
        Setting::set('loja_livre', '0');
    }

    public function test_admin_cria_edita_alterna_e_exclui_cupom(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.catalogo.cupons.store'), [
                'codigo' => 'golden15',
                'descricao' => 'Campanha 15% OFF',
                'tipo' => 'percentual',
                'valor' => '15',
                'valor_minimo' => '100',
                'desconto_maximo' => '80',
                'limite_usos' => 50,
                'limite_por_cliente' => 1,
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.catalogo.cupons.index'));

        $cupom = Coupon::where('codigo', 'GOLDEN15')->firstOrFail();
        $this->assertSame('percentual', $cupom->tipo);
        $this->assertSame(15, $cupom->valor);
        $this->assertSame(10000, $cupom->valor_minimo_cents);
        $this->assertSame(8000, $cupom->desconto_maximo_cents);

        $this->actingAs($this->admin)
            ->post(route('admin.catalogo.cupons.toggle', $cupom))
            ->assertRedirect();
        $this->assertFalse($cupom->fresh()->ativo);

        $this->actingAs($this->admin)
            ->delete(route('admin.catalogo.cupons.destroy', $cupom))
            ->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $cupom->id]);
    }

    public function test_cliente_aplica_cupom_percentual_e_finaliza_pedido_com_desconto(): void
    {
        $cupom = Coupon::create([
            'codigo' => 'DESCONTO10',
            'tipo' => 'percentual',
            'valor' => 10,
            'valor_minimo_cents' => 10000,
            'limite_usos' => 5,
            'ativo' => true,
        ]);

        $this->post(route('site.loja.carrinho.adicionar'), [
            'produto' => $this->produto->id,
            'quantidade' => 1,
        ])->assertRedirect(route('site.loja.carrinho'));

        $this->post(route('site.loja.cupom.aplicar'), ['codigo' => 'desconto10'])
            ->assertSessionHas('success');

        $this->get(route('site.loja.carrinho'))
            ->assertOk()
            ->assertSee('DESCONTO10');

        $this->post(route('site.loja.pedido.registrar'), $this->dadosCheckout())
            ->assertRedirect();

        $pedido = Order::latest('id')->firstOrFail();
        $this->assertSame($cupom->id, $pedido->coupon_id);
        $this->assertSame('DESCONTO10', $pedido->cupom_codigo);
        $this->assertSame(30000, $pedido->subtotal_cents);
        $this->assertSame(3000, $pedido->desconto_cents);
        $this->assertSame(2500, $pedido->frete_cents);
        $this->assertSame(29500, $pedido->total_cents);
        $this->assertSame(1, $cupom->fresh()->usos);
    }

    public function test_cupom_frete_gratis_zera_frete_no_pedido(): void
    {
        Coupon::create([
            'codigo' => 'FRETEZERO',
            'tipo' => 'frete_gratis',
            'valor' => 0,
            'ativo' => true,
        ]);

        $this->post(route('site.loja.carrinho.adicionar'), [
            'produto' => $this->produto->id,
            'quantidade' => 1,
        ]);

        $this->post(route('site.loja.cupom.aplicar'), ['codigo' => 'FRETEZERO'])
            ->assertSessionHas('success');

        $this->post(route('site.loja.pedido.registrar'), $this->dadosCheckout())
            ->assertRedirect();

        $pedido = Order::latest('id')->firstOrFail();
        $this->assertSame('FRETEZERO', $pedido->cupom_codigo);
        $this->assertSame(0, $pedido->frete_cents);
        $this->assertSame(0, $pedido->desconto_cents);
        $this->assertSame(30000, $pedido->total_cents);
    }

    public function test_recusa_cupom_expirado_esgotado_ou_abaixo_do_minimo(): void
    {
        Coupon::create([
            'codigo' => 'EXPIRADO',
            'tipo' => 'fixo',
            'valor' => 2000,
            'valido_ate' => now()->subHour(),
            'ativo' => true,
        ]);

        Coupon::create([
            'codigo' => 'MINIMO500',
            'tipo' => 'fixo',
            'valor' => 5000,
            'valor_minimo_cents' => 50000,
            'ativo' => true,
        ]);

        $this->post(route('site.loja.carrinho.adicionar'), [
            'produto' => $this->produto->id,
            'quantidade' => 1,
        ]);

        $this->post(route('site.loja.cupom.aplicar'), ['codigo' => 'EXPIRADO'])
            ->assertSessionHas('error');

        $this->post(route('site.loja.cupom.aplicar'), ['codigo' => 'MINIMO500'])
            ->assertSessionHas('error');
    }

    /** @return array<string, mixed> */
    private function dadosCheckout(): array
    {
        return [
            'nome' => 'Comprador Cupom',
            'email' => 'comprador@golden.test',
            'telefone' => '35999998888',
            'tipo_pessoa' => 'fisica',
            'documento' => '12345678901',
            'entrega' => 'entrega',
            'cep' => '37190000',
            'endereco' => 'Rua Principal, 100',
            'bairro' => 'Centro',
            'cidade' => 'Três Pontas',
            'uf' => 'MG',
            'pagamento' => 'pix',
            'consent' => '1',
        ];
    }

    public function test_admin_salva_produto_apenas_mensalidade_ou_sob_consulta_e_exibe_imagens_na_edicao(): void
    {
        $this->produto->update(['imagem' => 'img/home/rastreador-j16.jpg', 'galeria' => ['img/home/rastreador-j6.jpg']]);

        $this->actingAs($this->admin)
            ->get(route('admin.catalogo.produtos.edit', $this->produto))
            ->assertOk()
            ->assertSee('img/home/rastreador-j16.jpg')
            ->assertSee('img/home/rastreador-j6.jpg');

        $this->actingAs($this->admin)
            ->post(route('admin.catalogo.produtos.update', $this->produto), [
                'nome' => $this->produto->nome,
                'preco' => '',
                'mensalidade' => '59.90',
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.catalogo.produtos.index'));

        $this->assertSame(0, $this->produto->fresh()->preco_cents);
        $this->assertSame(5990, $this->produto->fresh()->mensalidade_cents);

        $this->actingAs($this->admin)
            ->post(route('admin.catalogo.produtos.update', $this->produto), [
                'nome' => $this->produto->nome,
                'preco' => '299',
                'mensalidade' => '59.90',
                'sob_consulta' => '1',
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.catalogo.produtos.index'));

        $this->assertSame(0, $this->produto->fresh()->preco_cents);
        $this->assertNull($this->produto->fresh()->mensalidade_cents);
    }
}
