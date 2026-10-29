<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Gravar sem recarregar.
 *
 * O painel sempre gravou por POST de formulário e devolveu um redirect: a tela ia embora e voltava
 * do zero — o scroll pulava, a aba fechava, o toast só aparecia depois da volta. A camada nova
 * (App\Http\Middleware\ResponderEscritaAjax + public/js/golden-salvar.js) troca isso por JSON, e o
 * que estas provas fecham é o contrato entre as duas metades: o que o navegador recebe continua
 * sendo redirect, o que chega para o segundo plano é mensagem, campo, destino e a marca de ser a
 * mesma tela. Sem esse contrato o script ficaria cego — e sem as marcas nas views a tela gravaria
 * certa e mostraria o valor velho.
 */
class SalvarEmSegundoPlanoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Os cabeçalhos fixos que o public/js/golden-salvar.js manda em todo envio; `X-Golden-Tela` é
     *  o que muda de formulário para formulário, e por isso só aparece onde importa. */
    private const CABECALHOS = [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_a_escrita_pedida_em_segundo_plano_devolve_JSON_em_vez_de_redirect(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['_aba' => 'medicao', 'ga_measurement_id' => 'G-NOVO'], self::CABECALHOS)
            ->assertOk()
            ->assertJsonStructure(['ok', 'mensagens', 'campos', 'mesma_tela', 'redirecionar'])
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mesma_tela', true)
            ->assertJsonPath('redirecionar', null)
            ->assertJsonPath('campos', [])
            ->assertJsonPath('mensagens.0.tipo', 'sucesso')
            ->assertJsonPath('mensagens.0.texto', 'Medição e consentimento salvos.');

        $this->assertSame('G-NOVO', Setting::get('ga_measurement_id'), 'Respondeu que salvou, mas não salvou nada.');
    }

    public function test_a_mesma_escrita_feita_pelo_navegador_continua_um_redirect_intacto(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['_aba' => 'medicao', 'ga_measurement_id' => 'G-NOVO'])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('success', 'Medição e consentimento salvos.');
    }

    public function test_o_recado_que_saiu_no_JSON_nao_reaparece_na_carga_seguinte(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();

        // Guardado na sessão, o recado explodiria em dois toasts: o deste envio e o da carga que a
        // troca de região acabou de fazer.
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['_aba' => 'medicao', 'ga_measurement_id' => 'G-NOVO'], self::CABECALHOS)
            ->assertOk()
            ->assertSessionMissing('success');

        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Medição e consentimento salvos.');
    }

    public function test_o_motivo_da_validacao_chegona_com_o_nome_do_campo(): void
    {
        // Quem usa `validate()` já sai como 422 antes de chegar aqui; o que esta camada traduz é o
        // controller que valida na mão e devolve a pessoa para a mesma tela com a bolsa de erros.
        $this->get(route('login'))->assertOk();

        $this->post(route('login'), ['email' => 'admin@golden.com.br', 'password' => 'senha-que-nao-bate'], self::CABECALHOS)
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('campos.email.0', 'Credenciais inválidas ou conta inativa.');
    }

    public function test_a_escrita_que_muda_de_tela_diz_para_onde_ir(): void
    {
        $campanha = Campaign::create(['name' => 'Black Novembro Frota', 'source' => 'google', 'medium' => 'cpc']);

        // Quem exclui volta para `back()`, e o `back()` é a última tela aberta: sem este GET o
        // destino seria a raiz do site, e a prova estaria conferindo o atalho do Laravel, não o
        // destino que a pessoa estava vendo.
        $this->actingAs($this->admin)->get(route('admin.campanhas.index'))->assertOk();

        $this->actingAs($this->admin)
            ->delete(route('admin.campanhas.destroy', $campanha), [], self::CABECALHOS)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mesma_tela', false)
            ->assertJsonPath('redirecionar', route('admin.campanhas.index'))
            ->assertJsonPath('mensagens.0.texto', 'Campanha excluída.');

        $this->assertNull(Campaign::find($campanha->id), 'Respondeu que excluiu, mas a campanha continua no banco.');
    }

    public function test_quem_cria_nao_continua_diante_do_formulario_vazio(): void
    {
        // O formulário de criação mora em /campanhas/nova e grava em /campanhas, que é o endereço
        // para onde o controller manda depois. Comparado com a requisição, isso pareceria "a mesma
        // tela" e o navegador ficaria parado no formulário vazio; a tela vem no cabeçalho que o
        // public/js/golden-salvar.js manda, e é contra ela que o destino é medido.
        $this->actingAs($this->admin)
            ->post(route('admin.campanhas.store'), [
                'name' => 'Prova Segundo Plano', 'channel' => 'anuncio', 'source' => 'google', 'medium' => 'cpc',
            ], self::CABECALHOS + ['X-Golden-Tela' => route('admin.campanhas.create')])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mesma_tela', false)
            ->assertJsonPath('redirecionar', route('admin.campanhas.index'))
            ->assertJsonPath('mensagens.0.texto', 'Campanha criada.');

        $this->assertNotNull(Campaign::where('name', 'Prova Segundo Plano')->first());
    }

    public function test_a_tela_que_o_servidor_nao_informa_continua_sendo_a_da_requisicao(): void
    {
        // Nem todo POST vem do navegador com o cabeçalho da tela: um `back()` de quem não o manda
        // ainda precisa ser lido como "volta para onde a pessoa já estava", sem trocar de página.
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), ['_aba' => 'medicao', 'ga_measurement_id' => 'G-SEM-TELA'], self::CABECALHOS)
            ->assertOk()
            ->assertJsonPath('mesma_tela', true)
            ->assertJsonPath('redirecionar', null);
    }

    public function test_a_chave_que_a_tela_le_depois_do_envio_continua_na_sessao(): void
    {
        // A sonda não é toast: é o pacote que o card do financeiro desenha. Expulsa da sessão junto
        // com os avisos, a região trocada em segundo plano chegaria sem o resultado do teste.
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.whmcs.sondar'), ['sonda_email' => 'maria@exemplo.com.br'], self::CABECALHOS)
            ->assertOk()
            ->assertJsonPath('mensagens.0.tipo', 'erro');

        $this->assertIsArray(session('sonda'), 'A carga de fundo não teria mais o que mostrar no card.');
        $this->assertSame('sem_credencial', session('sonda')['estado']);
    }

    public function test_o_portal_recebe_a_mesma_folha_e_a_sua_rota_de_entrada(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('js/golden-salvar.js', $html, 'A tela grava por POST e ninguém intercepta.');
        $this->assertStringContainsString('name="golden-entrada" content="'.route('login').'"', $html);

        $cliente = Customer::create([
            'nome' => 'Maria Conta', 'email' => 'maria@exemplo.com.br',
            'email_portal' => 'maria@exemplo.com.br', 'status' => 'ativo',
        ]);
        $cliente->password = 'senha-portal-123';
        $cliente->save();

        $portal = $this->actingAs($cliente, 'cliente')->get(route('portal.dados'))->assertOk()->getContent();
        $this->assertStringContainsString('js/golden-salvar.js', $portal, 'O portal grava por POST e ninguém intercepta.');
        $this->assertStringContainsString('name="golden-entrada" content="'.route('portal.login').'"', $portal);
    }

    public function test_o_que_muda_a_tela_declara_a_regiao_e_o_que_escapa_pediu_para_fora_da_fila(): void
    {
        // Sem a região declarada, a tela gravaria e continuaria mostrando o valor velho. Sem a
        // saída, um formulário do Livewire seria interceptado com o DOM trocado por baixo do
        // componente que manda nele.
        $contratos = [
            ['admin/settings/index', 'admin.settings.update', '.app-content'],
            ['admin/settings/index', 'admin.settings.whmcs.sondar', '.app-content'],
            ['admin/settings/index', 'admin.settings.email.sondar', '.app-content'],
            ['admin/settings/index', 'admin.settings.chaves', '.app-content'],
            ['admin/settings/index', 'admin.settings.whatsapp', '.app-content'],
            ['admin/settings/index', 'admin.settings.loja', '.app-content'],
            ['admin/crons/index', 'admin.crons.toggle', '.app-content'],
            ['admin/crons/index', 'admin.crons.executar', '.app-content'],
            ['admin/crons/show', 'admin.crons.toggle', '.app-content-header'],
            ['admin/campaigns/index', 'admin.campanhas.toggle', '.app-content'],
            ['admin/funil/index', 'admin.funil.toggle', '.app-content'],
            ['admin/funil/show', 'admin.funil.toggle', '.app-content-header'],
            ['admin/leads/show', 'admin.leads.status', '.app-content'],
            ['admin/leads/show', 'admin.leads.nota', '.app-content'],
            ['admin/customers/show', 'admin.clientes.revincular', '.app-content'],
            ['admin/customers/show', 'admin.clientes.interacao', '.app-content'],
            ['admin/customers/show', 'admin.clientes.portal.entrar', 'fora-da-fila'],
            ['admin/orders/show', 'admin.pedidos.status', '.app-content'],
            ['admin/orders/show', 'admin.pedidos.documentos', '.app-content'],
            ['admin/tickets/show', 'admin.chamados.responder', '.app-content'],
            ['admin/tickets/show', 'admin.chamados.status', '.app-content'],
            ['admin/notificacoes/index', 'admin.notificacoes.lidas', '.app-content'],
            ['admin/products/estoque', 'admin.catalogo.produtos.estoque', '.app-content'],
            ['admin/testimonials/index', 'admin.conteudo.depoimentos.importar', '.app-content'],
            ['admin/assistente/conversas/show', 'admin.assistente.conversas.responder', '.app-content'],
            ['admin/assistente/conversas/show', 'admin.assistente.conversas.status', '.app-content'],
            ['admin/assistente/conhecimento/index', 'admin.assistente.conhecimento.semear', '.app-content'],
            ['portal/chamado', 'portal.chamados.responder', '.app-content'],
            ['livewire/google-ads-dashboard', 'admin.google-ads.campanhas.status', 'fora-da-fila'],
        ];

        foreach ($contratos as [$tela, $roteiro, $regiao]) {
            $abertura = $this->aberturaDeFormulario($tela, $roteiro);

            if ($regiao === 'fora-da-fila') {
                $this->assertStringContainsString('data-sem-ajax', $abertura,
                    "O formulário de {$roteiro} precisa do navegador de verdade e não avisou a camada de segundo plano.");

                continue;
            }

            $this->assertMatchesRegularExpression(
                '/data-atualiza="[^"]*'.preg_quote($regiao, '/').'[^"]*"/',
                $abertura,
                "O formulário de {$roteiro} grava e a tela continua dizendo o valor velho: falta a região {$regiao}."
            );
        }
    }

    public function test_excluir_ja_nasce_com_a_listagem_no_lugar(): void
    {
        // Todo botão de excluir do painel sai do mesmo componente, então a região trocada é a
        // escolha dele: sem um padrão declarado, cada listagem teria que repetir o atributo à mão
        // — e a que esquecesse mostraria a linha apagada até a pessoa apertar F5.
        $fonte = (string) file_get_contents(resource_path('views/components/admin/delete.blade.php'));

        $this->assertMatchesRegularExpression(
            "/'atualiza'\s*=>\s*'\.app-content'/",
            $fonte,
            'O componente de exclusão perdeu o padrão da região trocada.'
        );
        $this->assertStringContainsString('data-atualiza="{{ $atualiza }}"', $fonte,
            'A exclusão não repassa mais a região para o formulário.');
    }

    public function test_toda_regiao_de_salvar_e_uma_das_duas_que_o_painel_troca(): void
    {
        // O evento `golden:trocou` reanima abas e envios de arquivo; uma região inventada trocaria
        // um pedaço de tela que ninguém sabe remontar depois.
        $declaradas = [];

        foreach (File::allFiles(resource_path('views')) as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/data-atualiza="([^"]*)"/', $arquivo->getContents(), $achados);

            foreach ($achados[1] as $valor) {
                if (str_contains($valor, '{{')) {
                    // Um componente que repassa o prop não declara nada: a região é escolhida pela
                    // tela que o usa, e esta varredura já confere as telas.
                    continue;
                }

                foreach (explode(',', $valor) as $regiao) {
                    $declaradas[] = [$arquivo->getFilename(), trim($regiao)];
                }
            }
        }

        $this->assertNotEmpty($declaradas, 'Nenhuma região declarada em lugar nenhum — a varredura está cega.');

        foreach ($declaradas as [$arquivo, $regiao]) {
            $this->assertContains($regiao, ['.app-content', '.app-content-header'],
                "A tela {$arquivo} declarou a região '{$regiao}', que o segundo plano não sabe trocar.");
        }
    }

    /** A abertura de `<form>` que contém o roteiro, para conferir os atributos do envio. */
    private function aberturaDeFormulario(string $tela, string $roteiro): string
    {
        $fonte = (string) file_get_contents(resource_path('views/'.$tela.'.blade.php'));
        $pos = strpos($fonte, $roteiro);
        $this->assertNotFalse($pos, "A tela {$tela} não tem o formulário de {$roteiro}.");

        $inicio = strrpos(substr($fonte, 0, $pos), '<form');
        $this->assertNotFalse($inicio, "{$roteiro} não está dentro de um <form> na tela {$tela}.");

        return substr($fonte, $inicio, strpos($fonte, '>', $inicio) - $inicio);
    }
}
