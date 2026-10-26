<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\PedidoNovoEquipe;
use App\Mail\PedidoRecebido;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\Rastreio;
use App\Support\Carrinho;
use App\Support\Notifica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Loja virtual. As telas de navegação (vitrine, produto, carrinho, checkout) são montadas
 * sobre o catálogo e as configurações já em cache — nenhuma consulta ao banco por visita.
 * A única leitura fresca acontece em registrar(), dentro da transação do pedido, onde preço
 * e estoque precisam ser os reais daquele instante.
 */
class StoreController extends Controller
{
    public function __construct(private readonly Rastreio $rastreio) {}

    public function index(Request $request): View
    {
        $atual = (string) $request->query('categoria', '');

        $produtos = collect(Product::catalogoParaSite())
            ->when($atual !== '', fn ($c) => $c->where('categoria_slug', $atual))
            ->values();

        return view('site.store.index', [
            'produtos' => $produtos,
            'categorias' => collect(Product::categoriasDaVitrine())->where('slug', '!=', '')->values(),
            'categoriaAtual' => $atual,
        ]);
    }

    public function show(string $slug): View
    {
        $produto = Product::paraSite($slug);
        abort_if($produto === null, 404);

        $relacionados = collect(Product::catalogoParaSite())
            ->where('categoria_slug', $produto['categoria_slug'])
            ->where('slug', '!=', $produto['slug'])
            ->take(3)
            ->values();

        return view('site.store.show', [
            'produto' => $produto,
            'relacionados' => $relacionados,
        ]);
    }

    public function carrinho(): View
    {
        $carrinho = Carrinho::daSessao();
        $linhas = $carrinho->linhas();
        $subtotal = array_sum(array_column($linhas, 'subtotal'));

        return view('site.store.cart', [
            'linhas' => $linhas,
            'subtotal' => $subtotal,
            'frete' => Carrinho::fretePara($subtotal),
            'freteGratisEm' => Carrinho::faltaParaFreteGratis($subtotal),
        ]);
    }

    public function adicionar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'produto' => ['required', 'integer'],
            'quantidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'ir' => ['nullable', 'in:carrinho,checkout'],
        ]);

        $produto = Product::doCatalogo((int) $data['produto']);

        if ($produto === null) {
            return back()->with('error', 'Este produto não está disponível.');
        }

        if (! $produto['disponivel']) {
            return back()->with('error', "O {$produto['nome']} está esgotado. Fale no WhatsApp para encomendar a reposição.");
        }

        Carrinho::daSessao()->adicionar((int) $produto['id'], (int) ($data['quantidade'] ?? 1));

        $destino = ($data['ir'] ?? 'carrinho') === 'checkout'
            ? route('site.loja.checkout')
            : route('site.loja.carrinho');

        return redirect()->to($destino)->with('success', "{$produto['nome']} adicionado ao carrinho.");
    }

    public function atualizar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantidades' => ['required', 'array'],
            'quantidades.*' => ['integer', 'min:0', 'max:999'],
        ]);

        $carrinho = Carrinho::daSessao();

        foreach ($data['quantidades'] as $id => $quantidade) {
            $carrinho->ajustar((int) $id, (int) $quantidade);
        }

        return back()->with('success', 'Carrinho atualizado.');
    }

    public function remover(Request $request): RedirectResponse
    {
        $data = $request->validate(['produto' => ['required', 'integer']]);

        Carrinho::daSessao()->remover((int) $data['produto']);

        return back()->with('success', 'Item removido do carrinho.');
    }

    public function checkout(): View|RedirectResponse
    {
        $carrinho = Carrinho::daSessao();
        $linhas = $carrinho->linhas();

        if ($linhas === []) {
            return redirect()->route('site.loja.index')->with('error', 'Seu carrinho está vazio.');
        }

        $subtotal = array_sum(array_column($linhas, 'subtotal'));

        return view('site.store.checkout', $this->dadosDoCheckout($linhas, $subtotal) + [
            'entregas' => $this->entregasDisponiveis(),
            'pagamentos' => $this->pagamentosDoFormulario(),
        ]);
    }

    /**
     * Grava o pedido. Preços e estoque são relidos do banco aqui: o que entra na
     * transação é o valor real da venda, nunca o que estava em cache na vitrine.
     */
    public function registrar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'telefone' => ['required', 'string', 'max:32'],
            'tipo_pessoa' => ['required', 'in:fisica,juridica'],
            'documento' => ['required', 'string', 'max:20'],
            'entrega' => ['required', 'in:entrega,retira'],
            'cep' => ['nullable', 'string', 'max:12'],
            'endereco' => ['required', 'string', 'max:160'],
            'bairro' => ['required', 'string', 'max:96'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'cidade' => ['required', 'string', 'max:96'],
            'uf' => ['required', 'string', 'max:2'],
            'pagamento' => ['required', 'string', 'max:16'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['nullable'], // honeypot
        ], [
            'consent.accepted' => 'É preciso aceitar o uso dos dados para processar o pedido.',
        ]);

        if ($request->filled('website')) {
            return back()->with('error', 'Pedido não registrado.');
        }

        $entregas = $this->entregasDisponiveis();

        if (! array_key_exists($data['entrega'], $entregas)) {
            throw ValidationException::withMessages(['entrega' => 'Esta modalidade de entrega não está disponível.']);
        }

        if ($data['entrega'] === 'entrega') {
            foreach (['cep', 'endereco', 'bairro'] as $campo) {
                if (blank($request->input($campo))) {
                    throw ValidationException::withMessages([$campo => 'Preencha o endereço completo para receber a entrega.']);
                }
            }
        }

        $documento = preg_replace('/\D/', '', $data['documento']);

        if (strlen($documento) !== ($data['tipo_pessoa'] === 'fisica' ? 11 : 14)) {
            throw ValidationException::withMessages([
                'documento' => $data['tipo_pessoa'] === 'fisica' ? 'Informe um CPF válido (somente números).' : 'Informe um CNPJ válido (somente números).',
            ]);
        }

        $pagamentos = $this->pagamentosDoFormulario();

        if (! array_key_exists($data['pagamento'], $pagamentos)) {
            throw ValidationException::withMessages(['pagamento' => 'Escolha uma forma de pagamento válida.']);
        }

        if ($data['pagamento'] === 'retirada' && $data['entrega'] !== 'retira') {
            throw ValidationException::withMessages(['pagamento' => 'Pagamento na retirada exige a modalidade "Retirada na loja".']);
        }

        $itensDaSessao = Carrinho::daSessao()->linhas();

        if ($itensDaSessao === []) {
            return redirect()->route('site.loja.index')->with('error', 'Seu carrinho está vazio.');
        }

        $pedido = DB::transaction(function () use ($request, $data, $documento, $itensDaSessao) {
            $produtos = Product::whereIn('id', array_column($itensDaSessao, 'id'))
                ->where('ativo', true)
                ->get()
                ->keyBy('id');

            $aprovados = [];
            $subtotal = 0;

            foreach ($itensDaSessao as $linha) {
                $produto = $produtos->get($linha['id']);

                if ($produto === null) {
                    throw ValidationException::withMessages(['carrinho' => "{$linha['nome']} não está mais disponível."]);
                }

                if ($produto->controle_estoque && $produto->estoque < $linha['quantidade']) {
                    throw ValidationException::withMessages([
                        'carrinho' => "Estoque insuficiente de {$produto->nome}: restam {$produto->estoque} unidade(s).",
                    ]);
                }

                $unit = $produto->preco();
                $aprovados[] = ['produto' => $produto, 'quantidade' => $linha['quantidade'], 'unit' => $unit];
                $subtotal += $unit * $linha['quantidade'];
            }

            $frete = Carrinho::fretePara($subtotal, $data['entrega']);

            $pedido = Order::create([
                'nome' => $data['nome'],
                'email' => $data['email'],
                'telefone' => $data['telefone'],
                'tipo_pessoa' => $data['tipo_pessoa'],
                'documento' => $documento,
                'entrega' => $data['entrega'],
                'endereco' => $data['endereco'],
                'bairro' => $data['bairro'],
                'complemento' => $data['complemento'] ?? null,
                'cidade' => $data['cidade'],
                'uf' => strtoupper($data['uf']),
                'cep' => preg_replace('/\D/', '', (string) $data['cep']) ?: null,
                'subtotal_cents' => $subtotal,
                'frete_cents' => $frete,
                'desconto_cents' => 0,
                'total_cents' => $subtotal + $frete,
                'pagamento' => $data['pagamento'],
                'status' => 'pendente',
                'notas' => $data['notas'] ?? null,
                'consent_given' => true,
                'customer_id' => $this->clienteDaCompra($data, $documento)?->id,
                'visitor_id' => $request->cookie(config('golden.rastreio.cookie')),
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
            ]);

            foreach ($aprovados as $linha) {
                $produto = $linha['produto'];

                OrderItem::create([
                    'order_id' => $pedido->id,
                    'product_id' => $produto->id,
                    'nome' => $produto->nome,
                    'sku' => $produto->sku,
                    'quantidade' => $linha['quantidade'],
                    'preco_unit_cents' => $linha['unit'],
                    'subtotal_cents' => $linha['unit'] * $linha['quantidade'],
                ]);

                if ($produto->controle_estoque) {
                    StockMovement::registrar($produto, 'saida', $linha['quantidade'], 'Venda pelo site (pedido #'.$pedido->numero.')', null, $pedido->id);
                }
            }

            $pedido->registrarStatus('pendente', 'Pedido criado pela loja virtual.');

            return $pedido;
        });

        Activity::log('pedido_site', "Pedido {$pedido->numero} recebido pela loja ({$data['nome']}).", $pedido);
        $this->rastreio->registrarNaVisita($this->rastreio->visitaAtual($request), 'pedido', '/loja/pedido', null, $pedido->numero);
        $this->avisar($pedido);
        Carrinho::daSessao()->limpar();

        return redirect()->to($pedido->registroUrl())
            ->with('success', 'Pedido registrado! Enviamos a confirmação por e-mail.');
    }

    /**
     * Status público do pedido: número + token aleatório na URL. Sem o token não há
     * como percorrer a sequência de números e expor dados de outro cliente.
     */
    public function pedido(Request $request, string $numero, string $token): View
    {
        $pedido = Order::where('numero', $numero)->where('token', $token)->firstOrFail();

        return view('site.store.pedido', [
            'pedido' => $pedido->load(['items', 'histories.user']),
            'pixChave' => Setting::get('loja_pix_chave', ''),
        ]);
    }

    /** Cliente do CRM: reaproveita pelo documento ou e-mail, cria quando é a primeira compra. */
    private function clienteDaCompra(array $data, string $documento): ?Customer
    {
        $cliente = Customer::where('documento', $documento)->first()
            ?? Customer::where('email', $data['email'])->first();

        if ($cliente !== null) {
            return $cliente;
        }

        $novo = new Customer([
            'nome' => $data['nome'],
            'documento' => $documento,
            'tipo' => $data['tipo_pessoa'],
            'telefone' => $data['telefone'],
            'email' => $data['email'],
            'endereco' => $data['endereco'],
            'cidade' => $data['cidade'],
            'uf' => strtoupper($data['uf']),
            'cep' => $data['cep'] ?? null,
            'status' => 'prospecto',
        ]);
        $novo->save();

        return $novo;
    }

    /**
     * E-mails transacionais da venda. Um servidor de SMTP fora do ar nunca pode anular um
     * pedido já gravado — por isso tudo aqui é registrado e seguido em frente.
     *
     * O sino entra aqui, e não no lugar do e-mail: a loja pode estar sem SMTP no ar e a equipe
     * ainda precisa ver o pedido assim que abre o painel.
     */
    private function avisar(Order $pedido): void
    {
        Notifica::disparar(
            'pedido_novo',
            'Pedido '.$pedido->numero.' · '.$pedido->nome,
            'Pedido de '.($pedido->nome ?: 'cliente da loja').' ('.$pedido->email.') no valor de '
                .brl((int) $pedido->total_cents).'. Pagamento por '
                .(Order::PAGAMENTOS[$pedido->pagamento] ?? $pedido->pagamento)
                .', entrega em '.$pedido->cidade.'/'.$pedido->uf.'.',
            (string) $pedido->id,
            [
                'pedido_id' => $pedido->id,
                'numero' => $pedido->numero,
                'nome' => $pedido->nome,
                'email' => $pedido->email,
                'total' => brl((int) $pedido->total_cents),
                'pagamento' => $pedido->pagamento,
            ],
            'pedido_novo:'.$pedido->id,
        );

        try {
            Mail::to($pedido->email)->queue(new PedidoRecebido($pedido));

            if (($equipe = Setting::get('loja_email_equipe', '')) !== '') {
                Mail::to($equipe)->queue(new PedidoNovoEquipe($pedido));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, string> */
    private function entregasDisponiveis(): array
    {
        $entregas = ['entrega' => Order::ENTREGAS['entrega']];

        if (Setting::get('loja_retirada', '0') === '1') {
            $entregas['retira'] = Order::ENTREGAS['retira'];
        }

        return $entregas;
    }

    /** Formas de pagamento do checkout; "na retirada" só quando a retirada está ligada. */
    private function pagamentosDoFormulario(): array
    {
        $pagamentos = Order::PAGAMENTOS;
        unset($pagamentos['nao']);

        if (! array_key_exists('retira', $this->entregasDisponiveis())) {
            unset($pagamentos['retirada']);
        }

        return $pagamentos;
    }

    /** @return array<string, mixed> */
    private function dadosDoCheckout(array $linhas, int $subtotal): array
    {
        return [
            'linhas' => $linhas,
            'subtotal' => $subtotal,
            'frete' => Carrinho::fretePara($subtotal, 'entrega'),
            'freteGratisEm' => Carrinho::faltaParaFreteGratis($subtotal),
            'pixChave' => Setting::get('loja_pix_chave', ''),
        ];
    }
}
