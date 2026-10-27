<?php

namespace Tests\Feature;

use App\Mail\TesteDeDisparo;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\Sonda;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * A prova do disparo de e-mail, na tela de Configurações.
 *
 * O que estes testes fecham é a frase "salvei as credenciais e achei que estava funcionando". A
 * sonda é o único lugar do sistema que responde à pergunta antes do primeiro cliente esperar, e
 * ela só vale alguma coisa se as quatro respostas possíveis forem distintas: saiu, não havia o
 * que testar, a porta não abriu, o servidor abriu e recusou. Um botão que dissesse "erro" para
 * as três últimas mandaria a pessoa trocar credencial boa por credencial pior.
 *
 * O envio é testado com `Mail::fake()` quando interessa o **caminho** (qual mailable, para quem,
 * com que remetente) e com um SMTP de verdade apontado para uma porta fechada quando interessa o
 * **erro** — porque classificação de mensagem de exceção é exatamente o tipo de código que passa
 * verde com fake e erra na produção.
 */
class ProvaDeDisparoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_a_tela_de_chaves_mostra_o_caminho_do_envio_e_o_botao_de_prova(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Testar o disparo agora')
            ->assertSee('Remetente')
            ->assertSee('Enviar a prova');
    }

    public function test_sem_transporte_que_saia_da_maquina_nada_e_enviado_e_a_tela_diz_o_motivo(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);

        $sonda = $this->sondar('maria@exemplo.com.br');

        $this->assertSame('sem_disparo', $sonda['estado']);
        $this->assertStringContainsString('não há o que testar', strtolower($sonda['titulo']));
        $this->assertStringContainsString('array', $sonda['texto']);
        Mail::assertNothingSent();
    }

    public function test_a_prova_sai_pelo_smtp_configurado_no_painel_para_o_endereco_digitado(): void
    {
        Mail::fake();
        $this->smtpFingido();

        $sonda = $this->sondar('maria@exemplo.com.br');

        $this->assertSame('ok', $sonda['estado'], json_encode($sonda));
        $this->assertStringContainsString('segunda via da fatura', $sonda['texto']);

        Mail::assertSent(TesteDeDisparo::class, fn (TesteDeDisparo $m) => $m->hasTo('maria@exemplo.com.br'));

        // Renderizar de verdade: `Mail::fake()` intercepta antes do template, e um Blade quebrado
        // só apareceria na produção, no instante em que a fila tentasse montar a mensagem.
        $html = (string) (new TesteDeDisparo('maria@exemplo.com.br', app(Sonda::class)->viagem(), '26/10/2026 10:00'))->render();
        $this->assertStringContainsString('maria@exemplo.com.br', $html);
        $this->assertStringContainsString('smtp.seudominio.test', $html);
    }

    public function test_a_porta_que_nao_abre_e_descrita_como_sem_resposta_e_nao_como_credencial(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            // Porta 1: recusada na hora, sem depender de rede externa nem de timeout de 30 segundos.
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'avisos@goldenrastreamento.test',
            'mail.from.name' => 'Golden Rastreamento',
        ]);

        $sonda = $this->sondar('maria@exemplo.com.br');

        $this->assertSame('sem_resposta', $sonda['estado'], json_encode($sonda));
        $this->assertStringContainsString('não atendeu', strtolower($sonda['titulo']));
        // A frase tem que apontar para porta e criptografia, nunca para credencial: quem leu
        // "credencial" aqui trocaria uma senha que estava certa. O endereço tentado aparece
        // escrito, e a criptografia é nomeada na língua de quem administra.
        $this->assertStringContainsString('127.0.0.1:1', $sonda['texto']);
        $this->assertStringContainsString('STARTTLS', $sonda['texto']);
        $this->assertStringNotContainsString('senha', strtolower($sonda['titulo']));
    }

    public function test_cada_resposta_do_servidor_vira_um_estado_diferente_do_painel(): void
    {
        $sonda = app(Sonda::class);
        $viagem = $sonda->viagem();

        $casos = [
            'Connection could not be established with host "ssl://mail.exemplo.test:465": stream_socket_client(): unable to connect' => 'sem_resposta',
            'Expected response code "250" but got code "535", with message "535-5.7.1 Authentication failed"' => 'recusada',
            'Expected response code "250" but got code "550", with message "550 Relay access denied"' => 'recusada',
            'Undefined array key "host"' => 'falhou',
        ];

        foreach ($casos as $mensagem => $estado) {
            $resultado = $sonda->descreve(new RuntimeException($mensagem), 'maria@exemplo.com.br', $viagem);

            $this->assertSame($estado, $resultado['estado'], 'Erro descrito errado: '.$mensagem);
            $this->assertStringContainsString($mensagem, $resultado['texto'], 'O detalhe do servidor sumiu da frase.');
        }
    }

    public function test_a_prova_exige_um_destino_e_nada_sai_sem_ele(): void
    {
        Mail::fake();
        $this->smtpFingido();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.email.sondar'), ['sonda_email_destino' => ''])
            ->assertSessionHasErrors('sonda_email_destino');

        Mail::assertNothingSent();
    }

    public function test_servidor_e_remetente_sao_exigidos_mas_usuario_e_senha_andam_em_par(): void
    {
        // Começa sem nada no config e sem nada no banco: é o estado de instalação, em que o
        // `.env` não diz servidor, credencial nem remetente. Zerar as três aqui é o que faz o
        // teste valer em qualquer máquina — o `.env` de desenvolvimento tem caixa de disparo
        // preenchida, e ela entraria pela porta dos fundos como "valor vigente".
        config([
            'mail.mailers.smtp.host' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => null,
        ]);

        $faltam = fn (): string => implode(' | ', ChavesDeIntegracao::faltantesDoEmail());

        $this->assertStringContainsString('Servidor SMTP', $faltam());
        $this->assertStringContainsString('Remetente', $faltam());

        Setting::set('mail_host', 'smtp.kdkhost.com.br');
        Setting::set('mail_from_address', 'avisos@goldenrastreamento.test');
        ChavesDeIntegracao::aplicar();
        $this->assertStringNotContainsString('Servidor SMTP', $faltam());

        // Usuário sem senha é meia configuração — e é o erro que a pessoa comete ao colar o e-mail
        // e esquecer a caixa de máquina. Os dois vazios, ao contrário, são relay local legítimo.
        Setting::set('mail_username', 'disparos@goldenrastreamento.test');
        ChavesDeIntegracao::aplicar();
        $this->assertStringContainsString('Senha', $faltam());

        Setting::set('mail_password', 'senha-de-maquina');
        ChavesDeIntegracao::aplicar();
        $this->assertSame([], ChavesDeIntegracao::faltantesDoEmail());

        // A metade invertida do mesmo erro: senha na mão e ninguém para autenticar com ela. Apagar
        // a linha do banco não basta — `aplicar()` só joga valor, nunca o retira, então o config
        // ainda carregaria o usuário da requisição anterior. Numa requisição de verdade isso não
        // acontece; num teste que grava três vezes na mesma vida, é preciso zerar os dois lados.
        Setting::forget('mail_username');
        config(['mail.mailers.smtp.username' => null]);
        $this->assertStringContainsString('Usuário', $faltam());
    }

    public function test_o_que_o_painel_grava_no_banco_muda_o_remetente_da_mensagem(): void
    {
        config(['mail.from.address' => 'do-ambiente@test', 'mail.from.name' => 'Nome do ambiente']);

        Setting::set('mail_from_address', 'painel@test');
        Setting::set('mail_from_name', 'Nome do painel');
        ChavesDeIntegracao::aplicar();

        $viagem = app(Sonda::class)->viagem();
        $this->assertStringContainsString('painel@test', $viagem['Remetente']);
        $this->assertStringContainsString('Nome do painel', $viagem['Remetente']);
    }

    /** O SMTP que existe só para estes testes: endereço que nada resolve, credencial completa. */
    private function smtpFingido(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.seudominio.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'disparos@seudominio.test',
            'mail.mailers.smtp.password' => 'senha-de-maquina',
            'mail.from.address' => 'disparos@seudominio.test',
            'mail.from.name' => 'Golden Rastreamento',
        ]);
    }

    /** @return array{estado: string, titulo: string, texto: string} */
    private function sondar(string $destino): array
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.email.sondar'), ['sonda_email_destino' => $destino])
            ->assertRedirect();

        return (array) session('sonda_email');
    }
}
