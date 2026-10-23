<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ChamadoResposta;
use App\Models\Activity;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Fila de atendimento: os chamados que o cliente abre no portal.
 *
 * Quem responde é sempre a equipe com a própria conta logada — a autoria sai de `user_id`
 * da mensagem, não de um campo "de" que alguém precisaria preencher certo.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $chamados = Ticket::with(['customer', 'owner'])
            ->withCount('messages')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('prioridade'), fn ($q) => $q->where('prioridade', $request->prioridade))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)->orWhere('assunto', 'like', $t)
                    ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', $t)));
            })
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.tickets.index', [
            'chamados' => $chamados,
            'statuses' => Ticket::STATUS,
            'prioridades' => Ticket::PRIORIDADES,
            'kpis' => [
                'abertos' => Ticket::whereIn('status', Ticket::ABERTOS)->count(),
                'sem_resposta' => Ticket::whereIn('status', Ticket::ABERTOS)->whereNull('respondido_em')->count(),
                'aguardando' => Ticket::where('status', 'aguardando_cliente')->count(),
                'resolvidos' => Ticket::whereIn('status', ['resolvido', 'fechado'])->count(),
            ],
        ]);
    }

    public function show(Ticket $chamado): View
    {
        return view('admin.tickets.show', [
            'chamado' => $chamado->load(['messages.customer', 'messages.user', 'customer', 'order', 'owner']),
            'statuses' => Ticket::STATUS,
            'prioridades' => Ticket::PRIORIDADES,
            'responsaveis' => User::query()
                ->whereIn('role', ['admin', 'vendedor'])
                ->where('status', 'ativo')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ]);
    }

    /**
     * Resposta da equipe. Além de gravar a mensagem, move o chamado para "aguardando você":
     * a bola passa a ser do cliente, e é isso que esvazia a fila do painel.
     */
    public function responder(Request $request, Ticket $chamado): RedirectResponse
    {
        $dados = $request->validate(['corpo' => ['required', 'string', 'max:4000']]);

        $mensagem = TicketMessage::create([
            'ticket_id' => $chamado->id,
            'user_id' => $request->user()->id,
            'corpo' => $dados['corpo'],
        ]);

        $chamado->forceFill([
            'respondido_em' => $chamado->respondido_em ?? now(),
            'owner_id' => $chamado->owner_id ?? $request->user()->id,
        ])->save();

        if (in_array($chamado->status, Ticket::ABERTOS, true)) {
            $chamado->registrarStatus('aguardando_cliente');
        }

        Activity::log('chamado_respondido', "Resposta no chamado {$chamado->numero}.", $chamado);
        $this->avisarCliente($chamado, $mensagem);

        return back()->with('success', 'Resposta enviada ao cliente.');
    }

    public function atualizarStatus(Request $request, Ticket $chamado): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Ticket::STATUS))],
            'prioridade' => ['required', 'in:'.implode(',', array_keys(Ticket::PRIORIDADES))],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $anterior = $chamado->status;
        $chamado->fill(['prioridade' => $dados['prioridade'], 'owner_id' => $dados['owner_id'] ?: null])->save();
        $chamado->registrarStatus($dados['status']);

        Activity::log('chamado_status', "Chamado {$chamado->numero}: {$anterior} → {$dados['status']}.", $chamado);

        return back()->with('success', 'Chamado atualizado.');
    }

    public function destroy(Ticket $chamado): RedirectResponse
    {
        $numero = $chamado->numero;
        $chamado->delete();
        Activity::log('chamado_excluido', "Chamado {$numero} excluído.");

        return redirect()->route('admin.chamados.index')->with('success', 'Chamado excluído.');
    }

    /** O mesmo cuidado do pedido: sem SMTP no ar a resposta continua gravada no chamado. */
    private function avisarCliente(Ticket $chamado, TicketMessage $mensagem): void
    {
        $destino = $chamado->customer?->email_portal ?: $chamado->customer?->email;

        if ($destino === null || $destino === '') {
            return;
        }

        try {
            Mail::to($destino)->queue(new ChamadoResposta(
                $chamado,
                $mensagem,
                route('portal.chamados.show', $chamado),
                'A equipe Golden respondeu o seu chamado.'
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
