<?php

namespace App\Jobs;

use App\Mail\ContaGoogleAdsDesconectada;
use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\GoogleAdsCache;
use App\Support\Notifica;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Puxa a métrica de uma conta do Google Ads e escreve no cache — é o único caminho pelo qual
 * o painel vê número do Google.
 *
 * A tela nunca chama a API: pede ao cache e, se não há janela, enfileira este job. O agendamento
 * (`google-ads:sync`) faz o mesmo para todas as contas ativas. Assim uma recarga de painel não
 * custa uma chamada de rede nem estoura a cota, e o tempo de resposta do Google deixa de ser o
 * tempo de resposta da tela.
 *
 * As três tentativas com espera crescente (30s, 2min, 5min) são o colchão para o erro comum:
 * uma falha momentânea de rede ou de cota. Falha de credencial não se tenta de novo — o token
 * de atualização só se conserta com uma pessoa entrando no Google de novo, então a conta é
 * marcada como desconectada e o dono é avisado por e-mail.
 */
class SyncGoogleAdsMetrics implements ShouldQueue
{
    use Queueable;

    /** Duas tentativas depois da primeira, com espera entre elas. */
    public int $tries = 3;

    /** @var array<int, int> segundos de espera antes da 2ª e da 3ª tentativa */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $contaId,
        public int $dias = 30,
    ) {}

    public function handle(GoogleAdsService $servico): void
    {
        $conta = GoogleAdsAccount::find($this->contaId);

        if ($conta === null) {
            return;
        }

        try {
            $resultado = $servico->buscarCampanhasComMetricas($conta, $this->dias);
        } catch (Throwable $e) {
            if (GoogleAdsService::credencialRecusada($e)) {
                $this->desconectar($conta, $e);

                return;
            }

            throw $e;
        }

        GoogleAdsCache::gravar($conta, $this->dias, $resultado['campanhas'], $resultado['resumo']);
        $conta->forceFill(['sincronizado_em' => now()])->save();
    }

    /**
     * Acabaram as tentativas por um motivo que não é credencial (cota, timeout, campanha com
     * GAQL inválido). O registro da chamada já está em `google_ads_api_logs`; falta avisar a
     * janela — sem isto a tela ficaria em "Carregando…" para sempre.
     */
    public function failed(Throwable $e): void
    {
        $conta = GoogleAdsAccount::find($this->contaId);

        if ($conta === null || GoogleAdsService::credencialRecusada($e)) {
            return;
        }

        GoogleAdsCache::gravarErro($conta, $this->dias, $e->getMessage());
    }

    /**
     * Desconecta e avisa. A conta fica visível no painel com o motivo, porque apagar o vínculo
     * jogaria fora a métrica já sincronizada e obrigaria a refazer a autorização inteira.
     */
    private function desconectar(GoogleAdsAccount $conta, Throwable $e): void
    {
        $motivo = 'O Google recusou a credencial guardada. É preciso reconectar a conta.';

        $conta->desconectar($motivo);

        GoogleAdsCache::gravarErro($conta, $this->dias, $motivo.' '.$e->getMessage());

        $dono = $conta->user;

        if ($dono !== null && $dono->email !== null && $dono->email !== '') {
            Mail::to($dono->email)->queue(new ContaGoogleAdsDesconectada($conta, $dono));
        }

        Notifica::disparar(
            'ads_desconectado',
            'A conta de Google Ads '.$conta->customer_id.' pede reconexão',
            $motivo.' O painel continua mostrando a última métrica sincronizada, marcada como parada.',
            null,
            [
                'conta_id' => $conta->id,
                'customer_id' => $conta->customer_id,
                'motivo' => $motivo,
            ],
            'ads_desconectado:'.$conta->id,
        );

        Activity::log(
            'google_ads_desconectada',
            "Conta Google Ads {$conta->customer_id} desconectada: o Google recusou o token de atualização.",
            $conta,
        );
    }
}
