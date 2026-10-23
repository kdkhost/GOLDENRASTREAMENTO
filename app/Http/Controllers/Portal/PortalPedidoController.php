<?php

namespace App\Http\Controllers\Portal;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pedidos do cliente. Toda consulta sai de `$this->cliente()->orders()`, então o identificador
 * da URL só escolhe qual pedido DAQUELE cliente abrir — trocar o número na barra de endereços
 * devolve 404, não a compra do vizinho.
 */
class PortalPedidoController extends PortalBaseController
{
    public function index(Request $request): View
    {
        $pedidos = $this->cliente()->orders()
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('portal.pedidos', [
            'pedidos' => $pedidos,
            'statuses' => \App\Models\Order::STATUS,
        ]);
    }

    public function show(int $pedido): View
    {
        return view('portal.pedido', [
            'pedido' => $this->cliente()->orders()->with(['items', 'histories'])->findOrFail($pedido),
        ]);
    }
}
