<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $produtos = Product::with('category')
            ->vitrine()
            ->when($request->filled('categoria'), function ($q) use ($request) {
                $q->whereHas('category', fn ($c) => $c->where('slug', $request->categoria));
            })
            ->get();

        return view('site.store.index', [
            'produtos' => $produtos,
            'categorias' => Category::ativas()->withCount(['products' => fn ($q) => $q->where('ativo', true)])->get(),
            'categoriaAtual' => $request->categoria,
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $produto = Product::with('category')->where('slug', $slug)->where('ativo', true)->firstOrFail();
        $relacionados = Product::vitrine()
            ->where('id', '!=', $produto->id)
            ->where('category_id', $produto->category_id)
            ->limit(3)
            ->get();

        return view('site.store.show', [
            'produto' => $produto,
            'relacionados' => $relacionados,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'telefone' => ['required', 'string', 'max:32'],
            'endereco' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:96'],
            'uf' => ['required', 'string', 'max:4'],
            'cep' => ['nullable', 'string', 'max:12'],
            'pagamento' => ['required', 'in:cartao,boleto,pix'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'produtos' => ['required', 'array', 'min:1'],
            'produtos.*.product_id' => ['required', 'integer'],
            'produtos.*.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'website' => ['nullable'], // honeypot
        ], [
            'produtos.min' => 'Seu carrinho está vazio.',
        ]);

        if ($request->filled('website')) {
            abort(422);
        }

        $itens = [];
        foreach ($data['produtos'] as $linha) {
            $produto = Product::where('id', $linha['product_id'])->where('ativo', true)->first();
            if (!$produto) {
                continue;
            }
            $qtd = min(999, max(1, (int) $linha['quantidade']));
            $itens[] = ['produto' => $produto, 'quantidade' => $qtd];
        }

        if ($itens === []) {
            return back()->with('error', 'Nenhum produto válido no pedido.');
        }

        $pedido = \DB::transaction(function () use ($data, $itens, $request) {
            $pedido = Order::create([
                'numero' => Order::proximoNumero(),
                'nome' => $data['nome'],
                'email' => $data['email'] ?? null,
                'telefone' => $data['telefone'],
                'endereco' => $data['endereco'],
                'cidade' => $data['cidade'],
                'uf' => $data['uf'],
                'cep' => $data['cep'] ?? null,
                'pagamento' => $data['pagamento'],
                'status' => 'pendente',
                'notas' => $data['notas'] ?? null,
                'visitor_id' => $request->cookie('golden_visitor'),
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip() . config('app.key')) : null,
                'frete_cents' => \App\Models\Setting::get('loja_livre', '0') === '1' ? 0 : (int) \App\Models\Setting::get('loja_frete_cents', 0),
            ]);

            $subtotal = 0;
            foreach ($itens as $linha) {
                $produto = $linha['produto'];
                $unit = $produto->preco();
                $sub = $unit * $linha['quantidade'];
                OrderItem::create([
                    'order_id' => $pedido->id,
                    'product_id' => $produto->id,
                    'nome' => $produto->nome,
                    'quantidade' => $linha['quantidade'],
                    'preco_unit_cents' => $unit,
                    'subtotal_cents' => $sub,
                ]);
                $subtotal += $sub;

                if ($produto->controle_estoque) {
                    StockMovement::registrar($produto, 'saida', $linha['quantidade'], 'Venda pelo site (pedido #' . $pedido->numero . ')', null, $pedido->id);
                }
            }

            $pedido->subtotal_cents = $subtotal;
            $pedido->total_cents = $subtotal + (int) $pedido->frete_cents;
            $pedido->save();

            return $pedido;
        });

        \App\Models\Activity::log('pedido_site', "Pedido {$pedido->numero} recebido pela loja ({$data['nome']}).", $pedido);

        return redirect()->route('site.loja.confirmacao', $pedido->numero)
            ->with('success', 'Pedido registrado! Entraremos em contato para confirmar o pagamento.');
    }

    public function confirmacao(string $numero): View
    {
        $pedido = Order::where('numero', $numero)->firstOrFail();

        return view('site.store.confirm', ['pedido' => $pedido->load('items')]);
    }
}
