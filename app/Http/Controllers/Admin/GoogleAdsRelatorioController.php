<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleAdsAccount;
use App\Support\Csv;
use App\Support\GoogleAdsCache;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A tela do relatório: a métrica da janela, a análise do copiloto e o histórico dela.
 *
 * O conteúdo é do componente Livewire `RelatorioDoGoogleAds`, que lê a cache, enfileira a
 * sincronização quando falta e conversa com o copiloto no clique. Este controller só desenha
 * a página e exporta a planilha — a mesma que a tabela mostra, com as colunas do CSV da casa.
 */
class GoogleAdsRelatorioController extends Controller
{
    public function index(): View
    {
        return view('admin.google-ads.relatorio');
    }

    /** O CSV da janela que está na tela: sem chamar o Google, sem refazer conta. */
    public function exportar(Request $request): StreamedResponse
    {
        $conta = $this->conta($request, $request->integer('conta') ?: null);

        abort_if($conta === null, 404);

        $dias = $this->periodoValido((int) $request->query('dias', config('google_ads.dias_padrao', 30)));
        $janela = GoogleAdsCache::metricas($conta, $dias);
        $campanhas = collect($janela['campanhas'] ?? []);
        $sincronizado = $janela['sincronizado_em'] ?? null;

        return Csv::baixar('golden-google-ads-'.$dias.'dias', [
            'Campanha' => fn (array $c) => $c['nome'],
            'Conta' => fn () => $conta->nomeTela(),
            'Conversões' => fn (array $c) => number_format((float) $c['conversoes'], 2, ',', '.'),
            'CPC médio (R$)' => fn (array $c) => Csv::moeda($c['cpc_medio_centavos']),
            'CTR (%)' => fn (array $c) => number_format((float) $c['ctr'], 2, ',', '.'),
            'Custo (R$)' => fn (array $c) => Csv::moeda($c['custo_centavos']),
            'ID' => fn (array $c) => $c['id'],
            'Impressões' => fn (array $c) => $c['impressoes'],
            'Cliques' => fn (array $c) => $c['cliques'],
            'Período (dias)' => fn () => $dias,
            'ROAS' => fn (array $c) => $c['roas'] === null ? '' : number_format((float) $c['roas'], 2, ',', '.'),
            'Situação' => fn (array $c) => $c['status_rotulo'],
            'Sincronizado em' => fn () => $sincronizado ? Csv::data($sincronizado) : '',
            'Valor das conversões (R$)' => fn (array $c) => Csv::moeda($c['valor_conversoes_centavos']),
        ], $campanhas);
    }

    private function conta(Request $request, ?int $contaId): ?GoogleAdsAccount
    {
        $contas = GoogleAdsAccount::visiveisPara($request->user())->orderByDesc('id')->get();

        return $contaId === null ? $contas->first() : $contas->firstWhere('id', $contaId);
    }

    private function periodoValido(int $dias): int
    {
        return in_array($dias, (array) config('google_ads.periodos', [30]), true)
            ? $dias
            : (int) config('google_ads.dias_padrao', 30);
    }
}
