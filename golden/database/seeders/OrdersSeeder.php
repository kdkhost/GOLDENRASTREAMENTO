<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::count() > 0) {
            return;
        }

        $cliente = Customer::where('status', 'ativo')->first();
        $produtos = Product::take(4)->get();
        $statuses = ['pendente', 'pago', 'enviado', 'entregue'];
        $pagamentos = ['cartao', 'boleto', 'pix'];

        foreach (range(1, 6) as $i) {
            $status = $statuses[$i % 4];
            $pedido = Order::create([
                'numero' => Order::proximoNumero(),
                'customer_id' => $cliente?->id,
                'nome' => $cliente?->nome ?: 'Cliente Demonstração',
                'telefone' => '(19) 9' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'endereco' => 'Rua de exemplo, ' . rand(10, 999),
                'cidade' => 'Três Pontas',
                'uf' => 'MG',
                'pagamento' => $pagamentos[$i % 3],
                'status' => $status,
                'pago_em' => in_array($status, ['pago', 'enviado', 'entregue']) ? now()->subDays(rand(1, 10)) : null,
                'enviado_em' => in_array($status, ['enviado', 'entregue']) ? now()->subDays(rand(1, 5)) : null,
            ]);

            $subtotal = 0;
            foreach ($produtos->random(min(2, $produtos->count())) as $produto) {
                $qtd = rand(1, 3);
                $sub = $produto->preco() * $qtd;
                OrderItem::create([
                    'order_id' => $pedido->id,
                    'product_id' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => $qtd,
                    'preco_unit_cents' => $produto->preco(),
                    'subtotal_cents' => $sub,
                ]);
                $subtotal += $sub;
            }

            $pedido->subtotal_cents = $subtotal;
            $pedido->total_cents = $subtotal;
            $pedido->save();
        }
    }
}
