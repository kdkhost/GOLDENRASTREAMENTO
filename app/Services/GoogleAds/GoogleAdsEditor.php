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
            'recursos' => ['imagens' => [], 'sitelinks' => [], 'frases' => [], 'empresa' => null, 'biblioteca' => []],
        ];

        $this->lerCriterios($conta, $campanhaId, $lida);
        $this->lerGrupos($conta, $campanhaId, $lida);

        try {
            $this->lerRecursos($conta, $campanhaId, $lida);
        } catch (\Throwable $e) {
            report($e);
        }

        return $lida;
    }

    /**
     * O status atual da campanha no Google Ads (ENABLED, PAUSED, REMOVED).
     */
    public function lerStatus(GoogleAdsAccount $conta, string $campanhaId): ?string
    {
        if (! preg_match('/^\d{6,15}$/', $campanhaId)) {
            return null;
        }

        $this->prepararEscrita($conta);

        $linha = $this->consultar($conta, "SELECT campaign.id, campaign.status FROM campaign WHERE campaign.id = {$campanhaId}", 'campanha.status')[0] ?? null;

        return (string) data_get($linha, 'campaign.status') ?: null;
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
     * Sobe a imagem como recurso da conta e vincula à campanha (como AD_IMAGE ou BUSINESS_LOGO).
     *
     * A imagem vai em base64 dentro do JSON — a API não tem upload separado. O recurso nasce
     * na conta (vale para outras campanhas) e o vínculo é o que faz a campanha usá-la.
     * Guarda também uma cópia local de alta resolução em cache para nunca quebrar a visualização.
     *
     * @return array{asset: string, vinculo: string, tipo: string, aviso?: string}
     */
    public function vincularImagem(GoogleAdsAccount $conta, array $campanha, string $caminhoAbsoluto, string $nome, string $tipo = 'AUTO'): array
    {
        [$conteudo, $extensao, $eQuadrada, $tipo] = $this->prepararImagemParaGoogleAds($caminhoAbsoluto, $tipo);

        $this->prepararEscrita($conta);

        $asset = $this->criarAsset($conta, [
            'name' => mb_substr($nome, 0, 128),
            'imageAsset' => ['data' => base64_encode($conteudo)],
        ], 'imagem.criar');

        $assetId = basename($asset);

        // Salva cópia local em cache permanente para visualização sem falhas
        $pastaCache = storage_path('app/public/google-ads/assets');
        if (! is_dir($pastaCache)) {
            @mkdir($pastaCache, 0755, true);
        }
        @file_put_contents($pastaCache.'/'.$assetId.'.'.$extensao, $conteudo);

        try {
            $vinculo = $this->vincularAsset($conta, $campanha, $asset, $tipo, 'imagem.vincular');

            return ['asset' => $asset, 'vinculo' => $vinculo, 'tipo' => $tipo];
        } catch (GoogleAdsFalhou $e) {
            // Se AD_IMAGE falhar e a imagem for quadrada, tenta automaticamente como Logotipo da Empresa
            if ($tipo === 'AD_IMAGE' && $eQuadrada) {
                try {
                    $vinculo = $this->vincularAsset($conta, $campanha, $asset, 'BUSINESS_LOGO', 'imagem.vincular.logo');

                    return ['asset' => $asset, 'vinculo' => $vinculo, 'tipo' => 'BUSINESS_LOGO'];
                } catch (\Throwable) {
                    // segue para fallback
                }
            }

            // Se for BUSINESS_LOGO que falhou na campanha, tenta vincular no nível da Conta inteira (CustomerAsset)
            if ($tipo === 'BUSINESS_LOGO') {
                try {
                    $resCust = $this->chamar($conta, 'customers/'.$conta->customer_id.'/customerAssets:mutate', [
                        'operations' => [['create' => [
                            'asset' => $asset,
                            'fieldType' => 'BUSINESS_LOGO',
                        ]]],
                    ], 'imagem.vincular.customer_logo');

                    $vinculo = (string) data_get($resCust, 'results.0.resourceName', '');

                    return ['asset' => $asset, 'vinculo' => $vinculo, 'tipo' => 'BUSINESS_LOGO'];
                } catch (\Throwable) {
                    // segue
                }
            }

            throw $e;
        }
    }

    /**
     * Substitui uma imagem vinculada: envia e vincula a nova imagem primeiro e só remove o
     * vínculo antigo depois do sucesso, evitando que a campanha fique sem recurso visual.
     *
     * @return array{asset: string, vinculo: string, tipo: string}
     */
    public function substituirImagem(
        GoogleAdsAccount $conta,
        array $campanha,
        string $vinculoAntigo,
        string $caminhoAbsoluto,
        string $nome,
        string $tipo = 'AUTO'
    ): array {
        $nova = $this->vincularImagem($conta, $campanha, $caminhoAbsoluto, $nome, $tipo);

        if ($vinculoAntigo !== '' && $vinculoAntigo !== $nova['vinculo']) {
            $this->desvincularRecurso($conta, $vinculoAntigo);
        }

        return $nova;
    }

    /**
     * Valida dimensões, proporção, tamanho e formato antes do envio ao Google Ads para evitar
     * rejeições automáticas por imagem fora do padrão.
     *
     * @return array{0: string, 1: string, 2: bool, 3: string}
     */
    private function prepararImagemParaGoogleAds(string $caminhoAbsoluto, string $tipo): array
    {
        $conteudo = is_file($caminhoAbsoluto) ? file_get_contents($caminhoAbsoluto) : false;

        if ($conteudo === false || $conteudo === '') {
            throw new GoogleAdsFalhou('A imagem não foi encontrada no servidor. Envie o arquivo de novo.');
        }

        $info = @getimagesize($caminhoAbsoluto);
        if ($info === false) {
            $extensao = strtolower(pathinfo($caminhoAbsoluto, PATHINFO_EXTENSION)) ?: 'jpg';
            if ($tipo === 'AUTO') {
                $tipo = 'AD_IMAGE';
            }

            return [$conteudo, $extensao, false, $tipo];
        }

        $largura = (int) ($info[0] ?? 0);
        $altura = (int) ($info[1] ?? 0);
        $imageType = (int) ($info[2] ?? 0);
        $eQuadrada = $largura > 0 && $altura > 0 && abs($largura - $altura) / max($largura, $altura) <= 0.08;

        // Converte WebP/GIF para PNG ou JPEG quando GD estiver disponível para evitar recusa do Google Ads.
        if (! in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            if (! function_exists('imagecreatefromstring')) {
                throw new GoogleAdsFalhou('Formato de imagem não suportado pelo Google Ads. Envie JPG ou PNG.');
            }
            $recurso = @imagecreatefromstring($conteudo);
            if ($recurso === false) {
                throw new GoogleAdsFalhou('Não foi possível ler esta imagem. Salve como JPG ou PNG e tente novamente.');
            }
            ob_start();
            if ($eQuadrada) {
                imagepng($recurso);
                $extensao = 'png';
            } else {
                imagejpeg($recurso, null, 92);
                $extensao = 'jpg';
            }
            $conteudo = (string) ob_get_clean();
            imagedestroy($recurso);
        } else {
            $extensao = $imageType === IMAGETYPE_PNG ? 'png' : 'jpg';
        }

        if (strlen($conteudo) > 5 * 1024 * 1024) {
            throw new GoogleAdsFalhou('A imagem excede o limite máximo de 5 MB do Google Ads.');
        }

        if ($tipo === 'AUTO') {
            $tipo = $eQuadrada ? 'BUSINESS_LOGO' : 'AD_IMAGE';
        }

        if ($tipo === 'BUSINESS_LOGO' && ($largura < 128 || $altura < 128)) {
            throw new GoogleAdsFalhou("Logotipo muito pequeno ({$largura}×{$altura}px). O Google Ads exige no mínimo 128×128px (ideal: 1200×1200px) para aprovação automática.");
        }

        if ($tipo === 'AD_IMAGE') {
            if ($eQuadrada && ($largura < 300 || $altura < 300)) {
                throw new GoogleAdsFalhou("Imagem quadrada muito pequena ({$largura}×{$altura}px). O Google Ads exige no mínimo 300×300px (ideal: 1200×1200px).");
            }
            if (! $eQuadrada && ($largura < 600 || $altura < 314)) {
                throw new GoogleAdsFalhou("Imagem paisagem muito pequena ({$largura}×{$altura}px). O Google Ads exige no mínimo 600×314px (ideal: 1200×628px).");
            }
        }

        return [$conteudo, $extensao, $eQuadrada, $tipo];
    }

    /**
     * Define ou atualiza o nome da empresa exibido no topo do anúncio de busca (BUSINESS_NAME).
     *
     * @return array{asset: string, vinculo: string}
     */
    public function vincularNomeEmpresa(GoogleAdsAccount $conta, array $campanha, string $nomeEmpresa): array
    {
        $this->prepararEscrita($conta);

        $asset = $this->criarAsset($conta, [
            'name' => mb_substr('Nome: '.$nomeEmpresa, 0, 128),
            'textAsset' => ['text' => mb_substr(trim($nomeEmpresa), 0, 25)],
        ], 'nome_empresa.criar');

        return ['asset' => $asset, 'vinculo' => $this->vincularAsset($conta, $campanha, $asset, 'BUSINESS_NAME', 'nome_empresa.vincular')];
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
        $gaql = "SELECT campaign.id, campaign_asset.resource_name, campaign_asset.field_type, campaign_asset.status, asset.id, asset.name, asset.final_urls, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels, asset.sitelink_asset.link_text, asset.sitelink_asset.description1, asset.sitelink_asset.description2, asset.callout_asset.callout_text, asset.text_asset.text FROM campaign_asset WHERE campaign.id = {$campanhaId} AND campaign_asset.field_type IN ('AD_IMAGE', 'BUSINESS_LOGO', 'LOGO', 'LANDSCAPE_LOGO', 'SITELINK', 'CALLOUT', 'BUSINESS_NAME') AND campaign_asset.status != 'REMOVED'";

        foreach ($this->consultar($conta, $gaql, 'recursos.ler') as $linha) {
            $vinculo = (array) ($linha['campaignAsset'] ?? []);
            $asset = (array) ($linha['asset'] ?? []);
            $assetId = (string) ($asset['id'] ?? '');
            $base = [
                'vinculo' => (string) ($vinculo['resourceName'] ?? ''),
                'asset_id' => $assetId,
                'status' => (string) ($vinculo['status'] ?? ''),
                'campo' => (string) ($vinculo['fieldType'] ?? ''),
                'nivel' => 'Campanha',
            ];

            switch ((string) ($vinculo['fieldType'] ?? '')) {
                case 'AD_IMAGE':
                case 'BUSINESS_LOGO':
                case 'LOGO':
                case 'LANDSCAPE_LOGO':
                    $lida['recursos']['imagens'][] = $base + [
                        'nome' => (string) ($asset['name'] ?? ''),
                        'url' => (string) data_get($asset, 'imageAsset.fullSize.url', ''),
                        'proxy_url' => route('admin.google-ads.asset.imagem', ['conta' => $conta->id, 'asset' => $assetId]),
                        'largura' => (int) data_get($asset, 'imageAsset.fullSize.widthPixels', 0),
                        'altura' => (int) data_get($asset, 'imageAsset.fullSize.heightPixels', 0),
                        'tipo_rotulo' => in_array($vinculo['fieldType'], ['BUSINESS_LOGO', 'LOGO', 'LANDSCAPE_LOGO'], true) ? 'Logotipo' : 'Imagem de anúncio',
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
                case 'BUSINESS_NAME':
                    $lida['recursos']['empresa'] = $base + [
                        'nome' => (string) data_get($asset, 'textAsset.text', ''),
                    ];
                    break;
            }
        }

        // Lê também recursos vinculados aos grupos de anúncios da campanha (AdGroupAsset)
        try {
            $gaqlAg = "SELECT campaign.id, ad_group.id, ad_group_asset.resource_name, ad_group_asset.field_type, ad_group_asset.status, asset.id, asset.name, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels FROM ad_group_asset WHERE campaign.id = {$campanhaId} AND ad_group_asset.field_type IN ('AD_IMAGE', 'BUSINESS_LOGO', 'LOGO') AND ad_group_asset.status != 'REMOVED'";
            foreach ($this->consultar($conta, $gaqlAg, 'recursos.adgroup.ler') as $linha) {
                $vinculo = (array) ($linha['adGroupAsset'] ?? []);
                $asset = (array) ($linha['asset'] ?? []);
                $assetId = (string) ($asset['id'] ?? '');

                $lida['recursos']['imagens'][] = [
                    'vinculo' => (string) ($vinculo['resourceName'] ?? ''),
                    'asset_id' => $assetId,
                    'status' => (string) ($vinculo['status'] ?? ''),
                    'campo' => (string) ($vinculo['fieldType'] ?? ''),
                    'nivel' => 'Grupo de Anúncios',
                    'nome' => (string) ($asset['name'] ?? ''),
                    'url' => (string) data_get($asset, 'imageAsset.fullSize.url', ''),
                    'proxy_url' => route('admin.google-ads.asset.imagem', ['conta' => $conta->id, 'asset' => $assetId]),
                    'largura' => (int) data_get($asset, 'imageAsset.fullSize.widthPixels', 0),
                    'altura' => (int) data_get($asset, 'imageAsset.fullSize.heightPixels', 0),
                    'tipo_rotulo' => in_array($vinculo['fieldType'] ?? '', ['BUSINESS_LOGO', 'LOGO'], true) ? 'Logotipo (Grupo)' : 'Imagem (Grupo)',
                ];
            }
        } catch (\Throwable) {
            // Se a consulta de ad_group_asset falhar, segue com os recursos da campanha
        }

        // Lê também os recursos visuais de nível de conta (CustomerAsset - Logotipo/Imagens)
        try {
            $gaqlCust = "SELECT customer_asset.resource_name, customer_asset.field_type, customer_asset.status, asset.id, asset.name, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels FROM customer_asset WHERE customer_asset.field_type IN ('BUSINESS_LOGO', 'LOGO', 'AD_IMAGE') AND customer_asset.status != 'REMOVED'";
            foreach ($this->consultar($conta, $gaqlCust, 'recursos.customer.ler') as $linha) {
                $vinculo = (array) ($linha['customerAsset'] ?? []);
                $asset = (array) ($linha['asset'] ?? []);
                $assetId = (string) ($asset['id'] ?? '');

                $jaExiste = false;
                foreach ($lida['recursos']['imagens'] as $img) {
                    if ($img['asset_id'] === $assetId) {
                        $jaExiste = true;
                        break;
                    }
                }

                if (! $jaExiste && $assetId !== '') {
                    $lida['recursos']['imagens'][] = [
                        'vinculo' => (string) ($vinculo['resourceName'] ?? ''),
                        'asset_id' => $assetId,
                        'status' => (string) ($vinculo['status'] ?? ''),
                        'campo' => (string) ($vinculo['fieldType'] ?? ''),
                        'nivel' => 'Conta inteira',
                        'nome' => (string) ($asset['name'] ?? ''),
                        'url' => (string) data_get($asset, 'imageAsset.fullSize.url', ''),
                        'proxy_url' => route('admin.google-ads.asset.imagem', ['conta' => $conta->id, 'asset' => $assetId]),
                        'largura' => (int) data_get($asset, 'imageAsset.fullSize.widthPixels', 0),
                        'altura' => (int) data_get($asset, 'imageAsset.fullSize.heightPixels', 0),
                        'tipo_rotulo' => in_array($vinculo['fieldType'] ?? '', ['BUSINESS_LOGO', 'LOGO'], true) ? 'Logotipo (Conta)' : 'Imagem (Conta)',
                    ];
                }
            }
        } catch (\Throwable) {
            // segue
        }

        // Lê a biblioteca de imagens da conta no Google Ads para permitir visualização e vínculo com 1 clique
        try {
            $gaqlLib = "SELECT asset.id, asset.name, asset.type, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels FROM asset WHERE asset.type = 'IMAGE' ORDER BY asset.id DESC LIMIT 30";
            foreach ($this->consultar($conta, $gaqlLib, 'recursos.biblioteca.ler') as $linha) {
                $asset = (array) ($linha['asset'] ?? []);
                $assetId = (string) ($asset['id'] ?? '');

                if ($assetId !== '') {
                    $lida['recursos']['biblioteca'][] = [
                        'asset_id' => $assetId,
                        'resource' => (string) ($asset['resourceName'] ?? 'customers/'.$conta->customer_id.'/assets/'.$assetId),
                        'nome' => (string) ($asset['name'] ?? 'Imagem #'.$assetId),
                        'url' => (string) data_get($asset, 'imageAsset.fullSize.url', ''),
                        'proxy_url' => route('admin.google-ads.asset.imagem', ['conta' => $conta->id, 'asset' => $assetId]),
                        'largura' => (int) data_get($asset, 'imageAsset.fullSize.widthPixels', 0),
                        'altura' => (int) data_get($asset, 'imageAsset.fullSize.heightPixels', 0),
                    ];
                }
            }
        } catch (\Throwable) {
            // segue
        }
    }

    /**
     * Lista campanhas de pesquisa da conta com seus respectivos grupos de anúncios para publicação direta de RSA.
     *
     * @return array<int, array{id:string, nome:string, status:string, status_rotulo:string, grupos:array<int, array{id:string, resource:string, nome:string, status:string}>}>
     */
    public function listarCampanhasEGrupos(GoogleAdsAccount $conta): array
    {
        $this->prepararEscrita($conta);

        $campanhas = [];

        foreach ($this->consultar($conta, "SELECT campaign.id, campaign.name, campaign.status FROM campaign WHERE campaign.status != 'REMOVED' ORDER BY campaign.id DESC", 'criativo.campanhas.ler') as $linha) {
            $campanha = (array) ($linha['campaign'] ?? []);
            $id = (string) ($campanha['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $status = (string) ($campanha['status'] ?? 'UNKNOWN');
            $campanhas[$id] = [
                'id' => $id,
                'nome' => (string) ($campanha['name'] ?? 'Campanha #'.$id),
                'status' => $status,
                'status_rotulo' => $this->rotulo($status),
                'grupos' => [],
            ];
        }

        if ($campanhas === []) {
            return [];
        }

        foreach ($this->consultar($conta, "SELECT campaign.id, ad_group.id, ad_group.resource_name, ad_group.name, ad_group.status FROM ad_group WHERE ad_group.status != 'REMOVED' ORDER BY ad_group.id", 'criativo.grupos.ler') as $linha) {
            $campanhaId = (string) data_get($linha, 'campaign.id', '');
            if (! isset($campanhas[$campanhaId])) {
                continue;
            }
            $grupo = (array) ($linha['adGroup'] ?? []);
            $grupoId = (string) ($grupo['id'] ?? '');
            $resource = (string) ($grupo['resourceName'] ?? '');
            if ($grupoId === '' || $resource === '') {
                continue;
            }
            $campanhas[$campanhaId]['grupos'][] = [
                'id' => $grupoId,
                'resource' => $resource,
                'nome' => (string) ($grupo['name'] ?? 'Grupo #'.$grupoId),
                'status' => (string) ($grupo['status'] ?? 'UNKNOWN'),
            ];
        }

        return array_values(array_filter(
            $campanhas,
            static fn (array $campanha): bool => $campanha['grupos'] !== []
        ));
    }

    /**
     * Publica um novo anúncio responsivo de pesquisa (RSA) diretamente em um grupo de anúncios existente.
     *
     * @param  array{url: string, titulos: array<int, string>, descricoes: array<int, string>, caminhos?: array<int, string>}  $anuncio
     */
    public function publicarAnuncioEmGrupo(GoogleAdsAccount $conta, string $grupoResource, array $anuncio, bool $pausado = true): string
    {
        $this->prepararEscrita($conta);
        $customer = 'customers/'.$conta->customer_id;

        $resposta = $this->chamar($conta, $customer.'/adGroupAds:mutate', [
            'operations' => [['create' => [
                'adGroup' => $grupoResource,
                'status' => $pausado ? 'PAUSED' : 'ENABLED',
                'ad' => self::anuncioResponsivo(
                    (string) $anuncio['url'],
                    (array) $anuncio['titulos'],
                    (array) $anuncio['descricoes'],
                    (array) ($anuncio['caminhos'] ?? [])
                ),
            ]]],
        ], 'criativo.anuncio.publicar');

        $resource = (string) data_get($resposta, 'results.0.resourceName', '');

        if ($resource === '') {
            throw new GoogleAdsFalhou('O Google aceitou o envio mas não retornou o identificador do anúncio.');
        }

        return $resource;
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
