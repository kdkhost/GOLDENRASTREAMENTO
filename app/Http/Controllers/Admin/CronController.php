<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CronExecucao;
use App\Models\CronTarefa;
use App\Support\Agendador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A agenda do painel: a tela que substitui a edição de crontab.
 *
 * A hospedagem tem uma linha só, `* * * * * artisan schedule:run`, e ela não muda. Tudo o que
 * roda depois disso é uma linha de `cron_tarefas`, editada aqui, com histórico e botão de rodar
 * agora. A tela não executa nada por conta própria: o agendador lê a tabela no próximo minuto.
 *
 * O "rodar agora" é a exceção e existe por um motivo de operação: ninguém quer esperar até as
 * três da manhã para saber se a tarefa nova funciona. Ele abre a mesma execução que o cron abriria
 * e fecha com o mesmo resultado — muda só `acionada_por`, que é o que separa "falhou sozinho, avise
 * a equipe" de "falhou quando eu apertei, mostre para mim".
 */
class CronController extends Controller
{
    public function index(Request $request): View
    {
        $tarefas = CronTarefa::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)
                    ->orWhere('comando', 'like', $t)
                    ->orWhere('descricao', 'like', $t));
            })
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativa', $request->situacao === 'ativa'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('agenda'), fn ($q) => $q->where('agenda', $request->agenda))
            ->orderBy('id')
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.crons.index', [
            'tarefas' => $tarefas,
            'statuses' => CronTarefa::STATUS,
            'agendas' => collect(config('cron.agendas'))->map(fn (array $a): string => (string) $a['rotulo'])->all(),
            'batida_viva' => Agendador::batidaViva(),
            'segundos_sem_batida' => Agendador::segundosSemBatida(),
            'batida_em_palavras' => Agendador::batidaEmPalavras(),
            'linha_do_servidor' => '* * * * * cd ~/public_html && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1',
            'kpis' => [
                'total' => CronTarefa::count(),
                'ativas' => CronTarefa::ativas()->count(),
                'falhas' => CronTarefa::comFalha()->count(),
                'sem_batida' => CronTarefa::ativas()->get()->filter(fn (CronTarefa $t): bool => $t->estaSemBatida())->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $primeira = (string) array_key_first((array) config('cron.comandos_sugeridos'));

        return view('admin.crons.form', [
            'tarefa' => new CronTarefa([
                'comando' => $primeira,
                'agenda' => 'everyMinute',
                'ativa' => true,
                'sem_sobreposicao' => true,
            ]),
            'comandos' => $this->comandos(),
            'agendas' => collect(config('cron.agendas'))->map(fn (array $a): string => (string) $a['rotulo'])->all(),
            'dias' => config('cron.dias_semana'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tarefa = CronTarefa::create($this->validar($request));
        Activity::log('cron_criado', "Tarefa agendada {$tarefa->nome} criada ({$tarefa->comando}).", $tarefa);

        return redirect()->route('admin.crons.show', $tarefa)
            ->with('success', 'Tarefa criada. O agendador a registra na próxima batida do cron.');
    }

    public function show(CronTarefa $tarefa): View
    {
        return view('admin.crons.show', [
            'tarefa' => $tarefa,
            'execucoes' => $tarefa->execucoes()->paginate((int) config('cron.historico_na_ficha', 20)),
            'sucessos' => $tarefa->execucoes()->where('status', CronTarefa::SUCESSO)->count(),
            'falhas' => $tarefa->execucoes()->where('status', CronTarefa::FALHA)->count(),
        ]);
    }

    public function edit(CronTarefa $tarefa): View
    {
        return view('admin.crons.form', [
            'tarefa' => $tarefa,
            'comandos' => $this->comandos($tarefa->nomeComando()),
            'agendas' => collect(config('cron.agendas'))->map(fn (array $a): string => (string) $a['rotulo'])->all(),
            'dias' => config('cron.dias_semana'),
        ]);
    }

    public function update(Request $request, CronTarefa $tarefa): RedirectResponse
    {
        $tarefa->fill($this->validar($request, $tarefa))->save();
        Activity::log('cron_atualizado', "Tarefa agendada {$tarefa->nome} atualizada ({$tarefa->expressao}).", $tarefa);

        return redirect()->route('admin.crons.show', $tarefa)
            ->with('success', 'Tarefa atualizada. A nova agenda vale na próxima batida do cron.');
    }

    public function toggle(CronTarefa $tarefa): RedirectResponse
    {
        $tarefa->update(['ativa' => ! $tarefa->ativa]);
        $estado = $tarefa->ativa ? 'reativada' : 'pausada';

        Activity::log('cron_'.$estado, "Tarefa agendada {$tarefa->nome} {$estado}.", $tarefa);

        return back()->with('success', $tarefa->ativa
            ? 'Tarefa reativada. O agendador volta a chamá-la na próxima batida.'
            : 'Tarefa pausada. Ela deixa de ser registrada pelo agendador até ser reativada.');
    }

    /**
     * Rodar agora, na hora de quem clicou.
     *
     * O comando é executado dentro da requisição — por isso a recusa dos comandos da fila: um
     * worker preso por cinquenta segundos deixaria a tela girando para fazer o que o cron já faz
     * sozinho no minuto seguinte. O resto roda aqui, com o mesmo par abrir/fechar do agendador,
     * para o histórico não ter dois formatos de linha.
     */
    public function executar(Request $request, CronTarefa $tarefa): RedirectResponse
    {
        if (! $tarefa->podeRodarAgora()) {
            return back()->with('error', 'O comando "'.$tarefa->nomeComando().'" é a própria fila e não roda '
                .'dentro da requisição. Ele continua sendo executado pelo agendador, no minuto dele.');
        }

        $execucao = $tarefa->abrirExecucao(CronExecucao::POR_PAINEL, $request->user()->id);

        try {
            $codigo = Artisan::call($tarefa->comando);
            $saida = trim((string) Artisan::output());
            $ok = $codigo === 0;

            $tarefa->fecharExecucao(
                $execucao,
                $ok,
                $saida,
                $ok ? null : ($saida !== '' ? $saida : 'O comando devolveu o código '.$codigo.' sem escrever saída.')
            );
        } catch (\Throwable $falha) {
            $tarefa->fecharExecucao($execucao, false, null, $falha->getMessage());
            report($falha);

            return redirect()->route('admin.crons.show', $tarefa)
                ->with('error', 'A tarefa falhou antes de terminar: '.$falha->getMessage());
        }

        Activity::log('cron_executado', "Tarefa agendada {$tarefa->nome} executada pelo painel.", $tarefa);

        return redirect()->route('admin.crons.show', $tarefa)->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'A tarefa rodou agora e terminou bem. O resultado está no histórico.'
                : 'A tarefa rodou agora e terminou com erro. O motivo está no histórico.'
        );
    }

    public function destroy(CronTarefa $tarefa): RedirectResponse
    {
        if ($tarefa->sistema) {
            return back()->with('error', 'Esta é uma tarefa do sistema: a agenda e a pausa são suas, mas apagar '
                .'não é. Sem ela, o que ela faz para de acontecer em silêncio.');
        }

        $nome = $tarefa->nome;
        $tarefa->delete();
        Activity::log('cron_excluido', "Tarefa agendada {$nome} excluída.");

        return redirect()->route('admin.crons.index')->with('success', 'Tarefa excluída.');
    }

    /**
     * O caderno do select: os comandos que a config sugere, com o rótulo em português.
     *
     * A lista é sugestão, não permissão — quem valida é `CronTarefa::erroDoComando()` contra o
     * registro do artisan. Um comando já gravado que não esteja na lista entra com o próprio
     * nome, senão abrir a ficha de uma tarefa antiga trocaria o comando dela sem ninguém pedir.
     *
     * @return array<string,string>
     */
    private function comandos(?string $atual = null): array
    {
        $comandos = [];

        foreach ((array) config('cron.comandos_sugeridos') as $linha => $rotulo) {
            $comandos[CronTarefa::partesDoComando((string) $linha)[0]] = (string) $rotulo;
        }

        if ($atual !== null && $atual !== '') {
            $comandos[$atual] ??= 'Comando já gravado nesta tarefa';
        }

        return $comandos;
    }

    /**
     * A linha de comando vem em dois campos e é validada como uma só.
     *
     * A junção antes da validação é de propósito: quem recusa `;`, `&&` e pipe é uma regra só,
     * a do model, e ela precisa ver exatamente o texto que vai para o agendador — validar as
     * caixas separadas deixaria a combinação passar sem ser olhada.
     */
    private function validar(Request $request, ?CronTarefa $tarefa = null): array
    {
        $request->merge([
            'comando' => trim((string) $request->input('comando').' '.(string) $request->input('flags')),
        ]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('cron_tarefas', 'nome')->ignore($tarefa?->id)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'comando' => ['required', 'string', 'max:500', function (string $atributo, $valor, \Closure $falhar): void {
                if (($erro = CronTarefa::erroDoComando((string) $valor)) !== null) {
                    $falhar($erro);
                }
            }],
            'agenda' => ['required', Rule::in(array_keys((array) config('cron.agendas')))],
            'hora' => ['nullable', 'date_format:H:i'],
            'dia_semana' => ['nullable', 'required_if:agenda,weekly', 'integer', 'between:0,6'],
        ], [
            'nome.required' => 'Dê um nome à tarefa: é por ele que a listagem e os avisos a chamam.',
            'nome.unique' => 'Já existe uma tarefa com esse nome. Dois nomes iguais viram dois avisos iguais no sino.',
            'agenda.required' => 'Escolha de quanto em quanto tempo a tarefa roda.',
            'dia_semana.required_if' => 'A frequência semanal precisa do dia da semana.',
            'hora.date_format' => 'A hora vai no formato HH:MM — por exemplo, 03:30.',
        ]);

        $dados['ativa'] = $request->boolean('ativa');
        $dados['sem_sobreposicao'] = $request->boolean('sem_sobreposicao');

        // Preset que não usa hora ou dia não guarda lixo na linha: o campo some da tela, mas o
        // valor antigo continuaria no banco e reapareceria se a pessoa voltasse para a preset.
        $preset = (array) (config('cron.agendas')[$dados['agenda']] ?? []);

        if (($preset['hora'] ?? null) === null) {
            $dados['hora'] = null;
        }

        if (! ($preset['dia'] ?? false)) {
            $dados['dia_semana'] = null;
        }

        return $dados;
    }
}
