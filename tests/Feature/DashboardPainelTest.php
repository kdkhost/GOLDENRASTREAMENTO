<?php

namespace Tests\Feature;

use App\Models\Conversa;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Support\GuiaConfiguracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Comportamento do dashboard administrativo após a refatoração na paleta Golden:
 * faixa de estado operacional para admins, fila imediata de pendências, abas por
 * contexto, gavetas de ajuda `?` e ocultação de atalhos administrativos para vendedores.
 */
class DashboardPainelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
    }

    public function test_dashboard_admin_exibe_estado_operacional_abas_ajudas_e_pendencias_sem_azul(): void
    {
        Cache::flush();

        Setting::set('manutencao_ativa', '1');
        Setting::set('shield_ativo', '1');
        Setting::set('loja_ativa', '1');

        Lead::create([
            'name' => 'Lead Dashboard Teste',
            'phone' => '11988887777',
            'status' => 'novo',
        ]);

        Order::create([
            'nome' => 'Cliente Envio Teste',
            'telefone' => '11977776666',
            'status' => 'enviado',
            'codigo_rastreio' => 'BR999888777BR',
            'transportadora' => 'correios',
            'total_cents' => 34900,
            'consent_given' => true,
        ]);

        $cliente = Customer::create([
            'nome' => 'Maria Compradora Teste',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '11966665555',
            'email' => 'maria.dashboard@exemplo.com.br',
            'status' => 'ativo',
        ]);
        Ticket::create([
            'customer_id' => $cliente->id,
            'assunto' => 'Dúvida sobre despacho no painel',
            'canal' => 'portal',
            'status' => 'aberto',
            'prioridade' => 'normal',
        ]);

        $conversa = Conversa::abrir('site');
        $conversa->escalar('sem_fonte');

        $resposta = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $resposta->assertOk();

        $resposta
            ->assertSee('Estado operacional')
            ->assertSee('Manutenção Ativa (503)')
            ->assertSee('Loja Virtual Ativa')
            ->assertSee('Escudo Militar WAF Ativo')
            ->assertSee('data-abas="dashboard"', false)
            ->assertSee('data-aba="comercial"', false)
            ->assertSee('data-aba="pedidos"', false)
            ->assertSee('data-aba="campanhas"', false)
            ->assertSee('Chamados abertos')
            ->assertSee('Conversas aguardando equipe')
            ->assertSee('BR999888777BR')
            ->assertDontSee('tone="blue"', false)
            ->assertDontSee('tone-blue', false)
            ->assertDontSee('text-tone-blue', false)
            ->assertDontSee('fb-blue', false);

        foreach ([
            'dashboard_grafico_leads',
            'dashboard_para_fazer',
            'dashboard_funil',
            'dashboard_leads_recentes',
            'dashboard_pedidos_recentes',
            'dashboard_campanhas',
            'dashboard_modulos',
        ] as $chaveAjuda) {
            $idAjuda = GuiaConfiguracao::id($chaveAjuda);
            $resposta
                ->assertSee('data-ajuda="'.$idAjuda.'"', false)
                ->assertSee('data-ajuda-corpo="'.$idAjuda.'"', false);
        }
    }

    public function test_dashboard_vendedor_oculta_controles_exclusivos_de_admin(): void
    {
        Cache::flush();

        $resposta = $this->actingAs($this->vendedor)->get(route('admin.dashboard'));
        $resposta->assertOk();

        $resposta
            ->assertDontSee('Estado operacional')
            ->assertDontSee('Produtos ativos')
            ->assertDontSee('Páginas no ar')
            ->assertDontSee('Gerenciar loja')
            ->assertDontSee(route('admin.catalogo.produtos.index'), false)
            ->assertDontSee(route('admin.conteudo.paginas.index'), false)
            ->assertSee('Para fazer agora')
            ->assertSee('data-abas="dashboard"', false);
    }
}
