<?php

namespace Tests\Feature;

use App\Mail\PedidoNovoEquipe;
use App\Mail\PedidoRecebido;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Carrinho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fluxo comercial completo da loja: vitrine → carrinho → checkout com documento e entrega
 * → pedido com numeração e token → linha do tempo → status público.
 */
class FluxoDaLojaTest extends TestCase
{
    use RefreshDatabase;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        Cache::flush();

        $this->produto = Product::where('slug', 'rastreador-j16-4g')->firstOrFail();
    }

    private function dadosCheckout(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Marcelo da Golden',
            'email' => 'comprador@exemplo.com.br',
            'telefone' => '(35) 99888-7766',
            'tipo_pessoa' => 'fisica',
            'documento' => '12345678901',
            'entrega' => 'entrega',
            'cep' => '37160000',
            'endereco' => 'Av. Brasil, 1200',
            'bairro' => 'Centro',
            'complemento' => 'Galpão 2',
            'cidade' => 'Três Pontas',
            'uf' => 'MG',
            'pagamento' => 'pix',
            'notas' => 'Instalar na matriz',
            'consent' => '1',
        ], $extra);
    }

    private function encherSacola(int $quantidade = 2): void
    {
        $this->post('/loja/carrinho', [
            'produto' => $this->produto->id,
            'quantidade' => $quantidade,
        ])->assertRedirect(route('site.loja.carrinho'));
    }

    /** O pedido mais recente registrado por este teste pelo checkout da loja. */
    private function ultimoPedido(): Order
    {
        return Order::where('email', 'comprador@exemplo.com.br')->latest('id')->firstOrFail();
    }

    private function pedidosDaLoja(): int
    {
        return Order::where('email', 'comprador@exemplo.com.br')->count();
    }

    public function test_vitrine_mostra_o_catalogo_e_os_filtros_de_categoria(): void
    {
        $this->get('/loja')->assertOk()
            ->assertSee('Rastreador veicular J16 4G/2G')
            ->assertSee('Chips M2M');
    }

    public function test_cliente_percorre_a_loja_ate_registrar_o_pedido(): void
    {
        Mail::fake();
        $estoqueAntes = (int) $this->produto->estoque;

        $this->get(route('site.loja.produto', $this->produto->slug))->assertOk()
            ->assertSee('Comprar agora');

        $this->encherSacola();
        $this->get('/loja/carrinho')->assertOk()->assertSee('Rastreador veicular J16 4G/2G');
        $this->get('/loja/checkout')->assertOk()->assertSee('Finalizar pedido');

        $resposta = $this->post('/loja/pedido', $this->dadosCheckout());
        $pedido = $this->ultimoPedido();

        $this->assertNotNull($pedido);
        $resposta->assertRedirect($pedido->registroUrl());

        $this->assertSame('pendente', $pedido->status);
        $this->assertSame(2, (int) $pedido->items()->first()->quantidade);
        $this->assertSame($this->produto->preco() * 2, (int) $pedido->subtotal_cents);
        $this->assertMatchesRegularExpression('/^GR\d{2}-\d{5}$/', $pedido->numero);
        $this->assertSame(32, strlen((string) $pedido->token));
        $this->assertTrue($pedido->consent_given);
        $this->assertSame('12345678901', $pedido->documento);

        // Estoque baixou e ficou registrado no razão.
        $this->assertSame($estoqueAntes - 2, (int) $this->produto->fresh()->estoque);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->produto->id, 'order_id' => $pedido->id, 'tipo' => 'saida']);

        // Cliente novo entrou no CRM e a trilha do pedido começou.
        $this->assertDatabaseHas('customers', ['email' => 'comprador@exemplo.com.br', 'documento' => '12345678901']);
        $this->assertDatabaseHas('order_histories', ['order_id' => $pedido->id, 'status' => 'pendente']);

        Mail::assertQueued(PedidoRecebido::class);
        Mail::assertQueued(PedidoNovoEquipe::class, 0);

        // A sacola esvazia e a página pública do pedido responde pelo token.
        $this->get('/loja/carrinho')->assertDontSee('Rastreador veicular J16 4G/2G');
        $this->get($pedido->registroUrl())->assertOk()
            ->assertSee($pedido->numero)
            ->assertSee('Linha do tempo')
            ->assertSee('Pix');
    }

    public function test_o_link_publico_exige_o_token(): void
    {
        $pedido = Order::create(['nome' => 'Quem viu', 'status' => 'pendente', 'total_cents' => 100]);

        $this->get(route('site.loja.pedido', ['numero' => $pedido->numero, 'token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']))->assertNotFound();
        $this->get('/loja/pedido/'.$pedido->numero)->assertNotFound();
        $this->get($pedido->registroUrl())->assertOk()->assertSee($pedido->numero);
    }

    public function test_documento_quantidade_e_consentimento_sao_barreiras_reais(): void
    {
        $this->encherSacola();

        $this->from('/loja/checkout')
            ->post('/loja/pedido', $this->dadosCheckout(['documento' => '123']))
            ->assertSessionHasErrors('documento');

        $this->post('/loja/carrinho', ['produto' => $this->produto->id, 'quantidade' => 1]);
        $this->from('/loja/checkout')
            ->post('/loja/pedido', $this->dadosCheckout(['consent' => null]))
            ->assertSessionHasErrors('consent');

        // Honeypot preenchido: nada é gravado, sem erro na tela.
        $this->from('/loja/checkout')
            ->post('/loja/pedido', $this->dadosCheckout(['website' => 'http://spam.test']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->pedidosDaLoja());
    }

    public function test_o_carrinho_limita_a_prateleira_e_o_pedido_confere_o_estoque_real(): void
    {
        $estoque = (int) $this->produto->estoque;

        $this->post('/loja/carrinho', ['produto' => $this->produto->id, 'quantidade' => $estoque + 50])->assertRedirect();
        $this->assertSame($estoque, (int) session(Carrinho::CHAVE)[$this->produto->id]);

        // A prateleira esvaziou por fora do painel (balanço, venda em paralelo): a vitrine
        // ainda anuncia o antigo volume e é o banco, dentro da transação, que barra a venda.
        Product::where('id', $this->produto->id)->update(['estoque' => 0]);

        $this->from('/loja/checkout')->post('/loja/pedido', $this->dadosCheckout())->assertSessionHasErrors('carrinho');

        $this->assertSame(0, $this->pedidosDaLoja());
        $this->assertSame(0, (int) $this->produto->fresh()->estoque);
    }

    public function test_regras_de_frete_do_painel_chegam_no_pedido(): void
    {
        Mail::fake();
        Setting::set('loja_livre', '1');
        $this->encherSacola(1);
        $this->post('/loja/pedido', $this->dadosCheckout());
        $this->assertSame(0, (int) $this->ultimoPedido()->frete_cents);

        // Frete fixo cobrado quando o painel desliga o "frete grátis / combinar entrega".
        Setting::set('loja_livre', '0');
        Setting::set('loja_frete_cents', '1500');
        Setting::set('loja_frete_gratis_acima_cents', '90000');

        $this->encherSacola(1);
        $this->post('/loja/pedido', $this->dadosCheckout())->assertRedirect();
        $this->assertSame(1500, (int) $this->ultimoPedido()->frete_cents);

        // Acima do piso configurado o frete zera.
        $this->encherSacola(4);
        $this->post('/loja/pedido', $this->dadosCheckout());
        $this->assertSame(0, (int) $this->ultimoPedido()->frete_cents);

        // Retirada na loja só entra no fluxo quando o painel liga, e nunca paga frete.
        $this->encherSacola(1);
        $this->get('/loja/checkout')->assertOk()->assertDontSee('Retirada na loja');
        Setting::set('loja_retirada', '1');
        $this->get('/loja/checkout')->assertOk()->assertSee('Retirada na loja');

        $this->post('/loja/pedido', $this->dadosCheckout(['entrega' => 'retira', 'pagamento' => 'retirada']));
        $ultimo = $this->ultimoPedido();
        $this->assertSame('retira', $ultimo->entrega);
        $this->assertSame(0, (int) $ultimo->frete_cents);
    }

    public function test_mudar_status_no_painel_alimenta_a_linha_do_tempo(): void
    {
        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $pedido = Order::create(['nome' => 'Marcelo', 'status' => 'pendente', 'total_cents' => 29900, 'email' => 'x@y.z']);

        $this->actingAs($admin)
            ->from(route('admin.pedidos.show', $pedido))
            ->post(route('admin.pedidos.status', $pedido), [
                'status' => 'enviado',
                'pagamento' => 'pix',
                'notas' => 'Separado e postado',
                'observacao' => 'Saiu dos Correios hoje.',
            ])
            ->assertRedirect(route('admin.pedidos.show', $pedido));

        $pedido->refresh();
        $this->assertSame('enviado', $pedido->status);
        $this->assertNotNull($pedido->enviado_em);

        $marco = OrderHistory::where('order_id', $pedido->id)->where('status', 'enviado')->first();
        $this->assertNotNull($marco);
        $this->assertSame($admin->id, (int) $marco->user_id);
        $this->assertSame('Saiu dos Correios hoje.', $marco->observacao);

        $etapas = collect($pedido->jornada());
        $this->assertTrue($etapas->firstWhere('status', 'enviado')['atual']);
        $this->assertContains('separando', $etapas->pluck('status')->all());

        $this->get($pedido->registroUrl())->assertOk()->assertSee('Saiu dos Correios hoje.');
    }

    public function test_desligar_a_loja_tira_a_sacola_do_ar(): void
    {
        $this->encherSacola();
        Setting::set('loja_ativa', '0');

        $this->get('/loja')->assertNotFound();
        $this->get('/loja/carrinho')->assertNotFound();
        $this->post('/loja/pedido', $this->dadosCheckout())->assertNotFound();
    }

    public function test_sacola_vazia_devolve_para_a_loja_em_vez_de_quebrar_o_checkout(): void
    {
        $this->get('/loja/checkout')
            ->assertRedirect(route('site.loja.index'))
            ->assertSessionHas('error');

        $this->post('/loja/pedido', $this->dadosCheckout())->assertRedirect(route('site.loja.index'));
        $this->assertSame(0, $this->pedidosDaLoja());
    }

    public function test_cliente_existente_reaproveita_o_cadastro_do_crm(): void
    {
        Mail::fake();
        $cliente = Customer::create(['nome' => 'Frota Antiga', 'documento' => '12345678901', 'email' => 'velho@exemplo.com']);

        $this->encherSacola();
        $this->post('/loja/pedido', $this->dadosCheckout());

        $this->assertSame($cliente->id, (int) $this->ultimoPedido()->customer_id);
        $this->assertSame(1, Customer::where('documento', '12345678901')->count());
    }
}
