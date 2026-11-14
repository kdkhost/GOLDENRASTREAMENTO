<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AlertaEmpresaMail;
use App\Models\Activity;
use App\Models\Notificacao;
use App\Models\NotificacaoPreferencia;
use App\Models\Setting;
use App\Support\Notifica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
            'email_ativo' => Setting::get('notificacoes_email_ativo', '1') === '1',
            'email_destinatarios' => Setting::get('notificacoes_email_destinatarios', Setting::get('loja_email_equipe', '')),
            'celular_ativo' => Setting::get('notificacoes_celular_ativo', '1') === '1',
            'telegram_token' => Setting::get('notificacoes_telegram_token', ''),
            'telegram_chat_id' => Setting::get('notificacoes_telegram_chat_id', ''),
            'webhook_celular' => Setting::get('notificacoes_webhook_celular', ''),
        ]);
    }

    /**
     * O que a pessoa desligou do próprio sino, e — para o administrador — por quanto tempo as
     * notificações ficam guardadas e os canais de E-mail e Celular (Telegram/Webhook).
     */
    public function salvarPreferencias(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'tipos' => ['array'],
            'tipos.*' => ['string'],
            'retencao' => ['nullable', 'integer', 'between:1,365'],
            'expiracao' => ['nullable', 'integer', 'between:1,365'],
            'email_ativo' => ['nullable', 'boolean'],
            'email_destinatarios' => ['nullable', 'string', 'max:500'],
            'celular_ativo' => ['nullable', 'boolean'],
            'telegram_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'webhook_celular' => ['nullable', 'url', 'max:500'],
        ], [
            'retencao.between' => 'A retenção fica entre 1 e 365 dias.',
            'expiracao.between' => 'O prazo de uma notificação não lida fica entre 1 e 365 dias.',
            'webhook_celular.url' => 'O endereço do webhook móvel deve ser uma URL válida (ex: https://...).',
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

        if ($request->user()->isAdmin()) {
            if (isset($dados['retencao'], $dados['expiracao'])) {
                Setting::set('notificacoes_retencao_dias', (int) $dados['retencao']);
                Setting::set('notificacoes_expiracao_dias', (int) $dados['expiracao']);
            }

            Setting::set('notificacoes_email_ativo', $request->boolean('email_ativo') ? '1' : '0');
            Setting::set('notificacoes_email_destinatarios', trim((string) ($dados['email_destinatarios'] ?? '')));
            Setting::set('notificacoes_celular_ativo', $request->boolean('celular_ativo') ? '1' : '0');
            Setting::set('notificacoes_telegram_token', trim((string) ($dados['telegram_token'] ?? '')));
            Setting::set('notificacoes_telegram_chat_id', trim((string) ($dados['telegram_chat_id'] ?? '')));
            Setting::set('notificacoes_webhook_celular', trim((string) ($dados['webhook_celular'] ?? '')));

            Activity::log('notificacoes_preferencias', 'Canais de notificação (E-mail e Celular) e prazos atualizados.');
        }

        return back()->with('success', 'Preferências de notificação e canais de celular/e-mail salvos com sucesso.');
    }

    /**
     * Dispara um teste instantâneo de notificação no celular (Telegram ou Webhook).
     */
    public function testarCelular(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $token = trim((string) Setting::get('notificacoes_telegram_token', ''));
        $chatId = trim((string) Setting::get('notificacoes_telegram_chat_id', ''));
        $webhook = trim((string) Setting::get('notificacoes_webhook_celular', ''));

        if ($token === '' && $chatId === '' && $webhook === '') {
            return back()->with('error', 'Preencha o Token e Chat ID do Telegram ou um Webhook antes de disparar o teste para o celular.');
        }

        $enviou = false;
        if ($token !== '' && $chatId !== '') {
            $enviou = Notifica::enviarTelegram(
                $token,
                $chatId,
                'lead_novo',
                '⚡ Teste de Notificação no Celular',
                'Parabéns! Sua integração de alertas diretos no smartphone está 100% ativa e pronta para avisar sobre vendas, leads, coletas e entregas.',
                route('admin.notificacoes.index'),
                ['origem' => 'Painel Administrativo', 'status' => 'Conexão Bem-Sucedida']
            );
        }

        if ($webhook !== '') {
            $enviouWebhook = Notifica::enviarWebhookCelular(
                $webhook,
                'teste',
                '⚡ Teste de Notificação no Celular',
                'Alerta de teste enviado com sucesso pela Golden Rastreamento.',
                route('admin.notificacoes.index'),
                ['app' => 'Golden Rastreamento', 'tipo' => 'teste_webhook']
            );
            $enviou = $enviou || $enviouWebhook;
        }

        if ($enviou) {
            return back()->with('success', 'Notificação de teste enviada para o celular com sucesso! Verifique seu aplicativo.');
        }

        return back()->with('error', 'Não foi possível entregar a notificação de teste no celular. Verifique o Token e Chat ID informados.');
    }

    /**
     * Dispara um e-mail de teste para os destinatários cadastrados.
     */
    public function testarEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $destinatarios = Notifica::destinatariosDeEmail();
        if (empty($destinatarios)) {
            return back()->with('error', 'Nenhum e-mail de destino configurado para receber alertas.');
        }

        try {
            Mail::to($destinatarios)->send(
                new AlertaEmpresaMail(
                    'lead_novo',
                    '⚡ Teste de Alerta por E-mail',
                    'Este é um e-mail de teste confirmando que a Golden Rastreamento está pronta para notificar o administrador sobre tudo o que acontece na empresa.',
                    route('admin.notificacoes.index'),
                    [
                        'sistema' => 'Central de Notificações Golden',
                        'data_envio' => now()->format('d/m/Y H:i:s'),
                        'destinatarios' => implode(', ', $destinatarios),
                    ]
                )
            );

            return back()->with('success', 'E-mail de teste enviado com sucesso para: '.implode(', ', $destinatarios));
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha ao enviar e-mail de teste: '.$e->getMessage());
        }
    }
}
