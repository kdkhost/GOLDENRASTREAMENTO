<?php

namespace App\Http\Controllers\Portal;

use App\Mail\ChamadoEquipe;
use App\Mail\ChamadoRecebido;
use App\Mail\ChamadoResposta;
use App\Models\Activity;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chamados do cliente: abrir, acompanhar a conversa e responder.
 *
 * A conversa é sempre a fila de `ticket_messages` do chamado — a primeira mensagem é o relato
 * que abriu o ticket. Por isso a tela do portal e a do painel leem o mesmo histórico e não
 * existe campo "última resposta" para sincronizar.
 */
class PortalChamadoController extends PortalBaseController
{
    public function index(Request $request): View
    {
        $chamados = $this->cliente()->tickets()
            ->withCount('messages')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('portal.chamados', [
            'chamados' => $chamados,
            'statuses' => Ticket::STATUS,
            'abertos' => $this->cliente()->tickets()->whereIn('status', Ticket::ABERTOS)->count(),
        ]);
    }

    public function criar(Request $request): View
    {
        return view('portal.chamado-criar', [
            'pedidos' => $this->cliente()->orders()->limit(30)->get(),
            'prioridades' => Ticket::PRIORIDADES,
            'pedido' => $request->integer('pedido') ?: null,
        ]);
    }

    public function registrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'assunto' => ['required', 'string', 'max:180'],
            'corpo' => ['required', 'string', 'max:4000'],
            'prioridade' => ['nullable', 'string', 'in:'.implode(',', array_keys(Ticket::PRIORIDADES))],
            'pedido_id' => ['nullable', 'integer'],
        ]);

        $chamado = Ticket::create([
            'customer_id' => $this->cliente()->id,
            'order_id' => $this->pedidoDoCliente($request, $dados['pedido_id'] ?? null),
            'assunto' => $dados['assunto'],
            'canal' => 'portal',
            'status' => 'aberto',
            'prioridade' => $dados['prioridade'] ?? 'normal',
        ]);

        $mensagem = TicketMessage::create([
            'ticket_id' => $chamado->id,
            'customer_id' => $this->cliente()->id,
            'corpo' => $dados['corpo'],
        ]);

        Activity::log('portal_chamado_aberto', "Chamado {$chamado->numero}: {$chamado->assunto}", $chamado, por: $this->cliente()->nome);

        $this->avisarEquipe(new ChamadoEquipe($chamado));
        $this->avisarCliente(new ChamadoRecebido($chamado));

        return redirect()->route('portal.chamados.show', $chamado)
            ->with('success', "Chamado {$chamado->numero} aberto. A equipe responde por aqui e por e-mail.");
    }

    public function show(Ticket $chamado): View
    {
        return view('portal.chamado', [
            'chamado' => $this->autorizar($chamado)->load(['order', 'messages.user', 'messages.customer']),
        ]);
    }

    public function responder(Request $request, Ticket $chamado): RedirectResponse
    {
        $dados = $request->validate(['corpo' => ['required', 'string', 'max:4000']]);
        $chamado = $this->autorizar($chamado);

        $mensagem = TicketMessage::create([
            'ticket_id' => $chamado->id,
            'customer_id' => $this->cliente()->id,
            'corpo' => $dados['corpo'],
        ]);

        // O cliente falou: o chamado volta para a fila da equipe, nunca fica "esperando você".
        if ($chamado->status === 'aguardando_cliente') {
            $chamado->registrarStatus('em_analise');
        }

        Activity::log('portal_chamado_respondeu', "Resposta no chamado {$chamado->numero}", $chamado, por: $this->cliente()->nome);

        $this->avisarEquipe(new ChamadoResposta(
            $chamado,
            $mensagem,
            route('admin.chamados.show', $chamado),
            'O cliente respondeu este chamado no portal.'
        ));

        return redirect()->route('portal.chamados.show', $chamado)
            ->with('success', 'Sua mensagem foi enviada.');
    }

    /** O chamado só existe para o dono da ficha. Os demais são 404, não 403 com pista. */
    private function autorizar(Ticket $chamado): Ticket
    {
        abort_unless($chamado->customer_id === $this->cliente()->id, 404);

        return $chamado;
    }

    /** Vincula o pedido indicado apenas se ele for do cliente; senão o chamado nasce sem vínculo. */
    private function pedidoDoCliente(Request $request, mixed $pedidoId): ?int
    {
        if ($pedidoId === null || $pedidoId === '') {
            return null;
        }

        $id = $this->cliente()->orders()->whereKey((int) $pedidoId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
