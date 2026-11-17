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
use Database\Seeders\OrdersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_todo_status_do_pedido_sobrevive_ida_e_volta_no_banco(): void
    {
        // 'aguardando_pagamento' tem 20 caracteres e o MySQL truncava em 16: o
        // pedido morria no meio do fluxo. O SQLite aceita qualquer tamanho, então
        // é no banco real (MySQL em produção) que este teste vira barreira.
        $this->seed(OrdersSeeder::class);
        $this->seed(OrdersSeeder::class);
        $pedido = Order::firstOrFail();

        foreach (array_keys(Order::STATUS) as $status) {
            $pedido->update(['status' => $status]);
            OrderHistory::create(['order_id' => $pedido->id, 'status' => $status]);

            $this->assertSame($status, $pedido->refresh()->status);
            $this->assertSame($status, OrderHistory::latest('id')->firstOrFail()->status);
            $this->assertNotSame('', $pedido->statusLabel());
        }
    }

    public function test_adicionar_no_carrinho_via_ajax_retorna_json_sem_redirect_e_frete_retorna_localidade(): void
    {
        $produto = Product::where('ativo', true)->firstOrFail();

        $this->postJson(route('site.loja.carrinho.adicionar'), [
            'produto' => $produto->id,
            'quantidade' => 2,
            'ir' => 'carrinho',
        ])
            ->assertOk()
            ->assertJson([
                'sucesso' => true,
                'carrinho_itens' => 2,
            ]);

        Http::fake([
            'https://viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
        ]);

        $this->postJson(route('site.loja.frete.calcular'), [
            'cep' => '01001-000',
            'produto_id' => $produto->id,
            'quantidade' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('endereco_cep.resumo', 'Praça da Sé, Sé — São Paulo/SP');
    }

    public function test_carrinho_nao_exibe_frete_gratis_sem_cep_e_atualiza_quantidade_via_ajax(): void
    {
        $produto = Product::where('ativo', true)->firstOrFail();

        $this->postJson(route('site.loja.carrinho.adicionar'), [
            'produto' => $produto->id,
            'quantidade' => 3,
        ])->assertOk();

        $this->get(route('site.loja.carrinho'))
            ->assertOk()
            ->assertSee('Calcule pelo CEP')
            ->assertDontSee('id="cartFreteDisplay">Grátis');

        $this->postJson(route('site.loja.carrinho.atualizar'), [
            'quantidades' => [$produto->id => 1],
        ])
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('total_unidades', 1)
            ->assertJsonPath('frete_formatado', 'Calcule pelo CEP');

        $this->postJson(route('site.loja.carrinho.atualizar'), [
            'quantidades' => [$produto->id => 0],
        ])
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('vazio', true)
            ->assertJsonPath('total_unidades', 0);
    }

    public function test_melhor_envio_retorna_opcoes_e_faz_fallback_se_api_falhar(): void
    {
        Setting::set('melhor_envio_ativo', '1');
        Setting::set('melhor_envio_sandbox', '0');
        Setting::set('melhor_envio_token', 'token-melhor-envio');
        Setting::set('melhor_envio_cep_origem', '35500-000');
        Setting::set('melhor_envio_prazo_adicional', '1');

        $produto = Product::where('ativo', true)->firstOrFail();

        Http::fake([
            'https://viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ]),
            'https://melhorenvio.com.br/api/v2/me/shipment/calculate' => Http::response([
                [
                    'id' => 3,
                    'name' => '.Package',
                    'price' => '17.50',
                    'delivery_time' => 3,
                    'company' => ['name' => 'Jadlog'],
                ],
                [
                    'id' => 99,
                    'name' => 'Indisponível',
                    'error' => 'Trecho não atendido',
                    'company' => ['name' => 'Outra'],
                ],
            ]),
        ]);

        $this->postJson(route('site.loja.frete.calcular'), [
            'cep' => '01001-000',
            'produto_id' => $produto->id,
            'quantidade' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonFragment([
                'id' => 'melhor_envio_3',
                'nome' => 'Jadlog - .Package',
                'valor_cents' => 1750,
                'prazo_dias' => 4,
                'tipo' => 'melhor_envio',
            ])
            ->assertJsonMissing(['id' => 'melhor_envio_99']);

        // Agora simula falha na API do Melhor Envio com outro CEP e garante fallback para Correios
        Http::fake([
            'https://viacep.com.br/ws/30130010/json/' => Http::response([
                'cep' => '30130-010',
                'logradouro' => 'Praça Sete',
                'bairro' => 'Centro',
                'localidade' => 'Belo Horizonte',
                'uf' => 'MG',
            ]),
            'https://melhorenvio.com.br/api/v2/me/shipment/calculate' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $this->postJson(route('site.loja.frete.calcular'), [
            'cep' => '30130-010',
            'produto_id' => $produto->id,
            'quantidade' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonFragment(['id' => 'correios_sedex']);
    }

    public function test_fluxo_adicionar_multiplos_produtos_mantem_consistencia_e_quantidades(): void
    {
        $produtos = Product::where('ativo', true)->take(2)->get();
        $this->assertCount(2, $produtos);
        $p1 = $produtos[0];
        $p2 = $produtos[1];

        // 1. Visitante entra no produto 1 e adiciona 2 unidades via AJAX
        $res1 = $this->postJson(route('site.loja.carrinho.adicionar'), [
            'produto' => $p1->id,
            'quantidade' => 2,
            'modo' => 'somar',
            'ir' => 'carrinho',
        ]);

        $res1->assertOk()
            ->assertJson([
                'sucesso' => true,
                'carrinho_itens' => 2,
                'itens_count' => 1,
                'produto_id' => $p1->id,
                'produto_quantidade_carrinho' => 2,
            ]);

        // 2. Visita a página do produto 1: deve informar que já tem 2 no carrinho
        $resShowP1 = $this->get(route('site.loja.produto', $p1->slug));
        $resShowP1->assertOk();
        $this->assertEquals(2, $resShowP1->viewData('quantidadeNoCarrinho'));
        $resShowP1->assertSee('Item já adicionado na sua sacola:');
        $resShowP1->assertSee('value="2"', false);

        // 3. Navega para o produto 2 e adiciona 3 unidades
        $res2 = $this->postJson(route('site.loja.carrinho.adicionar'), [
            'produto' => $p2->id,
            'quantidade' => 3,
            'modo' => 'somar',
            'ir' => 'carrinho',
        ]);

        $res2->assertOk()
            ->assertJson([
                'sucesso' => true,
                'carrinho_itens' => 5, // 2 do p1 + 3 do p2
                'itens_count' => 2,
                'produto_id' => $p2->id,
                'produto_quantidade_carrinho' => 3,
            ]);

        // 4. Volta na página do produto 1: a quantidade no carrinho continua 2
        $resShowP1Novamente = $this->get(route('site.loja.produto', $p1->slug));
        $resShowP1Novamente->assertOk();
        $this->assertEquals(2, $resShowP1Novamente->viewData('quantidadeNoCarrinho'));

        // 5. Atualiza a quantidade do produto 1 usando modo 'definir' para 4 unidades
        $resUpdateP1 = $this->postJson(route('site.loja.carrinho.adicionar'), [
            'produto' => $p1->id,
            'quantidade' => 4,
            'modo' => 'definir',
            'ir' => 'carrinho',
        ]);

        $resUpdateP1->assertOk()
            ->assertJson([
                'sucesso' => true,
                'carrinho_itens' => 7, // 4 do p1 + 3 do p2
                'itens_count' => 2,
                'produto_id' => $p1->id,
                'produto_quantidade_carrinho' => 4,
            ]);

        // Verifica que o produto 2 não foi alterado indevidamente
        $carrinho = session(Carrinho::CHAVE);
        $this->assertEquals(4, $carrinho[$p1->id]);
        $this->assertEquals(3, $carrinho[$p2->id]);

        // 6. Visita a página do carrinho
        $resCart = $this->get(route('site.loja.carrinho'));
        $resCart->assertOk();
        $this->assertStringContainsString('no-store', (string) $resCart->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $resCart->headers->get('Cache-Control'));
        $resCart->assertSee('2 produtos na sua sacola (7 unidades no total).');
        $resCart->assertSee('Subtotal (7 unidades)');

        // 7. Envia atualização de carrinho indo para checkout (ir=checkout)
        $resFinalizar = $this->post(route('site.loja.carrinho.atualizar'), [
            'quantidades' => [
                $p1->id => 4,
                $p2->id => 3,
            ],
            'ir' => 'checkout',
        ]);
        $resFinalizar->assertRedirect(route('site.loja.checkout'));
    }
}
