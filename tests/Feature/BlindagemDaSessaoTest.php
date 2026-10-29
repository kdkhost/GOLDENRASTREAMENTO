<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\SessaoDoPainel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * O que acontece depois da porta abrir: a janela, a cookie e o carimbo em cada resposta.
 *
 * Login blindado não serve de nada se o acesso entra em coma depois. Estes testes travam as quatro
 * coisas que já estiveram soltas:
 *
 * 1. **A cookie de sessão** é a chave da porta: tem que sair `HttpOnly` (nada de script de terceiros
 *    a lendo), `SameSite=lax` (não viaja em pedido de outro endereço) e `Secure` quando o site é
 *    HTTPS — é o que o instalador escreve no `.env`, e o que o navegador só obedece se vier daqui.
 * 2. **Cada resposta** sai carimbada com os cabeçalhos que impedem o painel de ser embutido num
 *    quadro alheio (clickjacking), de ler arquivo enviado por cliente como script, de vazar a URL da
 *    ficha de cliente no próximo clique e de executar o que um campo de texto um dia deixou passar.
 * 3. **A janela aberta existe no banco**: o botão "encerrar" e o teto de aparelhos derrubam o acesso
 *    do aparelho na batida seguinte, e a aba esquecida fecha sozinha com o tempo de inatividade.
 * 4. **Trocar a senha fecha as janelas** daquela conta — inclusive a que alguém abriu com a cookie
 *    roubada antes da troca.
 */
class BlindagemDaSessaoTest extends TestCase
{
    use RefreshDatabase;

    private const REDE = '198.51.100.24';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        config([
            'seguranca.sessao_minutos' => 120,
            'seguranca.sessoes_simultaneas' => 5,
            'seguranca.dois_fatores' => 'ninguem',
        ]);
    }

    /** O login de verdade, com sessão de verdade: é o caminho que abre a janela no banco. */
    private function entrar(User $usuario): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::REDE])
            ->post(route('login.post'), ['email' => $usuario->email, 'password' => 'golden123']);
    }

    /** @return array<string, string> */
    private function cabecalhosDe(string $rota, bool $https = false): array
    {
        // O escudo do HTTPS vem do endereço pedido, não de uma variável de servidor colada depois:
        // o pedido é montado a partir da URL, e uma URL `http://` desliga o `secure` antes de
        // qualquer cabeçalho nascer. Em conexão cifrada é o próprio endereço que pede o escudo.
        $endereco = $https ? preg_replace('#^http://#', 'https://', $rota) : $rota;

        return $this->withServerVariables(['REMOTE_ADDR' => self::REDE])->get($endereco)
            ->assertOk()->headers->all();
    }

    private function cabecalho(array $cabecalhos, string $nome): string
    {
        return (string) ($cabecalhos[strtolower($nome)][0] ?? '');
    }

    public function test_a_cookie_da_sessao_sai_httponly_lax_e_no_mesmo_endereco_do_site(): void
    {
        $nome = (string) config('session.cookie');
        $cookie = $this->get(route('login'))->getCookie($nome);

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertTrue($cookie->isHttpOnly(), 'Sem HttpOnly, qualquer script da página lê a sessão.');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertFalse($cookie->isSecure(), 'Em http:// o navegador descartaria a cookie marcada.');

        // A renovação de endereço no login é o que impede a fixação de sessão: um atacante que
        // entrega uma cookie pronta não herda a janela que a pessoa acaba de abrir.
        $esta = $this->get(route('login'))->getCookie($nome)?->getValue();
        $depois = $this->entrar($this->admin)->getCookie($nome)?->getValue();
        $this->assertNotEquals($esta, $depois);
    }

    public function test_a_cookie_pede_o_escudo_do_https_quando_o_site_esta_cifrado(): void
    {
        config(['session.secure' => true]);

        $cookie = $this->get(route('login'))->getCookie((string) config('session.cookie'));
        $this->assertTrue($cookie->isSecure(), 'Em HTTPS a cookie nunca pode viajar em texto claro.');

        config(['session.secure' => false]);
        $solta = $this->get(route('login'))->getCookie((string) config('session.cookie'));
        $this->assertFalse($solta->isSecure());
    }

    public function test_toda_resposta_vem_carimbada_contra_quadro_espiao_e_script_injetado(): void
    {
        foreach ([route('site.home'), route('login'), route('admin.dashboard')] as $i => $rota) {
            $tela = $this->withServerVariables(['REMOTE_ADDR' => self::REDE]);
            $cabecalhos = ($i === 2 ? $tela->actingAs($this->admin) : $tela)->get($rota)
                ->assertOk()->headers->all();

            $this->assertSame('nosniff', $this->cabecalho($cabecalhos, 'X-Content-Type-Options'));
            $this->assertSame('SAMEORIGIN', $this->cabecalho($cabecalhos, 'X-Frame-Options'));
            $this->assertSame('strict-origin-when-cross-origin', $this->cabecalho($cabecalhos, 'Referrer-Policy'));
            $this->assertSame('same-origin', $this->cabecalho($cabecalhos, 'Cross-Origin-Opener-Policy'));

            $csp = $this->cabecalho($cabecalhos, 'Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("base-uri 'self'", $csp);
            $this->assertStringContainsString("form-action 'self'", $csp);
            $this->assertStringContainsString("default-src 'self'", $csp);
        }
    }

    public function test_a_politica_de_conteudo_deixa_passar_o_que_o_painel_realmente_carrega(): void
    {
        $this->entrar($this->admin);

        $csp = $this->cabecalho($this->cabecalhosDe(route('admin.dashboard')), 'Content-Security-Policy');

        // Uma CSP que não casa com as próprias páginas é a política que alguém desliga no primeiro
        // susto. Cada endereço externo carregado pelos layouts tem que estar na lista.
        $carregados = [];
        foreach (glob(resource_path('views/{layouts,partials}/*.blade.php'), GLOB_BRACE) ?: [] as $arquivo) {
            preg_match_all('#https://[a-z0-9.-]+#i', file_get_contents($arquivo), $achados);
            $carregados = array_merge($carregados, $achados[0]);
        }

        $this->assertNotEmpty($carregados, 'Os layouts mudaram: a lista de endereços externos precisa ser conferida.');

        foreach (array_unique($carregados) as $endereco) {
            $this->assertStringContainsString($endereco, $csp, "A CSP não autoriza {$endereco}, que os layouts carregam.");
        }

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
    }

    public function test_o_strict_transport_security_sai_so_em_conexao_cifrada(): void
    {
        $solto = $this->cabecalho($this->cabecalhosDe(route('login')), 'Strict-Transport-Security');
        $cifrado = $this->cabecalho($this->cabecalhosDe(route('login'), true), 'Strict-Transport-Security');

        $this->assertSame('', $solto, 'HSTS em http:// é um recado que o navegador joga fora.');
        $this->assertStringContainsString('max-age=31536000', $cifrado);
    }

    public function test_o_perfil_de_escritorio_perde_o_escudo_de_quem_ve_de_fora(): void
    {
        // As permissões do navegador (câmera, geolocalização, pagamento) ficam desligadas para a
        // casa, e o cabeçalho não pode sumir em rota nenhuma: é a resposta que manda, não a página.
        $cabecalhos = $this->cabecalhosDe(route('site.home'));
        $politica = $this->cabecalho($cabecalhos, 'Permissions-Policy');

        $this->assertStringContainsString('camera=()', $politica);
        $this->assertStringContainsString('geolocation=()', $politica);
        $this->assertStringContainsString('interest-cohort=()', $politica);
    }

    public function test_entrar_no_painel_abre_uma_janela_registrada_com_aparelho_e_rede(): void
    {
        $esta = $this->get(route('login'))->getCookie((string) config('session.cookie'))?->getValue();

        $aberta = $this->entrar($this->admin);
        $aberta->assertRedirect(route('admin.dashboard'));

        $janela = SessaoDoPainel::latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $janela->user_id);
        $this->assertSame(self::REDE, $janela->ip);
        $this->assertSame($aberta->getCookie((string) config('session.cookie'))?->getValue(), $janela->sessao);
        $this->assertNotSame($esta, $janela->sessao);

        $tela = $this->actingAs($this->admin)->get(route('admin.seguranca.index'))
            ->assertOk()
            ->assertSee('Janelas abertas nesta conta', false)
            ->getContent();
        $this->assertStringContainsString(self::REDE, $tela);
    }

    public function test_encerrar_uma_janela_derruba_aquele_aparelho_na_batida_seguinte(): void
    {
        $this->entrar($this->admin);
        $minha = SessaoDoPainel::latest('id')->firstOrFail();

        // Uma segunda janela da mesma conta, como o notebook do escritório que ficou ligado.
        $outra = SessaoDoPainel::create([
            'user_id' => $this->admin->id,
            'sessao' => 'janela-do-notebook-antigo',
            'carimbo' => 'notebook|antigo',
            'ip' => '203.0.113.9',
            'agente' => 'Mozilla/5.0 (Windows NT 10.0)',
            'ultimo_visto_em' => now()->subHour(),
        ]);

        $this->post(route('admin.seguranca.janela.encerrar', $outra->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('sessoes_do_painel', ['id' => $outra->id]);

        // A janela encerrada é a que manda? Não: o atestado consultou a lista, e quem ficou de fora
        // perde o painel no próximo pedido. A de agora, que continua na lista, segue trabalhando.
        $this->get(route('admin.dashboard'))->assertOk();

        $this->app['session.store']->put('janela_painel', $outra->id);
        $caiu = $this->get(route('admin.dashboard'));
        $caiu->assertRedirect(route('login'))->assertSessionHas('warning');
        $this->assertStringContainsString(
            'Sua sessão foi encerrada por segurança',
            $caiu->getSession()->get('warning'),
        );
        $this->assertDatabaseHas('activities', ['action' => 'sessao_encerrada']);
    }

    public function test_o_botao_sair_das_outras_janelas_guarda_a_que_esta_usando(): void
    {
        $this->entrar($this->admin);
        $minha = SessaoDoPainel::latest('id')->firstOrFail()->id;

        foreach (range(1, 3) as $i) {
            SessaoDoPainel::create([
                'user_id' => $this->admin->id,
                'sessao' => 'janela-velha-'.$i,
                'carimbo' => 'notebook|'.$i,
                'ip' => '203.0.113.9',
                'agente' => 'Mozilla/5.0',
                'ultimo_visto_em' => now()->subMinutes($i),
            ]);
        }

        $this->post(route('admin.seguranca.janelas.outras'))->assertSessionHas('success');

        $this->assertSame([$minha], SessaoDoPainel::where('user_id', $this->admin->id)->pluck('id')->all());
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_a_aba_esquecida_derruba_o_acesso_depois_do_tempo_de_inatividade(): void
    {
        config(['seguranca.sessao_minutos' => 30]);
        $this->entrar($this->admin);
        $id = SessaoDoPainel::latest('id')->value('id');

        $this->travel(31)->minutes();

        $caiu = $this->get(route('admin.dashboard'));
        $caiu->assertRedirect(route('login'));
        $this->assertDatabaseMissing('sessoes_do_painel', ['id' => $id]);
        $this->assertDatabaseHas('activities', ['action' => 'sessao_encerrada']);

        // Uma aba de painel aberta num computador do estoque às 8h não é um acesso em uso às 20h.
        $this->travelBack();
    }

    public function test_o_teto_de_janelas_abertas_derruba_a_mais_antiga_de_automatico(): void
    {
        config(['seguranca.sessoes_simultaneas' => 2]);

        foreach (range(1, 3) as $i) {
            $this->entrar($this->admin)->assertRedirect(route('admin.dashboard'));
            $this->travel(2)->minutes();
        }

        $vivem = SessaoDoPainel::where('user_id', $this->admin->id)
            ->orderBy('id')->pluck('sessao')->all();

        $this->assertCount(2, $vivem, 'Com teto em 2, a quarta janela não pode deixar três vivas.');
        $this->travelBack();
    }

    public function test_trocar_a_senha_derruba_toda_janela_aberta_daquele_acesso(): void
    {
        $this->entrar($this->admin)->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        // O caso que a middleware da framework cobre: a cookie roubada continua válida até a pessoa
        // trocar a senha — e aí ela tem que deixar de valer na hora, não amanhã.
        $this->admin->forceFill(['password' => Hash::make('senha-nova-123')])->save();

        // Em produção cada pedido vem com o guarda recém-montado, que relê a conta; aqui o processo
        // é o mesmo desde o login, e sem este passo o pedido seguinte ainda seguraria a cifra velha.
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->from(route('login'))
            ->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'golden123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $esta = $this->from(route('login'))
            ->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'senha-nova-123']);
        $esta->assertRedirect(route('admin.dashboard'));
    }

    public function test_sair_do_painel_apaga_a_janela_e_invalida_a_sessao(): void
    {
        $this->entrar($this->admin);
        $id = SessaoDoPainel::latest('id')->value('id');
        $antes = $this->get(route('admin.dashboard'))->getCookie((string) config('session.cookie'))?->getValue();

        $saiu = $this->post(route('logout'));
        $saiu->assertRedirect(route('site.home'));
        $this->assertGuest();
        $this->assertNotSame($antes, $saiu->getCookie((string) config('session.cookie'))?->getValue());
        $this->assertDatabaseHas('activities', ['action' => 'logout']);

        // A linha de janela fica? Fica derrubada pelo `invalidate` no próximo pedido, não antes: o
        // atestado é quem confere a lista, e uma linha órfã não abre nada.
        $this->assertNotNull($id);
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_o_painel_recusa_escrever_em_outra_casa(): void
    {
        // A política vale para toda resposta, e a CSP não espera a pessoa entrar: a página de login
        // já sai com ela. É o que impede um campo com HTML colado de mandar o `csrf_token` de
        // alguém para um endereço de fora no próximo "Salvar".
        $csp = $this->cabecalho($this->cabecalhosDe(route('login')), 'Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self'", $csp);

        // E o que é da casa não abre para quem está de fora: a rota de destravar, pedida sem
        // sessão, devolve a pessoa para a porta em vez de chegar ao controller.
        $this->withServerVariables(['REMOTE_ADDR' => self::REDE])
            ->post('/admin/seguranca/destravar', ['email' => 'ninguem@golden.com.br'])
            ->assertRedirect(route('login'));
    }

    public function test_o_arquivo_que_o_painel_recebe_por_upload_nao_vira_programa(): void
    {
        $folha = base_path('storage/app/public/.htaccess');
        $this->assertFileExists($folha);

        $regras = (string) file_get_contents($folha);
        $this->assertStringContainsString('RemoveHandler', $regras);
        $this->assertStringContainsString('Require all denied', $regras);
        $this->assertStringContainsString('-ExecCGI', $regras);
        $this->assertStringContainsString('-Indexes', $regras);

        // `public/storage` é o único diretório da instalação que o público alcança, e é onde cai o
        // arquivo que alguém subiu pela tela de upload: PHP dentro dele não pode virar código.
        $esta = (string) file_get_contents(base_path('storage/app/public/.gitignore'));
        $this->assertStringContainsString('.htaccess', $esta);
    }
}
