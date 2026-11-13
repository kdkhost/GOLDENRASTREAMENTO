<?php

namespace App\Services\GoogleAds\Concerns;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsApiLog;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Services\GoogleAds\GoogleAdsService;
use App\Support\ChavesDeIntegracao;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A conversa por REST com a API do Google Ads: renovar o token, chamar um endpoint, registrar
 * a chamada e traduzir a recusa.
 *
 * O escritor (que cria campanha do plano) e o editor (que mexe na campanha que já existe)
 * falam pela mesma boca. Mantê-la num lugar só é o que garante que os dois registram em
 * `google_ads_api_logs` do mesmo jeito, mandam os mesmos cabeçalhos e dizem a mesma frase em
 * português quando o Google recusa.
 *
 * Por que REST e não o cliente oficial como `GoogleAdsService`: toda chamada é um POST simples
 * com JSON, e escrever assim deixa o teste provar a escrita inteira com `Http::fake`, sem
 * credencial viva e sem tocar em conta de anunciante.
 */
trait FalaComOGoogleAds
{
    /** O token de acesso renovado uma vez por operação e reusado em todas as etapas. */
    private string $token = '';

    /**
     * As três portas que toda escrita atravessa antes da primeira chamada: credenciais da API
     * no painel, conta conectada e token de atualização guardado. Passou, o token de acesso
     * fica pronto para `chamar()`.
     */
    private function prepararEscrita(GoogleAdsAccount $conta): void
    {
        $faltando = GoogleAdsService::credenciaisFaltantes();

        if ($faltando->isNotEmpty()) {
            throw new GoogleAdsFalhou(
                'A escrita no Google Ads precisa das credenciais da API: falta '
                .$faltando->implode(', ').'. Preencha em '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.'
            );
        }

        if ($conta->status !== GoogleAdsAccount::ATIVO) {
            throw new GoogleAdsFalhou('A conta do Google Ads está desconectada. Reconecte antes de escrever nela.');
        }

        if (blank($conta->refresh_token)) {
            throw new GoogleAdsFalhou('A conta de Google Ads não tem token de acesso salvo. Reconecte a conta antes de continuar.');
        }

        $this->token = $this->renovarToken($conta);
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
                'A conta do Google Ads não renovou o acesso. Abra "Google Ads" no painel, use "Reconectar" e tente de novo.',
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
     * config. Região que o Google não conhece para a operação inteira: uma campanha sem
     * segmentação mostraria anúncio para o mundo, e é pior que não escrever.
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

            // `locationNames` não é a lista: é a mensagem que embrulha a lista (`names`). Mandar
            // a lista solta o Google recusa a chamada inteira com «Proto field is not repeating,
            // cannot start list», e nada chega a ser escrito.
            $resposta = $this->chamar($conta, 'geoTargetConstants:suggest', [
                'locale' => 'pt-BR',
                'countryCode' => (string) config('google_ads.copiloto.pais', 'BR'),
                'locationNames' => ['names' => [$nome]],
            ], 'regiao.sugerir');

            $sugestoes = (array) data_get($resposta, 'geoTargetConstantSuggestions', []);
            $recurso = $this->melhorRegiao($sugestoes, $nome);

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
            throw new GoogleAdsFalhou('Nenhuma região válida para a campanha: sem segmentação, o anúncio apareceria para o mundo inteiro.');
        }

        return $resolvidas;
    }

    /**
     * A sugestão que tem o nome pedido, não apenas a primeira da lista.
     *
     * O Google ordena por proximidade e isso nem casa com o pedido: "Mato Grosso" devolve
     * "Mato Grosso do Sul" na frente. Ficar com `[0]` segmentava o estado errado e, como as duas
     * buscas acabavam no mesmo identificador, o Mato Grosso de verdade sumia da campanha.
     *
     * @param  array<int, array<string, mixed>>  $sugestoes
     */
    private function melhorRegiao(array $sugestoes, string $nome): string
    {
        $procurado = strtolower(Str::ascii($nome));
        $primeira = '';

        foreach ($sugestoes as $sugestao) {
            $constante = (array) ($sugestao['geoTargetConstant'] ?? []);
            $recurso = (string) ($constante['resourceName'] ?? '');

            if ($recurso === '') {
                continue;
            }

            $primeira = $primeira !== '' ? $primeira : $recurso;

            if (strtolower(Str::ascii((string) ($constante['name'] ?? ''))) === $procurado) {
                return $recurso;
            }
        }

        return $primeira;
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
            $this->resumoDoPedido($corpo),
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
        $erro = (array) data_get((array) $resposta->json(), 'error.details.0.errors.0', []);
        $codigo = (array) ($erro['errorCode'] ?? []);
        $nomeCodigo = (string) (reset($codigo) ?: '');
        $trigger = (string) data_get($erro, 'trigger.stringValue', '');

        if ($nomeCodigo === 'UNSUPPORTED_FIELD_TYPE' && ($etapa === 'imagem.vincular' || $trigger === 'AD_IMAGE')) {
            return new GoogleAdsFalhou(
                'O Google Ads restringe o uso de recursos de imagem em contas recentes. A conta precisa ter pelo menos 60 dias de criação, boa conformidade com as políticas e veiculação ativa em pesquisas nos últimos 30 dias para liberar imagens. Sitelinks e frases de destaque continuam funcionando normalmente.',
                'HTTP '.$resposta->status().' em '.$etapa.': '.mb_substr($resposta->body(), 0, 1500),
            );
        }

        $motivo = match (true) {
            $resposta->status() === 401 => 'O Google recusou o acesso da conta conectada. Reconecte a conta do Google Ads e tente de novo.',
            $resposta->status() === 403 => 'A conta conectada não tem permissão para escrever nesta conta de anúncios. Confira o acesso de quem conectou a conta.',
            $resposta->status() === 429 => 'O Google limitou as chamadas por enquanto. Tente de novo em alguns minutos.',
            $resposta->status() >= 500 => 'O Google está instável agora e não concluiu a operação. Tente de novo em instantes.',
            default => 'O Google recusou a etapa "'.$etapa.'"'.$this->motivoDoGoogle($resposta).'.',
        };

        return new GoogleAdsFalhou(
            $motivo,
            'HTTP '.$resposta->status().' em '.$etapa.': '.mb_substr($resposta->body(), 0, 1500),
        );
    }

    /**
     * O código do erro que o Google devolveu, quando ele vem estruturado — é o que diz se foi a
     * palavra-chave, a imagem ou o orçamento. Sem ele, a frase fica só com a etapa.
     */
    private function motivoDoGoogle(Response $resposta): string
    {
        $erro = (array) data_get((array) $resposta->json(), 'error.details.0.errors.0', []);
        $codigo = (array) ($erro['errorCode'] ?? []);
        $nome = (string) (reset($codigo) ?: '');
        $mensagem = trim((string) ($erro['message'] ?? ''));

        if ($nome === '' && $mensagem === '') {
            return '';
        }

        return ' ('.trim($nome.($mensagem !== '' ? ': '.mb_substr($mensagem, 0, 200) : '')).')';
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

    /**
     * O pedido como vai para o log. A imagem entra em base64 e pesa megabytes: no registro fica
     * o tamanho dela, não o conteúdo.
     */
    private function resumoDoPedido(array $corpo): array
    {
        array_walk_recursive($corpo, static function (&$valor, $chave): void {
            if ($chave === 'data' && is_string($valor) && strlen($valor) > 200) {
                $valor = '[imagem de '.strlen($valor).' bytes em base64]';
            }
        });

        return $corpo;
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

    /**
     * A base da API, com um "v" só.
     *
     * A config escreve a versão como `V26` e o endereço quer `v26`: juntar os dois sem conferir
     * fazia `vv26`, e o Google respondia 404 à primeira escrita — a tela chamava isso de
     * "o Google recusou a etapa". Aceita `V26`, `v26` e `26`.
     */
    private function api(): string
    {
        $versao = ltrim(strtolower((string) config('google_ads.versao_api', 'v26')), 'v');

        return 'https://googleads.googleapis.com/v'.$versao;
    }
}
