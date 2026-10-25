<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\FunnelBlock;
use App\Models\FunnelPage;
use App\Models\Lead;
use App\Models\Visit;
use App\Models\VisitEvent;
use App\Services\Atribuidor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * As páginas de campanha: o operador monta o funil por blocos, cola os pixels do
 * Google/Meta/TikTok e acompanha o contador da página — visitas, cliques por chamada
 * e cadastros — sem tocar no site institucional.
 *
 * O contador é por caminho, não por id: a página guarda todo endereço que já teve,
 * então trocar o slug não zera o histórico nem esconde o tráfego que ainda chega pelo
 * link antigo.
 */
class FunnelController extends Controller
{
    public function index(Request $request): View
    {
        [$inicio, $fim] = $this->periodo($request);

        $paginas = FunnelPage::query()
            ->when($request->filled('busca'), function ($q) use ($request): void {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)
                    ->orWhere('slug', 'like', $t)
                    ->orWhere('titulo', 'like', $t));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('nome')
            ->get();

        return view('admin.funil.index', [
            'paginas' => $paginas,
            'medicoes' => $this->medicoes($paginas, $inicio, $fim),
            'periodo' => [$inicio, $fim],
            'totais' => $this->totais($paginas, $inicio, $fim),
        ]);
    }

    /** O relatório de uma campanha. */
    public function show(Request $request, FunnelPage $pagina): View
    {
        [$inicio, $fim] = $this->periodo($request);
        $caminhos = $pagina->caminhos();

        $visitas = fn () => Visit::query()->pessoas()->entre($inicio, $fim)->whereIn('landing_path', $caminhos);

        $contagem = $visitas()
            ->selectRaw('COUNT(*) as visitas, COUNT(DISTINCT visitor_id) as unicos, COALESCE(SUM(visualizacoes),0) as views, COALESCE(SUM(eventos),0) as cliques')
            ->first();

        $leads = Lead::where('funnel_page_id', $pagina->id)->whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59']);
        $totalLeads = (clone $leads)->count();
        $vendidos = (clone $leads)->where('status', 'vendido')->count();

        return view('admin.funil.show', [
            'pagina' => $pagina,
            'periodo' => [$inicio, $fim],
            'kpis' => [
                'visitas' => (int) $contagem->visitas,
                'unicos' => (int) $contagem->unicos,
                'views' => (int) $contagem->views,
                'cliques' => (int) $contagem->cliques,
                'leads' => $totalLeads,
                'vendidos' => $vendidos,
                'taxa' => $contagem->visitas > 0 ? round($totalLeads / $contagem->visitas * 100, 1) : 0.0,
                'desde_sempre' => Visit::pessoas()->whereIn('landing_path', $caminhos)->count(),
                'robos' => Visit::where('bot', true)->entre($inicio, $fim)->whereIn('landing_path', $caminhos)->count(),
            ],
            'porCanal' => $this->agruparPor($visitas, $pagina, $inicio, $fim, 'canal'),
            'porRede' => $this->agruparPor($visitas, $pagina, $inicio, $fim, 'rede'),
            'dispositivos' => $this->agruparPor($visitas, $pagina, $inicio, $fim, 'dispositivo'),
            'porClique' => $this->cliquesPorChamada($caminhos, $inicio, $fim),
            'porDia' => $this->porDia($caminhos, $pagina, $inicio, $fim),
            'visitas' => $visitas()->withCount('leads')->orderByDesc('ultima_em')->paginate(itens_por_pagina())->withQueryString(),
            'leads' => Lead::where('funnel_page_id', $pagina->id)->latest()->limit(10)->get(),
            'blocos' => $pagina->blocks()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.funil.form', [
            'pagina' => new FunnelPage([
                'status' => 'rascunho',
                'visibilidade' => 'noindex',
                'pele' => (string) array_key_first((array) config('golden.funil_peles')),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pagina = FunnelPage::create($this->validarPagina($request));
        Activity::log('funil_criado', "Página de funil {$pagina->nome} criada.", $pagina);

        return redirect()->route('admin.funil.edit', $pagina)
            ->with('success', 'Página criada. Agora monte os blocos da campanha.');
    }

    public function edit(FunnelPage $pagina): View
    {
        return view('admin.funil.form', ['pagina' => $pagina]);
    }

    public function update(Request $request, FunnelPage $pagina): RedirectResponse
    {
        $pagina->update($this->validarPagina($request, $pagina));
        Activity::log('funil_atualizado', "Página de funil {$pagina->nome} atualizada.", $pagina);

        return redirect()->route('admin.funil.edit', $pagina)->with('success', 'Página da campanha salva.');
    }

    public function destroy(FunnelPage $pagina): RedirectResponse
    {
        $nome = $pagina->nome;
        $pagina->delete();
        Activity::log('funil_excluido', "Página de funil {$nome} removida.");

        return redirect()->route('admin.funil.index')->with('success', 'Página de campanha removida.');
    }

    /**
     * A tecla de ligar e desligar a campanha no ar. Não é um status novo: é o mesmo
     * 'publicada'/'pausada' que o formulário oferece, só que sem reescrever a página
     * inteira. Uma campanha sem bloco nenhum não vai ao ar — a tecla não abre um
     * endereço em branco para quem clicou no anúncio.
     */
    public function toggle(FunnelPage $pagina): RedirectResponse
    {
        if ($pagina->status !== 'publicada') {
            if (! $pagina->blocks()->where('ativa', true)->exists()) {
                return back()->with('error', 'Monte pelo menos um bloco antes de publicar esta campanha.');
            }

            $pagina->update(['status' => 'publicada']);
            Activity::log('funil_publicada', "Página de funil {$pagina->nome} publicada.", $pagina);

            return back()->with('success', "A campanha {$pagina->nome} está no ar.");
        }

        $pagina->update(['status' => 'pausada']);
        Activity::log('funil_pausada', "Página de funil {$pagina->nome} pausada.", $pagina);

        return back()->with('success', "A campanha {$pagina->nome} saiu do ar — o link mostra 404 e o contador não para.");
    }

    /* ------------------------------------------------------------------ blocos */

    public function blocoCreate(Request $request, FunnelPage $pagina): View
    {
        $tipos = FunnelBlock::rotulos();
        $tipo = (string) $request->input('tipo', (string) array_key_first($tipos));

        if (! array_key_exists($tipo, $tipos)) {
            $tipo = (string) array_key_first($tipos);
        }

        return view('admin.funil.bloco', [
            'pagina' => $pagina,
            'bloco' => new FunnelBlock([
                'funnel_page_id' => $pagina->id,
                'tipo' => $tipo,
                'ordem' => ((int) $pagina->blocks()->max('ordem')) + 1,
                'ativa' => true,
            ]),
            'tipos' => $tipos,
            'novo' => true,
        ]);
    }

    public function blocoStore(Request $request, FunnelPage $pagina): RedirectResponse
    {
        $bloco = $pagina->blocks()->create($this->validarBloco($request, $pagina));
        Activity::log('funil_bloco_criado', "Bloco ".FunnelBlock::rotuloTipo($bloco->tipo)." adicionado em {$pagina->nome}.", $bloco);

        return redirect()->route('admin.funil.edit', $pagina)->with('success', 'Bloco adicionado à página.');
    }

    public function blocoEdit(FunnelPage $pagina, FunnelBlock $bloco): View
    {
        abort_unless($bloco->funnel_page_id === $pagina->id, 404);

        return view('admin.funil.bloco', [
            'pagina' => $pagina,
            'bloco' => $bloco,
            'tipos' => FunnelBlock::rotulos(),
            'novo' => false,
        ]);
    }

    public function blocoUpdate(Request $request, FunnelPage $pagina, FunnelBlock $bloco): RedirectResponse
    {
        abort_unless($bloco->funnel_page_id === $pagina->id, 404);
        $bloco->update($this->validarBloco($request, $pagina, $bloco));
        Activity::log('funil_bloco_atualizado', "Bloco ".FunnelBlock::rotuloTipo($bloco->tipo)." atualizado em {$pagina->nome}.", $bloco);

        return redirect()->route('admin.funil.edit', $pagina)->with('success', 'Bloco salvo.');
    }

    public function blocoDestroy(FunnelPage $pagina, FunnelBlock $bloco): RedirectResponse
    {
        abort_unless($bloco->funnel_page_id === $pagina->id, 404);
        $tipo = FunnelBlock::rotuloTipo($bloco->tipo);
        $bloco->delete();
        Activity::log('funil_bloco_excluido', "Bloco {$tipo} removido de {$pagina->nome}.");

        return back()->with('success', "Bloco {$tipo} removido da página.");
    }

    /**
     * O tipo decide os campos: o que ele não declarou nem é validado nem aparece na tela.
     * Assim um tipo novo entra pela config, sem controller novo e sem view nova.
     */
    private function validarBloco(Request $request, FunnelPage $pagina, ?FunnelBlock $bloco = null): array
    {
        $request->validate(['tipo' => ['required', 'string', Rule::in(array_keys(FunnelBlock::tipos()))]]);
        $tipo = (string) $request->input('tipo');

        $regras = [
            'titulo' => ['nullable', 'string', 'max:190'],
            'texto' => ['nullable', 'string', 'max:2000'],
            'corpo' => ['nullable', 'string', 'max:20000'],
            'itens' => ['nullable', 'string', 'max:8000'],
            'imagem' => ['nullable', 'string', 'max:255'],
            'imagem_alt' => ['nullable', 'string', 'max:190'],
            'cta_texto' => ['nullable', 'string', 'max:120'],
            'cta_destino' => ['nullable', 'string', 'max:255'],
            'cta2_texto' => ['nullable', 'string', 'max:120'],
            'cta2_destino' => ['nullable', 'string', 'max:255'],
            'limite_em' => ['nullable', 'date'],
        ];

        $declarados = array_keys(FunnelBlock::camposPara($tipo));
        $dados = $request->validate(collect($regras)->only($declarados)->all());

        $dados['tipo'] = $tipo;
        $dados['ordem'] = (int) $request->input('ordem', ((int) $pagina->blocks()->whereKeyNot($bloco?->id)->max('ordem')) + 1);
        $dados['ativa'] = $request->boolean('ativa');

        // O que o tipo não declara é zerado: um bloco que muda de tipo não pode levar
        // texto velho escondido para outro lugar da página.
        foreach (array_keys($regras) as $campo) {
            $dados[$campo] = in_array($campo, $declarados, true) ? ($dados[$campo] ?? null) : null;
        }

        return $dados;
    }

    /** @return array<string, mixed> */
    private function validarPagina(Request $request, ?FunnelPage $pagina = null): array
    {
        // O painel pergunta em reais; a coluna guarda centavos, para o JSON-LD e o
        // Google Ads não perderem valor no arredondamento. Apagar o campo zera o preço.
        if ($request->has('preco')) {
            $reais = trim((string) $request->input('preco'));
            $request->merge([
                'preco_cents' => $reais === '' ? null : (int) round((float) str_replace(['.', ','], ['', '.'], $reais) * 100),
            ]);
        }

        return $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('funnel_pages', 'nome')->ignore($pagina)],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', Rule::unique('funnel_pages', 'slug')->ignore($pagina)],
            'status' => ['required', Rule::in(array_keys(FunnelPage::statusRotulos()))],
            'visibilidade' => ['required', Rule::in(array_keys((array) config('golden.funil_visibilidade')))],
            'pele' => ['required', Rule::in(array_keys((array) config('golden.funil_peles')))],
            'titulo' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable', 'string', 'max:190'],
            'og_imagem' => ['nullable', 'string', 'max:255'],
            'og_imagem_alt' => ['nullable', 'string', 'max:190'],
            'canonical' => ['nullable', 'url', 'max:255'],
            'pixel_ga4' => ['nullable', 'string', 'max:40'],
            'pixel_google_ads' => ['nullable', 'string', 'max:40'],
            'conversao_google_ads' => ['nullable', 'string', 'max:60'],
            'pixel_meta' => ['nullable', 'string', 'max:60'],
            'pixel_tiktok' => ['nullable', 'string', 'max:60'],
            'codigo_head' => ['nullable', 'string', 'max:20000'],
            'codigo_body' => ['nullable', 'string', 'max:20000'],
            'preco_cents' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'nota_media' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'num_avaliacoes' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'nota_legal' => ['nullable', 'string', 'max:2000'],
        ], [
            'slug.regex' => 'O endereço só aceita letras, números e hífen (ex.: kit-frotas-10).',
            'slug.unique' => 'Já existe uma página de campanha com esse endereço.',
            'nome.unique' => 'Já existe uma página de campanha com esse nome.',
        ]);
    }

    /* ------------------------------------------------------------------ contador */

    /** @return array{0: string, 1: string} */
    private function periodo(Request $request): array
    {
        return [
            (string) $request->input('inicio', now()->subDays(29)->toDateString()),
            (string) $request->input('fim', now()->toDateString()),
        ];
    }

    /**
     * Uma consulta para todas as páginas da listagem: os caminhos de cada uma viram chaves
     * de um mapa, o tráfego é somado em grupo e redistribuído — a tela não faz uma query
     * por linha.
     *
     * @param  Collection<int, FunnelPage>  $paginas
     * @return array<int, array<string, int>>
     */
    private function medicoes(Collection $paginas, string $inicio, string $fim): array
    {
        $dono = [];
        foreach ($paginas as $pagina) {
            foreach ($pagina->caminhos() as $caminho) {
                $dono[$caminho] = (int) $pagina->id;
            }
        }

        $fora = $paginas->mapWithKeys(fn (FunnelPage $p) => [
            (int) $p->id => ['visitas' => 0, 'views' => 0, 'cliques' => 0, 'leads' => 0],
        ])->all();

        if ($dono === []) {
            return $fora;
        }

        $linhas = Visit::pessoas()
            ->entre($inicio, $fim)
            ->whereIn('landing_path', array_keys($dono))
            ->selectRaw('landing_path, COUNT(*) as visitas, COALESCE(SUM(visualizacoes),0) as views, COALESCE(SUM(eventos),0) as cliques')
            ->groupBy('landing_path')
            ->get();

        foreach ($linhas as $linha) {
            $id = $dono[$linha->landing_path];
            $atual = $fora[$id];
            $fora[$id] = [
                'visitas' => $atual['visitas'] + (int) $linha->visitas,
                'views' => $atual['views'] + (int) $linha->views,
                'cliques' => $atual['cliques'] + (int) $linha->cliques,
                'leads' => $atual['leads'],
            ];
        }

        // O cadastro do período vem pela página gravada no lead, na mesma régua das visitas.
        Lead::whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->whereIn('funnel_page_id', array_keys($fora))
            ->selectRaw('funnel_page_id as pagina, COUNT(*) as leads')
            ->groupBy('funnel_page_id')
            ->get()
            ->each(function (object $linha) use (&$fora): void {
                $fora[(int) $linha->pagina]['leads'] = (int) $linha->leads;
            });

        return $fora;
    }

    /**
     * Os números que a listagem mostra no alto: o histórico desde a primeira visita e o
     * recorte do período escolhido, para nenhum total depender de filtro sem avisar.
     *
     * @param  Collection<int, FunnelPage>  $paginas
     * @return array<string, int>
     */
    private function totais(Collection $paginas, string $inicio, string $fim): array
    {
        $caminhos = $paginas->flatMap(fn (FunnelPage $p) => $p->caminhos())->unique()->values()->all();
        $ids = $paginas->pluck('id')->all();

        if ($caminhos === []) {
            return ['visitas' => 0, 'leads' => 0, 'no_periodo' => 0, 'leads_periodo' => 0];
        }

        return [
            'visitas' => Visit::pessoas()->whereIn('landing_path', $caminhos)->count(),
            'leads' => Lead::whereIn('funnel_page_id', $ids)->count(),
            'no_periodo' => Visit::pessoas()->entre($inicio, $fim)->whereIn('landing_path', $caminhos)->count(),
            'leads_periodo' => Lead::whereIn('funnel_page_id', $ids)
                ->whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59'])->count(),
        ];
    }

    /**
     * Tráfego e cadastro agrupados pela mesma coluna e no mesmo período, para a taxa de
     * conversão da linha bater com as duas pontas. As oito colunas são as que o
     * x-admin.tabela-origem mostra — a régua do relatório da campanha é a mesma do
     * painel de atribuição, e uma linha sem visita não inventa número.
     *
     * @param  callable(): \Illuminate\Database\Eloquent\Builder  $visitas
     * @return Collection<string, object>
     */
    private function agruparPor(callable $visitas, FunnelPage $pagina, string $inicio, string $fim, string $campo): Collection
    {
        $trafego = $visitas()->whereNotNull($campo)->where($campo, '!=', '')
            ->selectRaw("$campo as grupo, COUNT(*) as visitas, COALESCE(SUM(visualizacoes),0) as views, COALESCE(SUM(eventos),0) as cliques, COALESCE(SUM(CASE WHEN pago THEN 1 ELSE 0 END),0) as pagas")
            ->groupBy($campo)
            ->orderByDesc('visitas')
            ->get();

        $negocio = Lead::where('funnel_page_id', $pagina->id)
            ->whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->whereNotNull($campo)
            ->selectRaw("$campo as grupo, COUNT(*) as leads, SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos, COALESCE(SUM(CASE WHEN status = 'vendido' THEN value ELSE 0 END),0) as receita")
            ->groupBy($campo)
            ->get()
            ->keyBy('grupo');

        return $trafego->map(function (object $linha) use ($negocio, $campo): object {
            $par = $negocio[$linha->grupo] ?? null;
            $linha->leads = (int) ($par->leads ?? 0);
            $linha->vendidos = (int) ($par->vendidos ?? 0);
            $linha->receita = (int) ($par->receita ?? 0);
            $linha->taxa = $linha->visitas > 0 ? round($linha->leads / $linha->visitas * 100, 1) : 0.0;
            $linha->rotulo = match ($campo) {
                'canal' => Atribuidor::rotuloCanal($linha->grupo),
                'rede' => Atribuidor::rotuloRede($linha->grupo),
                'dispositivo' => Visit::rotuloDispositivo($linha->grupo),
                default => (string) $linha->grupo,
            };
            $linha->icone = match ($campo) {
                'canal' => Atribuidor::iconeCanal($linha->grupo),
                'rede' => 'fa-hashtag',
                'dispositivo' => 'fa-mobile-screen',
                default => 'fa-circle-dot',
            };
            $linha->tom = $campo === 'canal' ? tom_por_status('canal', $linha->grupo) : 'neutral';

            return $linha;
        })->values();
    }

    /**
     * Cada clique nas chamadas da página, contado pelo rótulo que o botão declara no site:
     * é assim que o operador descobre qual chamada da campanha funciona.
     *
     * @param  array<int, string>  $caminhos
     * @return Collection<string, object>
     */
    private function cliquesPorChamada(array $caminhos, string $inicio, string $fim): Collection
    {
        return VisitEvent::query()
            ->whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->whereIn('path', $caminhos)
            ->whereIn('tipo', array_keys((array) config('golden.rastreio.eventos')))
            ->selectRaw('tipo, COALESCE(NULLIF(detalhe, ""), "(sem rótulo)") as grupo, COUNT(*) as cliques')
            ->groupBy('tipo')
            ->groupBy('grupo')
            ->orderByDesc('cliques')
            ->limit(15)
            ->get()
            ->map(function (object $linha): object {
                $linha->rotulo = (string) config('golden.rastreio.eventos.'.$linha->tipo.'.rotulo', $linha->tipo);
                $linha->icone = (string) config('golden.rastreio.eventos.'.$linha->tipo.'.icone', 'fa-circle');

                return $linha;
            });
    }

    /**
     * A série do contador: uma linha por dia, com visitas e cadastros do mesmo dia.
     *
     * @param  array<int, string>  $caminhos
     * @return array<int, array<string, mixed>>
     */
    private function porDia(array $caminhos, FunnelPage $pagina, string $inicio, string $fim): array
    {
        $dias = max(1, min(60, strtotime($fim.' 00:00:00') >= strtotime($inicio.' 00:00:00')
            ? (int) ceil((strtotime($fim) - strtotime($inicio)) / 86400) + 1
            : 1));

        $visitas = Visit::pessoas()->whereIn('landing_path', $caminhos)->entre($inicio, $fim)
            ->selectRaw('DATE(ultima_em) as dia, COUNT(*) as visitas')
            ->groupBy('dia')->pluck('visitas', 'dia');

        $leads = Lead::where('funnel_page_id', $pagina->id)
            ->whereBetween('created_at', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as leads')
            ->groupBy('dia')->pluck('leads', 'dia');

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = now()->subDays($i)->toDateString();
            $serie[] = ['dia' => $dia, 'visitas' => (int) ($visitas[$dia] ?? 0), 'leads' => (int) ($leads[$dia] ?? 0)];
        }

        return $serie;
    }
}
