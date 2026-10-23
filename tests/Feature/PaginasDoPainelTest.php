<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cada página GET do painel precisa abrir para o administrador. É o teste que pega view
 * com nome de rota errado, componente recebendo prop desconhecida ou controller que
 * esqueceu uma variável — nada disso aparece em php artisan view:cache.
 */
class PaginasDoPainelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_todas_as_paginas_do_painel_abrem_para_o_admin(): void
    {
        $produtos = Product::orderBy('id')->limit(2)->get();
        $umProduto = $produtos->first();

        // Chamado e contrato vivem sobre a ficha do CRM: sem um registro aberto, as
        // páginas de detalhe do atendimento não teriam o que mostrar.
        $cliente = Customer::firstOrFail();
        $chamado = Ticket::create([
            'customer_id' => $cliente->id,
            'assunto' => 'O rastreador parou de enviar',
            'canal' => 'portal',
            'status' => 'aberto',
            'prioridade' => 'normal',
        ]);
        TicketMessage::create(['ticket_id' => $chamado->id, 'customer_id' => $cliente->id, 'corpo' => 'Não recebo posição desde ontem.']);
        $contrato = Contract::create([
            'customer_id' => $cliente->id,
            'titulo' => 'Termo de adesão da plataforma',
            'tipo' => 'adesao',
            'status' => 'assinado',
        ]);

        // A jornada nasce de uma visita real: o site registra o acesso exatamente como
        // faria no navegador de um visitante vindo de uma conversa com a IA.
        $this->get('/?utm_source=chatgpt.com&utm_medium=ai-search&utm_campaign=teste')->assertOk();

        $parametros = [
            'admin.atribuicao.show' => ['visita' => Visit::latest('id')->firstOrFail()],
            'admin.campanhas.edit' => ['campaign' => Campaign::firstOrFail()],
            'admin.catalogo.categorias.edit' => ['category' => Category::firstOrFail()],
            'admin.catalogo.fornecedores.edit' => ['fornecedor' => Supplier::firstOrFail()],
            'admin.catalogo.produtos.edit' => ['produto' => $umProduto],
            'admin.catalogo.produtos.estoque.show' => ['produto' => $umProduto],
            'admin.chamados.show' => ['chamado' => $chamado],
            'admin.clientes.show' => ['cliente' => Customer::firstOrFail()],
            'admin.clientes.edit' => ['cliente' => Customer::firstOrFail()],
            'admin.contratos.edit' => ['contrato' => $contrato],
            'admin.conteudo.depoimentos.edit' => ['depoimento' => Testimonial::firstOrFail()],
            'admin.conteudo.faqs.edit' => ['faq' => Faq::firstOrFail()],
            'admin.conteudo.paginas.edit' => ['pagina' => Page::firstOrFail()],
            'admin.leads.show' => ['lead' => Lead::firstOrFail()],
            'admin.pedidos.show' => ['pedido' => Order::firstOrFail()],
            'admin.usuarios.edit' => ['user' => $this->admin],
        ];

        $this->actingAs($this->admin);
        $abertas = 0;

        foreach (Route::getRoutes() as $rota) {
            $nome = $rota->getName();

            if (! $nome || ! str_starts_with($nome, 'admin.') || ! in_array('GET', $rota->methods(), true) || $nome === 'admin.') {
                continue;
            }

            $url = route($nome, $parametros[$nome] ?? []);
            $resposta = $this->get($url);

            $this->assertSame(200, $resposta->getStatusCode(), "A página {$nome} ({$url}) não abriu.");
            $abertas++;
        }

        $this->assertGreaterThanOrEqual(41, $abertas, 'O painel tem menos páginas GET do que o esperado — algo sumiu do menu.');
    }

    public function test_as_paginas_de_lista_respeitam_os_filtros(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.leads.index', ['status' => 'novo', 'campanha' => Campaign::firstOrFail()->id, 'busca' => 'a']))->assertOk();
        $this->get(route('admin.clientes.index', ['status' => 'ativo', 'busca' => 'a']))->assertOk();
        $this->get(route('admin.pedidos.index', ['status' => 'pendente', 'busca' => 'GR']))->assertOk();
        $this->get(route('admin.catalogo.produtos.index', ['estoque' => 'baixo', 'categoria' => Category::firstOrFail()->id]))->assertOk();
        $this->get(route('admin.activities.index', ['acao' => 'lead_recebido']))->assertOk();
    }

    public function test_o_painel_do_vendedor_abre_sem_as_areas_restritas(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        $this->actingAs($vendedor);

        foreach (['admin/dashboard', 'admin/leads', 'admin/clientes', 'admin/campanhas', 'admin/pedidos'] as $pagina) {
            $this->get($pagina)->assertOk();
        }

        foreach (['admin/configuracoes', 'admin/usuarios', 'admin/catalogo/produtos', 'admin/conteudo/paginas', 'admin/auditoria'] as $pagina) {
            $this->get($pagina)->assertForbidden();
        }
    }
}
