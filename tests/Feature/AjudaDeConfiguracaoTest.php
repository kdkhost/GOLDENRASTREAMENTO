<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ChavesDeIntegracao;
use App\Support\GuiaConfiguracao;
use App\Support\GuiaPainel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A ajuda de cada item configurável é texto, e texto que apodrece em silêncio: o token mudou de
 * lugar, a rota do painel foi renomeada, o passo esqueceu uma tela — e o ícone continua abrindo
 * uma gaveta bonita dizendo coisa falsa. Estes testes não conferem prosa; conferem se a prosa ainda
 * fala do sistema que existe, se todo campo do painel tem quem explique, e se o que explica cabe
 * dentro do ícone em vez de despejado na tela.
 */
class AjudaDeConfiguracaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_todo_grupo_e_todo_campo_de_integracao_tem_ajuda(): void
    {
        foreach (ChavesDeIntegracao::grupos() as $id => $grupo) {
            $estaGaveta = GuiaConfiguracao::gaveta($id, $grupo['rotulo']);
            $this->assertNotNull($estaGaveta, "O grupo de chaves '{$id}' abriu a aba sem ícone de ajuda.");

            foreach ($grupo['campos'] as $campo) {
                $chave = $campo['chave'];
                $gaveta = GuiaConfiguracao::gaveta($chave, $campo['label']);

                $this->assertNotNull($gaveta, "O campo '{$chave}' está na tela e ninguém explica o que ele é.");
                $this->assertSame(GuiaConfiguracao::id($chave), $gaveta['id'],
                    "A gaveta de '{$chave}' chegou com id diferente do botão — o clique não teria corpo para copiar.");

                $tipos = array_column($gaveta['blocos'], 'tipo');
                $this->assertSame('texto', $tipos[0] ?? '', "A gaveta de '{$chave}' não abre dizendo o que o item é.");
                $this->assertContains('passos', $tipos, "A gaveta de '{$chave}' não tem ordem para seguir.");

                foreach ($gaveta['blocos'] as $bloco) {
                    if ($bloco['tipo'] !== 'passos') {
                        continue;
                    }

                    foreach ($bloco['itens'] ?? [] as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $item['icone'] ?? '',
                            "Um passo de '{$chave}' ficou sem ícone — sem número, é o ícone que o organiza.");
                        $this->assertNotEmpty($item['titulo'] ?? null, "Um passo de '{$chave}' não tem título.");
                        $this->assertNotEmpty($item['texto'] ?? null, "Um passo de '{$chave}' não explica nada.");
                    }
                }
            }
        }
    }

    /**
     * O pedido da casa: dizer também **onde** se busca cada credencial. Segredo sem endereço é
     * meio passo a passo — a pessoa chega na tela sabendo o que colar, e não sabe onde nasceu.
     */
    public function test_toda_credencial_aponta_para_onde_elas_nasce(): void
    {
        foreach (ChavesDeIntegracao::grupos() as $grupo) {
            foreach ($grupo['campos'] as $campo) {
                if (! ($campo['segredo'] ?? false)) {
                    continue;
                }

                $blocos = GuiaConfiguracao::gaveta($campo['chave'], $campo['label'])['blocos'];
                $links = collect($blocos)->firstWhere('tipo', 'links')['itens'] ?? [];

                $this->assertNotEmpty($links,
                    "A credencial '{$campo['chave']}' é segredo e a gaveta não diz onde ela nasce.");

                // O segredo nasce fora da casa: tem que haver ao menos um endereço https da fonte na
                // gaveta. Link de rota interna é atalho para a tela do painel, não é fonte de credencial.
                $this->assertNotEmpty(
                    array_filter($links, fn ($link) => str_starts_with((string) $link['url'], 'https://')),
                    "A gaveta de '{$campo['chave']}' só aponta para telas do painel — falta a página onde o segredo é criado.");

                $casa = rtrim((string) config('app.url'), '/');

                foreach ($links as $link) {
                    $url = (string) $link['url'];

                    // Atalho para uma tela do próprio painel obedece ao endereço desta instalação.
                    if ($casa !== '' && str_starts_with($url, $casa)) {
                        continue;
                    }

                    $this->assertMatchesRegularExpression('#^https://#', $url,
                        "O endereço sugerido por '{$campo['chave']}' não é https: {$url}");
                }
            }
        }
    }

    public function test_todo_endereco_sugerido_existe_ou_sai_da_gaveta(): void
    {
        foreach (GuiaConfiguracao::itens() as $chave => $item) {
            foreach ($item['links'] ?? [] as $link) {
                $this->assertNotEmpty($link['rotulo'] ?? null, "Um link de '{$chave}' está sem rótulo — seria um botão vazio.");

                if (isset($link['rota'])) {
                    $this->assertTrue(Route::has($link['rota']),
                        "O link de '{$chave}' aponta para a rota {$link['rota']}, que não existe mais.");

                    continue;
                }

                $url = (string) ($link['url'] ?? '');
                $this->assertMatchesRegularExpression('#^(https://|\{[a-z]+\})#', $url,
                    "O link de '{$chave}' não é https nem marcador da casa: {$url}.");

                // O marcador que depende de algo ainda não informado derruba o link — e aqui o
                // painel tem tudo informado, então nenhum endereço pode chegar à tela com chave.
                $resolvidos = collect(GuiaConfiguracao::gaveta($chave)['blocos'])
                    ->firstWhere('tipo', 'links')['itens'] ?? [];

                foreach ($resolvidos as $linkResolvido) {
                    $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', (string) ($linkResolvido['url'] ?? ''),
                        "A gaveta de '{$chave}' mostrou um endereço com marcador por resolver.");
                }
            }
        }
    }

    public function test_os_passos_da_ajuda_continuam_sem_numero(): void
    {
        foreach (GuiaConfiguracao::itens() as $chave => $item) {
            foreach ($item['passos'] ?? [] as $passo) {
                $this->assertDoesNotMatchRegularExpression('/^\s*(\d+[\.\)]|0\d|passo\s*\d+)\s/i', $passo['titulo'] ?? '',
                    "A ajuda de '{$chave}' voltou a começar um passo com número.");
            }
        }
    }

    /**
     * O desenho que a tela pediu: o ícone, não o parágrafo. A ajuda mora dentro do `<template>`,
     * que o navegador não desenha, e some com o texto explícito que ocupava lugar no cabeçalho.
     */
    public function test_a_tela_de_configuracoes_oferece_a_ajuda_sem_despeja_la_na_tela(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Cada tipo de configuração na sua aba', $html,
            'A tela voltou a explicar as próprias abas no cabeçalho, em texto que ninguém lê.');

        $botoes = $this->contar($html, '/class="ajuda-botao"/');
        $this->assertGreaterThan(40, $botoes, 'A tela perdeu os ícones de ajuda item por item.');

        // Botão sem corpo é gaveta aberta vazia: todo `data-ajuda` precisa ter o `<template>` dele.
        preg_match_all('/data-ajuda="(ajuda-[a-z0-9-]+)"/i', $html, $chamados);

        foreach (array_unique($chamados[1]) as $id) {
            $this->assertStringContainsString('data-ajuda-corpo="'.$id.'"', $html,
                "O ícone '{$id}' abriu a gaveta sem conteúdo nenhum.");
        }

        // A ajuda não ocupa linha nenhuma enquanto ninguém a pede: tudo que ela desenha está dentro
        // de um `<template>`, e o corpo da gaveta começa vazio.
        $this->assertMatchesRegularExpression('/<div class="ajuda-corpo" id="golden-ajuda-corpo"><\/div>/', $html);

        $this->assertStringContainsString('id="golden-ajuda"', $html);
        $this->assertStringContainsString('js/golden-ajuda.js', $html);
        $this->assertStringContainsString('css/golden-guia.css', $html);
    }

    /**
     * Um botão dentro de um formulário é um botão que envia o formulário. A ajuda tem de abrir a
     * gaveta e nada mais — por isso o `type="button"` é contrato, não detalhe de markup.
     */
    public function test_o_icone_da_ajuda_nunca_envia_o_formulario(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame($this->contar($html, '/class="ajuda-botao"/'),
            $this->contar($html, '/<button type="button"\s+class="ajuda-botao"/'),
            'Um ícone de ajuda voltou a ser botão de enviar o formulário.');
        $this->assertStringNotContainsString('<a class="guia-chamada', $html);
    }

    public function test_o_texto_da_ajuda_sai_em_portugues_e_sem_marca_crua(): void
    {
        $textos = [];

        foreach (GuiaConfiguracao::itens() as $item) {
            foreach (['onde', 'nota'] as $campo) {
                if (! empty($item[$campo])) {
                    $textos[] = $item[$campo];
                }
            }

            foreach ($item['passos'] ?? [] as $passo) {
                $textos[] = ($passo['titulo'] ?? '').' '.($passo['texto'] ?? '');
            }

            foreach ($item['links'] ?? [] as $link) {
                $textos[] = $link['rotulo'] ?? '';
            }
        }

        $this->assertNotEmpty($textos);
        $this->assertMatchesRegularExpression('/[ãçõêí]/u', implode(' ', $textos), 'A ajuda perdeu os acentos.');

        foreach ($textos as $texto) {
            $desenhado = GuiaPainel::inline($texto);

            // O que sobra de asterisco ou crase depois do `inline()` é marcador que a tela mostraria
            // cru — ou marca que veio de fora, que é o que não pode entrar.
            $this->assertStringNotContainsString('**', $desenhado, "A ajuda mostrou o asterisco em vez do negrito: {$texto}");
            $this->assertDoesNotMatchRegularExpression('/(?<!\*)\*(?!\*)/u', $desenhado, "Marca de um asterisco só na ajuda: {$texto}");
            $this->assertDoesNotMatchRegularExpression('/\b(the|and|with|click|here)\b/i', $desenhado,
                "A ajuda mostrou uma frase em outro idioma: {$texto}");
        }
    }

    /**
     * O endereço da caixa de conversa e o do financeiro nascem do que o painel já sabe. Sem esse
     * valor, o botão some da gaveta: link que não leva a lugar nenhum é pior do que link ausente.
     */
    public function test_o_link_que_depende_de_algo_ainda_nao_informado_nao_aparece(): void
    {
        config(['whmcs.url' => null]);

        $semFinanceiro = collect(GuiaConfiguracao::gaveta('whmcs')['blocos'])->firstWhere('tipo', 'links')['itens'] ?? [];
        $this->assertNotEmpty($semFinanceiro, 'A gaveta do WHMCS perdeu os links que independem do endereço.');

        foreach ($semFinanceiro as $link) {
            $this->assertStringNotContainsString('configapi.php', $link['url'],
                'A gaveta ofereceu a tela de API de um financeiro que ninguém informou.');
        }

        config(['whmcs.url' => 'https://financeiro.example.com.br/']);

        $comFinanceiro = collect(GuiaConfiguracao::gaveta('whmcs')['blocos'])->firstWhere('tipo', 'links')['itens'] ?? [];
        $this->assertContains('https://financeiro.example.com.br/admin/configapi.php', array_column($comFinanceiro, 'url'),
            'Com o endereço preenchido, a tela de API do WHMCS tem de aparecer na gaveta.');
    }

    /** Item que ninguém documentou não mostra ícone — nem espaço vazio no lugar dele. */
    public function test_item_sem_ajuda_escrita_nao_mostra_nada(): void
    {
        $this->assertNull(GuiaConfiguracao::item('campo_que_ninguem_escreveu'));
        $this->assertNull(GuiaConfiguracao::gaveta('campo_que_ninguem_escreveu', 'Rótulo'));

        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-ajuda="ajuda-campo-que-ninguem-escreveu"', $html);
    }

    private function contar(string $html, string $padrao): int
    {
        preg_match_all($padrao, $html, $encontrados);

        return count($encontrados[0]);
    }
}
