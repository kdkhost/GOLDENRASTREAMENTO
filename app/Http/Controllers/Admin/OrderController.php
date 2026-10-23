<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $pedidos = Order::withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)->orWhere('nome', 'like', $t)->orWhere('telefone', 'like', $t));
            })
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.orders.index', [
            'pedidos' => $pedidos,
            'kpis' => [
                'total' => Order::count(),
                'pendentes' => Order::where('status', 'pendente')->count(),
                'entregues' => Order::where('status', 'entregue')->count(),
            ],
            'faturamento' => Order::whereNotIn('status', ['cancelado', 'pendente'])->sum('total_cents'),
            'statuses' => Order::STATUS,
        ]);
    }

    public function show(Order $pedido): View
    {
        return view('admin.orders.show', [
            'pedido' => $pedido->load(['items.product', 'customer', 'user', 'histories.user']),
            'statuses' => Order::STATUS,
            'clientes' => Customer::orderBy('nome')->get(),
        ]);
    }

    /**
     * Muda status/pagamento e grava o marco na linha do tempo que o cliente vê. As datas
     * de pago_em/enviado_em são consequência do status (Order::registrarStatus).
     */
    public function atualizarStatus(Request $request, Order $pedido): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Order::STATUS))],
            'pagamento' => ['nullable', 'in:'.implode(',', array_keys(Order::PAGAMENTOS))],
            'notas' => ['nullable', 'string', 'max:5000'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $anterior = $pedido->status;
        $pedido->registrarStatus($data['status'], $data['observacao'] ?: null, $request->user()->id);
        $pedido->update(['pagamento' => $data['pagamento'], 'notas' => $data['notas'] ?? null]);

        Activity::log('pedido_status', "Pedido {$pedido->numero}: {$anterior} → {$data['status']}.", $pedido);

        return back()->with('success', 'Pedido atualizado.');
    }

    public function destroy(Order $pedido): RedirectResponse
    {
        $numero = $pedido->numero;
        foreach ((array) $pedido->documentos as $caminho) {
            Envio::remover($caminho, 'pedido_documento');
        }
        $pedido->delete();
        Activity::log('pedido_excluido', "Pedido {$numero} excluído.");

        return redirect()->route('admin.pedidos.index')->with('success', 'Pedido excluído.');
    }

    /**
     * Documentos do pedido (comprovante, nota fiscal, proposta). Os arquivos já estão no
     * disco — entraram pelo campo de arrastar-e-soltar —, então aqui só se reconcilia a
     * lista: o que saiu deixa de existir, o que ficou não é regravado.
     */
    public function documentos(Request $request, Order $pedido): RedirectResponse
    {
        $enviados = array_values(array_unique(array_filter((array) $request->input('documentos', []))));

        if (count($enviados) > 12) {
            return back()->with('error', 'São no máximo 12 documentos por pedido.');
        }

        foreach ($enviados as $caminho) {
            if (! is_string($caminho) || ! Envio::caminhoConfere($caminho, 'pedido_documento')) {
                throw ValidationException::withMessages([
                    'documentos' => 'Um dos arquivos não foi carregado por aqui. Solte no campo de novo.',
                ]);
            }
        }

        foreach (array_diff((array) $pedido->documentos, $enviados) as $saindo) {
            Envio::remover($saindo, 'pedido_documento');
        }

        $pedido->update(['documentos' => $enviados ?: null]);
        Activity::log('pedido_documentos', "Pedido {$pedido->numero}: ".count($enviados).' documento(s) anexado(s).', $pedido);

        return back()->with('success', 'Documentos atualizados.');
    }
}
