<?php

namespace App\Livewire;

use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GoogleAdsCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * A tela de Google Ads do painel: a conta conectada, o resumo do período e as campanhas.
 *
 * Ela não fala com o Google. Em toda requisição de tela existe um único caminho — ler a cache
 * que o job de fila preenche —, porque uma recarga de painel não pode custar uma chamada de
 * rede nem depender do tempo de resposta do Google. Quando a janela pedida ainda não tem
 * métrica, a tela enfileirar o job e mostra "Carregando…", voltando a perguntar sozinha até o
 * número aparecer.
 *
 * Os filtros (período, busca, situação) vivem aqui, e não num formulário GET, por uma razão:
 * o período novo pode exigir sincronização, e um `<select>` de HTML puro não tem como pedir
 * isso sem recarregar a página inteira.
 */
class GoogleAdsDashboard extends Component
{
    /** A janela pedida: um dos períodos de config('google_ads.periodos'). */
    public int $dias = 30;

    public string $busca = '';

    /** O enum de status (ENABLED, PAUSED…), não a etiqueta em português. */
    public string $situacao = '';

    public int $pagina = 1;

    /** A conta escolhida por um administrador que opera mais de uma conexão. */
    public ?int $contaId = null;

    public function mount(): void
    {
        $this->dias = $this->periodoValido((int) request()->query('dias', config('google_ads.dias_padrao', 30)));
        $this->busca = trim((string) request()->query('busca'));
        $this->situacao = (string) request()->query('situacao');
        $this->pagina = max(1, (int) request()->query('pagina', 1));
        $this->contaId = request()->integer('conta') ?: null;

        $this->sincronizarSeFaltar();
    }

    /** "Atualizar agora": descarta esta janela, enfileira de novo e deixa o polling mostrar. */
    public function atualizar(): void
    {
        $conta = $this->conta();

        if ($conta === null) {
            return;
        }

        GoogleAdsCache::esquecerJanela($conta, $this->dias);
        SyncGoogleAdsMetrics::dispatch($conta->id, $this->dias);
    }

    public function updatedDias(mixed $valor): void
    {
        $this->dias = $this->periodoValido((int) $valor);
        $this->pagina = 1;
        $this->sincronizarSeFaltar();
    }

    public function updatedBusca(): void
    {
        $this->pagina = 1;
    }

    public function updatedSituacao(): void
    {
        $this->pagina = 1;
    }

    public function updatedConta(): void
    {
        $this->pagina = 1;
        $this->sincronizarSeFaltar();
    }

    public function render(): View
    {
        $contas = GoogleAdsAccount::visiveisPara(auth()->user())->orderByDesc('id')->get();
        $conta = $this->conta($contas);
        $janela = $conta !== null ? GoogleAdsCache::metricas($conta, $this->dias) : null;
        $erro = $conta !== null ? GoogleAdsCache::erro($conta, $this->dias) : null;
        $faltando = GoogleAdsService::credenciaisFaltantes();

        return view('livewire.google-ads-dashboard', [
            'conta' => $conta,
            'contas' => $contas,
            'configurado' => $faltando->isEmpty(),
            // Dizer quais faltam é o que transforma "não funciona" em "falta instalar": o
            // administrador lê o nome da variável e preenche o ambiente dele.
            'credenciais_faltando' => $faltando,
            'resumo' => $janela['resumo'] ?? null,
            'campanhas' => $this->paginar($janela['campanhas'] ?? []),
            'sincronizado_em' => $janela['sincronizado_em'] ?? null,
            // Sem métrica e sem erro na fila: é o "Carregando…". Com erro, a tela diz o motivo
            // em vez de girar para sempre atrás de um job que já morreu.
            'carregando' => $conta !== null && $janela === null && $erro === null,
            'erro' => $erro,
            'periodos' => config('google_ads.periodos'),
            'rotulos' => config('google_ads.rotulos_metricas'),
            'situacoes' => $this->situacoesDisponiveis($janela),
        ]);
    }

    /**
     * A conta desta tela. A regra é uma só — `visiveisPara` —, então um vendedor nunca recebe
     * a conta de outro, mesmo acertando o `?conta=` na URL: o que não está na lista visível
     * volta para a primeira conta visível.
     */
    private function conta(?Collection $contas = null): ?GoogleAdsAccount
    {
        $contas ??= $this->contasVisiveis();

        return $this->contaId === null
            ? $contas->first()
            : $contas->firstWhere('id', $this->contaId) ?? $contas->first();
    }

    /** As conexões que o usuário da sessão pode ver — a mesma consulta do seletor e da tela. */
    private function contasVisiveis(): Collection
    {
        return GoogleAdsAccount::visiveisPara(auth()->user())->orderByDesc('id')->get();
    }

    /** Enfileira a janela pedida se ela ainda está vazia — e só se ninguém já pediu ela. */
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

    /** Busca + situação sobre a lista que já está em cache: nenhum filtro volta ao Google. */
    private function paginar(array $campanhas): LengthAwarePaginator
    {
        $termo = mb_strtolower($this->busca);

        $linhas = collect($campanhas)
            ->when($termo !== '', fn (Collection $c): Collection => $c->filter(
                fn (array $l): bool => str_contains(mb_strtolower($l['nome']), $termo)
                    || str_contains($l['id'], $this->busca)
            ))
            ->when($this->situacao !== '', fn (Collection $c): Collection => $c->filter(
                fn (array $l): bool => $l['status'] === $this->situacao
            ))
            ->sortByDesc('custo_centavos')
            ->values();

        $porPagina = itens_por_pagina();

        return new LengthAwarePaginator(
            $linhas->forPage($this->pagina, $porPagina),
            $linhas->count(),
            $porPagina,
            $this->pagina,
            [
                'path' => route('admin.google-ads.index'),
                'query' => array_filter([
                    'dias' => $this->dias,
                    'busca' => $this->busca !== '' ? $this->busca : null,
                    'situacao' => $this->situacao !== '' ? $this->situacao : null,
                    'conta' => $this->contaId,
                ]),
            ],
        );
    }

    /**
     * As situações que de fato aparecem nesta conta e período. Um filtro fixo com os sete
     * enums da API ofereceria opções que sempre devolveriam a tabela vazia.
     */
    private function situacoesDisponiveis(?array $janela): array
    {
        $servico = app(GoogleAdsService::class);

        return collect($janela['campanhas'] ?? [])
            ->pluck('status')
            ->unique()
            ->mapWithKeys(fn (string $codigo): array => [$codigo => $servico->traduzStatus($codigo)])
            ->all();
    }

    /** Fora das janelas do painel não há botão: volta para a padrão em vez de consultar o Google à toa. */
    private function periodoValido(int $dias): int
    {
        return in_array($dias, config('google_ads.periodos', [30]), true)
            ? $dias
            : (int) config('google_ads.dias_padrao', 30);
    }
}
