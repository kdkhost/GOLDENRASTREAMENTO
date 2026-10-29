<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\SessaoDoPainel;
use App\Models\User;
use App\Services\Seguranca\DoisFatores;
use App\Services\Seguranca\VigiaDeAcesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * A tela de Segurança do acesso: o que está fechado, o que está aberto e quem bateu na porta.
 *
 * Ela existe porque blindagem sem visibilidade é suposição. Antes, ninguém podia responder "quantas
 * janelas do meu login estão abertas?", "alguém tentou a senha do vendedor esta semana?" ou "a casa
 * toda tem a segunda chave acesa?" — as três perguntas cabiam numa cabeça só, e a cabeça era a do
 * administrador que tinha escrito o sistema.
 *
 * A tela é aberta a todo perfil do painel, porque as janelas e a segunda chave são da pessoa. O que
 * é da **casa** — a lista da equipe, o histórico de tentativas recusadas dos outros, destravar uma
 * conta e apagar a chave de um colega — só aparece para o administrador, e cada um desses pedidos é
 * conferido no controller, não apenas escondido na view.
 */
class SegurancaController extends Controller
{
    /** Os atos que a tela de Segurança resume: tudo que fala de porta, não de negócio. */
    private const MOVIMENTOS = [
        'login', 'login_codigo_de_recuperacao', 'login_dispositivo_novo', 'logout',
        'login_falhou', 'dois_fatores_falhou', 'sessao_encerrada', 'conta_destravada',
        'dois_fatores_ativado', 'dois_fatores_desligado', 'dois_fatores_codigos_renovados',
    ];

    public function index(Request $request, VigiaDeAcesso $vigia): View
    {
        $usuario = $request->user();
        $atual = $request->session()->get('janela_painel');

        // `MAX(created_at)` é coluna de consulta bruta: o banco devolve texto, e a tela fala em
        // "há 3 minutos". O corte é aqui, uma vez, para a view receber data de verdade.
        $ultimos = Activity::whereIn('action', ['login', 'login_codigo_de_recuperacao'])
            ->selectRaw('user_id, MAX(created_at) as em')
            ->groupBy('user_id')
            ->pluck('em', 'user_id')
            ->map(fn (?string $em): ?Carbon => $em === null ? null : Carbon::parse($em));

        return view('admin.seguranca.index', [
            'politica' => (string) config('seguranca.dois_fatores', 'ninguem'),
            'limites' => [
                'tentativas' => $vigia->limite(),
                'janela' => (int) config('seguranca.janela_minutos', 15),
                'bloqueio' => (int) config('seguranca.bloqueio_minutos', 30),
                'sessao_minutos' => (int) config('seguranca.sessao_minutos', 120),
                'simultaneas' => (int) config('seguranca.sessoes_simultaneas', 5),
            ],
            'janelas' => SessaoDoPainel::where('user_id', $usuario->id)
                ->orderByDesc('ultimo_visto_em')->get(),
            'janela_atual' => $atual === null ? null : (int) $atual,
            'codigos_restantes' => count((array) ($usuario->two_factor_recovery_codes ?? [])),
            'travada_por' => $vigia->bloqueadoPor($usuario->email, (string) $request->ip()),
            'tentativas_na_janela' => $vigia->tentativasNaJanela($usuario->email),
            'equipe' => $usuario->isAdmin()
                ? User::orderBy('name')->get(['id', 'name', 'email', 'role', 'status', 'two_factor_secret', 'two_factor_confirmed_at'])
                    ->map(fn (User $p): array => [
                        'pessoa' => $p,
                        'janelas' => SessaoDoPainel::where('user_id', $p->id)->count(),
                        'ultimo' => $ultimos[$p->id] ?? null,
                    ])
                : collect(),
            'movimentos' => $usuario->isAdmin()
                ? Activity::whereIn('action', self::MOVIMENTOS)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->latest()->limit(40)->get()
                : collect(),
        ]);
    }

    /** Fecha uma janela desta conta. A do próprio navegador fica de fora por construção. */
    public function encerrarJanela(Request $request, VigiaDeAcesso $vigia, int $janela): RedirectResponse
    {
        $fechou = $vigia->encerrar($request->user(), $janela);

        return back()->with($fechou ? 'success' : 'warning',
            $fechou ? 'Aquela janela foi encerrada.' : 'Esta janela já não estava aberta.');
    }

    public function encerrarAsOutras(Request $request, VigiaDeAcesso $vigia): RedirectResponse
    {
        $total = $vigia->encerrarAsOutras($request->user(), $request);

        return back()->with('success', $total > 0
            ? "As outras {$total} janela(s) do seu acesso foram encerradas."
            : 'Nenhuma outra janela estava aberta.');
    }

    /**
     * Destrava uma conta travada pelo porteiro.
     *
     * Existe para o caso honesto: alguém digitou a senha errada dez vezes, o escritório inteiro está
     * atrás da mesma rede, ou um teste automatizado batendeu o limite. É só para o administrador, e
     * fica registrado — destravar sem rastro é a mesma coisa que não ter trava nenhuma.
     */
    public function destravar(Request $request, VigiaDeAcesso $vigia): RedirectResponse
    {
        $this->garantirAdministrador($request);

        $pedido = $request->validate([
            'email' => ['required', 'string', 'max:190'],
        ]);

        $email = trim($pedido['email']);
        $vigia->destravar($email);

        Activity::log('conta_destravada', "Acesso destravado para {$email}.", User::where('email', $email)->first());

        return back()->with('success', "As contagens de {$email} foram zeradas: a porta reabre agora.");
    }

    /**
     * Apaga a segunda chave de um colega — o caminho de quem perdeu o celular.
     *
     * Sem este botão, a conta de um vendedor com o telefone roubado fica fechada para o dono e
     * aberta para quem tem o aparelho, e a única saída seria mexer no banco por linha de comando.
     * Com ele, o administrador apaga, o colega é obrigado a acender de novo na próxima entrada (se a
     * política pedir), e a auditoria guarda quem fez o quê.
     */
    public function desligarDoisFatores(Request $request, DoisFatores $dois, User $usuario): RedirectResponse
    {
        $this->garantirAdministrador($request);

        if (! $usuario->doisFatoresAtivo()) {
            return back()->with('info', 'Esta conta já não tem segunda chave acesa.');
        }

        $dois->desligar($usuario);
        Activity::log('dois_fatores_desligado', "Verificação em duas etapas apagada por {$request->user()->name}.", $usuario);

        return back()->with('success', "A segunda chave de {$usuario->name} foi apagada. Na próxima entrada, uma chave nova é acesa.");
    }

    private function garantirAdministrador(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Esta ação é do administrador.');
        }
    }
}
