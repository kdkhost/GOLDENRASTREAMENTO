<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsApiLog;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V26\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V26\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\V26\GoogleAdsException;
use Google\Ads\GoogleAds\V26\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V26\Resources\Campaign;
use Google\Ads\GoogleAds\V26\Services\CampaignOperation;
use Google\Ads\GoogleAds\V26\Services\ListAccessibleCustomersRequest;
use Google\Ads\GoogleAds\V26\Services\MutateCampaignsRequest;
use Google\Ads\GoogleAds\V26\Services\SearchGoogleAdsRequest;
use Google\ApiCore\ApiException;
use Google\Protobuf\FieldMask;
use Google\Rpc\Code;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * A única porta de saída para a API do Google Ads.
 *
 * Toda chamada do sistema passa aqui: montar o cliente a partir da conta conectada, ler
 * campanhas com métricas, listar as contas do MCC e — fase 2 — pausar e ativar. Nada fora
 * desta classe conhece GAQL, micro de moeda nem enumeração de status, e a tela nunca chama
 * a API: quem mostra é `GoogleAdsCache`, preenchida pelo job de fila.
 *
 * O dinheiro tem duas unidades na resposta do Google e as duas não são a mesma conta:
 * `cost_micros` e `average_cpc` chegam em milionésimos da moeda (dividir por um milhão faz
 * reais, como pede a especificação), enquanto `conversions_value` chega já em reais. As duas
 * desembocam em centavos, que é como o resto do sistema guarda dinheiro e como `brl()`
 * formata — por isso a tela não reimplementa nenhuma conta de moeda.
 *
 * A API oficial é a biblioteca `googleads/google-ads-php` (aqui, V26). Os invólucros da
 * extinta AdWords API — `edujugon/laravel-google-ads`, `spotonlive/laravel-google-ads` — não
 * têm versão que fale com a API atual; nada aqui depende deles.
 */
class GoogleAdsService
{
    /** Os status que a tela sabe escrever. Remover uma campanha não é ato de painel. */
    public const STATUS_ESCREVEVEIS = ['ENABLED', 'PAUSED'];

    /**
     * Cada credencial com o caminho de `config()` que a entrega e o nome da variável de
     * ambiente correspondente. A tela não escreve nesses caminhos: quem faz isso é
     * `ChavesDeIntegracao::aplicar()`, que joga o banco sobre o ambiente no começo da
     * requisição. Ler `config()` já é, portanto, perguntar «o que o painel definiu e, se ele
     * não definiu nada, o que o servidor diz» — e é por isto que nenhuma checagem aqui precisa
     * saber de onde veio o valor.
     *
     * O token de desenvolvedor não está aqui porque o Google o aposentou em 09/09/2026: quem
     * autoriza a chamada passou a ser o projeto do Google Cloud que emitiu o par OAuth. Um
     * token já gravado continua sendo enviado — os servidores o ignoram — mas a ausência dele
     * deixou de impedir qualquer coisa.
     */
    public const CREDENCIAIS = [
        'GOOGLE_CLIENT_ID' => 'services.google_ads.client_id',
        'GOOGLE_CLIENT_SECRET' => 'services.google_ads.client_secret',
    ];

    /**
     * As variáveis que faltam para qualquer chamada ao Google. Uma fonte só: o painel, o
     * consentimento e o agendamento perguntam a mesma pergunta, porque a resposta tem de ser
     * a mesma mensagem — uma integração pela metade é o pior estado possível para explicar.
     *
     * @return Collection<int, string>
     */
    public static function credenciaisFaltantes(): Collection
    {
        return collect(self::CREDENCIAIS)
            ->filter(fn (string $chave): bool => blank(config($chave)))
            ->keys();
    }

    /**
     * Cliente autenticado para uma conta conectada.
     *
     * O token de atualização é o segredo do usuário, lido decifrado do banco pelo cast
     * `encrypted`, e o par OAuth é a credencial da aplicação — quem manda é ele, desde que o
     * Google aposentou o token de desenvolvedor em 09/09/2026. Esse token, quando existe,
     * ainda é anexado ao cliente e ignorado pelo servidor.
     * `login_customer_id` é o MCC, quando existe: ele autoriza a entrada, enquanto o
     * `customer_id` da conta conectada diz de qual conta a consulta responde.
     */
    public function clientFor(GoogleAdsAccount $conta): GoogleAdsClient
    {
        return $this->montarCliente((string) $conta->refresh_token);
    }

    /**
     * As contas que este token alcança, pelo número.
     *
     * É a primeira pergunta depois do consentimento: o OAuth devolve a pessoa, não a conta de
     * anúncios. `listAccessibleCustomers` é a única chamada que o Google autoriza sem
     * `login_customer_id` — por isto ela monta o cliente direto do token, antes de existir
     * qualquer fila no banco.
     *
     * @return array<int, string> customer_ids sem hífen
     */
    public function contasAutorizadas(string $refreshToken): array
    {
        $inicio = microtime(true);

        try {
            $resposta = $this->montarCliente($refreshToken)->getCustomerServiceClient()
                ->listAccessibleCustomers(new ListAccessibleCustomersRequest());

            $ids = [];

            foreach ($resposta->getResourceNames() as $nome) {
                $numero = basename($nome);

                if (preg_match('/^\d+$/', $numero)) {
                    $ids[] = $numero;
                }
            }

            $this->registrar(
                $this->transitoria($ids[0] ?? '0', $refreshToken),
                'contas.acessiveis',
                ['metodo' => 'listAccessibleCustomers'],
                ['contas' => count($ids)],
                null,
                $inicio,
            );

            return $ids;
        } catch (Throwable $e) {
            $this->registrar(
                $this->transitoria('0', $refreshToken),
                'contas.acessiveis',
                ['metodo' => 'listAccessibleCustomers'],
                null,
                $e,
                $inicio,
            );

            throw $e;
        }
    }

    /**
     * O rótulo e a moeda de uma conta alcançada, para a conexão entrar no painel com nome —
     * "Conta 123-456-7890" é o que aparece quando o Google não tem descritivo.
     *
     * @return array{nome:?string, moeda:?string, gerente:bool}
     */
    public function detalhesDaConta(string $customerId, string $refreshToken, ?GoogleAdsAccount $conta = null): array
    {
        $inicio = microtime(true);
        $gaql = 'SELECT customer.descriptive_name, customer.currency_code, customer.manager FROM customer LIMIT 1';

        try {
            $linha = $this->montarCliente($refreshToken, $customerId)->getGoogleAdsServiceClient()
                ->search(new SearchGoogleAdsRequest([
                    'customer_id' => $customerId,
                    'query' => $gaql,
                    'page_size' => 1,
                ]))->iterateAllElements()->current();

            $dono = $linha?->getCustomer();
            $detalhes = [
                'nome' => $dono !== null && $dono->getDescriptiveName() !== '' ? $dono->getDescriptiveName() : null,
                'moeda' => $dono !== null && $dono->getCurrencyCode() !== '' ? $dono->getCurrencyCode() : null,
                'gerente' => (bool) $dono?->getManager(),
            ];

            $this->registrar($conta ?? $this->transitoria($customerId, $refreshToken), 'conta.detalhes', ['query' => $gaql], $detalhes, null, $inicio);

            return $detalhes;
        } catch (Throwable $e) {
            $this->registrar($conta ?? $this->transitoria($customerId, $refreshToken), 'conta.detalhes', ['query' => $gaql], null, $e, $inicio);

            throw $e;
        }
    }

    /**
     * Monta o cliente a partir do token, sem passar por uma fila do banco — é o que permite as
     * duas chamadas do instante do consentimento, quando ainda não se sabe qual conta conectar.
     */
    private function montarCliente(string $refreshToken, ?string $customerId = null): GoogleAdsClient
    {
        $oauth = (new OAuth2TokenBuilder())
            ->withClientId((string) config('services.google_ads.client_id'))
            ->withClientSecret((string) config('services.google_ads.client_secret'))
            ->withRefreshToken($refreshToken)
            ->withScopes(config('google_ads.escopos'))
            ->build();

        // O MCC entra como `login_customer_id` quando tem valor (no banco ou no ambiente): ele autoriza a entrada,
        // enquanto o `customer_id` da consulta diz de qual conta a resposta fala.
        $mcc = (string) config('google_ads.login_customer_id');

        $construtor = (new GoogleAdsClientBuilder())
            ->withLoginCustomerId($mcc !== '' ? (int) $mcc : null)
            ->withTransport((string) config('google_ads.transporte', 'rest'))
            ->withOAuth2Credential($oauth);

        // Token de desenvolvedor: aposentado em 09/09/2026. Só entra no cabeçalho quando está
        // gravado, porque `withDeveloperToken()` exige string e a ausência dele já não impede
        // nada — o valor é ignorado pelo Google. O acesso quem define é o projeto do Cloud.
        $desenvolvedor = (string) config('google_ads.developer_token');

        if ($desenvolvedor !== '') {
            $construtor->withDeveloperToken($desenvolvedor);
        }

        return $construtor->build();
    }

    /**
     * Uma conta que não existe no banco: serve só para as chamadas do consentimento, que ainda
     * não têm fila para registrar o log da API. O token entra aqui e morre com o objeto.
     */
    private function transitoria(string $customerId, string $refreshToken): GoogleAdsAccount
    {
        $conta = new GoogleAdsAccount(['customer_id' => $customerId]);
        $conta->setAttribute('refresh_token', $refreshToken);

        return $conta;
    }

    /**
     * As campanhas da conta com as métricas do período, prontas para a tela.
     *
     * Devolve `campanhas` (uma linha por campanha, dinheiro em centavos, status traduzido) e
     * `resumo` (os totais do período). O `dias` entra na cláusula de data: as janelas do
     * painel são as do próprio Google (LAST_7/30/90_DAYS); qualquer outro número vira
     * intervalo fechado de calendário até ontem.
     *
     * @return array{campanhas: array<int, array<string, mixed>>, resumo: array<string, mixed>, dias: int}
     */
    public function buscarCampanhasComMetricas(GoogleAdsAccount $conta, int $dias = 30): array
    {
        $inicio = microtime(true);
        $gaql = $this->gaqlCampanhas($dias);

        try {
            $pagina = $this->clientFor($conta)->getGoogleAdsServiceClient()
                ->search($this->pedido($conta, $gaql));

            $campanhas = [];

            foreach ($pagina->iterateAllElements() as $linha) {
                $campanhas[] = $this->linhaParaTela($linha);
            }

            $resumo = $this->resumo($campanhas);

            $this->registrar($conta, 'campanhas.metricas', ['query' => $gaql], [
                'campanhas' => count($campanhas),
                'custo_centavos' => $resumo['custo_centavos'],
            ], null, $inicio);

            return ['campanhas' => $campanhas, 'resumo' => $resumo, 'dias' => $dias];
        } catch (Throwable $e) {
            $this->registrar($conta, 'campanhas.metricas', ['query' => $gaql], null, $e, $inicio);

            throw $e;
        }
    }

    /**
     * As contas que o token conectado enxerga, a partir da conta conectada.
     *
     * É o que faz o MCC valer a pena: quem conecta o gerente percorre as contas da árvore e
     * escolhe qual o painel abre, sem pedir consentimento de novo. `customer_client` é o
     * recurso com que o próprio Google descreve a árvore.
     *
     * @return array<int, array{customer_id:string, nome:string, moeda:?string, nivel:int, gerente:bool, teste:bool}>
     */
    public function listarContas(GoogleAdsAccount $conta): array
    {
        $inicio = microtime(true);
        $gaql = 'SELECT customer_client.client_customer, customer_client.descriptive_name, '
            .'customer_client.currency_code, customer_client.level, customer_client.manager, '
            .'customer_client.test_account FROM customer_client';

        try {
            $contas = [];

            $pagina = $this->clientFor($conta)->getGoogleAdsServiceClient()
                ->search($this->pedido($conta, $gaql));

            foreach ($pagina->iterateAllElements() as $linha) {
                $filha = $linha->getCustomerClient();
                // `client_customer` vem como "customers/1234567890": o número é a última parte.
                $numero = basename($filha->getClientCustomer() ?: '');

                if (! preg_match('/^\d+$/', $numero)) {
                    continue;
                }

                $contas[] = [
                    'customer_id' => $numero,
                    'nome' => $filha->getDescriptiveName() ?: 'Conta '.$numero,
                    'moeda' => $filha->getCurrencyCode() ?: null,
                    'nivel' => (int) $filha->getLevel(),
                    'gerente' => (bool) $filha->getManager(),
                    'teste' => (bool) $filha->getTestAccount(),
                ];
            }

            $this->registrar($conta, 'arvore.contas', ['query' => $gaql], ['contas' => count($contas)], null, $inicio);

            return $contas;
        } catch (Throwable $e) {
            $this->registrar($conta, 'arvore.contas', ['query' => $gaql], null, $e, $inicio);

            throw $e;
        }
    }

    /**
     * Os anúncios de um grupo de campanhas (fase 2, para a ficha da campanha).
     *
     * O `LIMIT` não é enfeite: um grupo antigo pode ter centenas de anúncios, e a cota da API
     * é contada por chamada — puxar o grupo inteiro para mostrar vinte linhas pagaria por ele
     * de novo a cada recarga.
     *
     * @return array<int, array{id:string, titulo:string, situacao:string, impressoes:int, cliques:int, custo_centavos:int}>
     */
    public function listarAnunciosDoGrupo(GoogleAdsAccount $conta, string $grupoId, int $limite = 50, int $dias = 30): array
    {
        $inicio = microtime(true);

        if (! preg_match('/^\d{6,15}$/', $grupoId)) {
            throw new InvalidArgumentException('O identificador do grupo de campanhas tem de ser um número puro.');
        }

        $gaql = 'SELECT ad_group.id, ad_group.name, ad_group_ad.status, '
            .'metrics.impressions, metrics.clicks, metrics.cost_micros '
            ."FROM ad_group_ad WHERE ad_group.id = {$grupoId} {$this->clausulaData($dias)} "
            ."ORDER BY metrics.impressions DESC LIMIT {$limite}";

        try {
            $anuncios = [];

            $pagina = $this->clientFor($conta)->getGoogleAdsServiceClient()
                ->search($this->pedido($conta, $gaql, $limite));

            foreach ($pagina->iterateAllElements() as $linha) {
                $grupo = $linha->getAdGroup();
                $metricas = $linha->getMetrics();

                $anuncios[] = [
                    'id' => (string) $grupo->getId(),
                    'titulo' => $grupo->getName(),
                    'situacao' => $this->traduzStatus($linha->getAdGroupAd()->getStatus(), 'anuncio'),
                    'impressoes' => (int) $metricas->getImpressions(),
                    'cliques' => (int) $metricas->getClicks(),
                    'custo_centavos' => $this->microsParaCentavos((int) $metricas->getCostMicros()),
                ];
            }

            $this->registrar($conta, 'anuncios.grupo', ['query' => $gaql], ['anuncios' => count($anuncios)], null, $inicio);

            return $anuncios;
        } catch (Throwable $e) {
            $this->registrar($conta, 'anuncios.grupo', ['query' => $gaql], null, $e, $inicio);

            throw $e;
        }
    }

    /** Pausar é escrever: por isso passa por `mudarStatus`, que valida antes de chamar. */
    public function pausarCampanha(GoogleAdsAccount $conta, string $campanhaId, bool $validarSo = false): array
    {
        return $this->mudarStatus($conta, $campanhaId, 'PAUSED', $validarSo);
    }

    /** Reativar uma campanha pausada. Mesma operação, status trocado. */
    public function ativarCampanha(GoogleAdsAccount $conta, string $campanhaId, bool $validarSo = false): array
    {
        return $this->mudarStatus($conta, $campanhaId, 'ENABLED', $validarSo);
    }

    /**
     * A escrita pronta para produção, ainda em modo de teste.
     *
     * `validate_only` é a regra da casa antes de qualquer mudança real: o Google percorre todo
     * o caminho — permissão, status, FieldMask — devolve o que aconteceria e não toca em nada.
     * Assim a fase 2 entra com a operação já batida, não no escuro.
     */
    public function validarOperacao(GoogleAdsAccount $conta, string $campanhaId, string $status = 'PAUSED'): array
    {
        return $this->mudarStatus($conta, $campanhaId, $status, true);
    }

    /**
     * Muda o status de uma campanha; `validate_only` decide se isso escreve de verdade.
     *
     * O `resource_name` é montado com o `customer_id` da conta conectada, nunca com o MCC: é o
     * endereço do objeto dentro daquela conta, e um endereço gerente escreveria em outra conta.
     * O FieldMask diz ao Google que só `status` mudou — sem ele a operação reescreveria a
     * campanha inteira com os campos que a gente não leu.
     */
    public function mudarStatus(GoogleAdsAccount $conta, string $campanhaId, string $status, bool $validarSo = false): array
    {
        $inicio = microtime(true);

        if (! preg_match('/^\d{6,15}$/', $campanhaId)) {
            throw new InvalidArgumentException('O identificador da campanha tem de ser um número puro, sem o endereço da conta.');
        }

        if (! in_array($status, self::STATUS_ESCREVEVEIS, true)) {
            throw new InvalidArgumentException("O painel só escreve os status ENABLED e PAUSED; '{$status}' não é um deles.");
        }

        $operacao = new CampaignOperation([
            'update' => new Campaign([
                'resource_name' => 'customers/'.$conta->customer_id.'/campaigns/'.$campanhaId,
                'status' => CampaignStatus::value($status),
            ]),
            'update_mask' => new FieldMask(['paths' => ['status']]),
        ]);

        $pedido = new MutateCampaignsRequest([
            'customer_id' => (string) $conta->customer_id,
            'operations' => [$operacao],
            'validate_only' => $validarSo,
            // Com false uma falha derruba a chamada em exceção; com true o Google devolve o
            // erro na resposta e a tela explica o motivo em português.
            'partial_failure' => false,
        ]);

        $resumo = [
            'customer_id' => $conta->customer_id,
            'campanha_id' => $campanhaId,
            'status' => $status,
            'field_mask' => 'status',
            'validate_only' => $validarSo,
        ];

        try {
            $resposta = $this->clientFor($conta)->getCampaignServiceClient()->mutateCampaigns($pedido);
            $resultado = $resposta->getResults()[0] ?? null;

            $resposta = [
                'validou' => $validarSo,
                'escreveu' => ! $validarSo,
                'campanha_id' => $campanhaId,
                'status' => $status,
                'status_rotulo' => $this->traduzStatus($status),
                'resource_name' => $resultado ? $resultado->getCampaignResourceName() : null,
            ];

            $this->registrar($conta, $validarSo ? 'campanha.validar' : 'campanha.mudar_status', $resumo, $resposta, null, $inicio);

            return $resposta;
        } catch (Throwable $e) {
            $this->registrar($conta, $validarSo ? 'campanha.validar' : 'campanha.mudar_status', $resumo, null, $e, $inicio);

            throw $e;
        }
    }

    /**
     * Traduz o status da API para a tela. O código chega como número (é enumeração de
     * protobuf); sai a etiqueta em português que a lista do config conhece.
     *
     * O que a lista não tem sai com o próprio código em inglês: etiqueta errada seria pior que
     * código cru, porque o operador decidiria em cima dela.
     */
    public function traduzStatus(int|string|null $status, string $grupo = 'campanha'): string
    {
        if ($status === null) {
            return '';
        }

        $codigo = is_int($status) ? $this->nomeDoStatus($status) : $status;

        return config("google_ads.status_{$grupo}")[$codigo] ?? $codigo;
    }

    /**
     * Por causa disto a conta vira `desconectado`: o Google recusou a credencial.
     *
     * A especificação pede `AuthenticationError.REFRESH_TOKEN_ERROR`, e essa constante não
     * existe na API. O que existe é a família `OAUTH_TOKEN_*` (vencido, revogado, desativado,
     * inválido) mais `AUTHENTICATION_ERROR` e `DEVELOPER_TOKEN_INVALID`, e o status 16
     * (UNAUTHENTICATED) que vem antes de qualquer detalhe. São esses que esta lista pega — o
     * painel para de insistir numa chamada que sempre vai falhar e diz ao dono o que fazer.
     */
    public static function credencialRecusada(Throwable $e): bool
    {
        if ($e instanceof ApiException) {
            // Sobre REST o HTTP 401 chega convertido para o código gRPC correspondente
            // (ApiStatus::grpcToCode), então UNAUTHENTICATED é 16 nos dois transportes.
            if ($e->getCode() === Code::UNAUTHENTICATED) {
                return true;
            }

            if (str_contains(strtoupper((string) $e->getStatus()), 'UNAUTHENTICATED')) {
                return true;
            }
        }

        foreach ($e instanceof GoogleAdsException ? self::codigosDeAutenticacao($e) : [] as $codigo) {
            if ($codigo !== 0) {
                return true;
            }
        }

        return str_contains($e->getMessage(), 'OAUTH_TOKEN')
            || str_contains($e->getMessage(), 'REFRESH_TOKEN')
            || str_contains($e->getMessage(), 'UNAUTHENTICATED');
    }

    /** O nome em inglês de um número de enumeração, ou o próprio número quando não bate. */
    private function nomeDoStatus(int $codigo): string
    {
        try {
            return CampaignStatus::name($codigo);
        } catch (Throwable) {
            // Outra enumeração (anuncio, por exemplo) tem números que não estão nesta tabela;
            // o tradutor devolve o código e a tela mostra o que tem, sem inventar etiqueta.
            return (string) $codigo;
        }
    }

    /** Pedido de consulta: a conta conectada responde, nunca o MCC. */
    private function pedido(GoogleAdsAccount $conta, string $gaql, int $pagina = 300): SearchGoogleAdsRequest
    {
        return new SearchGoogleAdsRequest([
            'customer_id' => (string) $conta->customer_id,
            'query' => $gaql,
            // Sem isso a API paginaria de 10 em 10: uma agência com muitas campanhas pagaria
            // uma chamada por dez campanhas, e a cota é por chamada.
            'page_size' => $pagina,
        ]);
    }

    /** A consulta GAQL das campanhas no período. */
    private function gaqlCampanhas(int $dias): string
    {
        $campos = 'campaign.id, campaign.name, campaign.status, '
            .'metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, '
            .'metrics.ctr, metrics.average_cpc, metrics.conversions_value';

        return 'SELECT '.$campos.' FROM campaign '
            .$this->clausulaData($dias)
            .'ORDER BY metrics.cost_micros DESC';
    }

    /**
     * Cláusula de data da consulta. As janelas do painel usam o `DURING` do Google; fora
     * delas, o período vira intervalo fechado até ontem — o dia de hoje ainda está chegando, e
     * compará-lo com um mês inteiro seria mostrar um número pela metade.
     */
    private function clausulaData(int $dias): string
    {
        if (in_array($dias, [7, 14, 30, 60, 90, 180, 365], true)) {
            return "segments.date DURING LAST_{$dias}_DAYS ";
        }

        $fim = now()->subDay();
        $inicio = $fim->copy()->subDays(max($dias - 1, 0));

        return "segments.date BETWEEN '".$inicio->toDateString()."' AND '".$fim->toDateString()."' ";
    }

    /**
     * A linha da API virada em array de tela: só o que o painel mostra, com dinheiro em
     * centavos, CTR em porcentagem e status traduzido.
     */
    private function linhaParaTela(mixed $linha): array
    {
        /** @var Campaign $campanha */
        $campanha = $linha->getCampaign();
        $metricas = $linha->getMetrics();
        $custo = $this->microsParaCentavos((int) $metricas->getCostMicros());
        // `conversions_value` chega em reais, não em micros: é a única métrica de dinheiro da
        // consulta que não passa pela divisão de um milhão.
        $valor = $this->reaisParaCentavos((float) $metricas->getConversionsValue());
        $conversoes = (float) $metricas->getConversions();

        return [
            'id' => (string) $campanha->getId(),
            'resource_name' => $campanha->getResourceName(),
            'nome' => $campanha->getName(),
            'status' => $this->nomeDoStatus($campanha->getStatus()),
            'status_rotulo' => $this->traduzStatus($campanha->getStatus()),
            'impressoes' => (int) $metricas->getImpressions(),
            'cliques' => (int) $metricas->getClicks(),
            // A API devolve o CTR como fração (0,023 = 2,3%); a tela mostra porcentagem.
            'ctr' => $this->arredonda((float) $metricas->getCtr() * 100, 2),
            'cpc_medio_centavos' => $this->microsParaCentavos((int) $metricas->getAverageCpc()),
            'custo_centavos' => $custo,
            'conversoes' => $this->arredonda($conversoes, 2),
            'valor_conversoes_centavos' => $valor,
            // ROAS é o retorno por real gasto. Custo zero não tem razão: null, para a tela
            // escrever um traço, não um zero inventado.
            'roas' => $custo > 0 ? $this->arredonda($valor / $custo, 2) : null,
            'custo_por_conversao_centavos' => $conversoes > 0 ? $this->arredonda($custo / $conversoes) : null,
        ];
    }

    /**
     * Os totais do período. O CTR é ponderado (cliques totais sobre impressões totais): a
     * média aritmética dos CTRs daria o mesmo peso a uma campanha de mil impressões e a outra
     * de dez milhões.
     */
    private function resumo(array $campanhas): array
    {
        $impressoes = $cliques = $custo = $valor = 0;
        $conversoes = 0.0;

        foreach ($campanhas as $c) {
            $impressoes += $c['impressoes'];
            $cliques += $c['cliques'];
            $custo += $c['custo_centavos'];
            $valor += $c['valor_conversoes_centavos'];
            $conversoes += $c['conversoes'];
        }

        return [
            'campanhas' => count($campanhas),
            'impressoes' => $impressoes,
            'cliques' => $cliques,
            'custo_centavos' => $custo,
            'conversoes' => $this->arredonda($conversoes, 2),
            'valor_conversoes_centavos' => $valor,
            'ctr' => $impressoes > 0 ? $this->arredonda($cliques / $impressoes * 100, 2) : 0.0,
            'cpc_medio_centavos' => $cliques > 0 ? $this->arredonda($custo / $cliques) : 0,
            'roas' => $custo > 0 ? $this->arredonda($valor / $custo, 2) : null,
        ];
    }

    /** Micros de moeda → centavos: um real tem um milhão de micros e cem centavos. */
    private function microsParaCentavos(int $micros): int
    {
        return $this->arredonda($micros / (int) config('google_ads.micros_por_centavo', 10000));
    }

    /** Reais (a unidade em que `conversions_value` chega) → centavos. */
    private function reaisParaCentavos(float $reais): int
    {
        return $this->arredonda($reais * 100);
    }

    private function arredonda(float $valor, int $casas = 0): float|int
    {
        return $casas === 0 ? (int) round($valor) : round($valor, $casas);
    }

    /**
     * A caixa-preta da chamada: o que foi pedido, o que voltou, quanto levou.
     *
     * Nenhuma credencial passa por aqui — o `request_json` é a consulta e o número da conta,
     * e o `response_json` é o resumo da resposta, não o payload inteiro. Token de
     * desenvolvedor e token de atualização ficam de fora por construção, não por filtro.
     */
    private function registrar(
        GoogleAdsAccount $conta,
        string $endpoint,
        ?array $pedido,
        ?array $resposta,
        ?Throwable $erro,
        float $inicio,
    ): void {
        GoogleAdsApiLog::create([
            'user_id' => $conta->user_id,
            'google_ads_account_id' => $conta->id,
            'endpoint' => $endpoint,
            'request_json' => $pedido,
            'response_json' => $resposta,
            'error_message' => $erro ? mb_substr($this->mensagemDoErro($erro), 0, 1000) : null,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ]);
    }

    /** O texto do erro como o Google o escreveu, com o código da falha quando ele existe. */
    private function mensagemDoErro(Throwable $e): string
    {
        $texto = $e->getMessage();

        if ($e instanceof GoogleAdsException) {
            $detalhes = [];

            foreach ($e->getGoogleAdsFailure()?->getErrors() ?? [] as $error) {
                $codigo = $error->getErrorCode();

                if (! $codigo) {
                    continue;
                }

                foreach (['authentication', 'authorization', 'query', 'quota', 'mutate'] as $familia) {
                    $valor = $codigo->{'get'.ucfirst($familia).'Error'}();

                    if ($valor) {
                        $detalhes[] = "{$familia}:{$valor}";
                    }
                }
            }

            if ($detalhes) {
                $texto .= ' ['.implode(', ', $detalhes).']';
            }
        }

        return $texto;
    }

    /** Os códigos de autenticação que vieram na falha, se vieram. */
    private static function codigosDeAutenticacao(GoogleAdsException $e): array
    {
        $codigos = [];

        foreach ($e->getGoogleAdsFailure()?->getErrors() ?? [] as $error) {
            $codigo = $error->getErrorCode();

            if ($codigo) {
                $codigos[] = (int) $codigo->getAuthenticationError();
            }
        }

        return $codigos;
    }
}
