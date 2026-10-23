<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * O menu do painel é uma árvore, não uma lista: cada página vive dentro de um grupo, o
 * grupo da página aberta é o único aberto, e o item ativo é a própria página. Estas três
 * regras são o que a view não dá para conferir olhando — sem elas o menu volta a ser a
 * lista plana em que Configurações, Auditoria e o catálogo apareciam soltos.
 */
class MenuDoPainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail());
    }

    public function test_toda_listagem_do_painel_tem_um_item_no_menu(): void
    {
        $prefixos = collect(config('golden.menu_painel'))
            ->flatMap(fn (array $g) => array_merge(
                [$g['prefixo'] ?? $g['rota'] ?? ''],
                collect($g['filhos'] ?? [])->map(fn (array $f) => $f['prefixo'] ?? $f['rota'])->all(),
            ))
            ->filter()
            ->values();

        $fora = collect(array_keys(Route::getRoutes()->getRoutesByName()))
            ->filter(fn (string $nome) => str_starts_with($nome, 'admin.') && str_ends_with($nome, '.index'))
            ->reject(fn (string $nome) => $prefixos->contains(fn (string $p) => str_starts_with($nome, $p)))
            ->values()
            ->all();

        $this->assertSame([], $fora, 'Rotas de listagem do painel que nenhum item do menu aponta.');
    }

    public function test_cada_grupo_tem_gatilho_de_arvore_e_submenu(): void
    {
        $grupos = $this->grupos($this->get('/admin/dashboard')->content());

        $this->assertCount(4, $grupos, 'Os quatro grupos do menu do administrador.');
        $this->assertSame(['Comercial', 'Vendas', 'Conteúdo do site', 'Sistema'], array_keys($grupos));

        foreach ($grupos as $titulo => $bloco) {
            $this->assertStringContainsString('data-lte-toggle="treeview"', $bloco, "O grupo {$titulo} não abre.");
            $this->assertStringContainsString('<ul class="nav nav-treeview">', $bloco, "O grupo {$titulo} não tem submenu.");
        }
    }

    public function test_a_pagina_aberta_abre_um_sozinho_o_seu_grupo_e_fica_marcada(): void
    {
        $grupos = $this->grupos($this->get('/admin/leads')->content());

        $abertos = array_keys(array_filter($grupos, fn (string $b) => str_contains($b, 'menu-open')));
        $this->assertSame(['Comercial'], $abertos, 'Mais de um grupo aberto na mesma página.');

        $this->assertMatchesRegularExpression('#<p>Leads\s*<span class="badge#', $grupos['Comercial']);
        $this->assertMatchesRegularExpression(
            '#<i[^>]*></i>\s*<p>Leads\b#',
            $grupos['Comercial'],
            'O item Leads não está marcado como a página aberta.'
        );
        $this->assertStringNotContainsString('nav-link active', $grupos['Vendas']);
        $this->assertStringNotContainsString('nav-link active', $grupos['Sistema']);
    }

    public function test_rota_interna_de_um_recurso_acende_o_item_do_grupo(): void
    {
        // /admin/leads/7 não é item de menu: quem acende é o pai pelo prefixo do recurso.
        $lead = Lead::firstOrFail();
        $grupos = $this->grupos($this->get('/admin/leads/'.$lead->id)->content());

        $this->assertSame(['Comercial'], array_keys(array_filter($grupos, fn (string $b) => str_contains($b, 'menu-open'))));
        $this->assertMatchesRegularExpression('#<i[^>]*></i>\s*<p>Leads\b#', $grupos['Comercial']);

        $produtos = $this->grupos($this->get('/admin/catalogo/produtos')->content());
        $this->assertSame(['Vendas'], array_keys(array_filter($produtos, fn (string $b) => str_contains($b, 'menu-open'))));
    }

    public function test_dashboard_fica_fora_de_grupo_e_nenhum_grupo_abre(): void
    {
        $html = $this->get('/admin/dashboard')->content();
        $grupos = $this->grupos($html);

        $this->assertStringNotContainsString('menu-open', $this->sidebar($html));
        $this->assertMatchesRegularExpression('#<a href="[^"]*admin/dashboard"\s+class="nav-link active"#', $this->sidebar($html));
        $this->assertArrayNotHasKey('Dashboard', $grupos, 'Dashboard não é grupo: é o link solto no topo.');
    }

    public function test_bolha_de_pendencias_fica_dentro_do_grupo(): void
    {
        Lead::where('status', 'novo')->update(['status' => 'contato']);
        $lead = Lead::firstOrFail();
        $lead->update(['status' => 'novo']);

        $grupos = $this->grupos($this->get('/admin/dashboard')->content());

        $this->assertMatchesRegularExpression('#<p>Leads\s*<span class="badge badge-menu tone-gold ms-auto">1</span>#', $grupos['Comercial']);
        $this->assertStringNotContainsString('menu-open', $grupos['Comercial'], 'Bolha não é motivo para o grupo abrir.');
    }

    public function test_vendedor_ve_so_os_grupos_de_operacao(): void
    {
        $this->actingAs(User::where('email', 'vendedor@golden.com.br')->firstOrFail());
        $grupos = $this->grupos($this->get('/admin/dashboard')->content());

        $this->assertSame(['Comercial', 'Vendas'], array_keys($grupos));
        $this->assertStringNotContainsString('Produtos e estoque', $grupos['Vendas']);
        $this->assertStringContainsString('<p>Pedidos', $grupos['Vendas']);
    }

    public function test_loja_desligada_avisa_no_item_sem_sair_do_grupo(): void
    {
        \App\Models\Setting::set('loja_ativa', '0');
        $grupos = $this->grupos($this->get('/admin/dashboard')->content());

        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*loja"\s+class="nav-link  opacity-50"#',
            $grupos['Vendas'],
            'A loja desligada precisa continuar no menu, esmaecida.'
        );
        $this->assertStringContainsString('<span class="badge badge-menu tone-neutral ms-auto">desligada</span>', $grupos['Vendas']);
    }

    /** @return array<string,string> título do grupo → bloco HTML do <li> correspondente */
    private function grupos(string $html): array
    {
        $partes = preg_split('#(<li class="nav-item has-child)#', $this->sidebar($html), -1, PREG_SPLIT_DELIM_CAPTURE);
        $grupos = [];

        for ($i = 1, $n = count($partes); $i < $n; $i += 2) {
            $bloco = $partes[$i].($partes[$i + 1] ?? '');
            preg_match('#<p>(.*?)<i class="nav-arrow#s', $bloco, $titulo);
            $grupos[trim($titulo[1] ?? '')] = $bloco;
        }

        return $grupos;
    }

    private function sidebar(string $html): string
    {
        $inicio = strpos($html, '<aside class="app-sidebar');
        $fim = strpos($html, '</aside>', $inicio);

        $this->assertNotFalse($inicio, 'A sidebar do painel não foi renderizada.');

        return substr($html, $inicio, $fim - $inicio);
    }
}
