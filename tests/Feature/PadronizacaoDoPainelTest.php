<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O painel tem uma régua só por domínio: quantos itens cabem numa página, que cor cada
 * status veste e quem pode excluir. Estes testes travam as três regras — se alguém voltar
 * a escolher um tamanho próprio, escrever cor na view ou oferecer um botão que a rota nega,
 * a barra fica vermelha em vez de virar bug para o cliente ver.
 */
class PadronizacaoDoPainelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_a_lista_para_na_pagina_definida_no_config(): void
    {
        $porPagina = config('golden.itens_por_pagina');

        for ($i = 1; $i <= $porPagina + 3; $i++) {
            Product::create([
                'nome' => 'Rastreador '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'preco_cents' => 9900,
            ]);
        }

        $html = $this->actingAs($this->admin)
            ->get(route('admin.catalogo.produtos.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame($porPagina, substr_count($html, 'table-thumb'),
            'A listagem de produtos mostrou outra quantidade por página que config(\'golden.itens_por_pagina\').');
        $this->assertStringContainsString('page=', $html, 'A lista cheia não terminou com rodapé de paginação.');
    }

    public function test_nenhuma_listagem_escolhe_tamanho_de_pagina_proprio(): void
    {
        $arquivos = glob(app_path('Http/Controllers/Admin/*.php'));
        $comPagina = 0;

        foreach ($arquivos as $arquivo) {
            $codigo = file_get_contents($arquivo);

            if (! str_contains($codigo, 'paginate(')) {
                continue;
            }

            $comPagina++;
            $this->assertStringContainsString('paginate(itens_por_pagina()', $codigo,
                basename($arquivo).' chama paginate() com número próprio em vez de itens_por_pagina().');
        }

        $this->assertGreaterThanOrEqual(12, $comPagina, 'Menos listagens paginadas do que o painel tem — algo voltou a mostrar tudo.');
    }

    public function test_cada_status_do_sistema_tem_cor_no_config(): void
    {
        $esperado = [
            'funil' => Lead::STATUSES,
            'cliente' => array_keys(Customer::STATUS),
            'pedido' => array_keys(Order::STATUS),
            'movimento' => array_keys(StockMovement::TIPOS),
        ];

        foreach ($esperado as $grupo => $valores) {
            foreach ($valores as $valor) {
                $tom = tom_por_status($grupo, $valor);

                $this->assertNotSame('neutral', $tom,
                    "O status '{$valor}' do grupo {$grupo} caiu no fallback: falta cor em config('golden.tons_status').");
                $this->assertStringContainsString(
                    "--t-{$tom}-bg",
                    file_get_contents(public_path('css/golden-theme.css')),
                    "O tom '{$tom}' não existe no tema: a view não tem onde buscá-lo."
                );
            }
        }
    }

    public function test_as_listagens_usam_o_selo_de_situacao(): void
    {
        $this->actingAs($this->admin);

        foreach (['admin.pedidos.index', 'admin.clientes.index', 'admin.catalogo.produtos.index', 'admin.usuarios.index'] as $rota) {
            $this->assertStringContainsString('badge-status', $this->get(route($rota))->assertOk()->getContent(),
                "{$rota} mostra situação sem o selo padronizado.");
        }
    }

    public function test_o_botao_de_excluir_so_aparece_para_quem_a_rota_destrói_deixa(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
        $exclusao = 'name="_method" value="DELETE"';

        $this->actingAs($vendedor);
        foreach (['admin.clientes.index', 'admin.pedidos.index', 'admin.leads.index'] as $rota) {
            $this->assertStringNotContainsString($exclusao, $this->get(route($rota))->assertOk()->getContent(),
                "{$rota} ofereceu exclusão a um papel que a rota não deixa excluir.");
        }

        $this->actingAs($this->admin);
        $this->assertStringContainsString($exclusao, $this->get(route('admin.clientes.index'))->assertOk()->getContent(),
            'O administrador deixou de ver o botão de excluir.');
    }
}
