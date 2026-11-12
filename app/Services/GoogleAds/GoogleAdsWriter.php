<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsApiLog;
use App\Services\GoogleAds\Concerns\FalaComOGoogleAds;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;

/**
 * A mão que cria no Google Ads: o orçamento, a campanha pausada, os grupos, o anúncio, as
 * palavras-chave e as negativas de um plano já aprovado. A campanha que já existe é assunto
 * do `GoogleAdsEditor`; os dois falam com a API pelo mesmo `FalaComOGoogleAds`.
 *
 * A ordem não é decorativa. O Google devolve o identificador real de cada objeto criado, e o
 * objeto seguinte o referencia por ele: orçamento → campanha → grupos → anúncio/palavras. Os
 * IDs temporários (`-1`, `-2`) valem só dentro de uma requisição, e cada etapa aqui é uma
 * requisição própria.
 *
 * A campanha nasce **PAUSED** e nunca é ativada por este serviço. Aplicar um plano entrega a
 * estrutura pronta para revisão; começar a gastar é um ato separado, na tela da campanha, com
 * confirmação.
 */
final class GoogleAdsWriter
{
    use FalaComOGoogleAds;

    /**
     * Aplica o plano normalizado e devolve o que foi criado.
     *
     * @param  array<string, mixed>  $plano  o `plano_json` já conferido por `PlanoDeCampanha`
     * @return array<string, mixed>
     */
    public function aplicar(GoogleAdsAccount $conta, array $plano, ?int $planoId = null): array
    {
        $this->prepararEscrita($conta);

        $regioes = $this->resolverRegioes($conta, (array) ($plano['regioes'] ?? []));
        $orcamento = $this->criarOrcamento($conta, $plano);
        $campanha = $this->criarCampanha($conta, $plano, $orcamento);
        $this->criarCriteriosDeCampanha($conta, $campanha, $regioes);
        $grupos = $this->criarGrupos($conta, $plano, $campanha);
        $anuncios = $this->criarAnuncios($conta, $plano, $grupos);
        [$palavras, $negativas] = $this->criarPalavras($conta, $plano, $grupos, $campanha);

        $resultado = [
            'campanha_id' => basename($campanha),
            'campanha_resource' => $campanha,
            'orcamento_resource' => $orcamento,
            'plano_id' => $planoId,
            'regioes' => array_column($regioes, 'nome'),
            'grupos' => count($grupos),
            'anuncios' => $anuncios,
            'palavras_chave' => $palavras,
            'negativas' => $negativas,
            'status' => 'PAUSED',
            'status_rotulo' => 'Pausada — revise e ative pelo painel quando quiser veicular',
            'aplicado_em' => now()->toIso8601String(),
        ];

        GoogleAdsApiLog::create([
            'user_id' => $conta->user_id,
            'google_ads_account_id' => $conta->id,
            'endpoint' => 'escrita.plano.aplicado',
            'request_json' => ['plano_id' => $planoId, 'campanha' => $plano['nome'] ?? ''],
            'response_json' => $resultado,
            'error_message' => null,
            'duracao_ms' => 0,
        ]);

        return $resultado;
    }

    /** O orçamento diário da campanha; o recurso criado é o que a campanha referencia. */
    private function criarOrcamento(GoogleAdsAccount $conta, array $plano): string
    {
        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaignBudgets:mutate', [
            'operations' => [[
                'create' => [
                    'name' => mb_substr(($plano['nome'] ?? 'Campanha').' — orçamento', 0, 255),
                    'amountMicros' => (string) ((int) $plano['orcamento_diario_centavos'] * 10000),
                    'deliveryMethod' => 'STANDARD',
                    'explicitlyShared' => false,
                ],
            ]],
        ], 'orcamento.criar');

        $recurso = (string) data_get($resposta, 'results.0.resourceName', '');

        if ($recurso === '') {
            throw new GoogleAdsFalhou('O Google aceitou o orçamento mas não devolveu o identificador. Nada foi ativado — tente de novo.');
        }

        return $recurso;
    }

    /**
     * A campanha de pesquisa, sempre pausada.
     *
     * A segmentação de rede fica só na pesquisa Google: rede de display e parceiros trariam
     * cliques que não são o que o briefing pediu, e o copiloto não tem criativo para elas.
     */
    private function criarCampanha(GoogleAdsAccount $conta, array $plano, string $orcamento): string
    {
        $corpo = [
            'name' => mb_substr((string) ($plano['nome'] ?? 'Campanha de rastreamento'), 0, 128),
            'status' => 'PAUSED',
            'advertisingChannelType' => 'SEARCH',
            'campaignBudget' => $orcamento,
            'networkSettings' => [
                'targetGoogleSearch' => true,
                'targetSearchNetwork' => false,
                'targetContentNetwork' => false,
                'targetPartnerSearchNetwork' => false,
            ],
            'geoTargetTypeSetting' => [
                'positiveGeoTargetType' => 'PRESENCE',
                'negativeGeoTargetType' => 'PRESENCE',
            ],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
        ];

        // A V26 não tem mais campo de "maximizar cliques" na campanha: o Google tirou o
        // `maximizeClicks` (e o tipo MAXIMIZE_CLICKS do enum), o `targetCpc` que poderia
        // substituí-lo foi recusado para esta conta (`OPERATION_NOT_PERMITTED_FOR_CONTEXT`) e
        // nenhuma campanha é aceita sem estratégia de lance. Prova na API viva, em `validateOnly`.
        // Resta o CPC manual, e é o que o plano já escreve: `cpc_maximo_centavos` vira o lance
        // máximo de cada grupo em `criarGrupos()`.
        $corpo['manualCpc'] = ['enhancedCpcEnabled' => false];

        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaigns:mutate', [
            'operations' => [['create' => $corpo]],
        ], 'campanha.criar');

        $recurso = (string) data_get($resposta, 'results.0.resourceName', '');

        if ($recurso === '') {
            throw new GoogleAdsFalhou('O Google aceitou a campanha mas não devolveu o identificador. Nada foi ativado — tente de novo.');
        }

        return $recurso;
    }

    /**
     * Onde a campanha aparece: as regiões resolvidas e o idioma da casa.
     *
     * @param  array<int, array{nome: string, recurso: string}>  $regioes
     */
    private function criarCriteriosDeCampanha(GoogleAdsAccount $conta, string $campanha, array $regioes): void
    {
        $operacoes = [];

        foreach ($regioes as $regiao) {
            $operacoes[] = ['create' => [
                'campaign' => $campanha,
                'location' => ['geoTargetConstant' => $regiao['recurso']],
            ]];
        }

        $idioma = (string) config('google_ads.copiloto.idioma', '');

        if ($idioma !== '') {
            $operacoes[] = ['create' => [
                'campaign' => $campanha,
                'language' => ['languageConstant' => $idioma],
            ]];
        }

        $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaignCriteria:mutate', [
            'operations' => $operacoes,
        ], 'campanha.segmentar');
    }

    /**
     * Um grupo de anúncios por bloco do plano.
     *
     * O retorno é a lista de recursos na mesma ordem dos grupos do plano: é o índice que liga
     * cada anúncio e cada palavra-chave ao grupo certo na etapa seguinte.
     *
     * @return array<int, string>
     */
    private function criarGrupos(GoogleAdsAccount $conta, array $plano, string $campanha): array
    {
        $operacoes = [];
        $quantidade = 0;

        foreach ((array) ($plano['grupos'] ?? []) as $grupo) {
            $operacoes[] = ['create' => [
                'name' => mb_substr((string) $grupo['nome'], 0, 255),
                'campaign' => $campanha,
                'status' => 'ENABLED',
                'type' => 'SEARCH_STANDARD',
                'cpcBidMicros' => (string) ((int) ($plano['cpc_maximo_centavos'] ?? 200) * 10000),
            ]];

            $quantidade++;
        }

        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroups:mutate', [
            'operations' => $operacoes,
        ], 'grupos.criar');

        $recursos = array_values(array_map(
            static fn (array $resultado): string => (string) ($resultado['resourceName'] ?? ''),
            (array) ($resposta['results'] ?? []),
        ));

        if (count($recursos) !== $quantidade || in_array('', $recursos, true)) {
            throw new GoogleAdsFalhou('O Google criou os grupos mas a resposta veio incompleta. A campanha ficou pausada — confira no Google Ads antes de tentar de novo.');
        }

        return $recursos;
    }

    /** O mesmo anúncio responsivo em cada grupo, com as rotas que o plano pediu. */
    private function criarAnuncios(GoogleAdsAccount $conta, array $plano, array $grupos): int
    {
        $anuncio = (array) ($plano['anuncios'] ?? []);
        $operacoes = [];

        foreach ($grupos as $grupo) {
            $operacoes[] = ['create' => [
                'adGroup' => $grupo,
                'status' => 'ENABLED',
                'ad' => GoogleAdsEditor::anuncioResponsivo(
                    (string) $plano['url'],
                    (array) ($anuncio['titulos'] ?? []),
                    (array) ($anuncio['descricoes'] ?? []),
                    (array) ($anuncio['caminhos'] ?? []),
                ),
            ]];
        }

        $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroupAds:mutate', [
            'operations' => $operacoes,
        ], 'anuncios.criar');

        return count($operacoes);
    }

    /**
     * Palavras-chave por grupo e negativas na campanha.
     *
     * A negativa vai na campanha, não no grupo, e sem duplicata: uma palavra que não deve
     * gerar clique vale para a conta inteira, e repeti-la em oito grupos só gastaria cota.
     *
     * @param  array<int, string>  $grupos
     * @return array{0: int, 1: int}
     */
    private function criarPalavras(GoogleAdsAccount $conta, array $plano, array $grupos, string $campanha): array
    {
        $operacoes = [];
        $negativas = [];

        foreach ((array) ($plano['grupos'] ?? []) as $indice => $grupo) {
            if (! isset($grupos[$indice])) {
                continue;
            }

            foreach ((array) ($grupo['palavras_chave'] ?? []) as $palavra) {
                $operacoes[] = ['create' => [
                    'adGroup' => $grupos[$indice],
                    'status' => 'ENABLED',
                    'keyword' => [
                        'text' => (string) $palavra['texto'],
                        'matchType' => (string) $palavra['correspondencia'],
                    ],
                ]];
            }

            foreach ((array) ($grupo['negativas'] ?? []) as $negativa) {
                $chave = mb_strtolower(trim((string) $negativa));

                if ($chave !== '' && ! isset($negativas[$chave])) {
                    $negativas[$chave] = trim((string) $negativa);
                }
            }
        }

        if ($operacoes !== []) {
            $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroupCriteria:mutate', [
                'operations' => $operacoes,
            ], 'palavras.criar');
        }

        if ($negativas !== []) {
            $operacoesNegativas = [];

            foreach ($negativas as $texto) {
                $operacoesNegativas[] = ['create' => [
                    'campaign' => $campanha,
                    'negative' => true,
                    'keyword' => ['text' => $texto, 'matchType' => 'BROAD'],
                ]];
            }

            $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaignCriteria:mutate', [
                'operations' => $operacoesNegativas,
            ], 'negativas.criar');
        }

        return [count($operacoes), count($negativas)];
    }
}
