<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** Campos de mídia do produto → chave das regras em config/envios.php. */
    private const MIDIAS = ['imagem' => 'produto_imagem', 'video' => 'produto_video'];

    public function index(Request $request): View
    {
        return view('admin.products.index', [
            'produtos' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'categorias' => Category::orderBy('nome')->pluck('nome', 'id'),
            // KPIs na mesma régua do filtro e de Product::estadoEstoque().
            'kpis' => [
                'total' => Product::count(),
                'no_ar' => Product::where('ativo', true)->count(),
                'baixo' => Product::estoqueBaixo()->where('estoque', '>', 0)->count(),
                'zerado' => Product::estoqueZerado()->count(),
            ],
        ]);
    }

    /** A mesma régua da listagem e do filtro: quem filtra e quem pagina lêem isto. */
    private function query(Request $request)
    {
        return Product::with('category')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)->orWhere('sku', 'like', $t));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('category_id', $request->categoria))
            ->when($request->filled('estoque'), fn ($q) => $q->estoqueBaixo())
            ->orderBy('ordem')
            ->orderBy('nome');
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'produto' => new Product(['ativo' => true, 'controle_estoque' => true]),
            'categorias' => Category::orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = slugify_pt($data['nome']);

        $produto = Product::create($data);

        if ($produto->controle_estoque && $produto->estoque > 0) {
            StockMovement::registrar($produto, 'entrada', $produto->estoque, 'Estoque inicial', $request->user()->id);
        }

        Activity::log('produto_criado', "Produto {$produto->nome} criado.", $produto);

        return redirect()->route('admin.catalogo.produtos.index')->with('success', 'Produto criado.');
    }

    public function edit(Product $produto): View
    {
        return view('admin.products.form', [
            'produto' => $produto,
            'categorias' => Category::orderBy('nome')->get(),
        ]);
    }

    public function update(Request $request, Product $produto): RedirectResponse
    {
        $estoqueAnterior = $produto->estoque;
        $data = $this->validateProduct($request, $produto);
        $this->descartarSubstituidos($produto, $data);
        $produto->update($data);

        if ($produto->controle_estoque && $produto->estoque !== $estoqueAnterior) {
            StockMovement::registrar(
                $produto,
                'ajuste',
                $produto->estoque,
                'Ajuste manual pelo painel',
                $request->user()->id,
                null,
                $estoqueAnterior
            );
            $produto->refresh();
        }

        Activity::log('produto_atualizado', "Produto {$produto->nome} atualizado.", $produto);

        return redirect()->route('admin.catalogo.produtos.index')->with('success', 'Produto atualizado.');
    }

    public function destroy(Product $produto): RedirectResponse
    {
        $nome = $produto->nome;
        Envio::remover($produto->imagem, 'produto_imagem');
        Envio::remover($produto->video, 'produto_video');
        foreach ((array) ($produto->galeria ?? []) as $foto) {
            Envio::remover($foto, 'produto_galeria');
        }
        $produto->delete();
        Activity::log('produto_excluido', "Produto {$nome} excluído.");

        return back()->with('success', 'Produto excluído.');
    }

    public function estoqueForm(Product $produto): View
    {
        return view('admin.products.estoque', [
            'produto' => $produto,
            'movimentos' => $produto->movements()->with('user')->latest()->paginate(itens_por_pagina())->withQueryString(),
        ]);
    }

    public function estoque(Request $request, Product $produto): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:entrada,saida'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:190'],
        ]);

        StockMovement::registrar($produto, $data['tipo'], $data['quantidade'], $data['motivo'] ?? null, $request->user()->id);
        Activity::log('estoque_movimento', "Estoque de {$produto->nome} ajustado ({$data['tipo']} de {$data['quantidade']}).", $produto);

        return back()->with('success', 'Estoque atualizado.');
    }

    private function validateProduct(Request $request, ?Product $produto = null): array
    {
        // Normaliza valores monetários digitados com vírgula (padrão brasileiro) ou prefixo monetário
        $precoNormalizado = $this->normalizarPreco($request->input('preco'));
        $precoPromocionalNormalizado = $this->normalizarPreco($request->input('preco_promocional'));
        $mensalidadeNormalizada = $this->normalizarPreco($request->input('mensalidade'));

        $categoriaId = $request->input('category_id');
        if ($categoriaId === '' || $categoriaId === '0') {
            $categoriaId = null;
        }

        $request->merge([
            'preco' => $precoNormalizado,
            'preco_promocional' => $precoPromocionalNormalizado,
            'mensalidade' => $mensalidadeNormalizada,
            'category_id' => $categoriaId,
        ]);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:64'],
            'resumo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco' => ['nullable', 'numeric', 'min:0'],
            'preco_promocional' => ['nullable', 'numeric', 'min:0'],
            'mensalidade' => ['nullable', 'numeric', 'min:0'],
            'sob_consulta' => ['nullable', 'boolean'],
            'estoque' => ['nullable', 'integer', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'destaque' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
            'controle_estoque' => ['nullable', 'boolean'],
            'imagem' => ['nullable', 'string', 'max:255'],
            'galeria' => ['nullable', 'array', 'max:12'],
            'galeria.*' => ['string', 'max:255'],
            'video' => ['nullable', 'string', 'max:255'],
        ]);

        // Validação e normalização dos campos de mídia única (imagem e vídeo)
        foreach (self::MIDIAS as $campo => $envio) {
            $caminho = $data[$campo] ?? null;

            if ($caminho !== null && $caminho !== '') {
                $caminhoNormalizado = ltrim(trim($caminho), '/');
                if (str_starts_with($caminhoNormalizado, 'storage/')) {
                    $caminhoNormalizado = substr($caminhoNormalizado, 8);
                }

                $ehAtual = $produto && ($produto->{$campo} === $caminho || $produto->{$campo} === $caminhoNormalizado);
                $ehPadrao = $produto && $caminhoNormalizado === Product::imagemPadraoPorSlug($produto->slug, $produto->category?->slug);

                if (! $ehAtual && ! $ehPadrao && ! Envio::caminhoConfere($caminhoNormalizado, $envio)) {
                    throw ValidationException::withMessages([
                        $campo => 'Este arquivo não foi carregado por aqui. Solte-o no campo de novo.',
                    ]);
                }

                $data[$campo] = $caminhoNormalizado;
            } else {
                $data[$campo] = null;
            }
        }

        // Validação dos caminhos das fotos da galeria
        $galeria = array_values(array_unique(array_filter((array) ($data['galeria'] ?? []))));
        $galeriaProcessada = [];
        $galeriaExistente = (array) ($produto?->galeria ?? []);

        foreach ($galeria as $foto) {
            if (! is_string($foto)) {
                continue;
            }

            $fotoNormalizada = ltrim(trim($foto), '/');
            if (str_starts_with($fotoNormalizada, 'storage/')) {
                $fotoNormalizada = substr($fotoNormalizada, 8);
            }

            $ehExistente = in_array($foto, $galeriaExistente, true) || in_array($fotoNormalizada, $galeriaExistente, true);

            if (! $ehExistente && ! Envio::caminhoConfere($fotoNormalizada, 'produto_galeria') && ! Envio::caminhoConfere($fotoNormalizada, 'produto_imagem')) {
                throw ValidationException::withMessages([
                    'galeria' => 'Uma das fotos da galeria não foi carregada por aqui. Solte-a novamente no campo.',
                ]);
            }

            $galeriaProcessada[] = $fotoNormalizada;
        }

        $sobConsulta = $request->boolean('sob_consulta');

        $temPreco = isset($data['preco']) && $data['preco'] !== null && $data['preco'] !== '';
        $temPromocional = isset($data['preco_promocional']) && $data['preco_promocional'] !== null && $data['preco_promocional'] !== '';
        $temMensalidade = isset($data['mensalidade']) && $data['mensalidade'] !== null && $data['mensalidade'] !== '';

        $precoCents = $temPreco ? $this->paraCentavos($data['preco']) : 0;
        $precoPromocionalCents = $temPromocional && (float) $data['preco_promocional'] > 0 ? $this->paraCentavos($data['preco_promocional']) : null;
        $mensalidadeCents = $temMensalidade && (float) $data['mensalidade'] > 0 ? $this->paraCentavos($data['mensalidade']) : null;

        if ($sobConsulta && ! $temPreco && ! $temMensalidade) {
            $precoCents = 0;
            $precoPromocionalCents = null;
            $mensalidadeCents = null;
        } elseif ($sobConsulta && ! $temPreco) {
            $precoCents = 0;
            $precoPromocionalCents = null;
        }

        return [
            'nome' => trim($data['nome']),
            'category_id' => $data['category_id'] ?: null,
            'sku' => ! empty($data['sku']) ? trim($data['sku']) : null,
            'resumo' => ! empty($data['resumo']) ? trim($data['resumo']) : null,
            'descricao' => $data['descricao'] ?? null,
            'preco_cents' => $precoCents,
            'preco_promocional_cents' => $precoPromocionalCents,
            'mensalidade_cents' => $mensalidadeCents,
            'estoque' => isset($data['estoque']) ? (int) $data['estoque'] : 0,
            'estoque_minimo' => isset($data['estoque_minimo']) ? (int) $data['estoque_minimo'] : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'imagem' => $data['imagem'],
            'galeria' => ! empty($galeriaProcessada) ? $galeriaProcessada : null,
            'video' => $data['video'],
            'destaque' => $request->boolean('destaque'),
            'ativo' => $produto ? $request->boolean('ativo') : $request->boolean('ativo', true),
            'controle_estoque' => $produto ? $request->boolean('controle_estoque') : $request->boolean('controle_estoque', true),
        ];
    }

    private function normalizarPreco(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        if (is_string($valor)) {
            $limpo = trim(str_ireplace(['r$', ' '], '', $valor));

            if (str_contains($limpo, ',') && str_contains($limpo, '.')) {
                $limpo = str_replace('.', '', $limpo);
                $limpo = str_replace(',', '.', $limpo);
            } elseif (str_contains($limpo, ',')) {
                $limpo = str_replace(',', '.', $limpo);
            }

            if (is_numeric($limpo)) {
                return (float) $limpo;
            }
        }

        return null;
    }

    /** Tira do disco o arquivo que o operador acabou de substituir ou remover. */
    private function descartarSubstituidos(Product $produto, array $data): void
    {
        foreach (self::MIDIAS as $campo => $envio) {
            $anterior = $produto->{$campo};

            if ($anterior && $anterior !== $data[$campo]) {
                Envio::remover($anterior, $envio);
            }
        }

        $fotosAnteriores = (array) ($produto->galeria ?? []);
        $fotosNovas = (array) ($data['galeria'] ?? []);
        foreach (array_diff($fotosAnteriores, $fotosNovas) as $removida) {
            Envio::remover($removida, 'produto_galeria');
        }
    }

    private function paraCentavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }
}
