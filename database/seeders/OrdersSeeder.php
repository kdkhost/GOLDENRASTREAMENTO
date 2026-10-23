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
            $pago = in_array($status, ['pago', 'enviado', 'entregue'], true);
            $enviado = in_array($status, ['enviado', 'entregue'], true);

            $pedido = Order::create([
                'customer_id' => $cliente?->id,
                'nome' => $cliente?->nome ?: 'Cliente Demonstração',
                'email' => $cliente?->email ?: 'cliente@exemplo.com.br',
                'telefone' => '(19) 9'.rand(1000, 9999).'-'.rand(1000, 9999),
                'tipo_pessoa' => 'juridica',
                'documento' => '19'.rand(100000, 999999).rand(1000, 9999).'0001'.rand(10, 99),
                'entrega' => $i % 3 === 0 ? 'retira' : 'entrega',
                'endereco' => 'Rua de exemplo, '.rand(10, 999),
                'bairro' => 'Centro',
                'cidade' => 'Três Pontas',
                'uf' => 'MG',
                'cep' => '37160000',
                'frete_cents' => $i % 3 === 0 ? 0 : 3500,
                'pagamento' => $pagamentos[$i % 3],
                'status' => $status,
                'consent_given' => true,
                'pago_em' => $pago ? now()->subDays(rand(1, 10)) : null,
                'enviado_em' => $enviado ? now()->subDays(rand(1, 5)) : null,
            ]);

            $subtotal = 0;

            foreach ($produtos->random(min(2, $produtos->count())) as $produto) {
                $qtd = rand(1, 3);
                $sub = $produto->preco() * $qtd;
                OrderItem::create([
                    'order_id' => $pedido->id,
                    'product_id' => $produto->id,
                    'nome' => $produto->nome,
                    'sku' => $produto->sku,
                    'quantidade' => $qtd,
                    'preco_unit_cents' => $produto->preco(),
                    'subtotal_cents' => $sub,
                ]);
                $subtotal += $sub;
            }

            $pedido->subtotal_cents = $subtotal;
            $pedido->total_cents = $subtotal + $pedido->frete_cents;
            $pedido->save();

            // Linha do tempo coerente com o status final, para a página pública ter o que mostrar.
            $etapas = array_slice(Order::JORNADA, 0, array_search($status, Order::JORNADA, true) + 1);

            foreach ($etapas as $index => $etapa) {
                $pedido->histories()->create([
                    'status' => $etapa,
                    'observacao' => $index === 0 ? 'Pedido criado pela loja virtual.' : null,
                    'created_at' => now()->subDays(count($etapas) - $index),
                ]);
            }
        }
    }
}
