<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\PedidoNovoEquipe;
use App\Mail\PedidoRecebido;
use App\Models\Activity;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\Correios\CorreiosService;
use App\Services\MelhorEnvio\MelhorEnvioService;
use App\Services\Rastreio;
use App\Services\Whmcs\Cliente;
use App\Support\Carrinho;
use App\Support\Notifica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
    public function __construct(
        private readonly Rastreio $rastreio,
        private readonly CorreiosService $correios,
        private readonly MelhorEnvioService $melhorEnvio,
    ) {}

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

        $carrinho = Carrinho::daSessao();
        $quantidadeNoCarrinho = $carrinho->quantidadeDoProduto((int) $produto['id']);

        return view('site.store.show', [
            'produto' => $produto,
            'relacionados' => $relacionados,
            'quantidadeNoCarrinho' => $quantidadeNoCarrinho,
        ]);
    }

    public function carrinho(): Response|View
    {
        $carrinho = Carrinho::daSessao();
        $linhas = $carrinho->linhas();
        $subtotal = $carrinho->subtotalCents();
        $mensalidadeTotal = $carrinho->mensalidadeTotalCents();
        $mensalidadeCobradaHoje = $carrinho->mensalidadeCobradaHojeCents();
        $subtotalHoje = $carrinho->subtotalHojeCents();
        $desc = $carrinho->resumoDesconto($subtotalHoje);

        return response()
            ->view('site.store.cart', [
                'linhas' => $linhas,
                'subtotal' => $subtotal,
                'mensalidadeTotal' => $mensalidadeTotal,
                'mensalidadeCobradaHoje' => $mensalidadeCobradaHoje,
                'subtotalHoje' => $subtotalHoje,
                'frete' => 0,
                'freteGratisEm' => $desc['frete_gratis'] ? 0 : Carrinho::faltaParaFreteGratis($subtotal),
                'cupom' => $desc['cupom'],
                'descontoCents' => $desc['desconto_cents'],
                'freteGratisCupom' => $desc['frete_gratis'],
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function aplicarCupom(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
        ]);

        $carrinho = Carrinho::daSessao();
        if ($carrinho->vazio()) {
            $msg = 'Adicione pelo menos um item ao carrinho antes de aplicar um cupom.';
            if ($request->expectsJson()) {
                return response()->json(['sucesso' => false, 'mensagem' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $res = $carrinho->aplicarCupom($data['codigo']);

        if (! $res['ok']) {
            if ($request->expectsJson()) {
                return response()->json(['sucesso' => false, 'mensagem' => $res['erro']], 422);
            }

            return back()->withInput()->with('error', $res['erro']);
        }

        /** @var Coupon $cupom */
        $cupom = $res['cupom'];
        $msg = "Cupom {$cupom->codigo} aplicado ({$cupom->rotuloDesconto()})!";

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, $msg);
        }

        return back()->with('success', $msg);
    }

    public function removerCupom(Request $request): RedirectResponse|JsonResponse
    {
        $carrinho = Carrinho::daSessao();
        $carrinho->removerCupom();

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, 'Cupom removido do carrinho.');
        }

        return back()->with('success', 'Cupom removido do carrinho.');
    }

    public function adicionar(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'produto' => ['required', 'integer'],
            'quantidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'ir' => ['nullable', 'in:carrinho,checkout'],
            'modo' => ['nullable', 'in:somar,definir'],
            'operadora' => ['nullable', 'string', 'in:algar,vivo,claro,tim'],
        ]);

        $produto = Product::doCatalogo((int) $data['produto']);

        if ($produto === null) {
            if ($request->expectsJson()) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Este produto não está disponível.'], 404);
            }

            return back()->with('error', 'Este produto não está disponível.');
        }

        if (! $produto['disponivel']) {
            $msgEsgotado = "O {$produto['nome']} está esgotado. Fale no WhatsApp para encomendar a reposição.";
            if ($request->expectsJson()) {
                return response()->json(['sucesso' => false, 'mensagem' => $msgEsgotado], 422);
            }

            return back()->with('error', $msgEsgotado);
        }

        if (! empty($produto['sob_consulta'])) {
            $msgConsulta = "O {$produto['nome']} está disponível sob consulta. Fale com um consultor no WhatsApp para receber uma proposta.";
            if ($request->expectsJson()) {
                return response()->json(['sucesso' => false, 'mensagem' => $msgConsulta], 422);
            }

            return back()->with('error', $msgConsulta);
        }

        $carrinho = Carrinho::daSessao();
        $qtdPedida = max(1, (int) ($data['quantidade'] ?? 1));
        $qtdAnterior = $carrinho->quantidadeDoProduto((int) $produto['id']);
        $modo = $data['modo'] ?? 'somar';

        if (! empty($data['operadora'])) {
            $carrinho->definirOperadora((int) $produto['id'], $data['operadora']);
        } elseif (! empty($produto['chip_padrao'])) {
            $carrinho->definirOperadora((int) $produto['id'], (string) $produto['chip_padrao']);
        }

        if ($modo === 'definir') {
            $carrinho->ajustar((int) $produto['id'], $qtdPedida);
            $qtdAtual = $carrinho->quantidadeDoProduto((int) $produto['id']);
            $mensagem = "Quantidade de {$produto['nome']} atualizada para {$qtdAtual} no carrinho.";
        } else {
            $carrinho->adicionar((int) $produto['id'], $qtdPedida);
            $qtdAtual = $carrinho->quantidadeDoProduto((int) $produto['id']);
            $mensagem = $qtdAnterior > 0
                ? "Mais {$qtdPedida} unidade(s) de {$produto['nome']} adicionada(s) ao carrinho (total: {$qtdAtual})."
                : "{$produto['nome']} adicionado ao carrinho ({$qtdAtual} ".($qtdAtual === 1 ? 'unidade' : 'unidades').').';
        }

        $linhas = $carrinho->linhas();
        $totalUnidades = array_sum(array_column($linhas, 'quantidade'));
        $totalProdutos = count($linhas);

        $request->session()->save();

        if ($request->expectsJson()) {
            return response()->json([
                'sucesso' => true,
                'mensagem' => $mensagem,
                'carrinho_itens' => $totalUnidades,
                'itens_count' => $totalProdutos,
                'produto_id' => (int) $produto['id'],
                'produto_quantidade_carrinho' => $qtdAtual,
            ]);
        }

        $destino = ($data['ir'] ?? 'carrinho') === 'checkout'
            ? route('site.loja.checkout')
            : route('site.loja.carrinho');

        return redirect()->to($destino)->with('success', $mensagem);
    }

    public function alterarOperadora(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'produto' => ['required', 'integer'],
            'operadora' => ['required', 'string', 'in:algar,vivo,claro,tim'],
        ]);

        $carrinho = Carrinho::daSessao();
        $carrinho->definirOperadora((int) $data['produto'], $data['operadora']);
        $request->session()->save();

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, 'Operadora de chip atualizada.');
        }

        return back()->with('success', 'Operadora de chip atualizada.');
    }

    public function alterarPacotesAlertas(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'produto' => ['required', 'integer'],
            'pacotes' => ['nullable', 'array'],
            'pacotes.*' => ['string', 'max:64'],
        ]);

        $carrinho = Carrinho::daSessao();
        $carrinho->definirPacotesAlertas((int) $data['produto'], (array) ($data['pacotes'] ?? []));
        $request->session()->save();

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, 'Pacote adicional de alertas atualizado.');
        }

        return back()->with('success', 'Pacote adicional de alertas atualizado.');
    }

    public function atualizar(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantidades' => ['required', 'array'],
            'quantidades.*' => ['integer', 'min:0', 'max:999'],
            'ir' => ['nullable', 'in:carrinho,checkout'],
        ]);

        $carrinho = Carrinho::daSessao();

        foreach ($data['quantidades'] as $id => $quantidade) {
            $carrinho->ajustar((int) $id, (int) $quantidade);
        }

        $request->session()->save();

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, 'Carrinho atualizado.');
        }

        if (($data['ir'] ?? '') === 'checkout') {
            return redirect()->route('site.loja.checkout');
        }

        return back()->with('success', 'Carrinho atualizado.');
    }

    public function remover(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['produto' => ['required', 'integer']]);

        $carrinho = Carrinho::daSessao();
        $carrinho->remover((int) $data['produto']);

        $request->session()->save();

        if ($request->expectsJson()) {
            return $this->resumoCarrinhoJson($carrinho, 'Item removido do carrinho.');
        }

        return back()->with('success', 'Item removido do carrinho.');
    }

    private function resumoCarrinhoJson(Carrinho $carrinho, string $mensagem = ''): JsonResponse
    {
        $linhas = $carrinho->linhas();
        $subtotal = $carrinho->subtotalCents();
        $mensalidadeTotal = $carrinho->mensalidadeTotalCents();
        $mensalidadeCobradaHoje = $carrinho->mensalidadeCobradaHojeCents();
        $subtotalHoje = $carrinho->subtotalHojeCents();
        $desc = $carrinho->resumoDesconto($subtotalHoje);
        $freteGratisEm = $desc['frete_gratis'] ? 0 : Carrinho::faltaParaFreteGratis($subtotal);
        $totalSemFrete = max(0, $subtotalHoje - ($desc['desconto_cents'] ?? 0));

        return response()->json([
            'sucesso' => true,
            'mensagem' => $mensagem,
            'vazio' => $linhas === [],
            'itens_count' => count($linhas),
            'total_unidades' => array_sum(array_column($linhas, 'quantidade')),
            'mensalidade_total_cents' => $mensalidadeTotal,
            'mensalidade_total_formatada' => $mensalidadeTotal > 0 ? brl($mensalidadeTotal).'/mês' : null,
            'mensalidade_cobrada_hoje_cents' => $mensalidadeCobradaHoje,
            'mensalidade_cobrada_hoje_formatada' => $mensalidadeCobradaHoje > 0 ? brl($mensalidadeCobradaHoje) : null,
            'linhas' => array_map(fn (array $l): array => [
                'id' => (int) $l['id'],
                'quantidade' => (int) $l['quantidade'],
                'mensalidade_cents' => (int) ($l['mensalidade'] ?? 0),
                'mensalidade_formatada' => ! empty($l['mensalidade']) ? brl($l['mensalidade']).'/mês' : null,
                'cobrar_mensalidade_hoje' => (bool) ($l['cobrar_mensalidade_hoje'] ?? false),
                'operadora_codigo' => $l['operadora_codigo'] ?? 'algar',
                'operadora_nome' => $l['operadora_nome'] ?? 'Chip Multioperadora',
                'operadora_rotulo' => $l['operadora_rotulo'] ?? 'Chip Multioperadora',
                'pacotes_alertas_ids' => (array) ($l['pacotes_alertas_ids'] ?? []),
                'pacotes_alertas_unit_cents' => (int) ($l['pacotes_alertas_unit_cents'] ?? 0),
                'subtotal_formatado' => brl($l['subtotal']),
            ], $linhas),
            'subtotal_cents' => $subtotal,
            'subtotal_hoje_cents' => $subtotalHoje,
            'desconto_cents' => (int) ($desc['desconto_cents'] ?? 0),
            'total_sem_frete_cents' => $totalSemFrete,
            'subtotal_formatado' => brl($subtotal),
            'subtotal_hoje_formatado' => brl($subtotalHoje),
            'frete_formatado' => 'Calcule pelo CEP',
            'frete_gratis_faltam_formatado' => $freteGratisEm > 0 ? brl($freteGratisEm) : null,
            'cupom' => $desc['cupom'] ? [
                'codigo' => $desc['cupom']->codigo,
                'rotulo' => $desc['cupom']->rotuloDesconto(),
                'desconto_formatado' => ($desc['desconto_cents'] ?? 0) > 0 ? '− '.brl($desc['desconto_cents']) : 'Frete grátis',
                'frete_gratis' => (bool) $desc['frete_gratis'],
            ] : null,
            'total_formatado' => brl($totalSemFrete),
        ]);
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
     * Calcula o frete em tempo real para o CEP informado (Correios SEDEX/PAC, Mercado Envios, Retirada).
     */
    public function calcularFrete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cep' => ['required', 'string', 'max:12'],
            'produto_id' => ['nullable', 'integer'],
            'quantidade' => ['nullable', 'integer', 'min:1'],
        ]);

        $subtotal = 0;
        $prod = null;
        if (! empty($data['produto_id'])) {
            $prod = Product::find($data['produto_id']);
            if ($prod) {
                $subtotal = $prod->preco() * max(1, (int) ($data['quantidade'] ?? 1));
            }
        }

        $linhas = [];
        if ($subtotal <= 0) {
            $carrinho = Carrinho::daSessao();
            $linhas = $carrinho->linhas();
            $subtotal = array_sum(array_column($linhas, 'subtotal'));
        } elseif ($prod !== null) {
            $linhas = [[
                'id' => $prod->id,
                'preco' => $prod->preco(),
                'quantidade' => max(1, (int) ($data['quantidade'] ?? 1)),
            ]];
        }

        $opcoes = $this->calcularOpcoesFrete($data['cep'], $subtotal, $linhas);

        // Se for plano ou serviço com ativação digital
        if ($prod && ($prod->category?->slug === 'planos' || ($prod->preco_cents === 0 && $prod->mensalidade_cents > 0))) {
            array_unshift($opcoes, [
                'id' => 'digital',
                'codigo' => 'digital',
                'nome' => 'Ativação Digital Imediata (Sem frete)',
                'valor_cents' => 0,
                'valor_formatado' => 'Grátis',
                'prazo_dias' => 0,
                'prazo_texto' => 'Imediato',
                'gratis' => true,
                'descricao' => 'Liberação remota de acesso à plataforma web e aplicativo Golden.',
                'tipo' => 'digital',
            ]);
        }

        $cepLimpo = preg_replace('/\D/', '', (string) $data['cep']);
        $enderecoCep = $this->consultarLocalidadeCep($cepLimpo);

        return response()->json([
            'sucesso' => true,
            'cep' => preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cepLimpo),
            'subtotal' => $subtotal,
            'opcoes' => $opcoes,
            'endereco_cep' => $enderecoCep,
        ]);
    }

    /**
     * Consulta dados de endereço por CEP para preenchimento automático no formulário.
     */
    public function consultarCep(string $cep): JsonResponse
    {
        $cepLimpo = preg_replace('/\D/', '', $cep);
        $dados = $this->consultarLocalidadeCep($cepLimpo);

        if (! $dados) {
            return response()->json(['sucesso' => false, 'mensagem' => 'CEP não encontrado.'], 404);
        }

        return response()->json([
            'sucesso' => true,
            'cep' => preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cepLimpo),
            'endereco' => $dados,
        ]);
    }

    /**
     * Consulta logradouro, bairro, cidade e UF de um CEP via ViaCEP com cache de 24h.
     *
     * @return array{logradouro:string, bairro:string, cidade:string, uf:string, resumo:string}|null
     */
    private function consultarLocalidadeCep(string $cepLimpo): ?array
    {
        if (strlen($cepLimpo) !== 8) {
            return null;
        }

        return Cache::remember('viacep_'.$cepLimpo, 86400, function () use ($cepLimpo): ?array {
            try {
                $resp = Http::timeout(4)->get("https://viacep.com.br/ws/{$cepLimpo}/json/");
                if (! $resp->ok()) {
                    return null;
                }
                $json = $resp->json();
                if (! is_array($json) || ! empty($json['erro'])) {
                    return null;
                }

                $logradouro = trim((string) ($json['logradouro'] ?? ''));
                $bairro = trim((string) ($json['bairro'] ?? ''));
                $cidade = trim((string) ($json['localidade'] ?? ''));
                $uf = trim((string) ($json['uf'] ?? ''));

                $partes = array_filter([$logradouro, $bairro]);
                $local = trim($cidade.($uf !== '' ? '/'.$uf : ''));
                $resumo = implode(', ', $partes);
                if ($local !== '') {
                    $resumo = $resumo !== '' ? "{$resumo} — {$local}" : $local;
                }

                return [
                    'logradouro' => $logradouro,
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'uf' => $uf,
                    'resumo' => $resumo,
                ];
            } catch (\Throwable) {
                return null;
            }
        });
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
            'opcao_frete' => ['nullable', 'string', 'max:40'],
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

        $carrinho = Carrinho::daSessao();
        $itensDaSessao = $carrinho->linhas();

        if ($itensDaSessao === []) {
            return redirect()->route('site.loja.index')->with('error', 'Seu carrinho está vazio.');
        }

        $pedido = DB::transaction(function () use ($request, $data, $documento, $carrinho, $itensDaSessao) {
            $produtos = Product::whereIn('id', array_column($itensDaSessao, 'id'))
                ->where('ativo', true)
                ->get()
                ->keyBy('id');

            $aprovados = [];
            $subtotal = 0;
            $mensalidadeTotal = 0;
            $mensalidadeCobradaHoje = 0;

            foreach ($itensDaSessao as $linha) {
                $produto = $produtos->get($linha['id']);

                if ($produto === null) {
                    throw ValidationException::withMessages(['carrinho' => "{$linha['nome']} não está mais disponível."]);
                }

                if ($produto->sob_consulta) {
                    throw ValidationException::withMessages([
                        'carrinho' => "{$produto->nome} está disponível apenas sob consulta. Entre em contato com nossa equipe comercial.",
                    ]);
                }

                if ($produto->controle_estoque && $produto->estoque < $linha['quantidade']) {
                    throw ValidationException::withMessages([
                        'carrinho' => "Estoque insuficiente de {$produto->nome}: restam {$produto->estoque} unidade(s).",
                    ]);
                }

                $unit = $produto->preco();
                $operadoraEscolhida = $linha['operadora_codigo'] ?? ($produto->chip_padrao ?? 'algar');
                $mensalidadeChip = $produto->mensalidadeParaOperadora($operadoraEscolhida);

                $pacotesIdsMarcados = (array) ($linha['pacotes_alertas_ids'] ?? []);
                $pacotesEscolhidos = [];
                $pacotesAlertasCents = 0;

                foreach ($produto->pacotesAlertasAtivos() as $pacoteAtivo) {
                    if (in_array($pacoteAtivo['id'], $pacotesIdsMarcados, true)) {
                        $pacotesEscolhidos[] = [
                            'id' => $pacoteAtivo['id'],
                            'nome' => $pacoteAtivo['nome'],
                            'servicos' => $pacoteAtivo['servicos'],
                            'valor_cents' => $pacoteAtivo['valor_cents'],
                        ];
                        $pacotesAlertasCents += $pacoteAtivo['valor_cents'];
                    }
                }

                $mensalidadeUnit = $mensalidadeChip + $pacotesAlertasCents;
                $cobrarHoje = (bool) ($produto->cobrar_primeira_mensalidade ?? false);

                $aprovados[] = [
                    'produto' => $produto,
                    'quantidade' => $linha['quantidade'],
                    'unit' => $unit,
                    'mensalidade' => $mensalidadeUnit,
                    'cobrar_mensalidade_hoje' => $cobrarHoje,
                    'chip_operadora' => $operadoraEscolhida,
                    'pacotes_alertas' => $pacotesEscolhidos,
                    'pacotes_alertas_cents' => $pacotesAlertasCents,
                ];
                $subtotal += $unit * $linha['quantidade'];
                $mensalidadeTotal += $mensalidadeUnit * $linha['quantidade'];
                if ($cobrarHoje) {
                    $mensalidadeCobradaHoje += $mensalidadeUnit * $linha['quantidade'];
                }
            }

            $subtotalHoje = $subtotal + $mensalidadeCobradaHoje;
            $frete = 0;
            $transportadora = null;

            if ($data['entrega'] === 'retira') {
                $frete = 0;
                $transportadora = 'retira';
            } else {
                $opcaoFrete = $data['opcao_frete'] ?? null;
                $opcaoEscolhida = null;

                if (! empty($opcaoFrete) && ! empty($data['cep'])) {
                    $opcoes = $this->calcularOpcoesFrete((string) $data['cep'], $subtotal, $itensDaSessao);
                    $opcaoEscolhida = collect($opcoes)->first(fn ($item) => is_array($item) && ($item['id'] ?? null) === $opcaoFrete);
                }

                if (is_array($opcaoEscolhida) && isset($opcaoEscolhida['valor_cents'])) {
                    $frete = (int) $opcaoEscolhida['valor_cents'];
                    $transportadora = (string) ($opcaoEscolhida['id'] ?? 'correios');
                } else {
                    $frete = Carrinho::fretePara($subtotal, $data['entrega']);
                    $transportadora = 'correios';
                }
            }

            $cupomCodigo = $carrinho->cupomCodigo();
            $cupom = null;
            $descontoCents = 0;

            if ($cupomCodigo !== null) {
                $cupom = Coupon::where('codigo', $cupomCodigo)->lockForUpdate()->first();
                if ($cupom === null) {
                    $carrinho->removerCupom();
                    throw ValidationException::withMessages(['cupom' => 'O cupom aplicado não está mais disponível.']);
                }

                $validacaoCupom = $cupom->validarParaCompra($subtotalHoje, $data['email'], $documento);
                if (! $validacaoCupom['ok']) {
                    throw ValidationException::withMessages(['cupom' => $validacaoCupom['erro']]);
                }

                $calcCupom = $cupom->calcularDesconto($subtotalHoje);
                $descontoCents = $calcCupom['desconto_cents'];
                if ($calcCupom['frete_gratis']) {
                    $frete = 0;
                }
            }

            $pedido = Order::create([
                'nome' => $data['nome'],
                'email' => $data['email'],
                'telefone' => $data['telefone'],
                'tipo_pessoa' => $data['tipo_pessoa'],
                'documento' => $documento,
                'entrega' => $data['entrega'],
                'transportadora' => $transportadora,
                'endereco' => $data['endereco'],
                'bairro' => $data['bairro'],
                'complemento' => $data['complemento'] ?? null,
                'cidade' => $data['cidade'],
                'uf' => strtoupper($data['uf']),
                'cep' => preg_replace('/\D/', '', (string) $data['cep']) ?: null,
                'subtotal_cents' => $subtotal,
                'mensalidade_total_cents' => $mensalidadeTotal,
                'frete_cents' => $frete,
                'desconto_cents' => $descontoCents,
                'coupon_id' => $cupom?->id,
                'cupom_codigo' => $cupom?->codigo,
                'total_cents' => max(0, $subtotalHoje - $descontoCents) + $frete,
                'pagamento' => $data['pagamento'],
                'status' => 'pendente',
                'notas' => $data['notas'] ?? null,
                'consent_given' => true,
                'customer_id' => $this->clienteDaCompra($data, $documento)?->id,
                'visitor_id' => $request->cookie(config('golden.rastreio.cookie')),
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
            ]);

            if ($cupom !== null) {
                $cupom->increment('usos');
            }

            foreach ($aprovados as $linha) {
                $produto = $linha['produto'];

                OrderItem::create([
                    'order_id' => $pedido->id,
                    'product_id' => $produto->id,
                    'nome' => $produto->nome,
                    'sku' => $produto->sku,
                    'quantidade' => $linha['quantidade'],
                    'preco_unit_cents' => $linha['unit'],
                    'mensalidade_cents' => $linha['mensalidade'],
                    'subtotal_cents' => $linha['unit'] * $linha['quantidade'],
                    'chip_operadora' => $linha['chip_operadora'] ?? null,
                    'pacotes_alertas' => ! empty($linha['pacotes_alertas']) ? $linha['pacotes_alertas'] : null,
                    'pacotes_alertas_cents' => (int) ($linha['pacotes_alertas_cents'] ?? 0),
                    'cobrar_mensalidade_hoje' => (bool) ($linha['cobrar_mensalidade_hoje'] ?? true),
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
            $atualizacoes = array_filter([
                'telefone' => $cliente->telefone ?: ($data['telefone'] ?? null),
                'endereco' => $cliente->endereco ?: ($data['endereco'] ?? null),
                'cidade' => $cliente->cidade ?: ($data['cidade'] ?? null),
                'uf' => $cliente->uf ?: (isset($data['uf']) ? strtoupper($data['uf']) : null),
                'cep' => $cliente->cep ?: ($data['cep'] ?? null),
            ]);

            if ($atualizacoes !== []) {
                $cliente->updateQuietly($atualizacoes);
            }

            try {
                app(Cliente::class)->espelharCliente($cliente);
            } catch (\Throwable $e) {
                report($e);
            }

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

        try {
            app(Cliente::class)->espelharCliente($novo);
        } catch (\Throwable $e) {
            report($e);
        }

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
                .', entrega em '.$pedido->cidade.'/'.$pedido->uf.'.'
                .(($pedido->mensalidade_total_cents ?? 0) > 0 ? ' (Inclui 1ª mensalidade de '.brl($pedido->mensalidade_total_cents).')' : ''),
            (string) $pedido->id,
            [
                'pedido_id' => $pedido->id,
                'numero' => $pedido->numero,
                'nome' => $pedido->nome,
                'email' => $pedido->email,
                'total' => brl((int) $pedido->total_cents),
                'mensalidade' => ($pedido->mensalidade_total_cents ?? 0) > 0 ? brl($pedido->mensalidade_total_cents) : null,
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
    /**
     * Combina cotações em tempo real do Melhor Envio com as modalidades configuradas dos Correios.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<int, array<string, mixed>>
     */
    private function calcularOpcoesFrete(string $cep, int $subtotal, array $linhas = []): array
    {
        $opcoesMelhorEnvio = $this->melhorEnvio->calcularFrete($cep, $subtotal, $linhas);
        $correiosAtivo = Setting::get('correios_ativo', '1') === '1';
        $opcoesCorreios = ($correiosAtivo || $opcoesMelhorEnvio === [])
            ? $this->correios->calcularFrete($cep, $subtotal)
            : [];

        $envio = [];
        $extras = [];

        foreach (array_merge($opcoesMelhorEnvio, $opcoesCorreios) as $op) {
            if (! is_array($op)) {
                continue;
            }
            if (in_array($op['tipo'] ?? '', ['retira', 'digital', 'mercado_livre'], true)) {
                $extras[] = $op;
            } else {
                $envio[] = $op;
            }
        }

        usort($envio, fn (array $a, array $b): int => ((int) ($a['valor_cents'] ?? 0)) <=> ((int) ($b['valor_cents'] ?? 0)));

        return array_merge($envio, $extras);
    }

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
        $carrinho = Carrinho::daSessao();
        $mensalidadeTotal = $carrinho->mensalidadeTotalCents();
        $mensalidadeCobradaHoje = $carrinho->mensalidadeCobradaHojeCents();
        $subtotalHoje = $carrinho->subtotalHojeCents();
        $desc = $carrinho->resumoDesconto($subtotalHoje);
        $frete = $desc['frete_gratis'] ? 0 : Carrinho::fretePara($subtotal, 'entrega');

        return [
            'linhas' => $linhas,
            'subtotal' => $subtotal,
            'mensalidadeTotal' => $mensalidadeTotal,
            'mensalidadeCobradaHoje' => $mensalidadeCobradaHoje,
            'subtotalHoje' => $subtotalHoje,
            'frete' => $frete,
            'freteGratisEm' => $desc['frete_gratis'] ? 0 : Carrinho::faltaParaFreteGratis($subtotal),
            'pixChave' => Setting::get('loja_pix_chave', ''),
            'cupom' => $desc['cupom'],
            'descontoCents' => $desc['desconto_cents'],
            'freteGratisCupom' => $desc['frete_gratis'],
        ];
    }
}
