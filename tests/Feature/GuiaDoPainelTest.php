<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GuiaPainel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * O guia de uso do painel é texto que vira tela, e texto que apodrece em silêncio: a rota muda de
 * nome, o botão deixa de existir, o passo a passo esquece uma credencial, e a página continua
 * abrindo bonita dizendo coisa falsa. Estes testes não conferem prosa — conferem se a prosa ainda
 * fala do sistema que existe.
 */
class GuiaDoPainelTest extends TestCase
{
    use RefreshDatabase;

    /** Os tipos de bloco que `admin/guia/partials/blocos.blade.php` desenha. */
    private const TIPOS_DE_BLOCO = ['texto', 'passos', 'lista', 'codigo', 'tabela', 'aviso'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_o_guia_abre_para_quem_operaciona_o_painel(): void
    {
        foreach (['admin@golden.com.br', 'vendedor@golden.com.br'] as $email) {
            $estaTela = $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route('admin.guia.index'))
                ->assertOk();

            $estaTela->assertSee('Guia de uso')
                ->assertSee('Google Ads no painel')
                ->assertSee('Leads: do formulário ao cliente')
                ->assertSee('Procurar no guia');
        }
    }

    public function test_o_guia_aparece_na_barra_lateral_para_todos_os_papeis(): void
    {
        foreach (['admin@golden.com.br', 'vendedor@golden.com.br'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee('Guia de uso');
        }
    }

    public function test_toda_secao_tem_o_que_desenhar(): void
    {
        $ids = [];

        foreach (GuiaPainel::secoes() as $secao) {
            $id = $secao['id'] ?? null;
            $this->assertIsString($id, 'Uma seção do guia não tem id — o link do cabeçalho não teria âncora.');
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $id, "O id '{$id}' não serve como âncora.");
            $this->assertNotContains($id, $ids, "A seção '{$id}' aparece duas vezes no guia.");
            $ids[] = $id;

            $this->assertNotEmpty($secao['grupo'] ?? null, "A seção '{$id}' não tem grupo na coluna da esquerda.");
            $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $secao['icone'] ?? '', "A seção '{$id}' ficou sem ícone.");
            $this->assertNotEmpty($secao['titulo'] ?? null, "A seção '{$id}' não tem título.");
            $this->assertNotEmpty($secao['resumo'] ?? null, "A seção '{$id}' não tem resumo — abre a tela sem dizer o que é.");
            $this->assertNotEmpty($secao['blocos'] ?? [], "A seção '{$id}' chegou à tela sem conteúdo nenhum.");

            foreach ($secao['blocos'] as $bloco) {
                $this->assertContains($bloco['tipo'] ?? '?', self::TIPOS_DE_BLOCO,
                    "A seção '{$id}' usa o bloco '{$bloco['tipo']}', que nenhum partial desenha.");

                match ($bloco['tipo']) {
                    'texto', 'aviso' => $this->assertNotEmpty($bloco['conteudo'] ?? null, "Bloco vazio na seção '{$id}'."),
                    'lista' => $this->assertNotEmpty($bloco['itens'] ?? [], "Lista vazia na seção '{$id}'."),
                    'codigo' => $this->assertNotEmpty($bloco['linhas'] ?? [], "Código vazio na seção '{$id}'."),
                    'tabela' => $this->assertCount(
                        count($bloco['cabecalho']),
                        $bloco['linhas'][0],
                        "A tabela da seção '{$id}' tem linha com número de células diferente do cabeçalho."
                    ),
                    'passos' => $this->assertNotEmpty($bloco['itens'] ?? [], "Passo a passo vazio na seção '{$id}'."),
                    default => null,
                };

                foreach ($bloco['itens'] ?? [] as $passo) {
                    if (! is_array($passo)) {
                        continue;
                    }

                    $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $passo['icone'] ?? '',
                        "Um passo da seção '{$id}' ficou sem ícone — sem número, é o ícone que o organiza visualmente.");
                    $this->assertNotEmpty($passo['titulo'] ?? null, "Um passo da seção '{$id}' não tem título.");
                    $this->assertNotEmpty($passo['texto'] ?? null, "Um passo da seção '{$id}' não explica nada.");
                }
            }
        }
    }

    public function test_os_passos_do_guia_continuam_sem_numero(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.guia.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<ol', $html, 'O guia voltou a numerar os passos em lista ordenada.');

        foreach (GuiaPainel::secoes() as $secao) {
            foreach ($secao['blocos'] as $bloco) {
                foreach ($bloco['itens'] ?? [] as $passo) {
                    if (! is_array($passo)) {
                        continue;
                    }

                    $this->assertDoesNotMatchRegularExpression(
                        '/^\s*(\d+[\.\)]|0\d|passo\s*\d+)\s/i',
                        $passo['titulo'],
                        "A seção '{$secao['id']}' voltou a começar um passo com número."
                    );
                }
            }
        }
    }

    public function test_o_guia_aponta_para_telas_e_rotas_que_ainda_existem(): void
    {
        // Coleção associativa: `has()` pergunta pela chave — o nome da rota —, e `keys()` é o que
        // vira lista para procurar prefixo.
        $registradas = collect(Route::getRoutes()->getRoutesByName());

        foreach (GuiaPainel::secoes() as $secao) {
            if (! empty($secao['rota'])) {
                $this->assertTrue($registradas->has($secao['rota']),
                    "A seção '{$secao['id']}' oferece 'Abrir a tela' para {$secao['rota']}, que não existe mais.");
            }
        }

        foreach (config('guia_painel.rotas') as $prefixo => $id) {
            $this->assertNotNull(GuiaPainel::secao($id), "O mapa do guia aponta '{$prefixo}' para a seção inexistente '{$id}'.");
            $this->assertTrue($registradas->keys()->contains(fn (string $nome) => str_starts_with($nome, $prefixo)),
                "O mapa do guia conhece o prefixo '{$prefixo}', que nenhuma rota sua.");
        }
    }

    public function test_cada_item_do_menu_tem_secao_no_guia(): void
    {
        $prefixos = array_keys(config('guia_painel.rotas'));

        foreach (config('golden.menu_painel') as $grupo) {
            foreach (($grupo['filhos'] ?? []) + [$grupo] as $item) {
                $rota = $item['rota'] ?? null;

                if (! is_string($rota) || ! str_starts_with($rota, 'admin.') || $rota === 'admin.guia.index') {
                    continue;
                }

                $coberta = collect($prefixos)->contains(fn (string $p) => str_starts_with($rota, $p));
                $this->assertTrue($coberta, "O item '{$item['titulo']}' do menu não tem seção no guia.");
            }
        }
    }

    public function test_cada_tom_de_aviso_do_guia_existe_na_folha(): void
    {
        $folha = file_get_contents(base_path('public/css/golden-guia.css'));

        foreach (GuiaPainel::secoes() as $secao) {
            foreach ($secao['blocos'] as $bloco) {
                if (($bloco['tipo'] ?? '') !== 'aviso') {
                    continue;
                }

                $tom = $bloco['tom'] ?? 'amber';
                $this->assertStringContainsString('.guia-aviso--'.$tom, $folha,
                    "A seção '{$secao['id']}' pede o aviso '{$tom}', que não existe em golden-guia.css.");
            }
        }
    }

    public function test_a_linha_discreta_do_cabecalho_leva_a_secao_certa(): void
    {
        $destaTela = $this->actingAs(User::where('email', 'vendedor@golden.com.br')->firstOrFail())
            ->get(route('admin.leads.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('guia-chamada', $destaTela, 'A tela de Leads não oferece o guia.');
        $this->assertStringContainsString('#leads', $destaTela, 'O link de Leads não leva à seção de Leads.');

        // O Google Ads, que tem seção própria e uma dentro da outra, precisa cair cada um na sua.
        $criativo = $this->get(route('admin.google-ads.criativo'))->assertOk()->getContent();
        $this->assertStringContainsString('#criativo', $criativo);
        $this->assertStringNotContainsString('#google-ads"', $criativo);

        // E a própria tela do guia não se oferece.
        $guia = $this->get(route('admin.guia.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('guia-chamada mt-1', $guia);
    }

    /**
     * Consultar não pode custar a tela que estava aberta: o guia é a única página do painel que
     * abre em outra aba, tanto pelo item da barra lateral quanto pela linha do cabeçalho.
     */
    public function test_o_guia_abre_em_outra_aba(): void
    {
        $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail());

        $painel = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('admin.guia.index'), '/').'"[^>]*target="_blank" rel="noopener"/',
            $painel,
            'O Guia de uso da barra lateral voltou a abrir na mesma aba.'
        );

        $leads = $this->get(route('admin.leads.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/class="guia-chamada mt-1"[^>]*target="_blank" rel="noopener"/',
            $leads,
            'O "Como usar esta tela" voltou a levar a pessoa para fora da tela que ela estava preenchendo.'
        );
    }

    /**
     * O guia troca de seção no lugar: o índice manda, o painel da direita obedece, e a janela não
     * se mexe. O teste não roda JavaScript — ele trava o contrato que o script e a folha precisam
     * manter para o comportamento não regredir em silêncio.
     */
    public function test_o_guia_troca_a_secao_sem_rolar_a_pagina(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.guia.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-guia-conteudo', $html,
            'O painel da leitura perdeu o gancho que o script usa para trocar a seção.');
        $this->assertStringContainsString('data-alvo="', $html,
            'O índice perdeu o gancho que liga cada item à sua seção.');

        $script = file_get_contents(base_path('public/js/golden-guia.js'));
        $this->assertStringContainsString('preventDefault', $script,
            'O clique no índice voltou a seguir a âncora e a rolar a página.');
        $this->assertStringContainsString('replaceState', $script,
            'O endereço voltou a ser trocado por âncora; é a âncora que faz a janela pular.');

        $folha = file_get_contents(base_path('public/css/golden-guia.css'));
        $this->assertStringContainsString('data-pronta', $folha,
            'Sem a marca de pronto, as seções empilhadas aparecem antes do script escondê-las.');
        $this->assertStringContainsString('overflow-y:auto', $folha,
            'O painel da leitura perdeu a rolagem própria — a página inteira voltaria a rolar.');
    }

    public function test_o_guia_cita_cada_credencial_que_a_tela_pede(): void
    {
        $secao = GuiaPainel::secao('google-ads');
        // Sem JSON_UNESCAPED_SLASHES o json_encode escapa cada barra e a URI de callback
        // aparece como \/auth\/... — o teste reclamaria de um texto que está certo.
        $texto = json_encode($secao['blocos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach (array_keys(GoogleAdsService::CREDENCIAIS) as $nome) {
            $this->assertStringContainsString($nome, $texto,
                "A tela reclama de {$nome}, mas o guia ensina a preencher outra coisa.");
        }

        foreach (['GOOGLE_REDIRECT_URI', 'GOOGLE_ADS_LOGIN_CUSTOMER_ID', 'Chaves de integração', 'google-ads:sync'] as $essencial) {
            $this->assertStringContainsString($essencial, $texto, "O passo a passo do Google Ads esqueceu '{$essencial}'.");
        }

        // Quem administra pela tela não limpa configuração nem reinoda nada: salvar vale na hora.
        $this->assertStringNotContainsString('config:clear', $texto,
            'O guia do Google Ads ainda manda limpar a configuração — o painel dispensa isso.');

        $this->assertStringContainsString('/auth/google-ads/callback', $texto);
    }

    public function test_a_tela_do_google_ads_aponta_o_guia_em_vez_de_repiti_lo(): void
    {
        config(['google_ads.developer_token' => null, 'services.google_ads.client_id' => null, 'services.google_ads.client_secret' => null]);

        $html = $this->actingAs(User::where('email', 'vendedor@golden.com.br')->firstOrFail())
            ->get(route('admin.google-ads.index'))
            ->assertOk()
            ->getContent();

        // A tela de métrica não vira manual: o texto do guia mora na tela do guia, e o cabeçalho
        // leva até a seção certa. O cartão de abertura diz o que falta, sem despejar o passo a passo.
        $this->assertStringContainsString('Ligar o Google Ads', $html);
        $this->assertStringContainsString('Como usar esta tela', $html);
        $this->assertStringContainsString(route('admin.guia.index').'#google-ads', $html);
        $this->assertStringNotContainsString('Quatro passos que só existem no Google', $html);
        $this->assertStringNotContainsString('data-bs-toggle="collapse"', $html);
    }

    public function test_a_tela_carrega_a_folha_e_o_script_do_guia(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.guia.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('css/golden-guia.css', $html);
        $this->assertStringContainsString('js/golden-guia.js', $html);
        $this->assertStringContainsString('id="guia-busca"', $html);
        $this->assertStringContainsString('data-secao', $html);
    }

    public function test_o_texto_do_guia_sai_em_portugues_e_sem_marca_crua(): void
    {
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.guia.index'))
            ->assertOk()
            ->getContent();

        // O inline desenha `código` e **ênfase**; o que sobrar de marcador é texto que virou marca
        // por engano — ou marca que veio do config, que é o que não pode.
        $visivel = strip_tags($html);
        $this->assertStringNotContainsString('**', $visivel, 'O guia mostrou o asterisco em vez do negrito.');
        $this->assertStringNotContainsString('`', $visivel, 'O guia mostrou a crase em vez do código.');
        $this->assertMatchesRegularExpression('/[ãçõê]/u', $visivel, 'O guia perdeu os acentos.');

        // Ênfase de um asterisco só (`*assim*`) não é nada do que `inline()` desenha: chegaria à
        // tela como asterisco. O texto que passa por ele tem de usar `**` ou não usar marca.
        foreach (GuiaPainel::secoes() as $secao) {
            foreach ($this->textosFormataveis($secao) as $texto) {
                $this->assertDoesNotMatchRegularExpression('/(?<!\*)\*(?!\*)[^*\n]+(?<!\*)\*(?!\*)/u', $texto,
                    "A seção '{$secao['id']}' usou ênfase em um asterisco, que a tela mostraria cru.");
            }
        }
    }

    /**
     * Os textos que `GuiaPainel::inline()` formata, de cada seção. Os blocos de código ficam fora
     * de propósito: lá dentro o marcador é o comando, não ênfase.
     *
     * @param  array<string, mixed>  $secao
     * @return array<int, string>
     */
    private function textosFormataveis(array $secao): array
    {
        $textos = [$secao['titulo'], $secao['resumo']];

        foreach ($secao['blocos'] as $bloco) {
            if (($bloco['tipo'] ?? '') === 'codigo') {
                continue;
            }

            foreach (['conteudo', 'titulo'] as $chave) {
                if (isset($bloco[$chave])) {
                    $textos[] = $bloco[$chave];
                }
            }

            foreach ($bloco['itens'] ?? [] as $item) {
                $textos[] = is_string($item) ? $item : ($item['titulo'] ?? '').' '.($item['texto'] ?? '');
            }

            foreach ($bloco['linhas'] ?? [] as $linha) {
                foreach ((array) $linha as $celula) {
                    if (is_string($celula)) {
                        $textos[] = $celula;
                    }
                }
            }
        }

        return array_values(array_filter($textos, 'is_string'));
    }

    /**
     * A tela é uma tela do painel, então ela usa as peças do painel: um cartão por grupo, o campo
     * de busca compartilhado, e nada escrito à mão dentro da view. Cada classe utilitária solta
     * (`mb-0`, `fw-bold`, `text-body`) é uma decisão de desenho fora da folha — a mesma regra que
     * vale para as outras telas e que, somada, fazia o guia parecer outra aplicação.
     */
    public function test_a_tela_do_guia_e_montada_com_as_pecas_do_painel(): void
    {
        $fonteDaTela = file_get_contents(resource_path('views/admin/guia/index.blade.php'));
        $fonteDosBlocos = file_get_contents(resource_path('views/admin/guia/partials/blocos.blade.php'));

        $this->assertStringContainsString('<x-admin.filter', $fonteDaTela,
            'A busca do guia voltou a ser um <input> escrito na view.');
        $this->assertStringNotContainsString('<input', $fonteDaTela,
            'Campo desenhado à mão é campo que enjoa quando o padrão dos campos muda.');

        foreach (['mb-0', 'fw-bold', 'text-body', 'list-unstyled', 'align-middle', 'small text-secondary'] as $utilitaria) {
            $this->assertStringNotContainsString($utilitaria, $fonteDaTela,
                "A view do guia voltou a decidir espaçamento ou peso com a classe '{$utilitaria}'.");
            $this->assertStringNotContainsString($utilitaria, $fonteDosBlocos,
                "O partial de blocos voltou a decidir desenho com a classe '{$utilitaria}'.");
        }

        $grupos = array_keys(GuiaPainel::porGrupo());
        $html = $this->actingAs(User::where('email', 'admin@golden.com.br')->firstOrFail())
            ->get(route('admin.guia.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(count($grupos), substr_count($html, 'data-grupo="1"'),
            'Cada grupo do guia é um cartão; um a mais ou a menos é a tela fora do passo do config.');

        foreach ($grupos as $grupo) {
            $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', (string) GuiaPainel::iconeDoGrupo($grupo),
                "O cartão do grupo '{$grupo}' ficou sem ícone — seria o único cartão do painel sem.");
        }
    }

    /**
     * O que a arrumação custou a medir e não pode voltar: a primeira coluna da tabela presa em uma
     * linha só (empurrava o cartão para fora da tela estreita), o índice fixo mais alto que o
     * viewport (as últimas seções ficavam fora de alcance), e a medida de leitura espalhada bloco a
     * bloco em vez de um seletor único.
     */
    public function test_a_folha_e_o_script_do_guia_deixam_tudo_caber_na_tela(): void
    {
        $folha = file_get_contents(base_path('public/css/golden-guia.css'));

        $this->assertDoesNotMatchRegularExpression('/\.guia-tabela[^{]*\{[^}]*white-space\s*:\s*nowrap/', $folha,
            'A tabela do guia voltou a travar uma célula em linha única e a estourar o cartão no celular.');

        $this->assertStringContainsString('max-height:calc(100vh - 2rem)', $folha,
            'O índice fixo precisa caber na tela: sem altura máxima, ele rola o texto e nunca chega ao fim.');
        $this->assertMatchesRegularExpression('/\.guia-nav\{[^}]*overflow-y\s*:\s*auto/', $folha,
            'Quando o índice é maior que a tela, ele mesmo tem de rolar.');

        $this->assertSame(1, preg_match_all('/max-width\s*:\s*70ch/', $folha),
            'A medida de leitura é um seletor só; repeti-la é cada bloco escolher a própria largura.');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b/i', $folha,
            'Cor no guia vem de token de golden-theme.css, nunca de hexadecimal.');

        $script = file_get_contents(base_path('public/js/golden-guia.js'));
        $this->assertStringContainsString('[\u0300-\u036f]', $script,
            'A busca sem acento é o que faz "notificacao" encontrar "notificação".');
        $this->assertStringContainsString("querySelectorAll('[data-grupo]')", $script,
            'O grupo tem de sumir como cartão, não só as seções dele — cartão vazio com título é a tela pela metade.');
        $this->assertStringContainsString("querySelectorAll('[data-nav-grupo]')", $script,
            'O título do grupo no índice também sai quando não sobra seção nenhuma dele.');
    }
}
