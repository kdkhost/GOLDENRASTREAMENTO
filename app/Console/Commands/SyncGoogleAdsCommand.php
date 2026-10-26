<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleAdsMetrics;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\ChavesDeIntegracao;
use Illuminate\Console\Command;

/**
 * O batimento cardíaco da métrica: de tempos em tempos, enfileira uma sincronização para cada
 * conta conectada.
 *
 * O comando não fala com o Google — ele só põe os jobs na fila, porque é a fila que segura as
 * três tentativas com espera e o aviso de conta desconectada. Rodar a chamada aqui, no
 * processo do cron, perderia esse colchão e tomaria o tempo do cron inteiro.
 *
 * Falta credencial — no banco ou no ambiente —, o comando devolve falha antes de enfileirar
 * qualquer coisa:
 * um agendamento que dispara jobs fadados ao erro é a receita para encher `google_ads_api_logs`
 * e a caixa de entrada de todo mundo de madrugada. Com `--agendamento`, porém, a falta de
 * credencial é o estado normal de uma instalação que ainda não conectou conta nenhuma: aí vira
 * aviso e saída limpa, porque um cron que escreve ERROR no log a cada quinze minutos faz a equipe
 * parar de ler o log — e o log é onde se vê o erro de verdade.
 */
class SyncGoogleAdsCommand extends Command
{
    protected $signature = 'google-ads:sync
                            {--dias= : Janela em dias (o padrão é golden_ads.dias_padrao)}
                            {--conta= : Só a conta com este id (para testar uma conexão)}
                            {--agendamento : Rodado pelo cron: credencial ausente é aviso, não falha}';

    protected $description = 'Enfileirar a sincronização da métrica de todas as contas de Google Ads conectadas';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?: config('google_ads.dias_padrao', 30));

        if (! in_array($dias, config('google_ads.periodos', [7, 30, 90]), true)) {
            $this->error('A janela precisa ser uma das que o painel oferece: '.implode(', ', config('google_ads.periodos', [])).' dias.');

            return self::FAILURE;
        }

        $faltantes = GoogleAdsService::credenciaisFaltantes();

        if ($faltantes->isNotEmpty()) {
            $mensagem = 'Faltam as credenciais do Google Ads: '.$faltantes->implode(', ')
                .'. Preencha na tela '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.' do painel (ou, em último caso, no .env deste servidor).';

            if ($this->option('agendamento')) {
                $this->warn($mensagem);

                return self::SUCCESS;
            }

            $this->error($mensagem);

            return self::FAILURE;
        }

        // O agendamento sincroniza o mundo: a regra de "cada um vê só a sua conta" é da tela
        // e da policy, não da fila — aqui não há usuário autenticado para filtrar por.
        $contas = GoogleAdsAccount::ativas()
            ->when($this->option('conta'), fn ($consulta, $id): mixed => $consulta->whereKey($id))
            ->get();

        if ($contas->isEmpty()) {
            $this->warn('Nenhuma conta de Google Ads conectada.');

            return self::SUCCESS;
        }

        foreach ($contas as $conta) {
            SyncGoogleAdsMetrics::dispatch($conta->id, $dias);
        }

        $this->info("{$contas->count()} conta(s) enfileirada(s) para a janela de {$dias} dias.");

        return self::SUCCESS;
    }
}
