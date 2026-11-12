<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\Concerns\FalaComOGoogleAds;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;

/**
 * A mão que mexe na campanha que já existe no Google Ads: lê a campanha inteira (segmentação,
 * grupos, anúncios, palavras e recursos), edita nome, orçamento, regiões e negativas, cria,
 * edita, pausa e remove grupos, e vincula imagens, sitelinks e frases de destaque.
 *
 * Tudo o que esta classe escreve parte do que ela mesma leu: o controller nunca manda um
 * identificador solto para a API — ele prova pela leitura que o grupo, o anúncio ou o recurso
 * pertence àquela campanha e àquela conta. É o que impede um id trocado no formulário de
 * apagar o grupo de outra campanha.
 *
 * Mudar o status da campanha (ativar/pausar) continua no `GoogleAdsService`, pelo cliente
 * oficial, porque é a única escrita que passa por `validate_only` antes da escrita real.
 */
final class GoogleAdsEditor
{
    use FalaComOGoogleAds;

    /**
     * O anúncio responsivo de pesquisa como a API o recebe.
     *
     * O escritor (campanha nova) e o editor (grupo novo ou anúncio reescrito) montam o mesmo
     * objeto: título e descrição viram `{text}`, e as duas rotas da URL de exibição só entram
     * quando foram escritas — `path1` vazio o Google recusa.
     *
     * @param  array<int, string>  $titulos
     * @param  array<int, string>  $descricoes
     * @param  array<int, string>  $caminhos
     * @return array<string, mixed>
     */
    public static function anuncioResponsivo(string $url, array $titulos, array $descricoes, array $caminhos): array
    {
        $rotas = array_values(array_filter(array_map('trim', array_map('strval', $caminhos)), static fn (string $r): bool => $r !== ''));

        $responsivo = [
            'headlines' => array_values(array_map(static fn ($texto): array => ['text' => (string) $texto], $titulos)),
            'descriptions' => array_values(array_map(static fn ($texto): array => ['text' => (string) $texto], $descricoes)),
        ];

        if (isset($rotas[0])) {
            $responsivo['path1'] = $rotas[0];
        }

        if (isset($rotas[1])) {
            $responsivo['path2'] = $rotas[1];
        }

        return [
            'finalUrls' => [$url],
            'responsiveSearchAd' => $responsivo,
        ];
    }

    /**
     * A campanha inteira, como está no Google agora.
     *
     * São sete consultas GAQL, uma por recurso: campanha, critérios de campanha, nome das
     * regiões, grupos, anúncios, palavras-chave e recursos vinculados. A API não junta recursos
     * diferentes numa consulta só, e ler tudo de uma vez é o que deixa a tela da campanha
     * mostrar o estado real — não o que o painel acha que escreveu.
     *
     * @return array<string, mixed>
     */
    public function ler(GoogleAdsAccount $conta, string $campanhaId): array
    {
        if (! preg_match('/^\d{6,15}$/', $campanhaId)) {
            throw new GoogleAdsFalhou('O identificador da campanha tem de ser um número puro.');
        }

        $this->prepararEscrita($conta);

        $linha = $this->consultar($conta, "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign.campaign_budget, campaign.start_date_time, campaign.end_date_time, campaign_budget.amount_micros FROM campaign WHERE campaign.id = {$campanhaId} AND campaign.status != 'REMOVED'", 'campanha.ler')[0] ?? null;

        if ($linha === null) {
            throw new GoogleAdsFalhou('Esta campanha não existe nesta conta do Google Ads (ou foi removida).');
        }

        $campanha = (array) ($linha['campaign'] ?? []);
        $status = (string) ($campanha['status'] ?? 'UNKNOWN');

        $lida = [
            'id' => (string) ($campanha['id'] ?? $campanhaId),
            'resource' => (string) ($campanha['resourceName'] ?? 'customers/'.$conta->customer_id.'/campaigns/'.$campanhaId),
            'nome' => (string) ($campanha['name'] ?? ''),
            'status' => $status,
            'status_rotulo' => $this->rotulo($status),
            'tipo' => (string) ($campanha['advertisingChannelType'] ?? ''),
            'inicio' => (string) ($campanha['startDateTime'] ?? $campanha['start_date_time'] ?? $campanha['startDate'] ?? ''),
            'fim' => (string) ($campanha['endDateTime'] ?? $campanha['end_date_time'] ?? $campanha['endDate'] ?? ''),
            'orcamento' => [
                'resource' => (string) ($campanha['campaignBudget'] ?? ''),
                'diario_centavos' => (int) round(((int) data_get($linha, 'campaignBudget.amountMicros', 0)) / 10000),
            ],
            'regioes' => [],
            'idiomas' => [],
            'negativas' => [],
            'grupos' => [],
            'recursos' => ['imagens' => [], 'sitelinks' => [], 'frases' => []],
        ];

        $this->lerCriterios($conta, $campanhaId, $lida);
        $this->lerGrupos($conta, $campanhaId, $lida);
        $this->lerRecursos($conta, $campanhaId, $lida);

        return $lida;
    }

    /**
     * Nome, orçamento, regiões e negativas da campanha.
     *
     * Cada bloco só é escrito se mudou: o Google cobra cota por operação, e um updateMask
     * mínimo é o que impede reescrever campos que o painel nem leu. Regiões e negativas são
     * comparadas com o que foi lido, e o que sobrou é removido, o que faltou é criado.
     *
     * @param  array<string, mixed>  $campanha  o retorno de `ler()`
     * @param  array{nome?: string, orcamento_diario_centavos?: int, regioes?: array<int, string>, negativas?: array<int, string>}  $dados
     * @return array{nome: bool, orcamento: bool, regioes_criadas: int, regioes_removidas: int, negativas_criadas: int, negativas_removidas: int}
     */
    public function atualizarCampanha(GoogleAdsAccount $conta, array $campanha, array $dados): array
    {
        $this->prepararEscrita($conta);

        $resultado = ['nome' => false, 'orcamento' => false, 'regioes_criadas' => 0, 'regioes_removidas' => 0, 'negativas_criadas' => 0, 'negativas_removidas' => 0];
        $customer = 'customers/'.$conta->customer_id;

        $nome = mb_substr(trim((string) ($dados['nome'] ?? $campanha['nome'])), 0, 128);

        if ($nome !== '' && $nome !== $campanha['nome']) {
            $this->chamar($conta, $customer.'/campaigns:mutate', [
                'operations' => [[
                    'update' => ['resourceName' => $campanha['resource'], 'name' => $nome],
                    'updateMask' => 'name',
                ]],
            ], 'campanha.renomear');
            $resultado['nome'] = true;
        }

        $centavos = (int) ($dados['orcamento_diario_centavos'] ?? $campanha['orcamento']['diario_centavos']);

        if ($centavos > 0 && $centavos !== (int) $campanha['orcamento']['diario_centavos'] && $campanha['orcamento']['resource'] !== '') {
            $this->chamar($conta, $customer.'/campaignBudgets:mutate', [
                'operations' => [[
                    'update' => ['resourceName' => $campanha['orcamento']['resource'], 'amountMicros' => (string) ($centavos * 10000)],
                    'updateMask' => 'amountMicros',
                ]],
            ], 'orcamento.alterar');
            $resultado['orcamento'] = true;
        }

        if (array_key_exists('regioes', $dados)) {
            $novas = $this->resolverRegioes($conta, (array) $dados['regioes']);
            $atuais = array_column($campanha['regioes'], 'resource', 'constante');
            $queridas = array_column($novas, 'recurso', 'recurso');
            $operacoes = [];

            foreach ($novas as $regiao) {
                if (! isset($atuais[$regiao['recurso']])) {
                    $operacoes[] = ['create' => ['campaign' => $campanha['resource'], 'location' => ['geoTargetConstant' => $regiao['recurso']]]];
                    $resultado['regioes_criadas']++;
                }
            }

            foreach ($atuais as $constante => $recurso) {
                if (! isset($queridas[$constante])) {
                    $operacoes[] = ['remove' => $recurso];
                    $resultado['regioes_removidas']++;
                }
            }

            if ($operacoes !== []) {
                $this->chamar($conta, $customer.'/campaignCriteria:mutate', ['operations' => $operacoes], 'campanha.segmentar');
            }
        }

        if (array_key_exists('negativas', $dados)) {
            $queridas = [];

            foreach ((array) $dados['negativas'] as $texto) {
                $texto = trim((string) $texto);

                if ($texto !== '') {
                    $queridas[mb_strtolower($texto)] = $texto;
                }
            }

            $atuais = [];

            foreach ($campanha['negativas'] as $negativa) {
                $atuais[mb_strtolower((string) $negativa['texto'])] = (string) $negativa['resource'];
            }

            $operacoes = [];

            foreach ($queridas as $chave => $texto) {
                if (! isset($atuais[$chave])) {
                    $operacoes[] = ['create' => ['campaign' => $campanha['resource'], 'negative' => true, 'keyword' => ['text' => $texto, 'matchType' => 'BROAD']]];
                    $resultado['negativas_criadas']++;
                }
            }

            foreach ($atuais as $chave => $recurso) {
                if (! isset($queridas[$chave])) {
                    $operacoes[] = ['remove' => $recurso];
                    $resultado['negativas_removidas']++;
                }
            }

            if ($operacoes !== []) {
                $this->chamar($conta, $customer.'/campaignCriteria:mutate', ['operations' => $operacoes], 'negativas.alterar');
            }
        }

        return $resultado;
    }

    /**
     * Um grupo novo na campanha, com o anúncio e as palavras-chave de uma vez.
     *
     * @param  array<string, mixed>  $campanha  o retorno de `ler()`
     * @param  array{nome: string, cpc_maximo_centavos: int, palavras: array<int, array{texto: string, correspondencia: string}>, anuncio: array{url: string, titulos: array<int, string>, descricoes: array<int, string>, caminhos: array<int, string>}}  $dados
     * @return array{grupo_id: string, grupo_resource: string, palavras: int}
     */
    public function criarGrupo(GoogleAdsAccount $conta, array $campanha, array $dados): array
    {
        $this->prepararEscrita($conta);
        $customer = 'customers/'.$conta->customer_id;

        $resposta = $this->chamar($conta, $customer.'/adGroups:mutate', [
            'operations' => [['create' => [
                'name' => mb_substr((string) $dados['nome'], 0, 255),
                'campaign' => $campanha['resource'],
                'status' => 'ENABLED',
                'type' => 'SEARCH_STANDARD',
                'cpcBidMicros' => (string) (max(1, (int) $dados['cpc_maximo_centavos']) * 10000),
            ]]],
        ], 'grupo.criar');

        $grupo = (string) data_get($resposta, 'results.0.resourceName', '');

        if ($grupo === '') {
            throw new GoogleAdsFalhou('O Google aceitou o grupo mas não devolveu o identificador. Recarregue a campanha antes de tentar de novo.');
        }

        $anuncio = (array) $dados['anuncio'];

        $this->chamar($conta, $customer.'/adGroupAds:mutate', [
            'operations' => [['create' => [
                'adGroup' => $grupo,
                'status' => 'ENABLED',
                'ad' => self::anuncioResponsivo((string) $anuncio['url'], (array) $anuncio['titulos'], (array) $anuncio['descricoes'], (array) ($anuncio['caminhos'] ?? [])),
            ]]],
        ], 'anuncio.criar');

        $palavras = $this->escreverPalavras($conta, $grupo, (array) ($dados['palavras'] ?? []), []);

        return ['grupo_id' => basename($grupo), 'grupo_resource' => $grupo, 'palavras' => $palavras['criadas']];
    }

    /**
     * Nome, lance, palavras e anúncio de um grupo que já existe.
     *
     * Os títulos e descrições de um anúncio responsivo são imutáveis na API: um texto mudado
     * vira anúncio novo, e o antigo é removido só depois que o novo foi aceito — assim o grupo
     * nunca fica sem anúncio se o Google recusar o texto.
     *
     * @param  array<string, mixed>  $grupo  um item de `grupos` do `ler()`
     * @param  array{nome?: string, cpc_maximo_centavos?: int, palavras?: array<int, array{texto: string, correspondencia: string}>, anuncio?: array{url: string, titulos: array<int, string>, descricoes: array<int, string>, caminhos: array<int, string>}}  $dados
     * @return array{grupo: bool, palavras_criadas: int, palavras_removidas: int, anuncio: bool}
     */
    public function atualizarGrupo(GoogleAdsAccount $conta, array $grupo, array $dados): array
    {
        $this->prepararEscrita($conta);
        $customer = 'customers/'.$conta->customer_id;
        $resultado = ['grupo' => false, 'palavras_criadas' => 0, 'palavras_removidas' => 0, 'anuncio' => false];

        $campos = [];
        $nome = mb_substr(trim((string) ($dados['nome'] ?? $grupo['nome'])), 0, 255);

        if ($nome !== '' && $nome !== $grupo['nome']) {
            $campos['name'] = $nome;
        }

        $centavos = (int) ($dados['cpc_maximo_centavos'] ?? $grupo['cpc_maximo_centavos']);

        if ($centavos > 0 && $centavos !== (int) $grupo['cpc_maximo_centavos']) {
            $campos['cpcBidMicros'] = (string) ($centavos * 10000);
        }

        if ($campos !== []) {
            $this->chamar($conta, $customer.'/adGroups:mutate', [
                'operations' => [[
                    'update' => ['resourceName' => $grupo['resource']] + $campos,
                    'updateMask' => implode(',', array_keys($campos)),
                ]],
            ], 'grupo.alterar');
            $resultado['grupo'] = true;
        }

        if (array_key_exists('palavras', $dados)) {
            $palavras = $this->escreverPalavras($conta, $grupo['resource'], (array) $dados['palavras'], (array) $grupo['palavras']);
            $resultado['palavras_criadas'] = $palavras['criadas'];
            $resultado['palavras_removidas'] = $palavras['removidas'];
        }

        if (isset($dados['anuncio'])) {
            $novo = self::anuncioResponsivo(
                (string) $dados['anuncio']['url'],
                (array) $dados['anuncio']['titulos'],
                (array) $dados['anuncio']['descricoes'],
                (array) ($dados['anuncio']['caminhos'] ?? []),
            );
            $atual = (array) ($grupo['anuncios'][0] ?? null);
            $mesmo = $atual !== [] && self::anuncioResponsivo(
                (string) $atual['url'],
                (array) $atual['titulos'],
                (array) $atual['descricoes'],
                (array) $atual['caminhos'],
            ) === $novo;

            if (! $mesmo) {
                $this->chamar($conta, $customer.'/adGroupAds:mutate', [
                    'operations' => [['create' => ['adGroup' => $grupo['resource'], 'status' => 'ENABLED', 'ad' => $novo]]],
                ], 'anuncio.criar');

                $antigos = array_values(array_filter(array_map(
                    static fn (array $a): string => (string) ($a['resource'] ?? ''),
                    (array) $grupo['anuncios'],
                )));

                if ($antigos !== []) {
                    $this->chamar($conta, $customer.'/adGroupAds:mutate', [
                        'operations' => array_map(static fn (string $r): array => ['remove' => $r], $antigos),
                    ], 'anuncio.remover');
                }

                $resultado['anuncio'] = true;
            }
        }

        return $resultado;
    }

    /** Pausa ou reativa um grupo; `ENABLED` e `PAUSED` são os únicos status que o painel escreve. */
    public function mudarStatusDoGrupo(GoogleAdsAccount $conta, array $grupo, string $status): void
    {
        if (! in_array($status, ['ENABLED', 'PAUSED'], true)) {
            throw new GoogleAdsFalhou('O painel só ativa ou pausa um grupo.');
        }

        $this->prepararEscrita($conta);

        $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroups:mutate', [
            'operations' => [[
                'update' => ['resourceName' => $grupo['resource'], 'status' => $status],
                'updateMask' => 'status',
            ]],
        ], 'grupo.status');
    }

    /** Remove o grupo (e, com ele, anúncios e palavras). No Google isso não tem volta. */
    public function removerGrupo(GoogleAdsAccount $conta, array $grupo): void
    {
        $this->prepararEscrita($conta);

        $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroups:mutate', [
            'operations' => [['remove' => $grupo['resource']]],
        ], 'grupo.remover');
    }

    /**
     * Sobe a imagem como recurso da conta e vincula à campanha.
     *
     * A imagem vai em base64 dentro do JSON — a API não tem upload separado. O recurso nasce
     * na conta (vale para outras campanhas) e o vínculo é o que faz a campanha usá-la.
     *
     * @return array{asset: string, vinculo: string}
     */
    public function vincularImagem(GoogleAdsAccount $conta, array $campanha, string $caminhoAbsoluto, string $nome): array
    {
        $conteudo = is_file($caminhoAbsoluto) ? file_get_contents($caminhoAbsoluto) : false;

        if ($conteudo === false || $conteudo === '') {
            throw new GoogleAdsFalhou('A imagem não foi encontrada no servidor. Envie o arquivo de novo.');
        }

        $this->prepararEscrita($conta);

        $asset = $this->criarAsset($conta, [
            'name' => mb_substr($nome, 0, 128),
            'imageAsset' => ['data' => base64_encode($conteudo)],
        ], 'imagem.criar');

        return ['asset' => $asset, 'vinculo' => $this->vincularAsset($conta, $campanha, $asset, 'AD_IMAGE', 'imagem.vincular')];
    }

    /**
     * Um sitelink: link extra abaixo do anúncio.
     *
     * @param  array{texto: string, url: string, descricao1?: string, descricao2?: string}  $dados
     * @return array{asset: string, vinculo: string}
     */
    public function vincularSitelink(GoogleAdsAccount $conta, array $campanha, array $dados): array
    {
        $this->prepararEscrita($conta);

        $sitelink = ['linkText' => mb_substr(trim((string) $dados['texto']), 0, 25)];

        foreach (['descricao1' => 'description1', 'descricao2' => 'description2'] as $campo => $chave) {
            $valor = trim((string) ($dados[$campo] ?? ''));

            if ($valor !== '') {
                $sitelink[$chave] = mb_substr($valor, 0, 35);
            }
        }

        $asset = $this->criarAsset($conta, [
            'sitelinkAsset' => $sitelink,
            'finalUrls' => [(string) $dados['url']],
        ], 'sitelink.criar');

        return ['asset' => $asset, 'vinculo' => $this->vincularAsset($conta, $campanha, $asset, 'SITELINK', 'sitelink.vincular')];
    }

    /**
     * Uma frase de destaque (callout).
     *
     * @return array{asset: string, vinculo: string}
     */
    public function vincularFrase(GoogleAdsAccount $conta, array $campanha, string $texto): array
    {
        $this->prepararEscrita($conta);

        $asset = $this->criarAsset($conta, [
            'calloutAsset' => ['calloutText' => mb_substr(trim($texto), 0, 25)],
        ], 'frase.criar');

        return ['asset' => $asset, 'vinculo' => $this->vincularAsset($conta, $campanha, $asset, 'CALLOUT', 'frase.vincular')];
    }

    /**
     * Desfaz o vínculo de um recurso com a campanha. O recurso continua na conta — removê-lo
     * de lá tiraria de outras campanhas que o usam.
     */
    public function desvincularRecurso(GoogleAdsAccount $conta, string $vinculo): void
    {
        $this->prepararEscrita($conta);

        $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaignAssets:mutate', [
            'operations' => [['remove' => $vinculo]],
        ], 'recurso.desvincular');
    }

    /** O rótulo de status em português, como o restante do painel escreve. */
    public function rotulo(string $status, string $grupo = 'campanha'): string
    {
        return (string) (config("google_ads.status_{$grupo}")[$status] ?? $status);
    }

    private function criarAsset(GoogleAdsAccount $conta, array $asset, string $etapa): string
    {
        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/assets:mutate', [
            'operations' => [['create' => $asset]],
        ], $etapa);

        $recurso = (string) data_get($resposta, 'results.0.resourceName', '');

        if ($recurso === '') {
            throw new GoogleAdsFalhou('O Google aceitou o recurso mas não devolveu o identificador. Recarregue a campanha antes de tentar de novo.');
        }

        return $recurso;
    }

    private function vincularAsset(GoogleAdsAccount $conta, array $campanha, string $asset, string $tipo, string $etapa): string
    {
        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/campaignAssets:mutate', [
            'operations' => [['create' => [
                'campaign' => $campanha['resource'],
                'asset' => $asset,
                'fieldType' => $tipo,
            ]]],
        ], $etapa);

        return (string) data_get($resposta, 'results.0.resourceName', '');
    }

    /**
     * Cria o que falta e remove o que sobrou entre as palavras lidas e as pedidas; a chave de
     * comparação é texto + correspondência, porque "rastreador" em frase e em exata são duas
     * palavras distintas para o Google.
     *
     * @param  array<int, array{texto: string, correspondencia: string}>  $queridas
     * @param  array<int, array{resource: string, texto: string, correspondencia: string}>  $atuais
     * @return array{criadas: int, removidas: int}
     */
    private function escreverPalavras(GoogleAdsAccount $conta, string $grupo, array $queridas, array $atuais): array
    {
        $chave = static fn (string $texto, string $correspondencia): string => mb_strtolower(trim($texto)).'|'.strtoupper($correspondencia);
        $pedidas = [];

        foreach ($queridas as $palavra) {
            $texto = trim((string) ($palavra['texto'] ?? ''));

            if ($texto !== '') {
                $pedidas[$chave($texto, (string) ($palavra['correspondencia'] ?? 'PHRASE'))] = ['texto' => $texto, 'correspondencia' => strtoupper((string) ($palavra['correspondencia'] ?? 'PHRASE'))];
            }
        }

        $existentes = [];

        foreach ($atuais as $palavra) {
            $existentes[$chave((string) $palavra['texto'], (string) $palavra['correspondencia'])] = (string) $palavra['resource'];
        }

        $operacoes = [];
        $criadas = 0;
        $removidas = 0;

        foreach ($pedidas as $k => $palavra) {
            if (! isset($existentes[$k])) {
                $operacoes[] = ['create' => ['adGroup' => $grupo, 'status' => 'ENABLED', 'keyword' => ['text' => $palavra['texto'], 'matchType' => $palavra['correspondencia']]]];
                $criadas++;
            }
        }

        foreach ($existentes as $k => $recurso) {
            if (! isset($pedidas[$k])) {
                $operacoes[] = ['remove' => $recurso];
                $removidas++;
            }
        }

        if ($operacoes !== []) {
            $this->chamar($conta, 'customers/'.$conta->customer_id.'/adGroupCriteria:mutate', ['operations' => $operacoes], 'palavras.alterar');
        }

        return ['criadas' => $criadas, 'removidas' => $removidas];
    }

    private function lerCriterios(GoogleAdsAccount $conta, string $campanhaId, array &$lida): void
    {
        $linhas = $this->consultar($conta, "SELECT campaign.id, campaign_criterion.resource_name, campaign_criterion.type, campaign_criterion.negative, campaign_criterion.status, campaign_criterion.keyword.text, campaign_criterion.keyword.match_type, campaign_criterion.location.geo_target_constant, campaign_criterion.language.language_constant FROM campaign_criterion WHERE campaign.id = {$campanhaId} AND campaign_criterion.type IN ('LOCATION', 'LANGUAGE', 'KEYWORD') AND campaign_criterion.status != 'REMOVED'", 'campanha.criterios');

        $constantes = [];

        foreach ($linhas as $linha) {
            $criterio = (array) ($linha['campaignCriterion'] ?? []);
            $recurso = (string) ($criterio['resourceName'] ?? '');

            switch ((string) ($criterio['type'] ?? '')) {
                case 'LOCATION':
                    $constante = (string) data_get($criterio, 'location.geoTargetConstant', '');

                    if ($constante !== '' && empty($criterio['negative'])) {
                        $constantes[] = $constante;
                        $lida['regioes'][] = ['resource' => $recurso, 'constante' => $constante, 'nome' => basename($constante)];
                    }
                    break;
                case 'LANGUAGE':
                    $idioma = (string) data_get($criterio, 'language.languageConstant', '');
                    $lida['idiomas'][] = $idioma === (string) config('google_ads.copiloto.idioma')
                        ? (string) config('google_ads.copiloto.idioma_rotulo', $idioma)
                        : basename($idioma);
                    break;
                case 'KEYWORD':
                    if (! empty($criterio['negative'])) {
                        $lida['negativas'][] = [
                            'resource' => $recurso,
                            'texto' => (string) data_get($criterio, 'keyword.text', ''),
                            'correspondencia' => (string) data_get($criterio, 'keyword.matchType', 'BROAD'),
                        ];
                    }
                    break;
            }
        }

        if ($constantes === []) {
            return;
        }

        // O nome da região é cosmético: se a consulta de constantes falhar, a tela mostra o
        // número e a edição continua funcionando.
        try {
            $ids = implode(', ', array_map(static fn (string $c): string => basename($c), $constantes));
            $nomes = [];

            foreach ($this->consultar($conta, "SELECT geo_target_constant.id, geo_target_constant.resource_name, geo_target_constant.name, geo_target_constant.canonical_name FROM geo_target_constant WHERE geo_target_constant.id IN ({$ids})", 'regioes.nomes') as $linha) {
                $constante = (array) ($linha['geoTargetConstant'] ?? []);
                $nomes[(string) ($constante['resourceName'] ?? '')] = (string) ($constante['name'] ?? '');
            }

            foreach ($lida['regioes'] as &$regiao) {
                $regiao['nome'] = $nomes[$regiao['constante']] ?? $regiao['nome'];
            }
            unset($regiao);
        } catch (GoogleAdsFalhou) {
            // segue com o número da constante
        }
    }

    private function lerGrupos(GoogleAdsAccount $conta, string $campanhaId, array &$lida): void
    {
        $grupos = [];

        foreach ($this->consultar($conta, "SELECT campaign.id, ad_group.id, ad_group.resource_name, ad_group.name, ad_group.status, ad_group.cpc_bid_micros FROM ad_group WHERE campaign.id = {$campanhaId} AND ad_group.status != 'REMOVED' ORDER BY ad_group.id", 'grupos.ler') as $linha) {
            $grupo = (array) ($linha['adGroup'] ?? []);
            $id = (string) ($grupo['id'] ?? '');
            $status = (string) ($grupo['status'] ?? 'UNKNOWN');

            $grupos[$id] = [
                'id' => $id,
                'resource' => (string) ($grupo['resourceName'] ?? ''),
                'nome' => (string) ($grupo['name'] ?? ''),
                'status' => $status,
                'status_rotulo' => $this->rotulo($status, 'anuncio'),
                'cpc_maximo_centavos' => (int) round(((int) ($grupo['cpcBidMicros'] ?? 0)) / 10000),
                'palavras' => [],
                'anuncios' => [],
            ];
        }

        if ($grupos === []) {
            return;
        }

        foreach ($this->consultar($conta, "SELECT campaign.id, ad_group.id, ad_group_ad.resource_name, ad_group_ad.status, ad_group_ad.ad.id, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions, ad_group_ad.ad.responsive_search_ad.path1, ad_group_ad.ad.responsive_search_ad.path2, ad_group_ad.policy_summary.approval_status FROM ad_group_ad WHERE campaign.id = {$campanhaId} AND ad_group_ad.status != 'REMOVED'", 'anuncios.ler') as $linha) {
            $grupoId = (string) data_get($linha, 'adGroup.id', '');

            if (! isset($grupos[$grupoId])) {
                continue;
            }

            $anuncio = (array) ($linha['adGroupAd'] ?? []);
            $rsa = (array) data_get($anuncio, 'ad.responsiveSearchAd', []);
            $status = (string) ($anuncio['status'] ?? 'UNKNOWN');
            $aprovacao = (string) data_get($anuncio, 'policySummary.approvalStatus', '');

            $grupos[$grupoId]['anuncios'][] = [
                'resource' => (string) ($anuncio['resourceName'] ?? ''),
                'id' => (string) data_get($anuncio, 'ad.id', ''),
                'status' => $status,
                'status_rotulo' => $this->rotulo($status, 'anuncio'),
                'url' => (string) (data_get($anuncio, 'ad.finalUrls.0') ?? ''),
                'titulos' => array_values(array_map(static fn ($t): string => (string) (is_array($t) ? ($t['text'] ?? '') : $t), (array) ($rsa['headlines'] ?? []))),
                'descricoes' => array_values(array_map(static fn ($t): string => (string) (is_array($t) ? ($t['text'] ?? '') : $t), (array) ($rsa['descriptions'] ?? []))),
                'caminhos' => array_values(array_filter([(string) ($rsa['path1'] ?? ''), (string) ($rsa['path2'] ?? '')], static fn (string $c): bool => $c !== '')),
                'aprovacao' => $aprovacao,
                'aprovacao_rotulo' => (string) (config('google_ads.aprovacao_anuncio')[$aprovacao] ?? $aprovacao),
            ];
        }

        foreach ($this->consultar($conta, "SELECT campaign.id, ad_group.id, ad_group_criterion.resource_name, ad_group_criterion.status, ad_group_criterion.type, ad_group_criterion.negative, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type FROM ad_group_criterion WHERE campaign.id = {$campanhaId} AND ad_group_criterion.type = 'KEYWORD' AND ad_group_criterion.status != 'REMOVED' AND ad_group_criterion.negative = FALSE", 'palavras.ler') as $linha) {
            $grupoId = (string) data_get($linha, 'adGroup.id', '');

            if (! isset($grupos[$grupoId])) {
                continue;
            }

            $criterio = (array) ($linha['adGroupCriterion'] ?? []);

            $grupos[$grupoId]['palavras'][] = [
                'resource' => (string) ($criterio['resourceName'] ?? ''),
                'texto' => (string) data_get($criterio, 'keyword.text', ''),
                'correspondencia' => (string) data_get($criterio, 'keyword.matchType', 'PHRASE'),
                'correspondencia_rotulo' => PlanoDeCampanha::correspondenciaEmPortugues((string) data_get($criterio, 'keyword.matchType', 'PHRASE')),
            ];
        }

        $lida['grupos'] = array_values($grupos);
    }

    private function lerRecursos(GoogleAdsAccount $conta, string $campanhaId, array &$lida): void
    {
        foreach ($this->consultar($conta, "SELECT campaign.id, campaign_asset.resource_name, campaign_asset.field_type, campaign_asset.status, asset.id, asset.name, asset.final_urls, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels, asset.sitelink_asset.link_text, asset.sitelink_asset.description1, asset.sitelink_asset.description2, asset.callout_asset.callout_text FROM campaign_asset WHERE campaign.id = {$campanhaId} AND campaign_asset.field_type IN ('AD_IMAGE', 'SITELINK', 'CALLOUT') AND campaign_asset.status != 'REMOVED'", 'recursos.ler') as $linha) {
            $vinculo = (array) ($linha['campaignAsset'] ?? []);
            $asset = (array) ($linha['asset'] ?? []);
            $base = [
                'vinculo' => (string) ($vinculo['resourceName'] ?? ''),
                'asset_id' => (string) ($asset['id'] ?? ''),
                'status' => (string) ($vinculo['status'] ?? ''),
            ];

            switch ((string) ($vinculo['fieldType'] ?? '')) {
                case 'AD_IMAGE':
                    $lida['recursos']['imagens'][] = $base + [
                        'nome' => (string) ($asset['name'] ?? ''),
                        'url' => (string) data_get($asset, 'imageAsset.fullSize.url', ''),
                        'largura' => (int) data_get($asset, 'imageAsset.fullSize.widthPixels', 0),
                        'altura' => (int) data_get($asset, 'imageAsset.fullSize.heightPixels', 0),
                    ];
                    break;
                case 'SITELINK':
                    $lida['recursos']['sitelinks'][] = $base + [
                        'texto' => (string) data_get($asset, 'sitelinkAsset.linkText', ''),
                        'descricao1' => (string) data_get($asset, 'sitelinkAsset.description1', ''),
                        'descricao2' => (string) data_get($asset, 'sitelinkAsset.description2', ''),
                        'url' => (string) (data_get($asset, 'finalUrls.0') ?? ''),
                    ];
                    break;
                case 'CALLOUT':
                    $lida['recursos']['frases'][] = $base + [
                        'texto' => (string) data_get($asset, 'calloutAsset.calloutText', ''),
                    ];
                    break;
            }
        }
    }

    /**
     * Uma consulta GAQL por REST. O resultado vem em `results`, com os campos em camelCase
     * e os números grandes como texto; sem `pageSize`, que a V26 recusa.
     *
     * @return array<int, array<string, mixed>>
     */
    private function consultar(GoogleAdsAccount $conta, string $gaql, string $etapa): array
    {
        $resposta = $this->chamar($conta, 'customers/'.$conta->customer_id.'/googleAds:search', ['query' => $gaql], $etapa);

        return array_values((array) ($resposta['results'] ?? []));
    }
}
