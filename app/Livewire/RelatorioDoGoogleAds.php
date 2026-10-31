<?php

namespace App\Livewire;

use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsRelatorio;
use App\Services\Assistente\Provedores\Fabrica;
use App\Services\GoogleAds\Copiloto;
use App\Services\GoogleAds\Excecoes\CopilotoIndisponivel;
use App\Support\GoogleAdsCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * O relatório do Google Ads com a leitura do copiloto.
 *
 * A métrica continua vindo só da cache — nenhuma recarga de tela chama o Google. Quando a
 * janela pedida ainda está vazia, o componente enfileira a sincronização e pergunta de novo
 * sozinho, como a tela de métricas; quando ela chega, o botão "Analisar" entrega os números ao
 * copiloto e a análise fica gravada no histórico, junto com a foto do período.
 *
 * O histórico é por conta e por janela: comparar a leitura de sete dias com a de trinta não
 * diria nada, e cada linha guarda os números que a produziram. Ver uma análise antiga não gasta
 * uma chamada de IA.
 */
class RelatorioDoGoogleAds extends Component
{
    public int $dias = 30;

    public ?int $contaId = null;

    public int $pagina = 1;

    /** A análise aberta; nulo mostra a mais recente da janela. */
    public ?int $relatorioId = null;

    /** A falha da última tentativa de análise, em português. */
    public ?string $falha = null;

    public function mount(): void
    {
        $this->dias = $this->periodoValido((int) request()->query('dias', config('google_ads.dias_padrao', 30)));
        $this->contaId = request()->integer('conta') ?: null;
        $this->relatorioId = request()->integer('relatorio') ?: null;

        $this->sincronizarSeFaltar();
    }

    public function updatedDias(mixed $valor): void
    {
        $this->dias = $this->periodoValido((int) $valor);
        $this->pagina = 1;
        $this->relatorioId = null;
        $this->falha = null;
        $this->sincronizarSeFaltar();
    }

    public function updatedContaId(): void
    {
        $this->pagina = 1;
        $this->relatorioId = null;
        $this->falha = null;
        $this->sincronizarSeFaltar();
    }

    /** "Atualizar agora": descarta a janela e deixa o job trazer de novo. */
    public function atualizar(): void
    {
        $conta = $this->conta();

        if ($conta === null) {
            return;
        }

        GoogleAdsCache::esquecerJanela($conta, $this->dias);
        SyncGoogleAdsMetrics::dispatch($conta->id, $this->dias);
    }

    /** Abre uma análise do histórico — nenhuma chamada nova à IA. */
    public function ver(int $relatorioId): void
    {
        $existe = $this->historicoQuery()->whereKey($relatorioId)->exists();

        if ($existe) {
            $this->relatorioId = $relatorioId;
            $this->falha = null;
        }
    }

    /**
     * Pede a leitura ao copiloto e guarda a foto do período com ela.
     *
     * A chamada é síncrona (é o tempo da IA), mas nada é gravado antes de a resposta chegar
     * inteira: uma falha deixa a tela como estava e diz o motivo em português.
     */
    public function gerar(): void
    {
        $this->falha = null;
        $conta = $this->conta();

        if ($conta === null) {
            $this->falha = 'Conecte uma conta do Google Ads antes de pedir a análise.';

            return;
        }

        $janela = GoogleAdsCache::metricas($conta, $this->dias);

        if ($janela === null) {
            $this->falha = 'A métrica deste período ainda não chegou. Ela está sendo sincronizada — tente de novo em instantes.';
            $this->sincronizarSeFaltar();

            return;
        }

        try {
            $analise = app(Copiloto::class)->analisar($janela['resumo'], $janela['campanhas'], $this->dias);
        } catch (CopilotoIndisponivel $e) {
            $this->falha = $e->getMessage();
            $this->dispatch('golden-aviso', tipo: 'erro', texto: $e->getMessage());

            return;
        }

        $relatorio = GoogleAdsRelatorio::create([
            'user_id' => auth()->id(),
            'google_ads_account_id' => $conta->id,
            'dias' => $this->dias,
            'resumo_json' => $janela['resumo'],
            'campanhas_json' => $janela['campanhas'],
            'analise_json' => $analise,
            'modelo' => $analise['modelo'],
            'gerado_em' => now(),
        ]);

        $this->relatorioId = $relatorio->id;
        $this->dispatch('golden-aviso', tipo: 'sucesso', texto: 'Análise do copiloto pronta e guardada no histórico.');
    }

    public function paginaAnterior(): void
    {
        $this->pagina = max(1, $this->pagina - 1);
    }

    public function proximaPagina(): void
    {
        $this->pagina++;
    }

    public function render(): View
    {
        $contas = GoogleAdsAccount::visiveisPara(auth()->user())->orderByDesc('id')->get();
        $conta = $this->conta($contas);
        $janela = $conta !== null ? GoogleAdsCache::metricas($conta, $this->dias) : null;
        $erro = $conta !== null ? GoogleAdsCache::erro($conta, $this->dias) : null;
        $historico = $this->historico($conta);

        $relatorio = $this->relatorioId !== null
            ? $historico->firstWhere('id', $this->relatorioId)
            : $historico->first();

        return view('livewire.relatorio-google-ads', [
            'conta' => $conta,
            'contas' => $contas,
            'resumo' => $janela['resumo'] ?? null,
            'campanhas' => $this->paginar($janela['campanhas'] ?? []),
            'sincronizado_em' => $janela['sincronizado_em'] ?? null,
            'carregando' => $conta !== null && $janela === null && $erro === null,
            'erro' => $erro,
            'relatorio' => $relatorio,
            'relatorios' => $historico,
            'periodos' => config('google_ads.periodos'),
            'ia_pronta' => Fabrica::pronta('conversa'),
            'ia_faltando' => Fabrica::faltando(),
        ]);
    }

    /** A conta desta tela, pela mesma régua de visibilidade do painel. */
    private function conta(?Collection $contas = null): ?GoogleAdsAccount
    {
        $contas ??= GoogleAdsAccount::visiveisPara(auth()->user())->orderByDesc('id')->get();

        return $this->contaId === null
            ? $contas->first()
            : $contas->firstWhere('id', $this->contaId) ?? $contas->first();
    }

    private function historico(?GoogleAdsAccount $conta): Collection
    {
        if ($conta === null) {
            return collect();
        }

        return $this->historicoQuery()
            ->where('google_ads_account_id', $conta->id)
            ->where('dias', $this->dias)
            ->latest('gerado_em')
            ->limit(12)
            ->get();
    }

    private function historicoQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return GoogleAdsRelatorio::query()
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('user_id', auth()->id()));
    }

    /** Sem métrica não se enfileira duas vezes: a trava é a mesma da tela de campanhas. */
    private function sincronizarSeFaltar(): void
    {
        $conta = $this->conta();

        if ($conta === null || GoogleAdsCache::metricas($conta, $this->dias) !== null) {
            return;
        }

        if (GoogleAdsCache::acabaDeSerEnfileirado($conta, $this->dias)) {
            return;
        }

        SyncGoogleAdsMetrics::dispatch($conta->id, $this->dias);
    }

    private function paginar(array $campanhas): LengthAwarePaginator
    {
        $linhas = collect($campanhas)->sortByDesc('custo_centavos')->values();
        $porPagina = itens_por_pagina();

        return new LengthAwarePaginator(
            $linhas->forPage($this->pagina, $porPagina),
            $linhas->count(),
            $porPagina,
            $this->pagina,
        );
    }

    private function periodoValido(int $dias): int
    {
        return in_array($dias, (array) config('google_ads.periodos', [30]), true)
            ? $dias
            : (int) config('google_ads.dias_padrao', 30);
    }
}
