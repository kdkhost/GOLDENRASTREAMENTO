<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Category;
use App\Models\ConhecimentoItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\Assistente\Semeador;
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
            'produto' => new Product([
                'ativo' => true,
                'controle_estoque' => true,
                'destaque' => false,
                'ordem' => 0,
                'tipo_cobranca' => 'mensal',
                'permite_escolha_chip' => true,
                'cobrar_primeira_mensalidade' => Setting::get('loja_cobrar_primeira_mensalidade', '1') === '1',
            ]),
            'categorias' => Category::orderBy('nome')->get(),
            'recursosDisponiveis' => Product::recursosDisponiveis(),
            'operadorasDisponiveis' => Product::operadorasDisponiveis(),
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
        $produto->esquecerCache();
        $this->sincronizarCatalogoAssistente();

        if ($request->input('salvar_e_continuar') === '1') {
            return redirect()->route('admin.catalogo.produtos.edit', $produto)->with('success', "Produto '{$produto->nome}' criado com sucesso.");
        }

        return redirect()->route('admin.catalogo.produtos.index')->with('success', "Produto '{$produto->nome}' criado com sucesso.");
    }

    public function edit(Product $produto): View
    {
        return view('admin.products.form', [
            'produto' => $produto,
            'categorias' => Category::orderBy('nome')->get(),
            'recursosDisponiveis' => Product::recursosDisponiveis(),
            'operadorasDisponiveis' => Product::operadorasDisponiveis(),
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
        $produto->esquecerCache();
        $this->sincronizarCatalogoAssistente();

        if ($request->input('salvar_e_continuar') === '1') {
            return redirect()->route('admin.catalogo.produtos.edit', $produto)->with('success', "Produto '{$produto->nome}' atualizado com sucesso.");
        }

        return redirect()->route('admin.catalogo.produtos.index')->with('success', "Produto '{$produto->nome}' atualizado.");
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
        $this->sincronizarCatalogoAssistente();

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
            'tipo_cobranca' => ['nullable', 'string', 'in:mensal,unico,combo'],
            'cobrar_primeira_mensalidade' => ['nullable', 'boolean'],
            'chip_operadora' => ['nullable', 'string', 'max:64'],
            'beneficio_chip_texto' => ['nullable', 'string', 'max:255'],
            'permite_escolha_chip' => ['nullable', 'boolean'],
            'operadoras_chip' => ['nullable', 'array'],
            'operadoras_chip.*' => ['string', 'in:algar,vivo,claro,tim'],
            'precos_chip' => ['nullable', 'array'],
            'precos_chip.*' => ['nullable'],
            'chip_padrao' => ['nullable', 'string', 'in:algar,vivo,claro,tim'],
            'garantia' => ['nullable', 'string', 'max:64'],
            'anatel_homologado' => ['nullable', 'string', 'max:64'],
            'recursos' => ['nullable', 'array'],
            'recursos.*' => ['string', 'max:64'],
            'recursos_itens' => ['nullable', 'array'],
            'recursos_itens.*.titulo' => ['nullable', 'string', 'max:160'],
            'recursos_itens.*.detalhe' => ['nullable', 'string', 'max:255'],
            'recursos_itens.*.tipo_valor' => ['nullable', 'string', 'in:incluso,mensal,unico,sem_valor'],
            'recursos_itens.*.valor' => ['nullable'],
            'recursos_itens.*.ativo' => ['nullable', 'boolean'],
            'pacotes_alertas' => ['nullable', 'array'],
            'pacotes_alertas.*.id' => ['nullable', 'string', 'max:64'],
            'pacotes_alertas.*.nome' => ['nullable', 'string', 'max:120'],
            'pacotes_alertas.*.valor' => ['nullable'],
            'pacotes_alertas.*.servicos' => ['nullable'],
            'pacotes_alertas.*.ativo' => ['nullable', 'boolean'],
            'peso_gramas' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'comprimento_cm' => ['nullable', 'integer', 'min:0', 'max:300'],
            'largura_cm' => ['nullable', 'integer', 'min:0', 'max:300'],
            'altura_cm' => ['nullable', 'integer', 'min:0', 'max:300'],
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
                $ehAssetPublico = str_starts_with($caminhoNormalizado, 'img/');

                if (! $ehAtual && ! $ehPadrao && ! $ehAssetPublico && ! Envio::caminhoConfere($caminhoNormalizado, $envio)) {
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

        $precosChip = [];
        if ($request->has('precos_chip') && is_array($request->input('precos_chip'))) {
            foreach ($request->input('precos_chip') as $chaveOp => $valOp) {
                $valNorm = $this->normalizarPreco($valOp);
                if ($valNorm !== null && $valNorm > 0) {
                    $precosChip[$chaveOp] = $this->paraCentavos($valNorm);
                }
            }
        }

        $pacotesAlertas = [];
        $idsUsados = [];
        if ($request->has('pacotes_alertas') && is_array($request->input('pacotes_alertas'))) {
            foreach ($request->input('pacotes_alertas') as $pacote) {
                if (! is_array($pacote)) {
                    continue;
                }

                $nomePacote = trim((string) ($pacote['nome'] ?? ''));
                $valNorm = $this->normalizarPreco($pacote['valor'] ?? null);
                $valorCents = ($valNorm !== null && $valNorm > 0) ? $this->paraCentavos($valNorm) : 0;

                if ($nomePacote === '' || $valorCents <= 0) {
                    continue;
                }

                $baseId = trim((string) ($pacote['id'] ?? ''));
                if ($baseId === '') {
                    $baseId = slugify_pt($nomePacote) ?: 'pacote-alertas';
                }
                $idFinal = $baseId;
                $sufixo = 2;
                while (in_array($idFinal, $idsUsados, true)) {
                    $idFinal = $baseId.'-'.$sufixo;
                    $sufixo++;
                }
                $idsUsados[] = $idFinal;

                $servicosBruto = $pacote['servicos'] ?? [];
                if (is_string($servicosBruto)) {
                    $servicosBruto = preg_split('/[\r\n,;]+/', $servicosBruto) ?: [];
                }
                $servicosLimpos = array_values(array_filter(array_map(
                    fn ($s): string => trim((string) $s),
                    (array) $servicosBruto
                )));

                $pacotesAlertas[] = [
                    'id' => $idFinal,
                    'nome' => $nomePacote,
                    'servicos' => $servicosLimpos,
                    'valor_cents' => $valorCents,
                    'ativo' => ! empty($pacote['ativo']),
                ];
            }
        }

        $recursosProcessados = null;
        if ($request->boolean('recursos_estruturado') || $request->has('recursos_itens')) {
            $listaRecursos = [];
            foreach ((array) $request->input('recursos_itens', []) as $rec) {
                if (! is_array($rec)) {
                    continue;
                }
                $tituloRec = trim((string) ($rec['titulo'] ?? ''));
                if ($tituloRec === '') {
                    continue;
                }
                $tipoValor = (string) ($rec['tipo_valor'] ?? 'incluso');
                if (! in_array($tipoValor, ['incluso', 'mensal', 'unico', 'sem_valor'], true)) {
                    $tipoValor = 'incluso';
                }
                $valNormRec = $this->normalizarPreco($rec['valor'] ?? null);
                $valorCentsRec = ($valNormRec !== null && $valNormRec > 0 && in_array($tipoValor, ['mensal', 'unico'], true))
                    ? $this->paraCentavos($valNormRec)
                    : 0;

                $listaRecursos[] = [
                    'titulo' => $tituloRec,
                    'detalhe' => trim((string) ($rec['detalhe'] ?? '')),
                    'tipo_valor' => $tipoValor,
                    'valor_cents' => $valorCentsRec,
                    'ativo' => ! empty($rec['ativo']),
                ];
            }
            $recursosProcessados = ! empty($listaRecursos) ? $listaRecursos : null;
        } elseif (! empty($data['recursos'])) {
            $recursosProcessados = array_values(array_filter((array) $data['recursos']));
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
            'sob_consulta' => $sobConsulta,
            'cobrar_primeira_mensalidade' => $request->boolean('cobrar_primeira_mensalidade'),
            'tipo_cobranca' => $data['tipo_cobranca'] ?? 'mensal',
            'chip_operadora' => ! empty($data['chip_operadora']) ? trim($data['chip_operadora']) : null,
            'beneficio_chip_texto' => ! empty($data['beneficio_chip_texto']) ? trim($data['beneficio_chip_texto']) : null,
            'permite_escolha_chip' => $request->boolean('permite_escolha_chip'),
            'operadoras_chip' => ! empty($data['operadoras_chip']) ? array_values($data['operadoras_chip']) : null,
            'precos_chip' => ! empty($precosChip) ? $precosChip : null,
            'chip_padrao' => ! empty($data['chip_padrao']) ? $data['chip_padrao'] : 'algar',
            'garantia' => ! empty($data['garantia']) ? trim($data['garantia']) : null,
            'anatel_homologado' => ! empty($data['anatel_homologado']) ? trim($data['anatel_homologado']) : null,
            'recursos' => $recursosProcessados,
            'pacotes_alertas' => ! empty($pacotesAlertas) ? $pacotesAlertas : null,
            'peso_gramas' => isset($data['peso_gramas']) && $data['peso_gramas'] !== '' ? (int) $data['peso_gramas'] : null,
            'comprimento_cm' => isset($data['comprimento_cm']) && $data['comprimento_cm'] !== '' ? (int) $data['comprimento_cm'] : null,
            'largura_cm' => isset($data['largura_cm']) && $data['largura_cm'] !== '' ? (int) $data['largura_cm'] : null,
            'altura_cm' => isset($data['altura_cm']) && $data['altura_cm'] !== '' ? (int) $data['altura_cm'] : null,
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
            $limpo = trim($valor);
            // Remove R$, espaços, /mês, etc mantendo apenas dígitos, vírgula e ponto
            $limpo = preg_replace('/[^\d,.]/', '', $limpo);

            if ($limpo === '' || $limpo === null) {
                return null;
            }

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

    /** Mantém o catálogo da assistente virtual Ana sempre atualizado quando o item já existe na base. */
    private function sincronizarCatalogoAssistente(): void
    {
        if (ConhecimentoItem::where('referencia', 'golden:catalogo')->exists()) {
            app(Semeador::class)->catalogo();
        }
    }
}
