<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use App\Services\Seguranca\DoisFatores;
use App\Services\Seguranca\VigiaDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * A segunda chave da porta: o código de seis dígitos que nasce no celular da pessoa.
 *
 * Estes testes existem porque o dois fatores é a parte do acesso em que é mais fácil *fazer de conta*
 * que funciona: um formulário que aceita qualquer coisa, um segredo gravado em texto claro, uma sessão
 * que entrou antes do código bater. Cada teste ataca um desses modos de parecer sem ser:
 *
 * - a senha certa sozinha **não** abre — a meia-porta tira a autenticação de propósito;
 * - o segredo só vira banco depois de um código real bater, e é gravado cifrado: um despejo da
 *   tabela `users` entrega exatamente o que entregava antes, que é nada;
 * - o código de recuperação abre a porta **uma** vez e se apaga;
 * - o campo do código é contado, porque seis dígitos sem limite são um milhão de combinações na
 *   espera do script que a porta da senha acabou de dispensar;
 * - a política da tela de Configurações (`ninguem`, `administradores`, `equipe`) é o que obriga, e a
 *   tela de acendimento fica fora do `/admin` para o caminho não virar laço.
 *
 * O relógio do TOTP vira a cada 30 segundos, então cada código é gerado no minuto do pedido.
 */
class AutenticacaoDoisFatoresTest extends TestCase
{
    use RefreshDatabase;

    /** As duas chaves internas da sessão: a espera do QR e a folha mostrada uma vez. */
    private const PENDENTE = 'dois_fatores_pendente_na_configuracao';

    private const DESAFIO = 'dois_fatores_pendente';

    private const FOLHA = 'dois_fatores_codigos_mostrar';

    private User $admin;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        config([
            'seguranca.tentativas' => 5,
            'seguranca.dois_fatores' => 'ninguem',
            'seguranca.desafio_minutos' => 10,
        ]);
    }

    /** Acende a chave de uma conta pelo caminho de dentro, e devolve o segredo com os oito códigos. */
    private function acenderChave(User $usuario): array
    {
        $dois = app(DoisFatores::class);
        $segredo = $dois->novoSegredo();
        $codigos = $dois->novosCodigosDeRecuperacao();

        $usuario->forceFill([
            'two_factor_secret' => $segredo,
            'two_factor_recovery_codes' => $codigos,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$segredo, $codigos];
    }

    private function codigoDe(string $segredo): string
    {
        return (new Google2FA)->getCurrentOtp($segredo);
    }

    /** A senha certa num dia em que a conta tem a segunda chave acesa: para no meio da porta. */
    private function baterNaMeiaPorta(User $usuario): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('login.post'), ['email' => $usuario->email, 'password' => 'golden123']);
    }

    private function sessao(string $chave): mixed
    {
        return $this->app['session.store']->get($chave);
    }

    public function test_acender_a_chave_exige_um_codigo_real_antes_de_gravar_o_segredo(): void
    {
        $tela = $this->actingAs($this->vendedor)
            ->get(route('dois-fatores.configurar'))
            ->assertOk()
            ->assertSee('Leia o código com o aplicativo', false)
            ->getContent();

        // O QR é desenhado pela própria página. Um PNG em `storage/app/public` seria a chave secreta
        // de alguém publicada em URL, e o cache do navegador a seguraria depois da saída.
        $this->assertStringContainsString('<svg', $tela);
        $this->assertNull($this->vendedor->fresh()->two_factor_secret);

        $segredo = $this->sessao(self::PENDENTE)['segredo'];
        $this->assertNotEmpty($segredo);

        $this->post(route('dois-fatores.confirmar'), ['codigo' => '000000'])
            ->assertSessionHasErrors('codigo');
        $this->assertNull($this->vendedor->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_configuracao_falhou']);
        $this->assertSame(1, app(VigiaDeAcesso::class)->tentativasDoCodigo($this->vendedor));

        $this->post(route('dois-fatores.confirmar'), ['codigo' => $this->codigoDe($segredo)])
            ->assertRedirect(route('dois-fatores.codigos'));

        $acesa = $this->vendedor->fresh();
        $this->assertTrue($acesa->doisFatoresAtivo());
        $this->assertSame($segredo, $acesa->two_factor_secret);
        $this->assertCount(8, (array) $acesa->two_factor_recovery_codes);
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_ativado', 'user_id' => $this->vendedor->id]);

        // Confirmar é a prova de que o aplicativo da pessoa lê o mesmo número: um segredo gravado sem
        // prova deixaria a conta pedindo código que ninguém tem.
        $this->assertSame(0, app(VigiaDeAcesso::class)->tentativasDoCodigo($this->vendedor));
        $this->assertNull($this->sessao(self::PENDENTE));
    }

    public function test_o_segredo_e_os_codigos_sao_gravados_cifrados(): void
    {
        [$segredo, $codigos] = $this->acenderChave($this->admin);

        $cru = DB::table('users')->where('id', $this->admin->id)->first();

        $this->assertSame($segredo, $this->admin->fresh()->two_factor_secret);
        $this->assertStringNotContainsString($segredo, (string) $cru->two_factor_secret);
        $this->assertStringNotContainsString($codigos[0], (string) $cru->two_factor_recovery_codes);

        // O que desce para o banco é o pacote cifrado da framework — vetor, valor e selo — nunca a
        // chave em texto. Um despejo da tabela entrega exatamente o que entregava antes: nada.
        $pacote = json_decode(base64_decode((string) $cru->two_factor_secret), true);
        $this->assertIsArray($pacote);
        $this->assertArrayHasKey('iv', $pacote);
        $this->assertArrayHasKey('value', $pacote);
        $this->assertArrayHasKey('mac', $pacote);
        $this->assertNotSame($segredo, $pacote['value']);
    }

    public function test_a_senha_certa_para_na_meia_porta_quando_a_chave_esta_acesa(): void
    {
        [$segredo] = $this->acenderChave($this->admin);

        $this->baterNaMeiaPorta($this->admin)->assertRedirect(route('login.dois-fatores'));

        // O ponto todo: a pessoa sai da autenticação de propósito. Uma sessão já logada antes do
        // código seria o dois fatores como enfeite da tela.
        $this->assertGuest();
        $this->assertDatabaseMissing('activities', ['action' => 'login']);

        $tela = $this->get(route('login.dois-fatores'))->assertOk()->getContent();
        // A tela diz quem está entrando, pela pessoa, e não pela credencial: o nome basta para
        // reconhecer a própria meia-porta, e a chave do aplicativo nunca chega perto do HTML.
        $this->assertStringContainsString($this->admin->name, $tela);
        $this->assertStringNotContainsString($segredo, $tela);

        // Na meia-porta fica só a identidade à espera, com prazo. Nada de credencial, nada de segredo,
        // e a opção "lembrar" não atravessa: ela é uma porta que se abre sozinha.
        $pendente = $this->sessao(self::DESAFIO);
        $this->assertSame($this->admin->id, $pendente['usuario']);
        $this->assertFalse($pendente['lembre']);
        $this->assertIsInt($pendente['desde']);
    }

    public function test_a_opcao_lembrar_so_passa_a_valer_depois_do_codigo(): void
    {
        [$segredo] = $this->acenderChave($this->admin);

        $this->post(route('login.post'), [
            'email' => $this->admin->email,
            'password' => 'golden123',
            'remember' => 'on',
        ])->assertRedirect(route('login.dois-fatores'));

        $nome = $this->app['auth']->guard('web')->getRecallerName();

        // A meia-porta é uma porta que se abre sozinha: se o "lembrar" ficasse válido aqui, bastaria
        // recarregar o site amanhã para entrar sem o segundo fator que a casa acabou de exigir.
        $esta = (string) $this->admin->fresh()->remember_token;
        $meiaPorta = (string) $this->cookieDaMeiaPorta($nome);
        $this->assertTrue($meiaPorta === '' || ! str_contains($meiaPorta, $esta));

        $this->post(route('login.dois-fatores.post'), ['codigo' => $this->codigoDe($segredo)])
            ->assertRedirect(route('admin.dashboard'));

        $depois = $this->cookieDaMeiaPorta($nome);
        $this->assertStringContainsString((string) $this->admin->fresh()->remember_token, (string) $depois);
    }

    /** O valor da cookie "lembrar" que está na fila daquela resposta, sem ela ser emitida de fato. */
    private function cookieDaMeiaPorta(string $nome): ?string
    {
        $fila = collect($this->app['cookie']->getQueuedCookies())
            ->filter(fn ($c): bool => $c->getName() === $nome)
            ->last();

        return $fila?->getValue();
    }

    public function test_o_codigo_do_aplicativo_abre_a_porta_e_o_errado_nao(): void
    {
        [$segredo] = $this->acenderChave($this->admin);
        $this->baterNaMeiaPorta($this->admin);

        $this->post(route('login.dois-fatores.post'), ['codigo' => '123456'])
            ->assertRedirect()
            ->assertSessionHasErrors('codigo');
        $this->assertGuest();
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_falhou']);

        $this->post(route('login.dois-fatores.post'), ['codigo' => $this->codigoDe($segredo)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(0, app(VigiaDeAcesso::class)->tentativasDoCodigo($this->admin));
        $this->assertDatabaseHas('activities', ['action' => 'login', 'user_id' => $this->admin->id]);

        // A meia-porta se apaga no caminho: a aba do desafio não serve duas vezes.
        $this->assertNull($this->sessao(self::DESAFIO));
    }

    public function test_o_codigo_de_recuperacao_abre_a_porta_uma_sozinho_e_se_apaga(): void
    {
        [, $codigos] = $this->acenderChave($this->admin);
        $usado = $codigos[0];

        $this->baterNaMeiaPorta($this->admin);
        $this->post(route('login.dois-fatores.post'), ['codigo' => $usado])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        $restam = array_values((array) $this->admin->fresh()->two_factor_recovery_codes);
        $this->assertCount(7, $restam);
        $this->assertNotContains($usado, $restam);
        $this->assertDatabaseHas('activities', ['action' => 'login_codigo_de_recuperacao']);
        $this->assertDatabaseMissing('activities', ['action' => 'login', 'user_id' => $this->admin->id]);
    }

    public function test_repetir_o_codigo_queimado_recusa_e_nao_abre_nada(): void
    {
        [, $codigos] = $this->acenderChave($this->admin);
        $usado = $codigos[0];

        $this->baterNaMeiaPorta($this->admin);
        $this->post(route('login.dois-fatores.post'), ['codigo' => $usado])->assertRedirect();

        $this->post(route('login.dois-fatores.cancelar'))->assertRedirect(route('login'));
        $this->baterNaMeiaPorta($this->admin);

        $recusado = $this->post(route('login.dois-fatores.post'), ['codigo' => $usado]);
        $recusado->assertSessionHasErrors('codigo');
        $this->assertGuest();

        // Um código queimado não vale nada para quem o encontra num print — nem na segunda tentativa,
        // nem com a senha digitada de novo no meio.
        $this->assertCount(7, array_values((array) $this->admin->fresh()->two_factor_recovery_codes));
    }

    public function test_estourar_o_limite_de_codigos_fecha_o_campo_do_codigo(): void
    {
        $this->acenderChave($this->admin);
        $vigia = app(VigiaDeAcesso::class);

        $this->baterNaMeiaPorta($this->admin);
        foreach (range(1, 5) as $i) {
            $this->from(route('login.dois-fatores'))
                ->post(route('login.dois-fatores.post'), ['codigo' => '00000'.$i]);
        }

        $this->assertNotNull($vigia->codigoBloqueadoPor($this->admin->fresh()));

        $this->from(route('login.dois-fatores'))
            ->post(route('login.dois-fatores.post'), ['codigo' => '999999'])
            ->assertRedirect(route('login.dois-fatores'));
        $this->assertGuest();

        // A tela devolve o prazo em linguagem de gente, e o contador de tentativas congela em zero:
        // seis dígitos sem limite seriam um milhão de combinações esperando o script da vez.
        $this->get(route('login.dois-fatores'))
            ->assertOk()
            ->assertSee('Códigos demais tentados.', false)
            ->assertSee('Restam 0 tentativa(s)', false);
    }

    public function test_a_meia_porta_expira_e_manda_bater_a_senha_de_novo(): void
    {
        [$segredo] = $this->acenderChave($this->admin);
        $this->baterNaMeiaPorta($this->admin);

        $this->travel(11)->minutes();

        $this->get(route('login.dois-fatores'))->assertRedirect(route('login'));
        $this->post(route('login.dois-fatores.post'), ['codigo' => $this->codigoDe($segredo)])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');
        $this->assertGuest();

        // Uma aba do desafio aberta desde ontem não é um acesso em andamento: sem prazo, o segundo
        // fator viraria formalidade pulável por quem guardou a URL nos favoritos.
        $this->travelBack();
    }

    public function test_a_politica_da_casa_decide_quem_e_obrigado_a_ter_a_chave(): void
    {
        config(['seguranca.dois_fatores' => 'administradores']);

        $this->actingAs($this->vendedor)->get(route('admin.seguranca.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('dois-fatores.configurar'))
            ->assertSessionHas('warning');

        config(['seguranca.dois_fatores' => 'equipe']);
        $this->actingAs($this->vendedor)->get(route('admin.seguranca.index'))
            ->assertRedirect(route('dois-fatores.configurar'));

        // O acendimento fica fora do prefixo `/admin` de propósito: é a rota que quebra o laço — sem
        // ela, a política deixaria a pessoa presa numa porta que aponta para ela mesma.
        $this->actingAs($this->vendedor)->get(route('dois-fatores.configurar'))->assertOk();

        config(['seguranca.dois_fatores' => 'ninguem']);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_a_folha_de_codigos_aparece_uma_sozinha_e_renovar_invalida_a_anterior(): void
    {
        $this->actingAs($this->vendedor)->get(route('dois-fatores.configurar'))->assertOk();
        $segredo = $this->sessao(self::PENDENTE)['segredo'];

        $primeira = $this->post(route('dois-fatores.confirmar'), ['codigo' => $this->codigoDe($segredo)])
            ->assertRedirect(route('dois-fatores.codigos'));
        $this->assertNotNull($this->sessao(self::FOLHA));

        $folha = $this->get(route('dois-fatores.codigos'))->assertOk();
        $codigos = array_values((array) $this->vendedor->fresh()->two_factor_recovery_codes);
        $folha->assertSee($codigos[0], false);

        $this->post(route('dois-fatores.codigos.renovar'))->assertRedirect(route('dois-fatores.codigos'));
        $novos = array_values((array) $this->vendedor->fresh()->two_factor_recovery_codes);
        $this->assertNotContains($codigos[0], $novos);
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_codigos_renovados']);

        // "Guardei" apaga a folha da sessão, e ela não volta: o que se vê uma vez, se vê uma vez.
        $this->post(route('dois-fatores.codigos.guardar'))->assertRedirect(route('admin.seguranca.index'));
        $this->assertNull($this->sessao(self::FOLHA));
        $this->get(route('dois-fatores.codigos'))->assertRedirect(route('dois-fatores.configurar'));
        $this->assertNotNull($primeira);
    }

    public function test_desligar_a_propria_chave_custao_codigo_e_a_politica_da_casa_manda(): void
    {
        $this->actingAs($this->vendedor)->get(route('dois-fatores.configurar'))->assertOk();
        $segredo = $this->sessao(self::PENDENTE)['segredo'];
        $this->post(route('dois-fatores.confirmar'), ['codigo' => $this->codigoDe($segredo)]);

        $sem_codigo = $this->post(route('dois-fatores.desligar'), ['codigo' => 'esqueci-tudo']);
        $sem_codigo->assertSessionHasErrors('codigo');
        $this->assertTrue($this->vendedor->fresh()->doisFatoresAtivo());

        // Desligar a exigência é decisão da casa, não da pessoa: se qualquer perfil apagasse a própria
        // chave, a política seria uma frase escrita num campo de texto.
        config(['seguranca.dois_fatores' => 'equipe']);
        $esta = $this->post(route('dois-fatores.desligar'), [
            'codigo' => $this->codigoDe((string) $this->vendedor->fresh()->two_factor_secret),
        ]);
        $esta->assertRedirect(route('admin.seguranca.index'))->assertSessionHas('error');
        $this->assertTrue($this->vendedor->fresh()->doisFatoresAtivo());

        config(['seguranca.dois_fatores' => 'ninguem']);
        $this->post(route('dois-fatores.desligar'), [
            'codigo' => $this->codigoDe((string) $this->vendedor->fresh()->two_factor_secret),
        ])->assertRedirect(route('admin.seguranca.index'))->assertSessionHas('info');

        $apagada = $this->vendedor->fresh();
        $this->assertFalse($apagada->doisFatoresAtivo());
        $this->assertNull($apagada->two_factor_secret);
        $this->assertNull($apagada->two_factor_recovery_codes);
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_desligado']);
    }

    public function test_a_lista_da_equipe_distingue_quem_tem_a_chave_acesa(): void
    {
        $this->acenderChave($this->vendedor);

        // A linha da equipe julga `doisFatoresAtivo()`, que lê o segredo: a consulta que não traz a
        // coluna devolve "desligada" para todo mundo, e o botão de apagar a chave de um colega
        // simplesmente nunca aparece — a tela mente justo para quem pode consertar.
        $tela = $this->actingAs($this->admin)
            ->get(route('admin.seguranca.index'))
            ->assertOk()
            ->assertSee('acesa')
            ->assertSee('desligada');

        $this->assertStringContainsString(
            route('admin.seguranca.dois-fatores.desligar', $this->vendedor->id),
            $tela->getContent(),
        );
    }

    public function test_o_admin_apaga_a_chave_de_quem_perdeu_o_celular_e_o_colega_nao(): void
    {
        $this->acenderChave($this->vendedor);

        $this->actingAs($this->vendedor)
            ->post(route('admin.seguranca.dois-fatores.desligar', $this->vendedor->id))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.seguranca.dois-fatores.desligar', $this->vendedor->id))
            ->assertSessionHas('success');

        $this->assertFalse($this->vendedor->fresh()->doisFatoresAtivo());
        $this->assertDatabaseHas('activities', ['action' => 'dois_fatores_desligado']);

        // Repetir o clique não é um segundo rastro: sem chave acesa, o pedido devolve um aviso.
        $this->actingAs($this->admin)
            ->post(route('admin.seguranca.dois-fatores.desligar', $this->vendedor->id))
            ->assertSessionHas('info');
        $this->assertSame(1, Activity::where('action', 'dois_fatores_desligado')->count());
    }

    public function test_a_politica_do_painel_nao_alcanda_conta_do_portal(): void
    {
        config(['seguranca.dois_fatores' => 'equipe']);

        // As telas da chave pertencem à conta do painel (`auth:web`). Quem entra pelo portal é outra
        // tabela, sem segredo de aplicativo nenhum, e não encontra nada delas pelo caminho.
        $this->get(route('dois-fatores.configurar'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $cliente = Customer::create([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'email' => 'maria@exemplo.com.br',
            'status' => 'ativo',
        ]);
        $cliente->email_portal = 'maria@exemplo.com.br';
        $cliente->password = 'senha-portal-123';
        $cliente->save();

        // A casa pode exigir o dois fatores de toda a equipe: o cliente continua entrando e vendo a
        // própria conta, porque o cadeado do painel não tem nada a cobrar de quem nunca entrou nele.
        $this->actingAs($cliente->fresh(), 'cliente')->get(route('portal.inicio'))->assertOk();
    }
}
