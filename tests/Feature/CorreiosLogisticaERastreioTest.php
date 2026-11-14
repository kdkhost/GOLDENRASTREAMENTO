<?php

namespace Tests\Feature;

use App\Mail\PedidoEnviadoRastreio;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Correios\CorreiosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreiosLogisticaERastreioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CorreiosService $correios;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        Cache::flush();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->correios = app(CorreiosService::class);
    }

    public function test_calculo_frete_sem_contrato_retorna_opcoes_sedex_e_pac(): void
    {
        Setting::set('correios_ativo', '1');
        Setting::set('correios_possui_contrato', '0');
        Setting::set('correios_sedex_ativo', '1');
        Setting::set('correios_pac_ativo', '1');
        Setting::set('correios_prazo_adicional', 1);

        $opcoes = $this->correios->calcularFrete('01310-100', 50000); // R$ 500,00

        $this->assertNotEmpty($opcoes);
        $ids = array_column($opcoes, 'id');
        $this->assertContains('correios_sedex', $ids);
        $this->assertContains('correios_pac', $ids);

        $sedex = collect($opcoes)->firstWhere('id', 'correios_sedex');
        $this->assertGreaterThan(0, $sedex['valor_cents']);
        $this->assertGreaterThan(0, $sedex['prazo_dias']);
    }

    public function test_calculo_frete_com_contrato_aplica_desconto_contratual(): void
    {
        // Sem contrato
        Setting::set('correios_ativo', '1');
        Setting::set('correios_possui_contrato', '0');
        Setting::set('correios_sedex_ativo', '1');
        Setting::set('correios_pac_ativo', '1');
        Setting::set('correios_prazo_adicional', 0);
        Setting::set('correios_taxa_manuseio', '0.00');

        $semContrato = $this->correios->calcularFrete('30130-100', 10000);
        $sedexBalcao = collect($semContrato)->firstWhere('id', 'correios_sedex');

        // Com contrato
        Setting::set('correios_possui_contrato', '1');
        Setting::set('correios_codigo_administrativo', '08000000');
        Setting::set('correios_cartao_postagem', '0057018901');

        $comContrato = $this->correios->calcularFrete('30130-100', 10000);
        $sedexContrato = collect($comContrato)->firstWhere('id', 'correios_sedex');

        $this->assertLessThan($sedexBalcao['valor_cents'], $sedexContrato['valor_cents']);
    }

    public function test_calculo_frete_inclui_mercado_envios_quando_ativo(): void
    {
        Setting::set('correios_ativo', '1');
        Setting::set('mercado_livre_envio_ativo', '1');

        $opcoes = $this->correios->calcularFrete('35500-000', 20000);
        $ids = array_column($opcoes, 'id');

        $this->assertContains('mercado_livre', $ids);
    }

    public function test_endpoint_publico_calcular_frete_retorna_json(): void
    {
        $response = $this->postJson('/loja/frete/calcular', [
            'cep' => '01310100',
        ]);

        $response->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonStructure([
                'sucesso',
                'cep',
                'subtotal',
                'opcoes',
            ]);
    }

    public function test_pagina_publica_de_rastreamento_carrega_formulario(): void
    {
        $this->get('/rastreamento')->assertOk()
            ->assertSee('Rastreamento de Encomendas')
            ->assertSee('Rastrear Agora');
    }

    public function test_pagina_publica_de_rastreamento_com_codigo_exibe_detalhes_e_eventos(): void
    {
        $this->get('/rastreamento/QB123456789BR')->assertOk()
            ->assertSee('QB123456789BR')
            ->assertSee('Histórico de Movimentações');
    }

    public function test_redirect_de_rastrear_para_rastreamento(): void
    {
        $this->get('/rastrear/QB123456789BR')
            ->assertRedirect(route('site.rastreio.show', ['codigo' => 'QB123456789BR']));
    }

    public function test_admin_salva_despacho_e_notifica_cliente_por_email(): void
    {
        Mail::fake();

        $pedido = Order::create([
            'nome' => 'Cliente Teste Frete',
            'email' => 'cliente.frete@exemplo.com.br',
            'telefone' => '31999998888',
            'tipo_pessoa' => 'fisica',
            'documento' => '12345678901',
            'entrega' => 'entrega',
            'endereco' => 'Rua das Flores, 100',
            'bairro' => 'Centro',
            'cidade' => 'Divinópolis',
            'uf' => 'MG',
            'cep' => '35500000',
            'subtotal_cents' => 15000,
            'frete_cents' => 2500,
            'desconto_cents' => 0,
            'total_cents' => 17500,
            'pagamento' => 'pix',
            'status' => 'pago',
            'consent_given' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.pedidos.rastreio', $pedido), [
                'transportadora' => 'correios_sedex',
                'codigo_rastreio' => 'SL123456789BR',
                'marcar_enviado' => '1',
                'notificar_cliente' => '1',
            ])
            ->assertRedirect();

        $pedido->refresh();

        $this->assertSame('correios_sedex', $pedido->transportadora);
        $this->assertSame('SL123456789BR', $pedido->codigo_rastreio);
        $this->assertSame('enviado', $pedido->status);
        $this->assertNotNull($pedido->rastreio_notificado_em);

        Mail::assertSent(PedidoEnviadoRastreio::class, function ($mail) use ($pedido) {
            return $mail->hasTo('cliente.frete@exemplo.com.br')
                && $mail->pedido->id === $pedido->id;
        });
    }

    public function test_admin_consulta_rastreio_em_tempo_real_via_json(): void
    {
        $pedido = Order::create([
            'nome' => 'Cliente Rastreio Real',
            'email' => 'cliente.real@exemplo.com.br',
            'telefone' => '31999998888',
            'tipo_pessoa' => 'fisica',
            'documento' => '12345678901',
            'entrega' => 'entrega',
            'endereco' => 'Rua das Flores, 100',
            'bairro' => 'Centro',
            'cidade' => 'Divinópolis',
            'uf' => 'MG',
            'cep' => '35500000',
            'subtotal_cents' => 15000,
            'frete_cents' => 2500,
            'desconto_cents' => 0,
            'total_cents' => 17500,
            'pagamento' => 'pix',
            'status' => 'enviado',
            'transportadora' => 'correios_sedex',
            'codigo_rastreio' => 'SL123456789BR',
            'consent_given' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.pedidos.rastreio.consultar', $pedido));

        $response->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('codigo', 'SL123456789BR')
            ->assertJsonStructure([
                'sucesso',
                'pedido',
                'transportadora',
                'codigo',
                'rastreio' => ['codigo', 'transportadora', 'status', 'eventos'],
            ]);
    }
}
