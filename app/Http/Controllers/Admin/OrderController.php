<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $pedidos = Order::with('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)->orWhere('nome', 'like', $t)->orWhere('telefone', 'like', $t));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'pedidos' => $pedidos,
            'faturamento' => Order::whereNotIn('status', ['cancelado', 'pendente'])->sum('total_cents'),
            'statuses' => Order::STATUS,
        ]);
    }

    public function show(Order $pedido): View
    {
        return view('admin.orders.show', [
            'pedido' => $pedido->load(['items.product', 'customer', 'user']),
            'statuses' => Order::STATUS,
            'clientes' => Customer::orderBy('nome')->get(),
        ]);
    }

    public function atualizarStatus(Request $request, Order $pedido): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Order::STATUS))],
            'pagamento' => ['nullable', 'in:' . implode(',', array_keys(Order::PAGAMENTOS))],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);

        $anterior = $pedido->status;
        $pedido->fill($data);
        if ($data['status'] === 'pago' && $anterior !== 'pago' && !$pedido->pago_em) {
            $pedido->pago_em = now();
        }
        if ($data['status'] === 'enviado' && !$pedido->enviado_em) {
            $pedido->enviado_em = now();
        }
        $pedido->save();

        Activity::log('pedido_status', "Pedido {$pedido->numero}: {$anterior} → {$data['status']}.", $pedido);

        return back()->with('success', 'Pedido atualizado.');
    }

    public function destroy(Order $pedido): RedirectResponse
    {
        $numero = $pedido->numero;
        $pedido->delete();
        Activity::log('pedido_excluido', "Pedido {$numero} excluído.");

        return redirect()->route('admin.pedidos.index')->with('success', 'Pedido excluído.');
    }
}
