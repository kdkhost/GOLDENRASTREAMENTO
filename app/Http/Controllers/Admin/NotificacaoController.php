<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Notificacao;
use App\Models\NotificacaoPreferencia;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A central de notificações do painel: o sino, a página dedicada e a ficha de cada aviso.
 *
 * A regra que organiza tudo aqui é a linha por destinatário. Cada pessoa tem as suas, com o
 * próprio `lida_em` — abrir uma notificação tira ela do sino de quem abriu, e de mais ninguém.
 * É por isso que a leitura acontece na abertura da ficha, e não num botão: o gesto de ler é o
 * gesto de resolver, e um passo a mais transforma o sino numa caixa de entrada que ninguém
 * esvazia.
 *
 * A página dinâmica não conhece tipo nenhum: ela lê título, texto, catálogo e o contexto
 * estruturado que o aviso carrega. Um tipo novo de aviso entra pela config e já tem ficha.
 */
class NotificacaoController extends Controller
{
    public function index(Request $request): View
    {
        $minhas = Notificacao::para($request->user());

        $notificacoes = (clone $minhas)
            ->when($request->filled('situacao'), function ($q) use ($request) {
                $request->situacao === 'novas' ? $q->naovistas() : $q->vistas();
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('texto', 'like', $t));
            })
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.notificacoes.index', [
            'notificacoes' => $notificacoes,
            'tipos' => Notificacao::tiposParaFiltro(),
            'kpis' => [
                'novas' => (clone $minhas)->naovistas()->count(),
                'lidas' => (clone $minhas)->vistas()->count(),
                'hoje' => (clone $minhas)->where('created_at', '>=', now()->startOfDay())->count(),
                'vencem' => (clone $minhas)->naovistas()
                    ->whereNotNull('expira_em')
                    ->where('expira_em', '<=', now()->addDays(3))
                    ->count(),
            ],
        ]);
    }

    /**
     * A ficha do aviso — e o instante em que ele sai do sino.
     *
     * A leitura é gravada uma vez: `marcarComoLida()` não reescreve o `lida_em` de quem volta
     * à página depois. `era_nova` serve para a tela dizer, em uma linha, o que acabou de
     * acontecer com o sino.
     */
    public function show(Request $request, Notificacao $notificacao): View
    {
        abort_unless($notificacao->user_id === $request->user()->id, 404);

        $eraNova = ! $notificacao->foiLida();
        $notificacao->marcarComoLida();

        return view('admin.notificacoes.show', [
            'notificacao' => $notificacao,
            'era_nova' => $eraNova,
        ]);
    }

    /** O "marcar todas": esvazia o sino de uma vez, sem apagar o histórico da listagem. */
    public function lidas(Request $request): RedirectResponse
    {
        $quantas = Notificacao::para($request->user())->naovistas()->update(['lida_em' => now()]);

        return back()->with('success', $quantas === 0
            ? 'O sino já estava em dia: nenhuma notificação nova.'
            : $quantas.' notificaç'.($quantas === 1 ? 'ão saiu' : 'ões saíram').' do sino.');
    }

    public function destroy(Request $request, Notificacao $notificacao): RedirectResponse
    {
        abort_unless($notificacao->user_id === $request->user()->id, 404);

        $notificacao->delete();

        return redirect()->route('admin.notificacoes.index')->with('success', 'Notificação excluída.');
    }

    /**
     * O JSON do sino. É o mesmo formato que a partial renderiza no primeiro carregamento, para
     * o JavaScript reconstruir a lista sem uma segunda gramática de HTML.
     */
    public function sino(Request $request): JsonResponse
    {
        $limite = max(1, (int) config('notificacoes.sino_json_limite', 12));
        $novas = Notificacao::para($request->user())->naovistas()->latest();

        $total = (clone $novas)->count();
        $itens = (clone $novas)->limit($limite)->get()->map(fn (Notificacao $n): array => $n->paraSino())->all();

        return response()
            ->json([
                'total' => $total,
                'restantes' => max(0, $total - $limite),
                'itens' => $itens,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function preferencias(Request $request): View
    {
        $desligados = NotificacaoPreferencia::desligadosPara($request->user());

        $tipos = collect(Notificacao::catalogo())
            ->map(fn (array $item, string $tipo): array => [
                'tipo' => $tipo,
                'rotulo' => (string) ($item['rotulo'] ?? $tipo),
                'icone' => (string) ($item['icone'] ?? 'fa-bell'),
                'tom' => (string) ($item['tom'] ?? 'neutral'),
                'audiencia' => ($item['audiencia'] ?? 'equipe') === 'admin' ? 'Só administradores' : 'Toda a equipe',
                'ativo' => ! in_array($tipo, $desligados, true),
            ])
            ->values()
            ->all();

        return view('admin.notificacoes.preferencias', [
            'tipos' => $tipos,
            'retencao' => Notificacao::diasDeRetencao(),
            'expiracao' => max(1, (int) Setting::get('notificacoes_expiracao_dias', config('notificacoes.expiracao_dias_padrao', 15))),
        ]);
    }

    /**
     * O que a pessoa desligou do próprio sino, e — para o administrador — por quanto tempo as
     * notificações ficam guardadas.
     *
     * A linha de preferência só existe para o tipo desligado (o padrão é receber), então marcar
     * apaga a linha e desmarcar a cria. Os dois números são globais: eles valem para a poda de
     * todo mundo, e por isso só o administrador os envia.
     */
    public function salvarPreferencias(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'tipos' => ['array'],
            'tipos.*' => ['string'],
            'retencao' => ['nullable', 'integer', 'between:1,365'],
            'expiracao' => ['nullable', 'integer', 'between:1,365'],
        ], [
            'retencao.between' => 'A retenção fica entre 1 e 365 dias.',
            'expiracao.between' => 'O prazo de uma notificação não lida fica entre 1 e 365 dias.',
        ]);

        $catalogo = array_keys(Notificacao::catalogo());
        $marcados = array_intersect($catalogo, (array) ($dados['tipos'] ?? []));

        foreach ($catalogo as $tipo) {
            if (in_array($tipo, $marcados, true)) {
                NotificacaoPreferencia::where('user_id', $request->user()->id)->where('tipo', $tipo)->delete();
                continue;
            }

            NotificacaoPreferencia::updateOrCreate(
                ['user_id' => $request->user()->id, 'tipo' => $tipo],
                ['no_painel' => false]
            );
        }

        if ($request->user()->isAdmin() && isset($dados['retencao'], $dados['expiracao'])) {
            Setting::set('notificacoes_retencao_dias', (int) $dados['retencao']);
            Setting::set('notificacoes_expiracao_dias', (int) $dados['expiracao']);

            Activity::log('notificacoes_preferencias', 'Prazos da central de notificações atualizados.');
        }

        return back()->with('success', 'Preferências salvas. O sino passa a mostrar só o que você escolheu.');
    }
}
