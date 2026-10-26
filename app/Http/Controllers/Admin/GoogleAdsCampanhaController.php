<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GoogleAdsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Pausar e ativar campanha — a escrita na API do Google Ads.
 *
 * A tela é do componente Livewire, mas o ato de escrever é um `<form>` POST comum, como
 * qualquer ato destrutivo do painel: é assim que ele herda a confirmação em SweetAlert2 e o
 * retorno em toastr sem que a tela precise de JavaScript próprio.
 *
 * Nada aqui vai ao Google de uma vez. São duas chamadas: primeiro `validate_only`, que percorre
 * permissão, status e FieldMask sem tocar na campanha; só depois, se o Google aprovou, a mesma
 * operação escrita de verdade. Uma conta real de anunciante não é lugar de experimentar.
 *
 * A campanha também nunca vem do formulário como verdade: o identificador é conferido contra a
 * lista sincronizada **desta conta**, para o painel não escrever em um número que alguém
 * digitou ou que pertence a outra conta conectada.
 */
class GoogleAdsCampanhaController extends Controller
{
    public function __construct(private GoogleAdsService $servico) {}

    public function mudarStatus(Request $request, string $campanha): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', Rule::in(GoogleAdsService::STATUS_ESCREVEVEIS)],
            'conta' => ['nullable', 'integer'],
            'dias' => ['nullable', 'integer'],
        ]);

        $conta = $this->conta($request, isset($dados['conta']) ? (int) $dados['conta'] : null);

        if ($conta === null) {
            return $this->volta($request, 'error', 'Nenhuma conta de Google Ads conectada a esta sessão — ou a conta pedida não está entre as suas.');
        }

        if ($conta->status !== GoogleAdsAccount::ATIVO) {
            return $this->volta($request, 'error', "A conta {$conta->nomeTela()} está desconectada: reconecte antes de mudar o status de uma campanha.");
        }

        $dias = (int) ($dados['dias'] ?: config('google_ads.dias_padrao', 30));
        $campanha_tela = collect(GoogleAdsCache::metricas($conta, $dias)['campanhas'] ?? [])
            ->firstWhere('id', $campanha);

        if ($campanha_tela === null) {
            return $this->volta($request, 'error', 'Esta campanha não está na lista sincronizada desta conta. Atualize a tela e tente de novo.');
        }

        $rotulo = $this->servico->traduzStatus($dados['status']);

        try {
            // A prova antes da escrita: se o Google reprovar a operação simulada, nada acontece.
            $this->servico->validarOperacao($conta, $campanha, $dados['status']);
            $this->servico->mudarStatus($conta, $campanha, $dados['status']);
        } catch (Throwable $e) {
            if (GoogleAdsService::credencialRecusada($e)) {
                $conta->desconectar('O Google recusou a credencial durante uma escrita. É preciso reconectar a conta.');
            }

            return $this->volta($request, 'error', "O Google não aplicou a mudança em {$campanha_tela['nome']}: ".$e->getMessage());
        }

        Activity::log(
            'google_ads_campanha_status',
            "Campanha {$campanha_tela['nome']} ({$campanha}) passou para {$rotulo} na conta {$conta->numeroConta()}.",
            $conta,
            ['campaign_id' => $campanha, 'status' => $dados['status']],
        );

        // A linha velha da tabela é a da conta do Google antes da mudança: descarta a janela e
        // pede de novo, para a tela mostrar o status que o Google aceita agora.
        GoogleAdsCache::esquecerJanela($conta, $dias);
        SyncGoogleAdsMetrics::dispatch($conta->id, $dias);

        return $this->volta($request, 'success', "{$campanha_tela['nome']} agora está {$rotulo}. A métrica está sendo atualizada.");
    }

    /**
     * A conta desta escrita, pela mesma régua de visibilidade da tela: o `?conta=` do formulário
     * só vale se estiver entre as que o usuário da sessão pode ver.
     */
    private function conta(Request $request, ?int $contaId): ?GoogleAdsAccount
    {
        $contas = GoogleAdsAccount::visiveisPara($request->user())->orderByDesc('id')->get();

        return $contaId === null ? $contas->first() : $contas->firstWhere('id', $contaId);
    }

    /**
     * Volta para a tela com os filtros no endereço — o formulário é POST, quem descreve a tela
     * é a query que veio na ação.
     */
    private function volta(Request $request, string $tipo, string $mensagem): RedirectResponse
    {
        return redirect()
            ->route('admin.google-ads.index', array_filter($request->only(['dias', 'busca', 'situacao', 'conta']), fn ($valor): bool => $valor !== null && $valor !== ''))
            ->with($tipo, $mensagem);
    }
}
