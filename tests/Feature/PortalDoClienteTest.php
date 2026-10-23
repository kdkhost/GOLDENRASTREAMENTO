<?php

namespace Tests\Feature;

use App\Mail\ChamadoEquipe;
use App\Mail\ChamadoRecebido;
use App\Mail\ChamadoResposta;
use App\Mail\RedefinirSenhaPortal;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Portal do cliente: a ficha do CRM é a conta, o guard 'cliente' é a porta e nada atravessa
 * para o painel. O auto-cadastro procura a ficha que existe (documento, depois e-mail) em vez
 * de criar gêmea e herda os pedidos feitos como convidado. Pedido, chamado e contrato de um
 * cliente nunca aparecem para o outro — e devolvem 404, não 403, para não entregar a lista de
 * ids existentes. O chamado é uma conversa só: portal e painel escrevem na mesma fila de
 * mensagens, e cada fala avisa por e-mail sem depender do SMTP estar de pé.
 */
class PortalDoClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    /** Ficha de CRM sem conta no portal — é exatamente o que o auto-cadastro procura. */
    private function ficha(array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'nome' => 'Maria Compradora',
            'documento' => '123.456.789-01',
            'tipo' => 'fisica',
            'telefone' => '(35) 99888-7766',
            'email' => 'maria@exemplo.com.br',
            'status' => 'ativo',
        ], $extra));
    }

    /** Ficha que já tem chave de acesso: e-mail de acesso próprio + senha. */
    private function conta(array $extra = []): Customer
    {
        $cliente = $this->ficha($extra);
        $cliente->email_portal = $extra['email_portal'] ?? 'maria@exemplo.com.br';
        $cliente->password = 'senha-portal-123';
        $cliente->save();

        return $cliente->fresh();
    }

    private function pedido(?Customer $dono, array $extra = []): Order
    {
        $pedido = Order::create(array_merge([
            'customer_id' => $dono?->id,
            'nome' => $dono?->nome ?: 'Convidado',
            'email' => $dono?->email ?: 'convidado@exemplo.com.br',
            'telefone' => $dono?->telefone,
            'entrega' => 'entrega',
            'endereco' => 'Av. Brasil, 1200',
            'bairro' => 'Centro',
            'cidade' => 'Três Pontas',
            'uf' => 'MG',
            'cep' => '37160000',
            'pagamento' => 'pix',
            'status' => 'pago',
            'subtotal_cents' => 19900,
            'frete_cents' => 3500,
            'total_cents' => 23400,
            'consent_given' => true,
        ], $extra));

        OrderItem::create([
            'order_id' => $pedido->id,
            'nome' => 'Rastreador veicular J16 4G/2G',
            'sku' => 'J16-4G',
            'quantidade' => 1,
            'preco_unit_cents' => 19900,
            'subtotal_cents' => 19900,
        ]);

        return $pedido;
    }

    private function chamado(Customer $dono, array $extra = []): Ticket
    {
        $chamado = Ticket::create(array_merge([
            'customer_id' => $dono->id,
            'assunto' => 'Equipamento fora do ar',
            'canal' => 'portal',
            'status' => 'aberto',
            'prioridade' => 'normal',
        ], $extra));

        TicketMessage::create([
            'ticket_id' => $chamado->id,
            'customer_id' => $dono->id,
            'corpo' => 'Não recebo posição desde ontem.',
        ]);

        return $chamado;
    }

    private function contrato(Customer $dono, string $status = 'assinado', ?string $arquivo = null): Contract
    {
        return Contract::create([
            'customer_id' => $dono->id,
            'titulo' => 'Termo de adesão da plataforma',
            'tipo' => 'adesao',
            'status' => $status,
            'arquivo' => $arquivo,
            'celebrado_em' => now()->subDays(10)->toDateString(),
            'vigencia_ate' => now()->addYear()->toDateString(),
            'valor_mensal_cents' => 9900,
        ]);
    }

    /** @return array<string, mixed> O formulário do painel inteiro, com o PDF que ele envia. */
    private function dadosContrato(Customer $cliente, string $arquivo, string $status): array
    {
        return [
            'customer_id' => $cliente->id,
            'titulo' => 'Termo de adesão da frota de 5 veículos',
            'tipo' => 'adesao',
            'status' => $status,
            'arquivo' => $arquivo,
            'celebrado_em' => now()->subDays(3)->toDateString(),
            'vigencia_ate' => now()->addYear()->toDateString(),
            'valor_mensal' => '99.00',
            'observacoes' => 'Instalação por conta do cliente.',
        ];
    }

    public function test_nenhuma_tela_do_portal_abre_sem_login(): void
    {
        foreach (['portal.inicio', 'portal.pedidos', 'portal.chamados', 'portal.chamados.criar', 'portal.contratos', 'portal.dados'] as $nome) {
            $this->get(route($nome))->assertRedirect(route('portal.login'));
        }

        $this->get(route('portal.inicio'))->assertSessionHas('warning');
    }

    public function test_auto_cadastro_reaproveita_a_ficha_do_crm_e_o_pedido_de_convidado(): void
    {
        $ficha = $this->ficha();
        $solto = $this->pedido(null, ['email' => 'maria@exemplo.com.br']);

        $resposta = $this->post(route('portal.cadastro.post'), [
            'nome' => 'Maria Compradora',
            'email' => 'Maria@Exemplo.com.br',
            'telefone' => '(35) 99888-7766',
            'documento' => '12345678901',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
            'consent' => '1',
        ]);

        // Uma ficha só: o auto-cadastro entregou a chave à que já existia, não criou gêmea.
        $this->assertSame(1, Customer::where('email', 'maria@exemplo.com.br')->count());
        $resposta->assertRedirect(route('portal.inicio'))->assertSessionHas('success');
        $this->assertAuthenticatedAs($ficha, 'cliente');

        $ficha->refresh();
        $this->assertSame('maria@exemplo.com.br', $ficha->email_portal);
        $this->assertTrue(Hash::check('senha-nova-123', (string) $ficha->password));
        $this->assertNotNull($ficha->ultimo_acesso_em);

        // O pedido feito sem conta passa a pertencer à ficha — é a primeira tela que ele vê.
        $this->assertSame($ficha->id, $solto->fresh()->customer_id);
        $this->assertDatabaseHas('activities', ['action' => 'portal_autocadastro', 'subject_id' => $ficha->id]);

        // ...e o portal abre com o pedido na tela.
        $this->get(route('portal.inicio'))->assertOk()->assertSee($solto->numero);
    }

    public function test_auto_cadastro_de_quem_nunca_passou_pela_golden_cria_a_ficha(): void
    {
        $antes = Customer::count();

        $this->post(route('portal.cadastro.post'), [
            'nome' => 'Newton Primeiro Acesso',
            'email' => 'newton@exemplo.com.br',
            'telefone' => '(35) 91234-5678',
            'documento' => '987.654.321-09',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
            'consent' => '1',
        ])->assertRedirect(route('portal.inicio'));

        $novo = Customer::latest('id')->firstOrFail();
        $this->assertSame($antes + 1, Customer::count());
        $this->assertSame('98765432109', $novo->documento);
        $this->assertSame('fisica', $novo->tipo);
        $this->assertSame('ativo', $novo->status);
        $this->assertAuthenticatedAs($novo, 'cliente');
        $this->assertDatabaseHas('activities', ['action' => 'portal_criou_ficha', 'subject_id' => $novo->id]);
    }

    public function test_o_portal_nao_abre_duas_contas_para_o_mesmo_email_de_acesso(): void
    {
        $this->conta();

        $this->post(route('portal.cadastro.post'), [
            'nome' => 'Outra Maria',
            'email' => 'maria@exemplo.com.br',
            'telefone' => '(35) 90000-0000',
            'documento' => '11122233344',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
            'consent' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('cliente');
        $this->assertSame(1, Customer::where('email_portal', 'maria@exemplo.com.br')->count());
    }

    public function test_o_login_usa_o_email_de_acesso_e_nao_o_email_da_ficha(): void
    {
        $cliente = $this->conta(['email' => 'gestora@exemplo.com.br', 'email_portal' => 'portal@exemplo.com.br']);
        $cliente->password = 'senha-portal-123';
        $cliente->email = 'gestora@exemplo.com.br';
        $cliente->email_portal = 'portal@exemplo.com.br';
        $cliente->save();

        // O e-mail do CRM não é credencial: duas fichas podem compartilhá-lo.
        $this->post(route('portal.login.post'), ['email' => 'gestora@exemplo.com.br', 'password' => 'senha-portal-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('cliente');

        $this->post(route('portal.login.post'), ['email' => 'portal@exemplo.com.br', 'password' => 'errada-123'])
            ->assertSessionHasErrors('email');

        $this->post(route('portal.login.post'), ['email' => 'portal@exemplo.com.br', 'password' => 'senha-portal-123'])
            ->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticatedAs($cliente, 'cliente');
        $this->assertDatabaseHas('activities', ['action' => 'portal_login', 'subject_id' => $cliente->id]);
    }

    public function test_ficha_desativada_no_crm_perde_o_acesso_do_portal(): void
    {
        $cliente = $this->conta(['status' => 'inativo']);

        $this->post(route('portal.login.post'), ['email' => 'maria@exemplo.com.br', 'password' => 'senha-portal-123'])
            ->assertSessionHasErrors('email');

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.inicio'))
            ->assertRedirect(route('portal.login'))
            ->assertSessionHas('error');
        $this->assertGuest('cliente');
    }

    public function test_a_sessao_do_portal_nao_abre_o_painel(): void
    {
        $this->conta();

        $this->post(route('portal.login.post'), ['email' => 'maria@exemplo.com.br', 'password' => 'senha-portal-123'])
            ->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticated('cliente');

        $this->get(route('portal.inicio'))->assertOk();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/clientes')->assertRedirect(route('login'));

        // Sair do portal fecha só esta sessão — e a ficha fica registrada na auditoria.
        $this->post(route('portal.sair'))->assertRedirect(route('site.home'));
        $this->assertGuest('cliente');
        $this->assertDatabaseHas('activities', ['action' => 'portal_sair']);
    }

    public function test_a_sessao_do_painel_nao_abre_o_portal(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/dashboard')->assertOk();

        foreach (['portal.inicio', 'portal.pedidos', 'portal.contratos', 'portal.dados'] as $nome) {
            $this->get(route($nome))->assertRedirect(route('portal.login'));
        }
    }

    public function test_cliente_ve_o_proprio_pedido_e_nao_o_do_vizinho(): void
    {
        $cliente = $this->conta();
        $vizinha = $this->ficha(['nome' => 'Vizinha Oliveira', 'email' => 'vizinha@exemplo.com.br', 'documento' => '646.855.555.55'.rand(10, 99)]);

        $meu = $this->pedido($cliente);
        $deles = $this->pedido($vizinha);

        $this->actingAs($cliente, 'cliente');

        $this->get(route('portal.pedidos'))->assertOk()->assertSee($meu->numero)->assertDontSee($deles->numero);
        $this->get(route('portal.pedidos.show', $meu))->assertOk()
            ->assertSee('Rastreador veicular J16 4G/2G')
            ->assertSee('Pix');
        // Id alheio na barra de endereços é 404: sem pista de que o pedido existe.
        $this->get(route('portal.pedidos.show', $deles))->assertNotFound();
    }

    public function test_chamado_aberto_no_portal_atende_no_painel_e_avisa_por_email(): void
    {
        Mail::fake();
        $cliente = $this->conta();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente, 'cliente');
        $this->post(route('portal.chamados.registrar'), [
            'assunto' => 'O rastreador parou de enviar posição',
            'corpo' => 'Desde ontem à noite a plataforma não mostra o caminhão.',
            'prioridade' => 'alta',
            'pedido_id' => $pedido->id,
        ])->assertRedirect();

        $chamado = Ticket::firstOrFail();
        $this->assertMatchesRegularExpression('/^CH\d{2}-\d{5}$/', $chamado->numero);
        $this->assertSame('aberto', $chamado->status);
        $this->assertSame('portal', $chamado->canal);
        $this->assertSame('alta', $chamado->prioridade);
        $this->assertSame($pedido->id, $chamado->order_id);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $chamado->id,
            'customer_id' => $cliente->id,
            'corpo' => 'Desde ontem à noite a plataforma não mostra o caminhão.',
        ]);
        Mail::assertQueued(ChamadoEquipe::class);
        Mail::assertQueued(ChamadoRecebido::class);

        // A equipe responde pelo painel: a autoria sai do user_id da mensagem.
        $this->actingAs($this->admin);
        $this->post(route('admin.chamados.responder', $chamado), ['corpo' => 'Chamamos a assistência técnica do seu estado.'])
            ->assertSessionHas('success');

        $chamado->refresh();
        $this->assertSame('aguardando_cliente', $chamado->status);
        $this->assertNotNull($chamado->respondido_em);
        $this->assertSame($this->admin->id, $chamado->owner_id);
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $chamado->id, 'user_id' => $this->admin->id]);
        Mail::assertQueued(ChamadoResposta::class);

        // A mesma fila aparece para os dois lados.
        $this->actingAs($cliente, 'cliente');
        $this->get(route('portal.chamados.show', $chamado))->assertOk()
            ->assertSee('Chamamos a assistência técnica do seu estado.')
            ->assertSee($chamado->numero);

        // O cliente retruca: a bola volta para a equipe.
        $this->post(route('portal.chamados.responder', $chamado), ['corpo' => 'Obrigada, vou aguardar.'])
            ->assertRedirect(route('portal.chamados.show', $chamado));
        $this->assertSame('em_analise', $chamado->fresh()->status);

        $this->assertDatabaseHas('activities', ['action' => 'portal_chamado_aberto']);
        $this->assertDatabaseHas('activities', ['action' => 'chamado_respondido']);
    }

    public function test_chamado_de_outro_cliente_e_um_404_para_as_duas_telas(): void
    {
        $cliente = $this->conta();
        $vizinha = $this->ficha(['nome' => 'Vizinha Oliveira', 'email' => 'vizinha@exemplo.com.br', 'documento' => '858.123.456-85']);
        $chamado = $this->chamado($vizinha);

        $this->actingAs($cliente, 'cliente')
            ->get(route('portal.chamados.show', $chamado))
            ->assertNotFound();

        $this->get(route('portal.chamados'))->assertOk()->assertDontSee($chamado->numero);
    }

    public function test_rascunho_de_contrato_nao_chega_ao_portal_e_o_pdf_e_do_dono(): void
    {
        Storage::fake('public');
        $caminho = 'contratos/termo-adesao-12345678.pdf';
        Storage::disk('public')->put($caminho, "%PDF-1.4\nfake do teste\n%%EOF");

        $cliente = $this->conta();
        $vizinha = $this->ficha(['nome' => 'Vizinha Oliveira', 'email' => 'vizinha@exemplo.com.br', 'documento' => '858.123.456-85']);

        $publicado = $this->contrato($cliente, 'assinado', $caminho);
        $rascunho = $this->contrato($cliente, 'rascunho', $caminho);
        $deles = $this->contrato($vizinha, 'vigente', $caminho);

        $this->actingAs($cliente, 'cliente');

        $this->get(route('portal.contratos'))->assertOk()
            ->assertSee($publicado->numero)
            ->assertDontSee($rascunho->numero)
            ->assertDontSee($deles->numero);

        $this->get(route('portal.contratos.arquivo', $publicado))->assertOk();
        $this->get(route('portal.contratos.arquivo', $rascunho))->assertNotFound();
        $this->get(route('portal.contratos.arquivo', $deles))->assertNotFound();

        // Sem arquivo no disco não há o que baixar — nem rota, nem botão.
        $vazio = $this->contrato($cliente, 'vigente');
        $this->get(route('portal.contratos.arquivo', $vazio))->assertNotFound();
    }

    public function test_a_recuperacao_de_senha_nao_diz_quem_e_cliente(): void
    {
        Mail::fake();
        $cliente = $this->conta();

        $this->post(route('portal.senha.esquecer.post'), ['email' => 'ninguem@exemplo.com.br'])
            ->assertSessionHas('success');
        $respostaPadrao = $this->app['session']->get('success');

        $this->post(route('portal.senha.esquecer.post'), ['email' => 'maria@exemplo.com.br'])
            ->assertSessionHas('success', $respostaPadrao);

        // As duas respostas dizem exatamente a mesma coisa; só uma delas dispara e-mail.
        Mail::assertQueued(RedefinirSenhaPortal::class, 1);

        $link = URL::temporarySignedRoute('portal.senha.redefinir', now()->addMinutes(60), ['cliente' => $cliente->id]);
        $this->get($link)->assertOk();

        $this->post($link, ['password' => 'senha-recuperada-123', 'password_confirmation' => 'senha-recuperada-123'])
            ->assertRedirect(route('portal.login'));
        $this->assertTrue(Hash::check('senha-recuperada-123', (string) $cliente->fresh()->password));

        // Link velho não vale: sem assinatura válida a tela devolve ao login.
        $velho = URL::temporarySignedRoute('portal.senha.redefinir', now()->subMinute(), ['cliente' => $cliente->id]);
        $this->get($velho)->assertRedirect(route('portal.login'))->assertSessionHas('error');
        $this->post($velho, ['password' => 'outra-qualquer-123', 'password_confirmation' => 'outra-qualquer-123'])
            ->assertRedirect(route('portal.login'));
    }

    public function test_trocar_a_senha_na_tela_meus_dados_exige_a_senha_atual(): void
    {
        $cliente = $this->conta();
        $this->actingAs($cliente, 'cliente');

        $this->post(route('portal.senha.salvar'), [
            'senha_atual' => 'senha-trocada-999',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasErrors('senha_atual');
        $this->assertTrue(Hash::check('senha-portal-123', (string) $cliente->fresh()->password));

        $this->post(route('portal.senha.salvar'), [
            'senha_atual' => 'senha-portal-123',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHas('success');
        $this->assertTrue(Hash::check('nova-senha-123', (string) $cliente->fresh()->password));
    }

    public function test_meus_dados_corrige_o_cadastro_sem_tocar_no_documento(): void
    {
        $cliente = $this->conta();
        $this->pedido($cliente);
        $this->actingAs($cliente, 'cliente');

        $this->get(route('portal.dados'))->assertOk()->assertSee('Maria Compradora');

        $this->post(route('portal.dados.salvar'), [
            'nome' => 'Maria C. Oliveira',
            'telefone' => '(35) 91111-2222',
            'email' => 'maria@exemplo.com.br',
            'endereco' => 'Rua Nova, 10',
            'cidade' => 'Três Pontas',
            'uf' => 'mg',
            'cep' => '37.160-000',
        ])->assertSessionHas('success');

        $cliente->refresh();
        $this->assertSame('Maria C. Oliveira', $cliente->nome);
        $this->assertSame('MG', $cliente->uf);
        $this->assertSame('37160000', $cliente->cep);
        // O documento é a chave do vínculo com pedido e contrato: o portal não reescreve.
        $this->assertSame('123.456.789-01', $cliente->documento);
        $this->assertSame('maria@exemplo.com.br', $cliente->email_portal);
        $this->assertDatabaseHas('activities', ['action' => 'portal_dados_atualizados', 'subject_id' => $cliente->id]);
    }

    public function test_o_site_oferece_a_entrada_do_portal_e_muda_de_destino_quem_ja_entrou(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Área do cliente')
            ->assertSee('/portal/entrar', false);

        $cliente = $this->conta();
        $this->actingAs($cliente, 'cliente');

        $this->get('/')->assertOk()->assertSee('href="'.route('portal.inicio').'"', false);
    }

    public function test_o_contrato_publicado_no_painel_chega_ao_portal_com_o_pdf(): void
    {
        Storage::fake('public');
        $cliente = $this->conta();

        // O PDF entra pelo dropzone do painel, como em qualquer campo de envio.
        $this->actingAs($this->admin);
        $envio = $this->post(route('admin.uploads.store'), [
            'campo' => Contract::CAMPO_ENVIO,
            'arquivo' => UploadedFile::fake()->create('termo-adesao.pdf', 120, 'application/pdf'),
        ])->assertOk()->json();
        $caminho = $envio['arquivo']['caminho'];

        $this->post(route('admin.contratos.store'), $this->dadosContrato($cliente, $caminho, 'rascunho'))
            ->assertSessionHas('success');

        $contrato = Contract::firstOrFail();
        $this->assertMatchesRegularExpression('/^CT\d{2}-\d{4}$/', $contrato->numero);
        $this->assertSame(9900, $contrato->valor_mensal_cents);
        $this->assertTrue($contrato->temArquivo());

        // Rascunho é documento interno: o portal ainda não mostra nem oferece o PDF.
        $this->actingAs($cliente, 'cliente');
        $this->get(route('portal.contratos'))->assertDontSee($contrato->numero);
        $this->get(route('portal.contratos.arquivo', $contrato))->assertNotFound();

        $this->actingAs($this->admin);
        $this->post(route('admin.contratos.update', $contrato), $this->dadosContrato($cliente, $caminho, 'vigente'))
            ->assertSessionHas('success');

        // Caminho que não saiu do dropzone não cola: o campo do formulário é texto livre.
        $this->post(route('admin.contratos.update', $contrato), $this->dadosContrato($cliente, 'contratos/forjado.pdf', 'vigente'))
            ->assertSessionHasErrors('arquivo');

        $this->actingAs($cliente, 'cliente');
        $this->get(route('portal.contratos'))->assertOk()->assertSee($contrato->numero);
        $this->get(route('portal.contratos.arquivo', $contrato))->assertOk();

        // Excluir pelo painel apaga o PDF junto — nada órfão em storage/contratos.
        $this->actingAs($this->admin)->delete(route('admin.contratos.destroy', $contrato))->assertSessionHas('success');
        $this->assertFalse(Storage::disk('public')->exists($caminho));
        $this->assertDatabaseMissing('contracts', ['id' => $contrato->id]);
    }

    public function test_a_ficha_do_crm_mostra_se_a_pessoa_tem_conta_no_portal(): void
    {
        $cliente = $this->conta();
        $semConta = $this->ficha(['nome' => 'Sem Conta Cardoso', 'email' => 'semconta@exemplo.com.br', 'documento' => '231.456.789-02']);

        $this->actingAs($this->admin);
        $this->get(route('admin.clientes.show', $cliente))->assertOk()
            ->assertSee('conta ativa')
            ->assertSee('maria@exemplo.com.br');
        $this->get(route('admin.clientes.show', $semConta))->assertOk()->assertSee('sem conta');

        // Chamado e contrato do cliente aparecem na mesma ficha que o portal lê.
        $this->chamado($cliente);
        $this->contrato($cliente);
        $this->get(route('admin.clientes.show', $cliente))->assertOk()
            ->assertSee('Termo de adesão da plataforma')
            ->assertSee('Equipamento fora do ar');
    }
}
