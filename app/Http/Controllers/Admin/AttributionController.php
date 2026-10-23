<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Visit;
use App\Models\VisitEvent;
use App\Services\Atribuidor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O painel de atribuição: quantas visitas, cliques e cadastros cada origem do site
 * produziu — anúncio pago, rede social, busca orgânica, busca por IA, indicação,
 * e-mail, parceiro ou acesso direto — e a jornada completa de cada visitante.
 *
 * Visitas e leads são somados com os MESMOS filtros (periodo + canal + rede +
 * dispositivo): a taxa de conversão de uma linha só faz sentido se os dois lados
 * estiveram na mesma régua.
 */
class AttributionController extends Controller
{
    public function index(Request $request): View
    {
        $periodo = $this->periodo($request);

        return view('admin.attribution.index', [
            'kpis' => $this->kpis($request, $periodo),
            'porCanal' => $this->agrupar($request, $periodo, 'canal'),
            'porRede' => $this->agrupar($request, $periodo, 'rede', 12),
            'porIa' => $this->agrupar($request, $periodo, 'rede', 0, ['busca_ia']),
            'porLanding' => $this->agrupar($request, $periodo, 'landing_path', 10),
            'porOrigem' => $this->agrupar($request, $periodo, 'origem_host', 10),
            'dispositivos' => $this->dispositivos($request, $periodo),
            'visitas' => $this->visitas($request, $periodo)->withCount('leads')->latest('ultima_em')->paginate(itens_por_pagina())->withQueryString(),
            'periodo' => $periodo,
            'canais' => Atribuidor::canaisParaFiltro(),
            'redes' => $this->redesNoPeriodo($periodo),
        ]);
    }

    /** A jornada de uma visita: cada page view e cada clique, na ordem em que aconteceram. */
    public function show(Visit $visita): View
    {
        $visita->load(['events' => fn ($q) => $q->orderBy('created_at'), 'leads']);

        $outras = Visit::where('visitor_id', $visita->visitor_id)
            ->where('id', '!=', $visita->id)
            ->withCount('leads')
            ->orderBy('aberta_em')
            ->get();

        return view('admin.attribution.show', [
            'visita' => $visita,
            'outras' => $outras,
            'jornada' => $this->jornada($visita, $outras),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $periodo = $this->periodo($request);
        $visitas = $this->visitas($request, $periodo)->withCount('leads')->latest('ultima_em')->get();

        $filename = 'golden-atribuicao-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($visitas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Abertura', 'Última atividade', 'Canal', 'Rede', 'Pago', 'Motivo', 'utm_source', 'utm_medium', 'utm_campaign', 'Clique', 'Origem (host)', 'Origem (caminho)', 'Destino', 'Dispositivo', 'Navegador', 'Views', 'Cliques', 'Leads']);
            foreach ($visitas as $v) {
                fputcsv($out, [
                    $v->aberta_em?->format('d/m/Y H:i'), $v->ultima_em?->format('d/m/Y H:i'),
                    Atribuidor::rotuloCanal($v->canal), Atribuidor::rotuloRede($v->rede), $v->pago ? 'sim' : 'não', $v->motivo,
                    $v->utm_source, $v->utm_medium, $v->utm_campaign, $v->clique_id,
                    $v->origem_host, $v->origem_path, $v->landing_path,
                    $v->dispositivo, $v->navegador, $v->visualizacoes, $v->eventos, $v->leads_count,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodo(Request $request): array
    {
        return [
            $request->input('inicio', now()->subDays(29)->toDateString()),
            $request->input('fim', now()->toDateString()),
        ];
    }

    /** A visita dos filtros, sempre sem robô. Serve de base para as contagens e para a lista. */
    private function visitas(Request $request, array $periodo, ?array $canais = null)
    {
        return Visit::query()
            ->pessoas()
            ->when($canais, fn ($q) => $q->whereIn('canal', $canais))
            ->entre($periodo[0], $periodo[1])
            ->when($request->filled('canal') && ! $canais, fn ($q) => $q->where('canal', $request->canal))
            ->when($request->filled('rede'), fn ($q) => $q->where('rede', $request->rede))
            ->when($request->filled('dispositivo'), fn ($q) => $q->where('dispositivo', $request->dispositivo))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('landing_path', 'like', $t)
                    ->orWhere('origem_host', 'like', $t)
                    ->orWhere('utm_campaign', 'like', $t)
                    ->orWhere('utm_content', 'like', $t));
            });
    }

    /**
     * O mesmo agrupamento do lado comercial. Nem toda coluna do tráfego tem par no
     * lead: landing page existe nas duas pontas (com outro nome), host de origem não —
     * o lead só guarda o referer inteiro. Sem coluna, a linha mostra o tráfego e
     * deixa o comércio em branco em vez de inventar números.
     */
    private function campoLead(string $campo): ?string
    {
        return match ($campo) {
            'landing_path' => 'landing_page',
            'origem_host' => null,
            default => $campo,
        };
    }

    /** Os leads na mesma régua de filtros, para a conversão bater com a visita. */
    private function leads(Request $request, array $periodo, string $campo)
    {
        return Lead::query()
            ->whereBetween('created_at', [$periodo[0].' 00:00:00', $periodo[1].' 23:59:59'])
            ->when($request->filled('dispositivo'), fn ($q) => $q->where('dispositivo', $request->dispositivo))
            ->when($request->filled('rede') && $campo !== 'rede', fn ($q) => $q->where('rede', $request->rede))
            ->when($request->filled('canal') && $campo !== 'canal', fn ($q) => $q->where('canal', $request->canal));
    }

    /**
     * Uma linha de relatório: visitas + views + cliques do lado do tráfego, leads +
     * vendas + receita do lado comercial, tudo agrupado pela coluna pedida.
     *
     * @return Collection<string, object>
     */
    private function agrupar(Request $request, array $periodo, string $campo, int $limite = 0, ?array $canais = null): Collection
    {
        $trafego = $this->visitas($request, $periodo, $canais)
            ->whereNotNull($campo)
            ->selectRaw("$campo as grupo, COUNT(*) as visitas, COALESCE(SUM(visualizacoes), 0) as views, COALESCE(SUM(eventos), 0) as cliques, COALESCE(SUM(CASE WHEN pago THEN 1 ELSE 0 END), 0) as pagas")
            ->groupBy($campo)
            ->get()
            ->filter(fn ($linha) => $linha->grupo !== null && $linha->grupo !== '');

        $colunaLead = $this->campoLead($campo);

        $comercio = $colunaLead === null
            ? collect()
            : $this->leads($request, $periodo, $campo)
                ->selectRaw("$colunaLead as grupo, COUNT(*) as leads, SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos, COALESCE(SUM(CASE WHEN status = 'vendido' THEN value ELSE 0 END), 0) as receita")
                ->groupBy($colunaLead)
                ->get()
                ->keyBy('grupo');

        $linhas = $trafego->map(function ($linha) use ($comercio, $campo, $colunaLead) {
            $negocio = $colunaLead === null ? null : $comercio[$linha->grupo] ?? null;
            $linha->leads = (int) ($negocio->leads ?? 0);
            $linha->vendidos = (int) ($negocio->vendidos ?? 0);
            $linha->receita = (int) ($negocio->receita ?? 0);
            $linha->taxa = $linha->visitas > 0 ? round($linha->leads / $linha->visitas * 100, 1) : 0.0;
            $linha->rotulo = $campo === 'canal'
                ? Atribuidor::rotuloCanal($linha->grupo)
                : ($campo === 'rede' ? Atribuidor::rotuloRede($linha->grupo) : $linha->grupo);
            $linha->icone = match ($campo) {
                'canal' => Atribuidor::iconeCanal($linha->grupo),
                'rede' => 'fa-hashtag',
                'landing_path' => 'fa-file-lines',
                default => 'fa-globe',
            };
            $linha->tom = $campo === 'canal' ? tom_por_status('canal', $linha->grupo) : 'gold';

            return $linha;
        })->sortByDesc('visitas')->values();

        return $limite ? $linhas->take($limite) : $linhas;
    }

    /** @return array<string, mixed> */
    private function kpis(Request $request, array $periodo): array
    {
        $contagem = $this->visitas($request, $periodo)
            ->selectRaw('COUNT(*) as visitas, COALESCE(SUM(visualizacoes),0) as views, COALESCE(SUM(eventos),0) as cliques, COALESCE(SUM(CASE WHEN pago THEN 1 ELSE 0 END),0) as pagos')
            ->first();
        $leads = $this->leads($request, $periodo, 'nenhum')->count();

        return [
            'visitas' => (int) $contagem->visitas,
            'views' => (int) $contagem->views,
            'cliques' => (int) $contagem->cliques,
            'leads' => $leads,
            'taxa' => $contagem->visitas > 0 ? round($leads / $contagem->visitas * 100, 1) : 0.0,
            'robos' => Visit::where('bot', true)->entre($periodo[0], $periodo[1])->count(),
            'pagos' => (int) $contagem->pagos,
        ];
    }

    /** @return Collection<string, object> */
    private function dispositivos(Request $request, array $periodo): Collection
    {
        return $this->visitas($request, $periodo)
            ->selectRaw('dispositivo as grupo, COUNT(*) as visitas')
            ->whereNotNull('dispositivo')
            ->groupBy('dispositivo')
            ->orderByDesc('visitas')
            ->get();
    }

    /**
     * As redes realmente vistas no período — alimenta o filtro sem oferecer lista
     * impossível (só o que o tráfego produziu).
     *
     * @return array<string, string>
     */
    private function redesNoPeriodo(array $periodo): array
    {
        return Visit::pessoas()->entre($periodo[0], $periodo[1])->whereNotNull('rede')
            ->distinct()->orderBy('rede')->pluck('rede')
            ->mapWithKeys(fn (string $rede) => [$rede => Atribuidor::rotuloRede($rede)])
            ->all();
    }

    /**
     * A linha do tempo da jornada inteira do visitante: todas as visitas dele, com os
     * eventos de cada uma, na ordem cronológica. É a resposta para "o que ele fez antes
     * de cadastrar".
     *
     * @return array<int, array<string, mixed>>
     */
    private function jornada(Visit $visita, Collection $outras): array
    {
        $todas = collect([$visita])->concat($outras)->sortBy(fn (Visit $v) => $v->aberta_em?->timestamp ?? 0);
        $eventos = VisitEvent::whereIn('visit_id', $todas->pluck('id'))->orderBy('created_at')->get()->groupBy('visit_id');

        $linhas = [];
        foreach ($todas as $v) {
            $linhas[] = [
                'tipo' => 'visita',
                'quando' => $v->aberta_em,
                'titulo' => Atribuidor::rotuloCanal($v->canal).' · '.$v->landing_path,
                'detalhe' => $v->id === $visita->id ? 'visita analisada' : $v->origemDetalhada(),
                'icone' => Atribuidor::iconeCanal($v->canal),
                'tom' => tom_por_status('canal', $v->canal),
            ];

            foreach ($eventos[$v->id] ?? collect() as $evento) {
                $linhas[] = [
                    'tipo' => 'evento',
                    'quando' => $evento->created_at,
                    'titulo' => $evento->rotulo(),
                    'detalhe' => trim((string) (($evento->path ?: '').' '.($evento->detalhe ?: '')) ?: $evento->destino),
                    'icone' => $evento->icone(),
                    'tom' => 'neutral',
                ];
            }
        }

        return $linhas;
    }
}
