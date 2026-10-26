<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CronTarefa;
use App\Models\Notificacao;
use App\Models\NotificacaoPreferencia;
use App\Models\Setting;
use App\Models\User;
use App\Support\Notifica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A central de notificações: uma linha por destinatário, o sino que esvazia na leitura e a
 * página que serve a qualquer tipo de aviso sem conhecer tipo nenhum.
 *
 * O teste que mais importa aqui é o da linha por pessoa: se a leitura de um apagasse o aviso
 * do outro, ou se "marcar todas" limpasse o sino da equipe inteira, o sino passaria a mentir —
 * e um sino que mente é um sino que ninguém abre.
 */
class CentralDeNotificacoesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->vendedor = User::where('email', 'vendedor@golden.com.br')->firstOrFail();
    }

    public function test_cada_pessoa_da_equipe_recebe_a_propria_linha(): void
    {
        $criadas = Notifica::disparar('lead_novo', 'Lead novo no funil', 'Chegou pelo formulário.', '7');

        $this->assertSame(2, $criadas);
        $this->assertSame(1, Notificacao::para($this->admin)->count());
        $this->assertSame(1, Notificacao::para($this->vendedor)->count());
        $this->assertSame('Lead novo no funil', Notificacao::para($this->vendedor)->firstOrFail()->titulo);
    }

    public function test_o_aviso_que_so_o_administrador_configura_nao_vai_para_o_vendedor(): void
    {
        $this->assertSame(1, Notifica::disparar('depoimento_novo', 'Depoimento novo', 'Aprovado pelo Google.'));

        $this->assertSame(0, Notificacao::para($this->vendedor)->count());
    }

    public function test_a_mesma_chave_nao_repete_o_mesmo_fato(): void
    {
        Notifica::disparar('cron_falhou', 'A tarefa falhou', null, '1', [], 'cron_falhou:1:'.now()->toDateString());
        $segunda = Notifica::disparar('cron_falhou', 'A tarefa falhou', null, '1', [], 'cron_falhou:1:'.now()->toDateString());

        $this->assertSame(0, $segunda);
        $this->assertSame(1, Notificacao::para($this->admin)->count());
    }

    public function test_o_tipo_desligado_nao_gera_aviso_para_quem_desligou(): void
    {
        NotificacaoPreferencia::create(['user_id' => $this->vendedor->id, 'tipo' => 'lead_novo', 'no_painel' => false]);

        $this->assertSame(1, Notifica::disparar('lead_novo', 'Lead novo', null, '8'));
        $this->assertSame(0, Notificacao::para($this->vendedor)->count());
        $this->assertSame(1, Notificacao::para($this->admin)->count());
    }

    public function test_abrir_a_ficha_marca_como_lida_e_tira_do_sino_de_quem_abriu(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', 'Chegou pelo funil.', '9');

        $minha = Notificacao::para($this->admin)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.notificacoes.show', $minha))
            ->assertOk()
            ->assertSee('Lida agora.');

        $this->assertNotNull($minha->refresh()->lida_em);
        $this->assertSame(0, Notificacao::para($this->admin)->naovistas()->count());
        $this->assertSame(1, Notificacao::para($this->vendedor)->naovistas()->count());
    }

    public function test_a_ficha_de_outra_pessoa_nao_abre(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', null, '10');
        $doVendedor = Notificacao::para($this->vendedor)->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.notificacoes.show', $doVendedor))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.notificacoes.destroy', $doVendedor))->assertNotFound();
    }

    public function test_marcar_todas_limpa_so_o_sino_de_quem_pediu(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', null, '11');
        Notifica::disparar('pedido_novo', 'Pedido pago', null, '12');

        $this->actingAs($this->admin)
            ->post(route('admin.notificacoes.lidas'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Notificacao::para($this->admin)->naovistas()->count());
        $this->assertSame(2, Notificacao::para($this->admin)->vistas()->count());
        $this->assertSame(2, Notificacao::para($this->vendedor)->naovistas()->count());
    }

    public function test_o_json_do_sino_respeita_o_teto_e_nao_e_cacheado(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Notifica::disparar('lead_novo', 'Lead '.$i, null, (string) $i);
        }

        $resposta = $this->actingAs($this->admin)
            ->getJson(route('admin.notificacoes.sino'))
            ->assertOk();

        $this->assertStringContainsString('no-store', (string) $resposta->headers->get('Cache-Control'));

        $resposta->assertJson([
            'total' => 15,
            'restantes' => 3,
        ]);

        $item = $resposta->json('itens.0');

        $this->assertSame(['id', 'titulo', 'texto', 'rotulo', 'icone', 'tom', 'quando', 'ficha', 'destino'], array_keys($item));
        $this->assertSame(route('admin.notificacoes.show', $item['id']), $item['ficha']);
        $this->assertCount(12, $resposta->json('itens'));
    }

    public function test_o_aviso_vencido_sai_do_sino_sem_esperar_a_poda(): void
    {
        $nova = Notificacao::create([
            'tipo' => 'lead_novo',
            'titulo' => 'Ainda vale',
            'user_id' => $this->admin->id,
        ]);
        Notificacao::create([
            'tipo' => 'lead_novo',
            'titulo' => 'Vencida',
            'user_id' => $this->admin->id,
            'expira_em' => now()->subMinute(),
        ]);

        $this->assertSame(1, Notificacao::para($this->admin)->naovistas()->count());
        $this->assertSame($nova->id, Notificacao::para($this->admin)->naovistas()->firstOrFail()->id);
    }

    public function test_todo_tipo_do_catalogo_aponta_para_uma_rota_que_existe(): void
    {
        foreach (Notificacao::catalogo() as $tipo => $item) {
            $this->assertTrue(
                Route::has((string) $item['rota']),
                "O tipo {$tipo} aponta para a rota {$item['rota']}, que não existe."
            );
        }

        $tarefa = CronTarefa::firstOrFail();
        $aviso = Notificacao::create([
            'tipo' => 'cron_falhou',
            'titulo' => 'A tarefa falhou',
            'rota' => 'admin.crons.show',
            'rota_param' => (string) $tarefa->id,
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(route('admin.crons.show', $tarefa), $aviso->link());
    }

    public function test_a_listagem_e_a_pagina_dinamica_abrem_para_a_equipe(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', 'Chegou pelo funil.', '13');
        $minha = Notificacao::para($this->admin)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.notificacoes.index'))
            ->assertOk()
            ->assertSee('Lead novo');

        $this->actingAs($this->vendedor)
            ->get(route('admin.notificacoes.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.notificacoes.show', $minha))
            ->assertOk()
            ->assertSee('Chegou pelo funil.');
    }

    public function test_excluir_apaga_so_da_central_de_quem_excluiu(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', null, '14');
        $minha = Notificacao::para($this->admin)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.notificacoes.destroy', $minha))
            ->assertRedirect(route('admin.notificacoes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notificacoes', ['id' => $minha->id]);
        $this->assertSame(1, Notificacao::para($this->vendedor)->count());
    }

    public function test_as_preferencias_desligam_o_tipo_e_voltar_a_ligar_apaga_a_linha(): void
    {
        $this->actingAs($this->admin);

        $tipos = array_keys(Notificacao::catalogo());
        $semLead = array_values(array_diff($tipos, ['lead_novo']));

        $this->post(route('admin.notificacoes.preferencias.salvar'), [
            'tipos' => $semLead,
            'retencao' => 20,
            'expiracao' => 10,
        ])->assertRedirect();

        $this->assertFalse(NotificacaoPreferencia::emPainel($this->admin, 'lead_novo'));
        $this->assertTrue(NotificacaoPreferencia::emPainel($this->admin, 'pedido_novo'));
        $this->assertSame(1, Notifica::disparar('lead_novo', 'Lead novo', null, '15'));
        $this->assertSame(0, Notificacao::para($this->admin)->count());
        $this->assertSame(1, Notificacao::para($this->vendedor)->count());
        $this->assertSame(20, (int) Setting::get('notificacoes_retencao_dias'));
        $this->assertSame(10, (int) Setting::get('notificacoes_expiracao_dias'));
        $this->assertSame(20, Notificacao::diasDeRetencao());
        $this->assertDatabaseHas('activities', ['action' => 'notificacoes_preferencias']);

        $this->post(route('admin.notificacoes.preferencias.salvar'), ['tipos' => $tipos])->assertRedirect();

        $this->assertTrue(NotificacaoPreferencia::emPainel($this->admin, 'lead_novo'));
        $this->assertSame(0, NotificacaoPreferencia::where('user_id', $this->admin->id)->count());
    }

    public function test_so_o_administrador_muda_os_prazos_da_central(): void
    {
        Setting::set('notificacoes_retencao_dias', 30);
        Setting::set('notificacoes_expiracao_dias', 15);

        $this->actingAs($this->vendedor)
            ->post(route('admin.notificacoes.preferencias.salvar'), [
                'tipos' => array_keys(Notificacao::catalogo()),
                'retencao' => 5,
                'expiracao' => 5,
            ])->assertRedirect();

        $this->assertSame(30, (int) Setting::get('notificacoes_retencao_dias'));
        $this->assertSame(15, (int) Setting::get('notificacoes_expiracao_dias'));
    }

    public function test_o_sino_aparece_no_topo_do_painel_com_o_contador(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-sino-url="'.route('admin.notificacoes.sino').'"', $html);
        $this->assertStringContainsString('data-sino-contador>0</span>', $html);
        $this->assertStringContainsString('sino-contador d-none', $html);
    }

    public function test_o_contador_do_sino_some_na_leitura_e_para_no_99_mais(): void
    {
        Notifica::disparar('lead_novo', 'Lead novo', null, '16');

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('data-sino-contador>1</span>', $html);

        for ($i = 0; $i < 105; $i++) {
            Notificacao::create(['tipo' => 'lead_novo', 'titulo' => 'Lead '.$i, 'user_id' => $this->admin->id]);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('data-sino-contador>99+</span>', $html);
    }
}
