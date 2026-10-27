<?php

namespace Tests\Feature;

use App\Models\CronExecucao;
use App\Models\CronTarefa;
use App\Models\Notificacao;
use App\Models\User;
use App\Support\Agendador;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A agenda mora no banco, e é a tela que a governa.
 *
 * A hospedagem tem uma única linha de crontab; tudo o que roda depois dela é uma linha de
 * `cron_tarefas`, e é por isso que estes testes existem: se a tela deixar de gravar o que o
 * agendador lê, ninguém percebe até a fila de e-mail parar de drenar em silêncio — que é o
 * tipo de falha em que nada quebra e nada reclama.
 */
class CentralDeCronsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->actingAs($this->admin);
    }

    public function test_a_agenda_nasce_com_as_quatro_tarefas_que_o_sistema_ja_fazia(): void
    {
        $this->assertSame(4, CronTarefa::doSistema()->count());
        $this->assertSame(CronTarefa::count(), CronTarefa::ativas()->count());
        $this->assertSame([], CronTarefa::ativas()->get()->filter(fn (CronTarefa $t): bool => $t->expressao === '')->all());
    }

    public function test_a_tela_mostra_a_linha_unica_da_hospedagem(): void
    {
        $this->get(route('admin.crons.index'))
            ->assertOk()
            ->assertSee('schedule:run')
            ->assertSee('Linha registrada no cron da hospedagem')
            ->assertSee('Drenar a fila de envios');
    }

    public function test_a_expressao_nasce_da_preset_na_escrita(): void
    {
        $this->post(route('admin.crons.store'), [
            'nome' => 'Aquecer o cache da vitrine',
            'descricao' => 'Tarefa de teste.',
            'comando' => 'cache:clear',
            'flags' => '',
            'agenda' => 'daily',
            'hora' => '03:30',
            'ativa' => 1,
            'sem_sobreposicao' => 1,
        ])->assertRedirect();

        $tarefa = CronTarefa::where('nome', 'Aquecer o cache da vitrine')->firstOrFail();

        $this->assertSame('30 03 * * *', $tarefa->expressao);
        $this->assertSame('cache:clear', $tarefa->comando);
        $this->assertTrue($tarefa->ativa);
        $this->assertFalse($tarefa->sistema);
        $this->assertDatabaseHas('activities', ['action' => 'cron_criado']);
    }

    public function test_a_preset_sem_hora_nao_guarda_hora_velha_na_linha(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Tarefa diária',
            'comando' => 'cache:clear',
            'agenda' => 'daily',
            'hora' => '03:00',
            'ativa' => true,
        ]);

        $this->post(route('admin.crons.update', $tarefa), [
            'nome' => $tarefa->nome,
            'comando' => 'cache:clear',
            'flags' => '',
            'agenda' => 'everyMinute',
            'hora' => '03:00',
            'ativa' => 1,
        ])->assertRedirect(route('admin.crons.show', $tarefa));

        $tarefa->refresh();

        $this->assertSame('* * * * *', $tarefa->expressao);
        $this->assertNull($tarefa->hora);
    }

    public function test_o_agendador_so_carrega_as_tarefas_ativas(): void
    {
        $tarefa = CronTarefa::firstOrFail();
        $tarefa->update(['ativa' => false]);

        $ids = collect(Agendador::tarefas())->pluck('id')->all();

        $this->assertNotContains($tarefa->id, $ids);
        $this->assertCount(CronTarefa::ativas()->count(), $ids);

        $this->post(route('admin.crons.toggle', $tarefa))->assertRedirect();

        $this->assertContains($tarefa->id, collect(Agendador::tarefas())->pluck('id')->all());
        $this->assertDatabaseHas('activities', ['action' => 'cron_reativada']);
    }

    public function test_o_comando_recusa_shell_e_comando_que_nao_existe(): void
    {
        $this->post(route('admin.crons.store'), [
            'nome' => 'Injeção',
            'comando' => 'cache:clear',
            'flags' => '&& rm -rf /',
            'agenda' => 'everyMinute',
            'ativa' => 1,
        ])->assertSessionHasErrors('comando');

        $this->post(route('admin.crons.store'), [
            'nome' => 'Comando fantasma',
            'comando' => 'nao:existe',
            'flags' => '',
            'agenda' => 'everyMinute',
            'ativa' => 1,
        ])->assertSessionHasErrors('comando');

        $this->assertSame(0, CronTarefa::whereIn('nome', ['Injeção', 'Comando fantasma'])->count());
    }

    public function test_o_comando_que_apaga_dados_nao_entra_na_agenda(): void
    {
        $this->post(route('admin.crons.store'), [
            'nome' => 'Limpar tudo',
            'comando' => 'migrate:fresh',
            'flags' => '',
            'agenda' => 'everyMinute',
            'ativa' => 1,
        ])->assertSessionHasErrors('comando');

        $this->assertSame(0, CronTarefa::where('nome', 'Limpar tudo')->count());
    }

    public function test_rodar_agora_deixa_o_mesmo_rastro_do_agendador(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Limpar o cache',
            'comando' => 'cache:clear',
            'agenda' => 'hourly',
            'hora' => '00:10',
            'ativa' => true,
        ]);

        $this->post(route('admin.crons.executar', $tarefa))
            ->assertRedirect(route('admin.crons.show', $tarefa))
            ->assertSessionHas('success');

        $execucao = $tarefa->execucoes()->firstOrFail();

        $this->assertSame(CronExecucao::POR_PAINEL, $execucao->acionada_por);
        $this->assertSame($this->admin->id, $execucao->user_id);
        $this->assertSame(CronTarefa::SUCESSO, $execucao->status);
        $this->assertNotNull($execucao->fim);
        $this->assertSame(CronTarefa::SUCESSO, $tarefa->refresh()->status);
        $this->assertDatabaseHas('activities', ['action' => 'cron_executado']);
    }

    public function test_o_worker_da_fila_nao_roda_dentro_da_requisicao(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Drenar a fila agora',
            'comando' => 'queue:work --stop-when-empty',
            'agenda' => 'everyMinute',
            'ativa' => true,
        ]);

        $this->assertFalse($tarefa->podeRodarAgora());

        $this->post(route('admin.crons.executar', $tarefa))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $tarefa->execucoes()->count());
    }

    public function test_a_tarefa_do_sistema_pode_ser_pausada_mas_nao_apagada(): void
    {
        $tarefa = CronTarefa::where('sistema', true)->firstOrFail();

        $this->delete(route('admin.crons.destroy', $tarefa))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cron_tarefas', ['id' => $tarefa->id]);

        $propria = CronTarefa::create([
            'nome' => 'Tarefa da casa',
            'comando' => 'cache:clear',
            'agenda' => 'daily',
            'hora' => '04:00',
            'ativa' => true,
        ]);

        $this->delete(route('admin.crons.destroy', $propria))->assertRedirect(route('admin.crons.index'));

        $this->assertDatabaseMissing('cron_tarefas', ['id' => $propria->id]);
        $this->assertDatabaseHas('activities', ['action' => 'cron_excluido']);
    }

    public function test_a_ficha_conta_sucesso_e_falha_do_historico(): void
    {
        $tarefa = CronTarefa::firstOrFail();
        $execucao = $tarefa->abrirExecucao();
        $tarefa->fecharExecucao($execucao, true, 'ok');

        $this->get(route('admin.crons.show', $tarefa))
            ->assertOk()
            ->assertSee('Histórico de execuções')
            ->assertSee('ok');
    }

    public function test_uma_tarefa_atrasada_e_reconhecida_pela_tolerancia_da_preset(): void
    {
        $diaria = CronTarefa::create([
            'nome' => 'Diária atrasada',
            'comando' => 'cache:clear',
            'agenda' => 'daily',
            'hora' => '03:00',
            'ativa' => true,
        ]);
        $diaria->forceFill(['created_at' => now()->subDays(3), 'rodou_em' => now()->subDays(3)])->save();

        $semanal = CronTarefa::create([
            'nome' => 'Semanal no prazo',
            'comando' => 'rastreio:podar',
            'agenda' => 'weekly',
            'hora' => '03:00',
            'dia_semana' => 2,
            'ativa' => true,
        ]);
        $semanal->forceFill(['created_at' => now()->subDays(3), 'rodou_em' => now()->subDays(3)])->save();

        $this->assertTrue($diaria->estaSemBatida());
        $this->assertFalse($semanal->estaSemBatida());
    }

    public function test_a_poda_da_presenca_avisa_uma_vez_por_tarefa_por_dia(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Tarefa que parou',
            'comando' => 'cache:clear',
            'agenda' => 'daily',
            'hora' => '03:00',
            'ativa' => true,
        ]);
        $tarefa->forceFill(['rodou_em' => now()->subDays(4)])->save();

        // A batida-cardíaco precisa estar viva: sem ela o comando não acusa tarefa nenhuma.
        Cache::put((string) config('cron.batida.chave_no_cache'), now()->timestamp);

        Artisan::call('notificacoes:apodrecer');
        Artisan::call('notificacoes:apodrecer');

        $this->assertSame(1, Notificacao::where('chave', 'cron_pausado:'.$tarefa->id.':'.now()->toDateString())
            ->where('user_id', $this->admin->id)
            ->count());
    }

    public function test_o_vendedor_nao_entra_na_agenda(): void
    {
        $this->actingAs(User::where('email', 'vendedor@golden.com.br')->firstOrFail());

        $this->get(route('admin.crons.index'))->assertForbidden();
        $this->post(route('admin.crons.toggle', CronTarefa::firstOrFail()))->assertForbidden();
    }

    public function test_a_linha_da_hospedagem_e_a_mesma_que_o_guia_documenta(): void
    {
        $linha = $this->get(route('admin.crons.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ea-php83', $linha);
        $this->assertStringContainsString('2&gt;&amp;1', $linha);
        $this->assertStringContainsString('value="* * * * * cd ~/public_html', $linha);
    }

    /**
     * A volta inteira do agendador: a linha do banco virou evento, o evento rodou, e o
     * histórico fechou com a saída do comando dentro.
     *
     * É este teste que falta quando a framework muda o contrato do callback. Ela entrega a saída
     * num parâmetro chamado `output` typeado `Stringable`; com outro nome o container tenta
     * resolver sozinho, lança `Unable to resolve dependency` e a execução nasce aberta e morre
     * aberta — sem linha vermelha na tela e sem aviso no sino.
     */
    public function test_o_agendador_fecha_o_historico_com_a_saida_do_comando(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Limpar o cache pela agenda',
            'comando' => 'cache:clear',
            'agenda' => 'everyMinute',
            'ativa' => true,
        ]);

        $this->eventoDaAgenda($tarefa)->run(app());

        $execucao = $tarefa->execucoes()->firstOrFail();

        $this->assertSame(CronExecucao::POR_AGENDAMENTO, $execucao->acionada_por);
        $this->assertSame(CronTarefa::SUCESSO, $execucao->status);
        $this->assertNotNull($execucao->fim);
        $this->assertStringContainsString('Application cache cleared', (string) $execucao->saida);
        $this->assertSame(CronTarefa::SUCESSO, $tarefa->refresh()->status);
    }

    public function test_a_falha_do_comando_fecha_o_historico_e_avisa_a_equipe(): void
    {
        $tarefa = CronTarefa::create([
            'nome' => 'Tarefa que não existe',
            'comando' => 'golden:nao-existe-no-teste',
            'agenda' => 'everyMinute',
            'ativa' => true,
        ]);

        $this->eventoDaAgenda($tarefa)->run(app());

        $execucao = $tarefa->execucoes()->firstOrFail();

        $this->assertSame(CronTarefa::FALHA, $execucao->status);
        $this->assertNotNull($execucao->fim);
        $this->assertNotEmpty((string) $execucao->erro);
        $this->assertSame(CronTarefa::FALHA, $tarefa->refresh()->status);
        $this->assertDatabaseHas('notificacoes', [
            'chave' => 'cron_falhou:'.$tarefa->id.':'.now()->toDateString(),
        ]);
    }

    /** O evento que o `Agendador` montou para esta linha, no schedule de verdade. */
    private function eventoDaAgenda(CronTarefa $tarefa): Event
    {
        $agenda = app(Schedule::class);
        Agendador::registrar($agenda);

        return collect($agenda->events())
            ->firstOrFail(fn (Event $evento): bool => $evento->description === $tarefa->nome);
    }
}
