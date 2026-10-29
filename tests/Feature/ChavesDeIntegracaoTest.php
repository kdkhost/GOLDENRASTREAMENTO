<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Assistente\Provedores\Fabrica;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As credenciais que o painel guarda no banco.
 *
 * A regra que estes testes fecham é a da casa: **o banco manda, o `.env` é plano de fundo**.
 * Cada um deles cobre uma das formas que essa regra tem de quebrar em produção — o valor do
 * ambiente que continua valendo depois de salvar na tela, o segredo que aparece no HTML ou na
 * linha do banco, o campo de segredo deixado em branco que apagaria a chave do Google só porque
 * alguém salvou o SMTP, e o formulário que gravaria por cima do ambiente sem ninguém querer.
 *
 * Em `setUp` a aplicação já bootou com o banco vazio, então `ChavesDeIntegracao::aplicar()` não
 * sobrepujou nada. Todo teste que grava chama `aplicar()` de novo: é exatamente o que a
 * requisição seguinte faz no servidor, e a única diferença entre os dois mundos.
 */
class ChavesDeIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_o_valor_gravado_no_banco_passa_na_frente_do_env(): void
    {
        config(['google_ads.developer_token' => 'token-do-ambiente']);

        ChavesDeIntegracao::guardar('google_ads_developer_token', 'token-do-painel');
        ChavesDeIntegracao::aplicar();

        $this->assertSame('token-do-painel', config('google_ads.developer_token'));
        $this->assertSame('banco', ChavesDeIntegracao::origem('google_ads_developer_token'));
    }

    public function test_o_que_o_banco_nao_tem_continua_vindo_do_env(): void
    {
        config(['services.google_ads.client_id' => 'id-do-ambiente']);

        $this->assertNull(ChavesDeIntegracao::doBanco('google_client_id'));
        $this->assertSame('id-do-ambiente', ChavesDeIntegracao::vigente('google_client_id'));
        $this->assertSame('ambiente', ChavesDeIntegracao::origem('google_client_id'));

        ChavesDeIntegracao::aplicar();
        $this->assertSame('id-do-ambiente', config('services.google_ads.client_id'));
    }

    public function test_campo_apagado_devolve_o_comando_ao_env(): void
    {
        config(['google_ads.login_customer_id' => '1234567890']);

        ChavesDeIntegracao::guardar('google_ads_login_customer_id', '9999999999');
        ChavesDeIntegracao::aplicar();
        $this->assertSame('9999999999', config('google_ads.login_customer_id'));

        // Vazio na tela não é "gravar vazio": é retirar o painel do caminho.
        ChavesDeIntegracao::guardar('google_ads_login_customer_id', '');
        $this->assertNull(ChavesDeIntegracao::doBanco('google_ads_login_customer_id'));
        $this->assertSame('ambiente', ChavesDeIntegracao::origem('google_ads_login_customer_id'));
    }

    public function test_toda_chave_aponta_para_um_config_que_existe(): void
    {
        foreach (ChavesDeIntegracao::campos() as $campo) {
            $pedacos = explode('.', $campo['config']);
            $folha = array_pop($pedacos);

            $this->assertArrayHasKey(
                $folha,
                (array) config(implode('.', $pedacos)),
                "A chave {$campo['chave']} aponta para {$campo['config']}, que não existe: sobrescrever um nome errado é gravar no nada."
            );
        }
    }

    public function test_segredo_sai_cifrado_do_banco_e_nunca_aparece_na_tela(): void
    {
        ChavesDeIntegracao::guardar('google_client_secret', 'segredo-supersecreto');

        $cru = (string) DB::table('settings')->where('key', 'google_client_secret')->value('value');

        $this->assertNotSame('segredo-supersecreto', $cru, 'O segredo foi gravado em texto puro.');
        $this->assertStringNotContainsString('segredo-supersecreto', $cru);
        $this->assertSame('segredo-supersecreto', ChavesDeIntegracao::doBanco('google_client_secret'));

        $html = $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('segredo-supersecreto', $html);
    }

    public function test_a_tela_mostra_o_nome_de_cada_chave_e_de_onde_elas_vem(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Chaves de integração')
            ->assertSee('Avaliações do Google')
            ->assertSee('E-mail de disparo')
            ->assertSee('GOOGLE_ADS_DEVELOPER_TOKEN', false)
            ->assertSee('MAIL_HOST', false)
            ->assertSee('vem do .env', false);
    }

    public function test_cada_campo_de_chave_ocupa_a_linha_inteira_do_cartao(): void
    {
        // Cada grupo é uma aba, e a aba é uma coluna só: campo dividido em meia coluna fazia a
        // dica e o nome da variável invadirem o vizinho — a tela ficava ilegível.
        foreach (ChavesDeIntegracao::campos() as $campo) {
            $this->assertArrayNotHasKey('col', $campo, "O campo {$campo['chave']} voltou a escolher a própria largura.");
        }

        $componente = file_get_contents(resource_path('views/components/admin/campo-chave.blade.php'));
        $this->assertStringContainsString('<div class="col-12">', $componente);

        $css = file_get_contents(public_path('css/admin-golden.css'));
        $this->assertStringContainsString('overflow-wrap:anywhere', $css, 'O nome longo de ambiente tem de quebrar dentro da linha.');
    }

    public function test_o_painel_grava_o_smtp_e_o_config_le_de_volta(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), [
                'grupo' => 'email',
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.kdkhost.com.br',
                'mail_port' => '465',
                'mail_scheme' => 'smtps',
                'mail_username' => 'disparos@goldenrastreamento.com.br',
                'mail_password' => 'senha-de-teste',
                'mail_from_address' => 'avisos@goldenrastreamento.com.br',
                'mail_from_name' => 'Golden Rastreamento',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        ChavesDeIntegracao::aplicar();

        $this->assertSame('smtp.kdkhost.com.br', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        // O seletor oferece as palavras de sempre da tela, mas o que chega no transporte tem que
        // ser o nome que o Symfony conhece: `ssl` ali dentro levanta `UnsupportedSchemeException`
        // e derruba cada disparo do sistema, do link de senha à segunda via.
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('senha-de-teste', config('mail.mailers.smtp.password'));
        $this->assertSame('avisos@goldenrastreamento.com.br', config('mail.from.address'));
    }

    public function test_a_criptografia_existe_no_nome_que_o_transporte_aceita(): void
    {
        // O `.env` do mundo fala `tls` e `ssl` — está em todo tutorial e em todo painel de
        // hospedagem. O transporte do Symfony só conhece `smtp` (STARTTLS) e `smtps` (SSL desde o
        // abraço), e um nome que ele não entende não é aviso: é a exceção que derruba o primeiro
        // disparo de verdade, do link de senha do portal à segunda via da fatura.
        $traducoes = [
            'tls' => 'smtp', 'starttls' => 'smtp', 'SMTP' => 'smtp',
            'ssl' => 'smtps', 'smtps' => 'smtps',
            '' => null, 'null' => null, 'nenhuma' => null, 'esqueci-de-novo' => null,
        ];

        foreach ($traducoes as $palavra => $esperado) {
            $this->assertSame($esperado, ChavesDeIntegracao::esquema($palavra), 'A palavra ['.$palavra.'] não foi traduzida.');
        }

        // E o config que a aplicação bootou tem que estar num desses nomes, nunca o que estava
        // escrito no `.env` — é a mesma porta que a tela passa quando grava.
        $declarado = config('mail.mailers.smtp.scheme');
        $this->assertTrue(
            $declarado === null || in_array($declarado, ['smtp', 'smtps'], true),
            'O transporte recebeu um nome de criptografia que ele não conhece: '.var_export($declarado, true)
        );
    }

    public function test_a_criptografia_ja_gravada_no_banco_tambem_sai_traduzida(): void
    {
        // A tradução do `.env` não bastava: o que a pessoa salva na tela entra no `config()` por
        // outro caminho, e uma instalação que gravou `ssl` antes desta regra continuaria com o
        // nome cru na linha — pronta para derrubar cada disparo sem que ninguém toque em nada.
        Setting::set('mail_scheme', 'ssl');
        ChavesDeIntegracao::aplicar();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'), 'O banco entregou a palavra velha cru ao transporte.');

        // E o campo mostra, no dia seguinte, o nome que o transporte vai usar, não o que foi
        // digitado: gravar `tls` pelo painel é dizer STARTTLS, que é `smtp`.
        ChavesDeIntegracao::guardar('mail_scheme', ' TLS ');
        $this->assertSame('smtp', ChavesDeIntegracao::doBanco('mail_scheme'));

        // Palavra que nada traduz não é recusada na cara: some do caminho e a porta decide.
        ChavesDeIntegracao::guardar('mail_scheme', 'criptografia-de-mentira');
        ChavesDeIntegracao::aplicar();
        $this->assertTrue(
            config('mail.mailers.smtp.scheme') === null || in_array(config('mail.mailers.smtp.scheme'), ['smtp', 'smtps'], true)
        );
    }

    /** Os dois ajustes numéricos do Google Ads: a tela é o lugar deles, e o cast volta inteiro. */
    public function test_o_painel_grava_os_numeros_do_google_ads_e_o_config_le_inteiro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), [
                'grupo' => 'google-ads',
                'google_ads_cache_minutos' => '5',
                'google_ads_dias' => '7',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        ChavesDeIntegracao::aplicar();

        $this->assertSame(5, config('google_ads.cache_minutos'));
        $this->assertSame(7, config('google_ads.dias_padrao'));
    }

    public function test_segredo_em_branco_sem_pedir_apagar_deixa_a_chave_onde_esta(): void
    {
        ChavesDeIntegracao::guardar('mail_password', 'primeira-senha');

        // A caixa de senha nasce vazia de propósito; salvar o resto do formulário não pode
        // apagar a chave que já estava guardada.
        $this->actingAs($this->admin)->post(route('admin.settings.chaves'), [
            'grupo' => 'email',
            'mail_host' => 'mail.seudominio.com.br',
            'mail_password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('primeira-senha', ChavesDeIntegracao::doBanco('mail_password'));

        // Apagar é ato explícito, e o que volta é o .env.
        config(['mail.mailers.smtp.password' => 'senha-do-ambiente']);

        $this->actingAs($this->admin)->post(route('admin.settings.chaves'), [
            'grupo' => 'email',
            'mail_password_apagar' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull(ChavesDeIntegracao::doBanco('mail_password'));
        $this->assertSame('senha-do-ambiente', ChavesDeIntegracao::vigente('mail_password'));
    }

    public function test_as_chaves_do_painel_desam_o_aviso_de_credencial_faltante(): void
    {
        config([
            'google_ads.developer_token' => null,
            'services.google_ads.client_id' => null,
            'services.google_ads.client_secret' => null,
        ]);

        $this->assertSame(
            ['GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'],
            GoogleAdsService::credenciaisFaltantes()->all(),
        );

        ChavesDeIntegracao::guardar('google_ads_developer_token', 'token-do-painel');
        ChavesDeIntegracao::aplicar();

        $this->assertSame(['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'], GoogleAdsService::credenciaisFaltantes()->all());
        $this->assertCount(2, ChavesDeIntegracao::faltantesDoGoogleAds());
    }

    public function test_grupo_desconhecido_nao_escreve_nada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), ['grupo' => 'wallet', 'mail_host' => 'qualquer'])
            ->assertSessionHas('error');

        $this->assertSame(0, Setting::where('key', 'mail_host')->count());
    }

    public function test_vendedor_ve_as_chaves_mas_nao_as_edita(): void
    {
        $vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        $this->actingAs($vendedor)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('admin.settings.chaves'), ['grupo' => 'email'])->assertForbidden();
    }

    /* ------------------------------------------------------------------ Assistente */

    public function test_a_tela_mostra_os_quatro_grupos_do_assistente(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Assistente — chave de cada provedor')
            ->assertSee('Assistente — modelo por capacidade')
            ->assertSee('Assistente — o que o chat aceita')
            ->assertSee('Assistente — aprender da internet')
            ->assertSee('ASSISTENTE_GEMINI_KEY', false)
            ->assertSee('ASSISTENTE_MODELO_VISAO', false);
    }

    public function test_a_tela_so_oferece_o_modelo_que_a_fabrica_sabe_usar(): void
    {
        // A lista do seletor e a lista que a chamada de HTTP aceita têm de ser a mesma lista.
        // Escrita à mão em dois lugares, o painel passaria a oferecer um modelo que morre na API.
        $campos = collect(ChavesDeIntegracao::grupos()['assistente-modelos']['campos']);

        foreach (array_keys((array) config('assistente.capacidades')) as $capacidade) {
            $campo = $campos->firstWhere('chave', 'assistente_rodada_'.$capacidade);

            $this->assertNotNull($campo, "A capacidade {$capacidade} ficou sem seletor na tela.");
            $this->assertSame(Fabrica::opcoesPara($capacidade), $campo['opcoes']);
        }

        $this->assertSame(Fabrica::opcoesDeVoz(), $campos->firstWhere('chave', 'assistente_voz_padrao')['opcoes']);
    }

    public function test_o_painel_recusa_o_modelo_que_nao_existe_naquela_capacidade(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), [
                'grupo' => 'assistente-modelos',
                'assistente_rodada_documento' => 'openai/gpt-4o',
            ])
            ->assertSessionHasErrors('assistente_rodada_documento');

        // GPT-4o conversa e vê imagem, mas não lê documento: a recusa protege da escolha muda.
        $this->assertSame(0, Setting::where('key', 'assistente_rodada_documento')->count());
    }

    public function test_o_painel_grava_o_interruptor_o_decimal_e_o_inteiro_cada_um_no_seu_tipo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), [
                'grupo' => 'assistente-chat',
                'assistente_documento' => '0',
                'assistente_documento_mb' => '6,5',
                'assistente_resposta_max' => '700',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        ChavesDeIntegracao::aplicar();

        $this->assertFalse(config('assistente.permissoes.documento'));
        $this->assertSame(6.5, config('assistente.midia.documento_mb_max'));
        $this->assertSame(700, config('assistente.resposta.caracteres_max'));

        // "Não" gravado é decisão do painel, e a tela diz isso em vez de "sem valor".
        $this->assertSame('banco', ChavesDeIntegracao::origem('assistente_documento'));
    }

    public function test_a_lista_de_enderecos_grava_sem_espaço_sem_repetido_e_le_de_volta_array(): void
    {
        ChavesDeIntegracao::guardar('assistente_dominios', ' goldenrastreamento.com.br, blog.golden , goldenrastreamento.com.br ');
        ChavesDeIntegracao::aplicar();

        $this->assertSame(['goldenrastreamento.com.br', 'blog.golden'], config('assistente.aprendizado.dominios'));
        $this->assertSame('goldenrastreamento.com.br, blog.golden', ChavesDeIntegracao::vigente('assistente_dominios'));
    }

    public function test_a_frase_de_erro_usa_o_nome_que_a_tela_mostra(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.chaves'), ['grupo' => 'assistente-chat', 'assistente_documento' => '2'])
            ->assertSessionHasErrors('assistente_documento');

        // "assistente_documento" é o nome da coluna; o administrador lê "O cliente pode mandar
        // documento". O rótulo vem de grupos() e o controle só o empresta ao validador.
        $frase = session('errors')->get('assistente_documento');

        $this->assertSame('O valor escolhido para O cliente pode mandar documento é inválido.', $frase[0]);
    }

    public function test_o_seletor_nao_perde_a_chave_que_o_validador_usa(): void
    {
        $componente = file_get_contents(resource_path('views/components/admin/field.blade.php'));

        // O laço das opções já se chamou `foreach ($options as $chave => $rotulo)`, e $chave é a
        // variável que guarda a chave pontuada do validador: depois da última <option> ela valia
        // o valor da opção, o @error() procurava um campo que ninguém digitou e a caixa ficava
        // vermelha sem uma palavra sobre o motivo.
        $this->assertStringNotContainsString('as $chave =>', $componente);
        $this->assertStringContainsString('@foreach($options as $valorOpcao => $rotuloOpcao)', $componente);
    }
}
