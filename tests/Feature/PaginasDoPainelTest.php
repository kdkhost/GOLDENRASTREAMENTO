<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\FunnelPage;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Video;
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

        // A campanha de funil vem semeada: sem ela não haveria bloco nem contador para abrir.
        $funil = FunnelPage::firstOrFail();

        // O assistente também precisa de fila e de base: as duas telas de detalhe (a ficha do
        // que ele sabe e a conversa do visitante) não abrem sem registro.
        ConhecimentoItem::create([
            'titulo' => 'O que faço quando o aparelho fica sem sinal',
            'corpo' => 'Confira o chip e a alimentação; se persistir, abrimos chamado com a operadora.',
            'tags' => 'sinal, plataforma',
            'origem' => 'painel',
            'ativo' => true,
        ]);
        $conversa = Conversa::abrir('site', 'https://goldenrastreamento.com.br/planos');
        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'cliente',
            'tipo' => 'texto',
            'corpo' => 'Vocês instalam em caminhão?',
        ]);

        $parametros = [
            'admin.assistente.conhecimento.edit' => ['item' => ConhecimentoItem::firstOrFail()],
            'admin.assistente.conversas.show' => ['conversa' => $conversa],
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
            'admin.conteudo.videos.edit' => ['video' => Video::firstOrFail()],
            'admin.funil.blocos.create' => ['pagina' => $funil],
            'admin.funil.blocos.edit' => ['pagina' => $funil, 'bloco' => $funil->blocks()->firstOrFail()],
            'admin.funil.edit' => ['pagina' => $funil],
            'admin.funil.show' => ['pagina' => $funil],
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

    public function test_a_paginacao_e_do_painel_e_nao_do_framework(): void
    {
        // O view default do Laravel é o do Tailwind: dentro do Bootstrap ele sai como caixa
        // sem estilo, em inglês, e com um SVG de ícone sem largura — desenhado do tamanho da
        // tela. Um único view, registrado no provider, atende painel e portal.
        $this->actingAs($this->admin);

        $listas = [
            'leads' => route('admin.leads.index'),
            'vídeos' => route('admin.conteudo.videos.index'),
        ];

        foreach ($listas as $tela => $url) {
            $conteudo = $this->get($url)->assertOk()->getContent();

            foreach (['class="paginacao"', 'pagination pagination-sm', 'de <strong>', 'aria-label="Próxima página"'] as $esperado) {
                $this->assertStringContainsString($esperado, $conteudo, "A paginação não chegou na lista de {$tela}.");
            }

            foreach (['Showing', 'px-4 py-2', 'dark:bg-gray-800', 'text-gray-700'] as $rastro) {
                $this->assertStringNotContainsString($rastro, $conteudo, "A lista de {$tela} carregou a paginação '{$rastro}' do framework.");
            }
        }
    }

    public function test_o_painel_do_vendedor_abre_sem_as_areas_restritas(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        $this->actingAs($vendedor);

        foreach (['admin/dashboard', 'admin/leads', 'admin/clientes', 'admin/campanhas', 'admin/pedidos'] as $pagina) {
            $this->get($pagina)->assertOk();
        }

        // A caixa de entrada é de operação — quem atende é a equipe inteira, como no chamado.
        Conversa::abrir('site', 'https://goldenrastreamento.com.br/planos');
        $this->get('admin/assistente/conversas')->assertOk();

        foreach (['admin/configuracoes', 'admin/usuarios', 'admin/catalogo/produtos', 'admin/conteudo/paginas', 'admin/auditoria', 'admin/funil', 'admin/assistente', 'admin/assistente/conhecimento'] as $pagina) {
            $this->get($pagina)->assertForbidden();
        }
    }
}
