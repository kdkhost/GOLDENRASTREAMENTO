<?php

namespace App\Support;

use App\Models\GoogleAdsAccount;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * A cache da métrica do Google Ads — e a única fonte do nome das chaves.
 *
 * A regra do painel é firme: nenhuma requisição de tela chama a API. A tela pergunta a esta
 * classe; se não há métrica, a tela manda o job sincronizar e mostra "Carregando…". Isso só
 * fecha se quem escreve e quem lê usarem exatamente a mesma chave, no mesmo store — é o que
 * esta classe garante.
 *
 * Dentro de uma chave por conta e usuário cabem as três janelas (7, 30, 90 dias), cada uma
 * com a sua marca de quando foi preenchida. Uma chave por conta porque o formato é combinado
 * com a operação; janelas separadas dentro dela porque trocar o período na tela não pode
 * apagar a sincronização que o agendamento acabou de fazer.
 *
 * O store vem de `config('google_ads.cache_store')`, com o store padrão do app por baixo: na
 * hospedagem de hoje, que não tem servidor Redis, cai em arquivo; num servidor com Redis,
 * uma variável de ambiente move a métrica para lá sem tocar em código.
 */
class GoogleAdsCache
{
    /** Prefixo de toda chave da integração, no formato combinado com a operação. */
    public const PREFIXO = 'google_ads';

    public static function store(): Repository
    {
        return Cache::store(config('google_ads.cache_store') ?: config('cache.default'));
    }

    /** A chave da métrica de uma conta: uma por usuário e por `customer_id`. */
    public static function chaveMetricas(GoogleAdsAccount $conta): string
    {
        return self::PREFIXO.':metrics:'.$conta->user_id.':'.$conta->customer_id;
    }

    /**
     * Trava que impede o "Carregando…" de virar bomba: enquanto um job de uma janela está a
     * caminho, o mesmo pedido não é enfileirado de novo a cada consulta da tela.
     */
    public static function chaveFila(GoogleAdsAccount $conta, int $dias): string
    {
        return self::PREFIXO.':fila:'.$conta->user_id.':'.$conta->customer_id.':'.$dias;
    }

    /** A métrica sincronizada desta janela, ou null quando ainda não foi preenchida. */
    public static function metricas(GoogleAdsAccount $conta, int $dias): ?array
    {
        $janelas = self::store()->get(self::chaveMetricas($conta), [])['janelas'] ?? [];

        return isset($janelas[$dias]) ? $janelas[$dias] : null;
    }

    /**
     * Grava o resultado de uma sincronização na janela pedida, sem jogar fora as vizinhas.
     * `campanhas` é a lista e `resumo`, os totais já calculados — a tela não refaz conta.
     */
    public static function gravar(GoogleAdsAccount $conta, int $dias, array $campanhas, array $resumo): array
    {
        $chave = self::chaveMetricas($conta);
        $bloco = self::store()->get($chave, []);
        $minutos = (int) config('google_ads.cache_minutos', 30);

        $janela = [
            'dias' => $dias,
            'campanhas' => $campanhas,
            'resumo' => $resumo,
            'sincronizado_em' => now()->toIso8601String(),
        ];

        // A janela entra por atribuição, não por array_merge: com chave numérica, o merge
        // renumera as entradas e a métrica de 30 dias passaria a morar na chave 0.
        $janelas = $bloco['janelas'] ?? [];
        $janelas[$dias] = $janela;
        $bloco['janelas'] = $janelas;
        unset($bloco['erros'][$dias]);

        self::store()->put($chave, $bloco, now()->addMinutes($minutos));
        self::store()->forget(self::chaveFila($conta, $dias));

        return $janela;
    }

    /**
     * Registra a falha de uma sincronização na janela dela. Sem isto, a tela que pergunta e
     * recebe null continuaria oferecendo "Carregando…" para um job que já morreu — o erro
     * tem de chegar a quem esperava por ele.
     */
    public static function gravarErro(GoogleAdsAccount $conta, int $dias, string $mensagem): void
    {
        $chave = self::chaveMetricas($conta);

        $bloco = self::store()->get($chave, []);
        $erros = $bloco['erros'] ?? [];
        $erros[$dias] = [
            'mensagem' => $mensagem,
            'desde' => now()->toIso8601String(),
        ];
        $bloco['erros'] = $erros;

        self::store()->put($chave, $bloco, now()->addMinutes((int) config('google_ads.cache_minutos', 30)));
        self::store()->forget(self::chaveFila($conta, $dias));
    }

    /** A falha registrada desta janela, ou null quando a última sincronização não falhou. */
    public static function erro(GoogleAdsAccount $conta, int $dias): ?array
    {
        $erros = self::store()->get(self::chaveMetricas($conta), [])['erros'] ?? [];

        return isset($erros[$dias]) ? $erros[$dias] : null;
    }

    /** Esquece toda a métrica da conta: a troca de conta conectada usa, para não misturar. */
    public static function esquecer(GoogleAdsAccount $conta): void
    {
        self::store()->forget(self::chaveMetricas($conta));
    }

    /**
     * Esquece só uma janela, e libera a fila dela. É o "Atualizar agora" da tela: o pedido é
     * por esta janela, e apagar as vizinhas jogaria fora o trabalho que o agendamento acabou
     * de fazer com 7 e 90 dias.
     */
    public static function esquecerJanela(GoogleAdsAccount $conta, int $dias): void
    {
        $chave = self::chaveMetricas($conta);
        $bloco = self::store()->get($chave, []);

        unset($bloco['janelas'][$dias], $bloco['erros'][$dias]);
        self::store()->put($chave, $bloco, now()->addMinutes((int) config('google_ads.cache_minutos', 30)));
        self::store()->forget(self::chaveFila($conta, $dias));
    }

    /**
     * Marca que um job desta janela foi enfileirado e devolve true quando já estava. O tempo
     * é o do próprio job com as tentativas — passa dele, a tela pode pedir de novo.
     */
    public static function acabaDeSerEnfileirado(GoogleAdsAccount $conta, int $dias): bool
    {
        $chave = self::chaveFila($conta, $dias);

        if (self::store()->has($chave)) {
            return true;
        }

        self::store()->put($chave, true, now()->addMinutes(5));

        return false;
    }

    /** As janelas que já têm métrica, para a tela não oferecer um período vazio. */
    public static function janelasProntas(GoogleAdsAccount $conta): array
    {
        return array_keys(self::store()->get(self::chaveMetricas($conta), [])['janelas'] ?? []);
    }
}
