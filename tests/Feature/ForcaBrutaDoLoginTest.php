<?php

namespace Tests\Feature;

use App\Mail\TentativasDeLogin;
use App\Models\Activity;
use App\Models\Notificacao;
use App\Models\User;
use App\Services\Seguranca\VigiaDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A porta do painel com porteiro.
 *
 * Antes de `VigiaDeAcesso`, uma senha errada não deixava rastro nenhum: nem auditoria, nem sino,
 * nem e-mail para o dono da conta. O único limite existente era o `throttle:10,1` da rota, que é
 * medido por IP — um atirador com lista de senhas vazadas troca de rede e recomeça, enquanto a
 * conta atacada continua abrindo para quem souber a senha. Estes testes travam o que foi construído
 * para fechar essa porta: contagem por e-mail **e** por rede, trava com prazo, rastro dos dois lados
 * e aviso — um só, na hora em que o limite estoura.
 *
 * Um detalhe que é de segurança e não de conforto: a tela nunca diz se o e-mail existe. "Senha
 * errada" para um endereço e "não temos essa conta" para outro é a lista de alvos pronta, de graça.
 */
class ForcaBrutaDoLoginTest extends TestCase
{
    use RefreshDatabase;

    /** A rede de onde o atirador bate, fixa para o teste poder contar as travas. */
    private const REDE = '203.0.113.77';

    private User $alvo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->alvo = User::where('email', 'vendedor@golden.com.br')->firstOrFail();

        config([
            'seguranca.tentativas' => 3,
            'seguranca.janela_minutos' => 15,
            'seguranca.bloqueio_minutos' => 30,
        ]);

        Mail::fake();
    }

    /** @param array<string, string> $dados */
    private function bateNaPorta(array $dados): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::REDE])
            ->post(route('login.post'), $dados);
    }

    public function test_a_senha_errada_deixa_rastro_na_auditoria_sem_dizer_o_que_errou(): void
    {
        $resposta = $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'senha-errada']);

        $resposta->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Credenciais inválidas ou conta inativa.',
            session('errors')->first('email'),
        );
        $this->assertGuest();

        $linha = Activity::where('action', 'login_falhou')->latest('id')->firstOrFail();
        $this->assertSame($this->alvo->email, $linha->user_name);
        $this->assertSame(self::REDE, $linha->ip);
        $this->assertStringContainsString($this->alvo->email, (string) $linha->description);
    }

    public function test_endereco_sem_conta_recebe_a_mesma_frase_que_endereco_com_conta(): void
    {
        $frase = 'Credenciais inválidas ou conta inativa.';

        $this->bateNaPorta(['email' => 'ninguem@quenaoexiste.com.br', 'password' => 'qualquer'])
            ->assertSessionHasErrors(['email' => $frase]);
        $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'qualquer'])
            ->assertSessionHasErrors(['email' => $frase]);

        // A frase é literal e idêntica nos dois casos: a tela de login não é o lugar onde se
        // descobre, de graça, quais endereços têm conta ativa no sistema.
        $this->assertStringNotContainsString('quenaoexiste', (string) session('errors')->first('email'));
    }

    public function test_ao_estourar_o_limite_a_conta_fica_fora_mesmo_com_a_senha_certa(): void
    {
        foreach (range(1, 3) as $i) {
            $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada-'.$i]);
        }

        $esta = $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'golden123']);

        $esta->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Foram tentativas demais',
            $esta->getSession()->get('errors')->first('email'),
        );
        $this->assertGuest();

        $this->assertDatabaseMissing('activities', ['action' => 'login']);
    }

    public function test_o_bloqueio_aplica_a_duracao_da_trava_e_nao_a_janela_de_contagem(): void
    {
        $vigia = app(VigiaDeAcesso::class);

        foreach (range(1, 3) as $i) {
            $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada-'.$i]);
        }

        $restam = $vigia->bloqueadoPor($this->alvo->email, self::REDE);

        $this->assertNotNull($restam);
        // Janela de 15 minutos, castigo de 30: os baldes são separados de propósito, porque no
        // RateLimiter o prazo é gravado no primeiro toque e não se estica mais. O `RateLimiter`
        // devolve o que falta, então a medição é uma faixa, não um número exato.
        $this->assertLessThanOrEqual(30 * 60, $restam);
        $this->assertGreaterThan(15 * 60, $restam);
    }

    public function test_o_aviso_sai_uma_sozinha_na_travessia_do_limite(): void
    {
        foreach (range(1, 4) as $i) {
            $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada-'.$i]);
        }

        Mail::assertQueued(TentativasDeLogin::class, 1);
        $this->assertSame(1, Notificacao::where('tipo', 'acesso_suspeito')->count());

        // A quarta batida nem chega a ser contada: com a porta já trancada, o pedido é recusado na
        // entrada. Um atirador que insiste não enche a auditoria nem o sino — o fato único já foi
        // registrado quando o limite estourou.
        $this->assertSame(3, Activity::where('action', 'login_falhou')->count());
    }

    public function test_uma_conta_atacada_nao_trava_o_resto_da_mesma_rede_antes_do_limite(): void
    {
        // O escritório inteiro atrás do mesmo roteador: a colega pode errar a senha dela sem herdar
        // a trava que as tentativas da primeira conta construíram.
        $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada']);
        $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada']);

        $colega = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $abriu = $this->withServerVariables(['REMOTE_ADDR' => self::REDE])
            ->post(route('login.post'), ['email' => $colega->email, 'password' => 'golden123']);

        $abriu->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($colega);
    }

    public function test_acertar_a_senha_zera_as_contagens_da_conta_e_da_rede(): void
    {
        $vigia = app(VigiaDeAcesso::class);

        $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada']);
        $this->assertSame(1, $vigia->tentativasNaJanela($this->alvo->email));

        $this->withServerVariables(['REMOTE_ADDR' => self::REDE])
            ->post(route('login.post'), ['email' => $this->alvo->email, 'password' => 'golden123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, $vigia->tentativasNaJanela($this->alvo->email));
        $this->assertNull($vigia->bloqueadoPor($this->alvo->email, self::REDE));
    }

    public function test_bater_com_endereco_que_nao_existe_tambem_fecha_a_rede(): void
    {
        // Enumeração de alvos: o atirador que varre a lista de endereços da casa não encontra a
        // senha de ninguém, mas gasta o mesmo limite. A trava da rede é o que o derruba.
        foreach (range(1, 3) as $i) {
            $this->bateNaPorta(['email' => 'alguem@quenaoexiste.com.br', 'password' => 'senha-errada-'.$i]);
        }

        $this->assertNotNull(app(VigiaDeAcesso::class)->bloqueadoPor('alguem@quenaoexiste.com.br', self::REDE));
    }

    public function test_destravar_zera_a_conta_e_preserva_a_trava_da_rede(): void
    {
        foreach (range(1, 3) as $i) {
            $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada-'.$i]);
        }

        $vigia = app(VigiaDeAcesso::class);
        $vigia->destravar($this->alvo->email);

        $this->assertNull($vigia->bloqueadoPor($this->alvo->email, '198.51.100.4'));
        $this->assertNotNull($vigia->bloqueadoPor('outra-conta@golden.com.br', self::REDE));
    }

    public function test_a_tela_de_seguranca_mostra_a_conta_travada_e_o_admin_a_destrava(): void
    {
        foreach (range(1, 3) as $i) {
            $this->bateNaPorta(['email' => $this->alvo->email, 'password' => 'errada-'.$i]);
        }

        $admin = User::where('email', 'admin@golden.com.br')->firstOrFail();

        $tela = $this->actingAs($admin)->get(route('admin.seguranca.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Destravar uma conta', $tela);
        $this->assertStringContainsString($this->alvo->email, $tela);

        $this->actingAs($admin)
            ->post(route('admin.seguranca.destravar'), ['email' => $this->alvo->email])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activities', ['action' => 'conta_destravada', 'user_id' => $admin->id]);
        $this->assertSame(0, app(VigiaDeAcesso::class)->tentativasNaJanela($this->alvo->email));
    }

    public function test_destravar_e_apagar_a_segunda_chave_sao_do_administrador(): void
    {
        $this->actingAs($this->alvo)
            ->post(route('admin.seguranca.destravar'), ['email' => $this->alvo->email])
            ->assertForbidden();

        $this->actingAs($this->alvo)
            ->post(route('admin.seguranca.dois-fatores.desligar', $this->alvo->id))
            ->assertForbidden();
    }
}
