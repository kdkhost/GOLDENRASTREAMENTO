<?php

namespace App\Http\Controllers\Portal;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\View\View;

/**
 * Primeira tela do portal: o resumo que responde "onde está minha coisa?" sem o cliente
 * precisar navegar. Os números vêm todos da mesma ficha do CRM, nada é decorativo.
 */
class PortalController extends PortalBaseController
{
    public function inicio(): View
    {
        $cliente = $this->cliente();

        return view('portal.inicio', [
            'cliente' => $cliente,
            'pedidos' => $cliente->orders()->withCount('items')->limit(4)->get(),
            'chamados' => $cliente->tickets()->limit(3)->get(),
            'contratos' => $this->contratosDoCliente($cliente)->limit(3)->get(),
            'abertos' => $cliente->tickets()->whereIn('status', Ticket::ABERTOS)->count(),
            // Toda contagem parte de uma consulta sem a ordem embutida da relação: SUM/COUNT
            // com ORDER BY criado_at é o tipo de coisa que o SQLite aceita e o MySQL recusa.
            'investido_cents' => (int) $cliente->orders()->reorder()->where('status', '!=', 'cancelado')->sum('total_cents'),
            'totais' => [
                'pedidos' => $cliente->orders()->reorder()->count(),
                'contratos' => $this->contratosDoCliente($cliente)->reorder()->count(),
            ],
        ]);
    }

    /** Rascunho da equipe não é documento do cliente — vale no resumo e na lista. */
    private function contratosDoCliente(Customer $cliente)
    {
        return $cliente->contracts()->where('status', '!=', 'rascunho');
    }
}
