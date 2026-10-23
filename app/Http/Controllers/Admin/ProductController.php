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
        $produtos = Product::with('category')
            ->when($request->filled('q'), fn ($q) => $q->where('nome', 'like', '%' . $request->q . '%'))
            ->when($request->filled('categoria'), fn ($q) => $q->where('category_id', $request->categoria))
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        return view('admin.products.index', [
            'produtos' => $produtos,
            'categorias' => Category::orderBy('nome')->get(),
        ]);
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
        $data = $this->validateProduct($request);
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
        $produto->delete();
        Activity::log('produto_excluido', "Produto {$nome} excluído.");

        return back()->with('success', 'Produto excluído.');
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

    private function validateProduct(Request $request): array
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:64'],
            'resumo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'preco' => ['required', 'numeric', 'min:0'],
            'preco_promocional' => ['nullable', 'numeric', 'min:0'],
            'mensalidade' => ['nullable', 'numeric', 'min:0'],
            'estoque' => ['nullable', 'integer', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'destaque' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
            'controle_estoque' => ['nullable', 'boolean'],
            'imagem' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'string', 'max:255'],
        ]);

        // Só se aceita arquivo que esteja de fato na pasta do campo (caminho vem do dropzone).
        foreach (self::MIDIAS as $campo => $envio) {
            $caminho = $data[$campo] ?? null;

            if ($caminho !== null && ! Envio::caminhoConfere($caminho, $envio)) {
                throw ValidationException::withMessages([
                    $campo => 'Este arquivo não foi carregado por aqui. Solte-o no campo de novo.',
                ]);
            }

            $data[$campo] = $caminho;
        }

        return [
            'nome' => $data['nome'],
            'category_id' => $data['category_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'resumo' => $data['resumo'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'preco_cents' => $this->paraCentavos($data['preco']),
            'preco_promocional_cents' => isset($data['preco_promocional']) && $data['preco_promocional'] > 0 ? $this->paraCentavos($data['preco_promocional']) : null,
            'mensalidade_cents' => isset($data['mensalidade']) && $data['mensalidade'] > 0 ? $this->paraCentavos($data['mensalidade']) : null,
            'estoque' => $data['estoque'] ?? 0,
            'estoque_minimo' => $data['estoque_minimo'] ?? 0,
            'ordem' => $data['ordem'] ?? 0,
            'imagem' => $data['imagem'],
            'video' => $data['video'],
            'destaque' => $request->boolean('destaque'),
            'ativo' => $request->boolean('ativo', true),
            'controle_estoque' => $request->boolean('controle_estoque', true),
        ];
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
    }

    private function paraCentavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }
}
