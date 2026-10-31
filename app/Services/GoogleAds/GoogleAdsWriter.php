<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsApiLog;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Support\ChavesDeIntegracao;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A única mão que escreve no Google Ads: cria o orçamento, a campanha pausada, os grupos, o
 * anúncio, as palavras-chave e as negativas de um plano já aprovado.
 *
 * Por que REST e não o cliente oficial como `GoogleAdsService`: aqui toda chamada é um POST
 * simples com JSON, e escrever assim deixa o teste provar a escrita inteira com `Http::fake`,
 * sem credencial viva e sem tocar em conta de anunciante. A leitura continua na biblioteca
 * oficial, que é o que a casa usa para GAQL.
 *
 * A ordem não é decorativa. O Google devolve o identificador real de cada objeto criado, e o
 * objeto seguinte o referencia por ele: orçamento → campanha → grupos → anúncio/palavras. Os
 * IDs temporários (`-1`, `-2`) valem só dentro de uma requisição, e cada etapa aqui é uma
 * requisição própria.
 *
 * A campanha nasce **PAUSED** e nunca é ativada por este serviço. Aplicar um plano entrega a
 * estrutura pronta para revisão no Google Ads; começar a gastar é um ato separado, na tela de
 * campanhas, com confirmação.
 */
final class GoogleAdsWriter
{
    /** O token de acesso renovado uma vez por aplicação e reusado em todas as etapas. */
    private string $token = '';

    /**
     * Aplica o plano normalizado e devolve o que foi criado.
     *
     * @param  array<string, mixed>  $plano  o `plano_json` já conferido por `PlanoDeCampanha`
     * @return array<string, mixed>
     */
    public function aplicar(GoogleAdsAccount $conta, array $plano, ?int $planoId = null): array
    {
        $faltando = GoogleAdsService::credenciaisFaltantes();

        if ($faltando->isNotEmpty()) {
            throw new GoogleAdsFalhou(
                'A escrita no Google Ads precisa das credenciais da API: falta '
                .$faltando->implode(', ').'. Preencha em '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.'
            );
        }

        if ($conta->status !== GoogleAdsAccount::ATIVO) {
            throw new GoogleAdsFalhou('A conta do Google Ads está desconectada. Reconecte antes de aplicar um plano.');
        }

        if (blank($conta->refresh_token)) {
            throw new GoogleAdsFalhou('A conta de Google Ads não tem token de acesso salvo. Reconecte a conta antes de aplicar.');
        }

        $this->token = $this->renovarToken($conta);

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
            'status_rotulo' => 'Pausada — revisar no Google Ads antes de ativar',
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

    /**
     * Troca o token de atualização por um de acesso.
     *
     * O refresh token pode ter sido revogado pelo anunciante — é o caso mais comum de "parou
     * de funcionar" —, e nesse caso a única saída é reconectar. A mensagem diz isso em vez de
     * mostrar o `invalid_grant` do Google.
     */
    private function renovarToken(GoogleAdsAccount $conta): string
    {
        $inicio = microtime(true);

        $resposta = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => (string) config('services.google_ads.client_id'),
            'client_secret' => (string) config('services.google_ads.client_secret'),
            'refresh_token' => (string) $conta->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $token = (string) ($resposta->json('access_token') ?? '');

        $this->registrar(
            $conta,
            'credencial.renovar',
            ['grant_type' => 'refresh_token'],
            $resposta->failed() ? null : ['expira_em_segundos' => $resposta->json('expires_in')],
            $resposta->failed() ? $resposta->body() : null,
            $inicio,
        );

        if ($resposta->failed() || $token === '') {
            throw new GoogleAdsFalhou(
                'A conta do Google Ads não renovou o acesso. Abra "Google Ads" no painel, use "Reconectar" e tente aplicar de novo.',
                'HTTP '.$resposta->status().': '.mb_substr($resposta->body(), 0, 1000),
            );
        }

        return $token;
    }

    /**
     * Os nomes de região viram constantes de local do Google.
     *
     * O plano fala "São Paulo", "Campinas"; a API só entende `geoTargetConstants/…`. A
     * sugestão é a mesma que a tela do Google faz ao digitar o nome, com o idioma e o país da
     * config. Região que o Google não conhece para a aplicação inteira: criar a campanha sem
     * segmentação mostraria anúncio para o mundo, e é pior que não criar.
     *
     * @param  array<int, string>  $regioes
     * @return array<int, array{nome: string, recurso: string}>
     */
    private function resolverRegioes(GoogleAdsAccount $conta, array $regioes): array
    {
        $resolvidas = [];
        $vistas = [];

        foreach ($regioes as $nome) {
            $nome = trim((string) $nome);

            if ($nome === '') {
                continue;
            }

            $resposta = $this->chamar($conta, 'geoTargetConstants:suggest', [
                'locale' => 'pt-BR',
                'countryCode' => (string) config('google_ads.copiloto.pais', 'BR'),
                'locationNames' => [$nome],
            ], 'regiao.sugerir');

            $recurso = (string) data_get($resposta, 'geoTargetConstantSuggestions.0.geoTargetConstant.resourceName', '');

            if ($recurso === '') {
                throw new GoogleAdsFalhou(
                    'Não localizei a região "'.$nome.'" no Google Ads. '
                    .'Escreva o nome como aparece na segmentação por local do Google Ads (ex.: "São Paulo", "Campinas").'
                );
            }

            if (! isset($vistas[$recurso])) {
                $vistas[$recurso] = true;
                $resolvidas[] = ['nome' => $nome, 'recurso' => $recurso];
            }
        }

        if ($resolvidas === []) {
            throw new GoogleAdsFalhou('O plano não trouxe nenhuma região válida para a campanha.');
        }

        return $resolvidas;
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

        if (($plano['estrategia_lance'] ?? 'MAXIMIZE_CLICKS') === 'MANUAL_CPC') {
            $corpo['manualCpc'] = new \stdClass();
        } else {
            $corpo['maximizeClicks'] = [
                'cpcBidCeilingMicros' => (string) ((int) ($plano['cpc_maximo_centavos'] ?? 200) * 10000),
            ];
        }

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
        $rotas = array_values((array) ($anuncio['caminhos'] ?? []));

        $responsivo = [
            'headlines' => array_map(static fn (string $texto): array => ['text' => $texto], (array) ($anuncio['titulos'] ?? [])),
            'descriptions' => array_map(static fn (string $texto): array => ['text' => $texto], (array) ($anuncio['descricoes'] ?? [])),
        ];

        if (isset($rotas[0]) && $rotas[0] !== '') {
            $responsivo['path1'] = $rotas[0];
        }

        if (isset($rotas[1]) && $rotas[1] !== '') {
            $responsivo['path2'] = $rotas[1];
        }

        $operacoes = [];

        foreach ($grupos as $grupo) {
            $operacoes[] = ['create' => [
                'adGroup' => $grupo,
                'status' => 'ENABLED',
                'ad' => [
                    'finalUrls' => [(string) $plano['url']],
                    'responsiveSearchAd' => $responsivo,
                ],
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

    /**
     * Um POST para a API, com o log da chamada e a tradução do erro.
     *
     * @return array<string, mixed>
     */
    private function chamar(GoogleAdsAccount $conta, string $caminho, array $corpo, string $etapa): array
    {
        $inicio = microtime(true);

        $resposta = Http::withHeaders($this->cabecalhos())
            ->acceptJson()
            ->timeout(60)
            ->post($this->api().'/'.$caminho, $corpo);

        $json = (array) $resposta->json();

        $this->registrar(
            $conta,
            $etapa,
            $corpo,
            $resposta->failed() ? null : $this->resumoDaResposta($json),
            $resposta->failed() ? $resposta->body() : null,
            $inicio,
        );

        if ($resposta->failed()) {
            throw $this->falha($resposta, $etapa);
        }

        return $json;
    }

    /** @return array<string, string> */
    private function cabecalhos(): array
    {
        $mcc = (string) config('google_ads.login_customer_id');

        return array_filter([
            'Authorization' => 'Bearer '.$this->token,
            'developer-token' => (string) config('google_ads.developer_token'),
            'login-customer-id' => $mcc,
            'Content-Type' => 'application/json',
        ], static fn (string $valor): bool => $valor !== '');
    }

    private function falha(Response $resposta, string $etapa): GoogleAdsFalhou
    {
        $motivo = match (true) {
            $resposta->status() === 401 => 'O Google recusou o acesso da conta conectada. Reconecte a conta do Google Ads e tente de novo.',
            $resposta->status() === 403 => 'A conta conectada não tem permissão para criar campanhas. Confira o acesso de quem conectou a conta.',
            $resposta->status() === 429 => 'O Google limitou as chamadas por enquanto. Tente de novo em alguns minutos.',
            $resposta->status() >= 500 => 'O Google está instável agora e não concluiu a operação. Tente de novo em instantes.',
            default => 'O Google recusou a etapa "'.$etapa.'". Confira os dados do plano e tente de novo.',
        };

        return new GoogleAdsFalhou(
            $motivo,
            'HTTP '.$resposta->status().' em '.$etapa.': '.mb_substr($resposta->body(), 0, 1500),
        );
    }

    /** O que fica no log: contagem de resultados, nunca o payload inteiro da conta. */
    private function resumoDaResposta(array $json): array
    {
        $resumo = [];

        if (isset($json['results'])) {
            $resumo['resultados'] = count((array) $json['results']);
        }

        if (isset($json['geoTargetConstantSuggestions'])) {
            $resumo['sugestoes'] = count((array) $json['geoTargetConstantSuggestions']);
        }

        return $resumo;
    }

    private function registrar(
        GoogleAdsAccount $conta,
        string $etapa,
        ?array $pedido,
        ?array $resposta,
        ?string $erro,
        float $inicio,
    ): void {
        GoogleAdsApiLog::create([
            'user_id' => $conta->user_id,
            'google_ads_account_id' => $conta->id,
            'endpoint' => 'escrita.'.$etapa,
            'request_json' => $pedido,
            'response_json' => $resposta,
            'error_message' => $erro ? mb_substr($erro, 0, 1000) : null,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ]);
    }

    private function api(): string
    {
        return 'https://googleads.googleapis.com/v'.strtolower((string) config('google_ads.versao_api', 'V26'));
    }
}
