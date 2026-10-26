<?php

namespace Tests\Feature;

use App\Mail\ChamadoEquipe;
use App\Mail\ChamadoRecebido;
use App\Mail\ChamadoResposta;
use App\Mail\PedidoNovoEquipe;
use App\Mail\PedidoRecebido;
use App\Mail\RedefinirSenhaPortal;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    public function test_o_cabecalho_entrega_a_conta_e_os_dois_servidores_de_rastreamento(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // No topo o texto virou ícone: ele abre o submenu com as duas portas de quem já é
        // cliente. O portal é rota interna; o financeiro é outro sistema e sai em nova aba.
        $topo = Str::before(Str::after($html, '<nav class="nav-links"'), '</nav>');
        $this->assertStringNotContainsString('Área do cliente', $topo);

        $cabecalho = Str::before(Str::after($html, '<div class="nav-cta">'), '<button class="nav-toggle"');
        $this->assertStringContainsString('class="nav-acesso" data-acesso', $cabecalho);
        $this->assertStringContainsString('aria-controls="acessoContaMenu"', $cabecalho);
        $this->assertStringContainsString('href="'.route('portal.login').'"', $cabecalho);
        $this->assertStringContainsString('href="'.config('golden.acessos.financeiro').'" target="_blank" rel="noopener"', $cabecalho);

        // O botão dourado continua botão, com a mesma classe de sempre: o clique abre o modal.
        $this->assertStringContainsString('class="btn btn-gold btn-sm nav-especialista" data-acesso-plataformas', $html);
        $this->assertStringContainsString('class="btn btn-gold btn-sm drawer-cta" data-acesso-plataformas', $html);
        $this->assertStringContainsString('aria-controls="acessoPlataformas">Rastreamento</button>', $html);
        $this->assertStringContainsString('id="acessoPlataformas"', $html);
        $this->assertStringContainsString('href="'.config('golden.acessos.servidor_1').'" target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('href="'.config('golden.acessos.servidor_2').'" target="_blank" rel="noopener"', $html);

        // O modal mostra só o nome do servidor: o endereço fica na própria ligação, não na tela.
        $modal = Str::before(Str::after($html, 'id="acessoPlataformas"'), 'class="acesso-ajuda"');
        $this->assertStringContainsString('<span class="acesso-opcao-texto"><b>Servidor 1</b></span>', $modal);
        $this->assertStringContainsString('<span class="acesso-opcao-texto"><b>Servidor 2</b></span>', $modal);
        $this->assertStringNotContainsString('<small', $modal);
    }

    public function test_os_enderecos_de_acesso_sao_trocados_no_painel_e_o_site_obedece(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee('acesso_financeiro_url', false)
            ->assertSee('acesso_servidor_1_url', false)
            ->assertSee('acesso_servidor_2_url', false);

        // O banco vence o config: é assim que a troca feita no painel chega ao site.
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '(19) 99710-1483',
            'acesso_financeiro_url' => 'https://financeiro.exemplo.com.br/entrar',
            'acesso_servidor_1_url' => 'https://servidor-um.exemplo.com.br',
            'acesso_servidor_2_url' => 'https://servidor-dois.exemplo.com.br:9000/',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'acesso_financeiro_url', 'value' => 'https://financeiro.exemplo.com.br/entrar']);
        $this->assertDatabaseHas('settings', ['key' => 'acesso_servidor_1_url', 'value' => 'https://servidor-um.exemplo.com.br']);

        $this->get('/')->assertOk()
            ->assertSee('https://financeiro.exemplo.com.br/entrar', false)
            ->assertSee('https://servidor-um.exemplo.com.br', false)
            ->assertSee('https://servidor-dois.exemplo.com.br:9000/', false)
            ->assertDontSee(config('golden.acessos.financeiro'), false)
            ->assertDontSee(config('golden.acessos.servidor_1'), false);
    }

    /** Todo rótulo do caminho até a plataforma é editável — e em branco volta ao padrão da casa. */
    public function test_os_textos_dos_acessos_sao_trocados_no_painel_e_o_site_obedece(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee('acesso_botao_rotulo', false)
            ->assertSee('acesso_servidor_1_titulo', false)
            ->assertSee('acesso_modal_titulo', false);

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '(19) 99710-1483',
            'acesso_botao_rotulo' => 'Entrar na plataforma',
            'acesso_conta_rotulo' => 'Minha conta Golden',
            'acesso_area_titulo' => 'Clientes',
            'acesso_portal_titulo' => 'Portal Golden',
            'acesso_portal_desc' => 'Seus pedidos e chamados',
            'acesso_financeiro_titulo' => 'Financeiro',
            'acesso_financeiro_desc' => 'Boletos e notas',
            'acesso_modal_titulo' => 'Escolha a plataforma',
            'acesso_modal_desc' => 'Cada frota mora em um servidor.',
            'acesso_servidor_1_titulo' => 'Servidor Centro',
            'acesso_servidor_2_titulo' => 'Servidor Sul',
            'acesso_servidor_1_icone' => 'fa-server',
            'acesso_servidor_2_icone' => 'fa-location-dot',
            'acesso_ajuda' => 'Ficou na dúvida?',
            'acesso_ajuda_link' => 'Chame a equipe',
        ])->assertRedirect()->assertSessionHas('success');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-controls="acessoPlataformas">Entrar na plataforma</button>', $html);
        $this->assertStringContainsString('aria-label="Minha conta Golden"', $html);
        $this->assertStringContainsString('<b>Portal Golden</b><small>Seus pedidos e chamados</small>', $html);
        $this->assertStringContainsString('<b>Financeiro</b><small>Boletos e notas</small>', $html);
        $this->assertStringContainsString('<h3 id="acessoPlataformasTitulo">Escolha a plataforma</h3>', $html);
        $this->assertStringContainsString('Cada frota mora em um servidor.', $html);
        $this->assertStringContainsString('Ficou na dúvida?', $html);
        $this->assertStringContainsString('>Chame a equipe</a>', $html);

        $modal = Str::before(Str::after($html, 'id="acessoPlataformas"'), 'class="acesso-ajuda"');
        $this->assertStringContainsString('<b>Servidor Centro</b>', $modal);
        $this->assertStringContainsString('<b>Servidor Sul</b>', $modal);
        $this->assertStringContainsString('fa-server', $modal);
        $this->assertStringContainsString('fa-location-dot', $modal);

        // Campo em branco devolve o rótulo ao config: o painel sai do caminho sem apagar nada.
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '(19) 99710-1483',
            'acesso_botao_rotulo' => '',
            'acesso_servidor_1_titulo' => '',
        ])->assertSessionHas('success');

        $limpo = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-controls="acessoPlataformas">'.config('golden.acessos.textos.botao').'</button>', $limpo);
        $this->assertStringContainsString('<b>'.config('golden.acessos.textos.servidor_1_titulo').'</b>', $limpo);
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

    /**
     * Todo aviso do portal — chamado aberto, resposta da equipe, recibo, link de recuperação —
     * entra em fila, e a hospedagem tem um único cron: schedule:run. Sem o worker pendurado nele
     * o e-mail morre na tabela jobs e o cliente fica esperando uma mensagem que nunca sai. O
     * envio em si já é coberto com Mail::fake; o que só se prova aqui é quem drena a fila.
     */
    public function test_a_fila_de_avisos_do_portal_tem_quem_rodando_no_cron(): void
    {
        $worker = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) ($e->command ?? ''), 'queue:work'));

        $this->assertNotNull($worker, 'Nenhum worker agendado: os e-mails do portal ficam presos na tabela jobs.');
        $this->assertStringContainsString('--stop-when-empty', (string) $worker->command,
            'Um worker sem --stop-when-empty nunca devolve o processo e o cron da hospedagem empilha um por minuto.');
        $this->assertSame('* * * * *', $worker->getExpression(),
            'A fila precisa ser drenada a cada minuto — é a menor janela que o schedule:run da hospedagem oferece.');
        $this->assertTrue((bool) $worker->withoutOverlapping,
            'Sem withoutOverlapping dois workers consomem a mesma fila e o cliente recebe o aviso em dobro.');
    }

    /**
     * O portal é o painel administrativo visto pela porta do cliente: mesma carcaça do AdminLTE
     * (navbar, sidebar com treeview, cabeçalho de página, rodapé), os mesmos pacotes e o mesmo
     * jogo de scripts. Se uma das duas layouts esquecer um dos pedaços, os dois deixam de ser
     * o mesmo produto — e é isso que a trava abaixo não deixa acontecer de novo.
     */
    public function test_o_portal_usa_a_mesma_caraca_do_painel(): void
    {
        $admin = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $portal = file_get_contents(resource_path('views/portal/layout.blade.php'));

        foreach ([
            'class="app-wrapper"',
            'class="app-header navbar',
            'class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"',
            'class="app-main"',
            'app-content-header',
            'class="app-footer',
            'admin.partials.theme-init',
            'admin.partials.sidebar-menu',
            'admin.partials.scripts',
            'css/golden-theme.css',
            'css/admin-golden.css',
            "@yield('page_title'",
            "@yield('page_actions'",
            'admin-lte@4.0.0-beta2/dist/css/adminlte.min.css',
        ] as $trecho) {
            $this->assertStringContainsString($trecho, $admin, "Faltou «{$trecho}» na layout do painel.");
            $this->assertStringContainsString($trecho, $portal, "Faltou «{$trecho}» na layout do portal — ele deixou de ser o painel.");
        }
    }

    /**
     * A sidebar do portal vem de config('golden.menu_portal') e do mesmo MenuPainel do painel:
     * o grupo da página aberta nasce aberto, o item atual fica ativo e a bolha conta o que está
     * no nome do cliente, não o total da empresa.
     */
    public function test_a_sidebar_do_portal_acende_a_rota_aberta_e_a_bolha_do_cliente(): void
    {
        $cliente = $this->conta();
        $this->chamado($cliente);
        $this->pedido($cliente, ['status' => 'pendente']);
        $this->actingAs($cliente, 'cliente');

        $html = $this->get(route('portal.chamados'))->assertOk()->getContent();

        $this->assertStringContainsString('<i class="nav-icon fas fa-headset"', $html, 'A sidebar do portal perdeu o grupo de atendimento.');
        $this->assertStringContainsString('menu-open', $html, 'O grupo da página aberta não nasceu aberto.');
        $this->assertStringContainsString('data-lte-toggle="treeview"', $html, 'A sidebar do portal voltou a ser lista plana, sem treeview.');
        $this->assertMatchesRegularExpression('/class="nav-link active".*?<i class="nav-icon fas fa-life-ring"/s', $html,
            'O item do menu não acendeu na página aberta.');
        $this->assertStringContainsString('<p>Meus chamados', $html, 'A sidebar do portal não lista "Meus chamados".');
        $this->assertStringContainsString('badge-menu tone-amber ms-auto">1<', $html,
            'A bolha de chamado aberto do cliente não apareceu na sidebar.');
        $this->assertStringContainsString('badge-menu tone-blue ms-auto">1<', $html,
            'A bolha de pedido em aberto do cliente não apareceu na sidebar.');

        $outro = $this->conta(['email_portal' => 'outra@exemplo.com.br', 'documento' => '987.654.321-00']);
        $this->actingAs($outro, 'cliente');
        $semNada = $this->get(route('portal.inicio'))->assertOk()->getContent();
        $this->assertStringNotContainsString('badge-menu tone-amber', $semNada,
            'A bolha de chamado apareceu para quem não tem chamado nenhum.');
    }

    /**
     * Mail::fake() intercepta o envio antes de construir o objeto, então um `->autor` escrito sem
     * parêntese — e `autor` não é coluna, é método — passava verde nos testes e estourava na
     * produção no instante em que a fila tentava montar o assunto. Renderizar de verdade é a única
     * forma de ver o mailable inteiro: envelope, view e cada acesso a dado do modelo.
     */
    public function test_todo_aviso_por_e_mail_renderiza_de_verdade(): void
    {
        $cliente = $this->conta();
        $chamado = $this->chamado($cliente);
        $abertura = $chamado->messages()->oldest()->firstOrFail();
        $respostaDaEquipe = TicketMessage::create([
            'ticket_id' => $chamado->id,
            'user_id' => $this->admin->id,
            'corpo' => 'Técnico agendado para amanhã à tarde.',
        ]);

        $avisos = [
            'ChamadoEquipe' => new ChamadoEquipe($chamado),
            'ChamadoRecebido' => new ChamadoRecebido($chamado),
            'ChamadoResposta para a equipe' => new ChamadoResposta(
                $chamado,
                $abertura,
                route('admin.chamados.show', $chamado),
                'O cliente respondeu o chamado.',
            ),
            'ChamadoResposta para o cliente' => new ChamadoResposta(
                $chamado,
                $respostaDaEquipe,
                route('portal.chamados.show', $chamado),
                'A equipe respondeu o seu chamado.',
            ),
            'RedefinirSenhaPortal' => new RedefinirSenhaPortal(
                $cliente,
                URL::temporarySignedRoute('portal.senha.redefinir', now()->addHour(), ['cliente' => $cliente->id]),
                60,
            ),
        ];

        foreach ($avisos as $nome => $aviso) {
            $this->assertNotEmpty($aviso->envelope()->subject, "O aviso «{$nome}» está sem assunto.");
            $this->assertStringContainsString('<body', $aviso->render(), "O aviso «{$nome}» renderizou sem corpo.");
        }

        // Os quatro avisos do chamado carregam o número em toda parte — é por ele que cliente e
        // equipe batem a conversa com a ficha.
        foreach (['ChamadoEquipe', 'ChamadoRecebido', 'ChamadoResposta para a equipe',
            'ChamadoResposta para o cliente'] as $nome) {
            $this->assertStringContainsString($chamado->numero, $avisos[$nome]->envelope()->subject,
                "O aviso «{$nome}» saiu do ar com um assunto sem número de chamado.");
        }

        // Os dois lados da mesma conversa assinam com quem escreveu: a equipe pelo nome do painel,
        // o cliente pelo nome da ficha — é esse autor() que já derrubou o envio.
        $this->assertStringContainsString($this->admin->name, $avisos['ChamadoResposta para o cliente']->render());
        $this->assertStringContainsString($cliente->nome, $avisos['ChamadoResposta para a equipe']->render());
        $this->assertStringContainsString($abertura->autor(), (string) $avisos['ChamadoResposta para a equipe']->envelope()->subject);
        $this->assertStringContainsString($cliente->nome, $avisos['RedefinirSenhaPortal']->render());
    }

    /**
     * Enquanto não há SMTP, o aviso sai pelo driver `log`, que grava a mensagem inteira no nível
     * debug. A produção roda com LOG_LEVEL=warning: o rastro do e-mail era filtrado junto com o
     * resto e não havia como conferir se o cliente foi avisado — o `queue:work` dizia DONE e
     * nada sobrava. O canal do e-mail tem nível próprio, então o registro existe com qualquer
     * LOG_LEVEL da aplicação.
     */
    public function test_o_aviso_gravado_em_log_deixa_rastro_apesar_do_log_level(): void
    {
        $canal = config('mail.mailers.log.channel');
        $this->assertNotNull($canal,
            'O mailer log voltou a herdar o canal padrão: com LOG_LEVEL=warning nenhum e-mail fica registrado.');

        $this->assertSame('debug', config("logging.channels.$canal.level"),
            "O canal {$canal} filtraria o aviso, que é gravado em nível debug.");

        $this->assertNotSame(config('logging.channels.single.path'), config("logging.channels.$canal.path"),
            'O rastro dos e-mails precisa estar fora do log da aplicação, que tem nível e rotatividade próprios.');
    }

    /**
     * O recibo da loja e o alerta interno de venda nova saem do mesmo molde dos avisos do portal:
     * um acesso errado no mailable derruba o e-mail do comprador na fila, silenciosamente.
     */
    public function test_os_avisos_da_loja_renderizam_de_verdade(): void
    {
        $cliente = $this->conta();

        foreach (['PedidoNovoEquipe' => new PedidoNovoEquipe($this->pedido($cliente)),
            'PedidoRecebido' => new PedidoRecebido($this->pedido($cliente))] as $nome => $aviso) {
            $html = $aviso->render();
            $this->assertStringContainsString('Rastreador veicular', $html,
                "O aviso «{$nome}» perdeu a linha do item comprado.");
            $this->assertStringContainsString('R$ 234,00', $html,
                "O aviso «{$nome}» não traz o total do pedido.");
        }
    }
}
