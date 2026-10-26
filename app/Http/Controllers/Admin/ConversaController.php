<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A caixa de entrada do assistente: as conversas que abriram na bolha do site ou do funil.
 *
 * Quem já tem conta fala pelo chamado, e esta tela não duplica aquela fila — ela existe para o
 * visitante anônimo, que é ninguém para o sistema até a primeira frase. Daí as pontes da tabela:
 * a conversa pode virar lead no meio e chamado depois, sempre com o histórico junto.
 *
 * A equipe assume a qualquer momento. `responder` é exatamente isso: a fala sai assinada por quem
 * está logado, a conversa marca `respondido_por_equipe_em` e a máquina sai de cena — o cliente nunca
 * recebe duas respostas da mesma pergunta.
 */
class ConversaController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.assistente.conversas.index', [
            'conversas' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'statuses' => Conversa::statuses(),
            'canais' => Conversa::canais(),
            'kpis' => [
                'abertas' => Conversa::where('status', 'aberta')->count(),
                'aguardando' => Conversa::where('status', 'aguardando_equipe')->count(),
                'resolvidas' => Conversa::whereIn('status', ['resolvida', 'fechada'])->count(),
            ],
        ]);
    }

    /** A mesma régua filtra a listagem e pagina a lista. */
    private function query(Request $request)
    {
        return Conversa::query()
            ->with('mensagens')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)
                    ->orWhere('contato', 'like', $t)
                    ->orWhere('pagina', 'like', $t)
                    ->orWhereHas('mensagens', fn ($m) => $m->where('corpo', 'like', $t)
                        ->orWhere('transcricao', 'like', $t)));
            })
            ->when($request->filled('canal'), fn ($q) => $q->where('canal', $request->canal))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('ultima_fala_em');
    }

    public function show(Conversa $conversa): View
    {
        return view('admin.assistente.conversas.show', [
            'conversa' => $conversa->load(['mensagens.user', 'lead', 'cliente', 'chamado']),
            'statuses' => Conversa::statuses(),
        ]);
    }

    /**
     * A fala humana. Não é um canal novo: ela assume a conversa, o que desliga o robô do resto
     * do fio e deixa registrado quem respondeu — a mesma autoria por `user_id` do chamado.
     */
    public function responder(Request $request, Conversa $conversa): RedirectResponse
    {
        $dados = $request->validate([
            'corpo' => ['required', 'string', 'max:4000'],
        ], [
            'corpo.required' => 'Escreva a resposta antes de enviar.',
        ], ['corpo' => 'resposta']);

        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'equipe',
            'user_id' => $request->user()->id,
            'tipo' => 'texto',
            'corpo' => $dados['corpo'],
        ]);

        $conversa->assumirPelaEquipe();
        $conversa->forceFill(['ultima_fala_em' => now()])->save();

        Activity::log('conversa_respondida', "Resposta da equipe na conversa {$conversa->id}.", $conversa);

        return back()->with('success', 'Sua resposta entrou na conversa.');
    }

    public function status(Request $request, Conversa $conversa): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Conversa::statuses()))],
        ], [], ['status' => 'situação']);

        $anterior = $conversa->status;
        $conversa->registrarStatus($dados['status']);

        Activity::log('conversa_status', "Conversa {$conversa->id}: {$anterior} → {$dados['status']}.", $conversa);

        return back()->with('success', 'Conversa atualizada.');
    }
}
