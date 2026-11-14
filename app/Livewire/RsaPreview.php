<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAds\Excecoes\GoogleAdsFalhou;
use App\Services\GoogleAds\GoogleAdsEditor;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Estúdio e publicador de anúncio responsivo (RSA) do Google Ads.
 *
 * Além de validar limites de caracteres em tempo real e desenhar a prévia fiel da busca para
 * desktop e celular, permite publicar o anúncio diretamente em um grupo de anúncios de qualquer
 * campanha ativa/pausada da conta conectada no Google Ads.
 */
class RsaPreview extends Component
{
    /** @var array<int, string> os títulos aceitos pelo Google no RSA (até 15) */
    public array $titulos = ['', '', ''];

    /** @var array<int, string> as descrições (até 4) */
    public array $descricoes = [''];

    public string $url_final = '';

    public string $rota1 = '';

    public string $rota2 = '';

    /** 'desktop' ou 'celular': a busca corta o anúncio de um jeito em cada tela. */
    public string $dispositivo = 'desktop';

    /** No desktop a primeira linha da descrição aparece sozinha; "mais" abre o resto. */
    public bool $descricao_aberta = false;

    /** Semente para rotacionar as combinações exibidas na prévia. */
    public int $semente = 0;

    /** Destino real de publicação no Google Ads. */
    public ?int $contaId = null;

    public string $campanhaId = '';

    public string $grupoResource = '';

    public string $statusPublicacao = 'PAUSED';

    /** @var array<int, array{id:int, nome:string}> */
    public array $contasDisponiveis = [];

    /** @var array<int, array{id:string, nome:string, status:string, status_rotulo:string, grupos:array<int, array{id:string, resource:string, nome:string, status:string}>}> */
    public array $campanhasDisponiveis = [];

    public string $erroDestinos = '';

    public string $erroPublicacao = '';

    public string $sucessoPublicacao = '';

    public string $publicadoCampanhaUrl = '';

    public function mount(): void
    {
        $usuario = auth()->user();

        if ($usuario === null) {
            return;
        }

        $contas = GoogleAdsAccount::visiveisPara($usuario)
            ->comCampanhas()
            ->where('status', GoogleAdsAccount::ATIVO)
            ->orderByDesc('id')
            ->get();

        $this->contasDisponiveis = $contas->map(fn (GoogleAdsAccount $conta): array => [
            'id' => $conta->id,
            'nome' => $conta->nomeTela().' ('.$conta->numeroConta().')',
        ])->values()->all();

        if ($this->contasDisponiveis !== []) {
            $this->contaId = $this->contasDisponiveis[0]['id'];
            $this->carregarCampanhas();
        }
    }

    public function updatedContaId(): void
    {
        $this->campanhaId = '';
        $this->grupoResource = '';
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';
        $this->carregarCampanhas();
    }

    public function updatedCampanhaId(): void
    {
        $this->grupoResource = '';
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';

        $campanha = collect($this->campanhasDisponiveis)->firstWhere('id', $this->campanhaId);
        if (is_array($campanha) && ! empty($campanha['grupos'])) {
            $this->grupoResource = (string) $campanha['grupos'][0]['resource'];
        }
    }

    public function recarregarDestinos(): void
    {
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';
        $this->carregarCampanhas();
    }

    public function render(): View
    {
        $limites = config('google_ads.rsa');
        $faltam = $this->faltas($limites);
        $campanhaSelecionada = collect($this->campanhasDisponiveis)->firstWhere('id', $this->campanhaId);
        $gruposDisponiveis = is_array($campanhaSelecionada) ? ($campanhaSelecionada['grupos'] ?? []) : [];

        return view('livewire.rsa-preview', [
            'limites' => $limites,
            'contadores' => [
                'titulos' => $this->contadores($this->titulos, (int) $limites['caracteres_titulo']),
                'descricoes' => $this->contadores($this->descricoes, (int) $limites['caracteres_descricao']),
                'rotas' => [
                    $this->contador($this->rota1, (int) $limites['caracteres_rota']),
                    $this->contador($this->rota2, (int) $limites['caracteres_rota']),
                ],
            ],
            'combinacoes' => $this->combinacoes((int) $limites['combinacoes']),
            'dominio' => $this->dominio(),
            'caminho' => $this->caminho(),
            'faltam' => $faltam,
            'forca' => $this->forcaDoAnuncio($limites, $faltam),
            'textoExportacao' => $this->textoExportacao(),
            'modelos' => $this->modelosDisponiveis(),
            'gruposDisponiveis' => $gruposDisponiveis,
        ]);
    }

    public function adicionarTitulo(): void
    {
        if (count($this->titulos) < (int) config('google_ads.rsa.titulos_max')) {
            $this->titulos[] = '';
        }
    }

    public function adicionarDescricao(): void
    {
        if (count($this->descricoes) < (int) config('google_ads.rsa.descricoes_max')) {
            $this->descricoes[] = '';
        }
    }

    /** Tira a linha e mantém as de cima numeradas: se sobrar só uma vazia, o Google exige três. */
    public function removerTitulo(int $posicao): void
    {
        unset($this->titulos[$posicao]);

        $this->titulos = array_values($this->titulos);

        if ($this->titulos === []) {
            $this->titulos = ['', '', ''];
        }
    }

    public function removerDescricao(int $posicao): void
    {
        unset($this->descricoes[$posicao]);

        $this->descricoes = array_values($this->descricoes);

        if ($this->descricoes === []) {
            $this->descricoes = [''];
        }
    }

    public function trocarDispositivo(string $para): void
    {
        $this->dispositivo = $para === 'celular' ? 'celular' : 'desktop';
    }

    public function abrirDescricao(): void
    {
        $this->descricao_aberta = ! $this->descricao_aberta;
    }

    public function sortearCombinacoes(): void
    {
        $this->semente++;
    }

    public function aplicarModelo(string $chave): void
    {
        $modelos = $this->modelosDisponiveis();

        if (! isset($modelos[$chave])) {
            return;
        }

        $modelo = $modelos[$chave];
        $this->titulos = $modelo['titulos'];
        $this->descricoes = $modelo['descricoes'];
        $this->url_final = $modelo['url_final'];
        $this->rota1 = $modelo['rota1'];
        $this->rota2 = $modelo['rota2'];
        $this->semente = 0;
        $this->descricao_aberta = false;
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';
    }

    public function limparRascunho(): void
    {
        $this->titulos = ['', '', ''];
        $this->descricoes = [''];
        $this->url_final = '';
        $this->rota1 = '';
        $this->rota2 = '';
        $this->semente = 0;
        $this->descricao_aberta = false;
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';
    }

    /**
     * Publica o anúncio responsivo diretamente no grupo de anúncios selecionado no Google Ads.
     */
    public function publicarNoGoogleAds(GoogleAdsEditor $editor): void
    {
        $this->erroPublicacao = '';
        $this->sucessoPublicacao = '';
        $this->publicadoCampanhaUrl = '';

        $limites = config('google_ads.rsa');
        $faltam = $this->faltas($limites);

        if ($faltam !== []) {
            $this->erroPublicacao = 'Corrija os requisitos pendentes do anúncio antes de publicar: '.implode(' ', $faltam);

            return;
        }

        $urlFinal = trim($this->url_final);
        if ($urlFinal === '' || filter_var($urlFinal, FILTER_VALIDATE_URL) === false || ! str_starts_with(strtolower($urlFinal), 'http')) {
            $this->erroPublicacao = 'Informe uma URL final válida começando com https:// ou http://.';

            return;
        }

        $usuario = auth()->user();
        if ($usuario === null || $this->contaId === null) {
            $this->erroPublicacao = 'Conecte e selecione uma conta ativa do Google Ads para publicar.';

            return;
        }

        $conta = GoogleAdsAccount::visiveisPara($usuario)
            ->comCampanhas()
            ->where('status', GoogleAdsAccount::ATIVO)
            ->find($this->contaId);

        if ($conta === null) {
            $this->erroPublicacao = 'A conta do Google Ads selecionada não está mais disponível ou ativa.';

            return;
        }

        if ($this->campanhaId === '' || $this->grupoResource === '') {
            $this->erroPublicacao = 'Selecione a campanha e o grupo de anúncios de destino.';

            return;
        }

        $titulos = $this->opcionaveis($this->titulos);
        $descricoes = $this->opcionaveis($this->descricoes);
        $caminhos = array_values(array_filter([
            mb_substr(trim($this->rota1, ' /'), 0, (int) $limites['caracteres_rota']),
            mb_substr(trim($this->rota2, ' /'), 0, (int) $limites['caracteres_rota']),
        ], static fn (string $r): bool => $r !== ''));

        $pausado = $this->statusPublicacao !== 'ENABLED';

        try {
            $resourceCriado = $editor->publicarAnuncioEmGrupo($conta, $this->grupoResource, [
                'url' => $urlFinal,
                'titulos' => $titulos,
                'descricoes' => $descricoes,
                'caminhos' => $caminhos,
            ], $pausado);

            Activity::log(
                'google_ads_anuncio_publicado',
                'Anúncio responsivo publicado via Estúdio RSA na campanha #'.$this->campanhaId.' ('.($pausado ? 'Pausado' : 'Ativo').').',
                $conta,
                [
                    'campaign_id' => $this->campanhaId,
                    'ad_group_resource' => $this->grupoResource,
                    'ad_group_ad_resource' => $resourceCriado,
                    'status' => $pausado ? 'PAUSED' : 'ENABLED',
                ]
            );

            $this->sucessoPublicacao = 'Anúncio publicado com sucesso no Google Ads como '.($pausado ? 'Pausado' : 'Ativo').'!';
            $this->publicadoCampanhaUrl = route('admin.google-ads.campanhas.show', [
                'campanha' => $this->campanhaId,
                'conta' => $conta->id,
            ]);
        } catch (GoogleAdsFalhou $erro) {
            $this->erroPublicacao = $erro->getMessage();
        } catch (Throwable $erro) {
            $this->erroPublicacao = 'Não foi possível publicar o anúncio no Google Ads: '.$erro->getMessage();
        }
    }

    private function carregarCampanhas(): void
    {
        $this->erroDestinos = '';
        $this->campanhasDisponiveis = [];

        $usuario = auth()->user();
        if ($usuario === null || $this->contaId === null) {
            return;
        }

        $conta = GoogleAdsAccount::visiveisPara($usuario)
            ->comCampanhas()
            ->where('status', GoogleAdsAccount::ATIVO)
            ->find($this->contaId);

        if ($conta === null) {
            return;
        }

        try {
            $this->campanhasDisponiveis = app(GoogleAdsEditor::class)->listarCampanhasEGrupos($conta);

            if ($this->campanhasDisponiveis !== []) {
                $this->campanhaId = (string) $this->campanhasDisponiveis[0]['id'];
                $this->grupoResource = (string) ($this->campanhasDisponiveis[0]['grupos'][0]['resource'] ?? '');
            }
        } catch (Throwable $erro) {
            $this->erroDestinos = 'Não foi possível listar campanhas e grupos desta conta agora: '.$erro->getMessage();
        }
    }

    /**
     * As combinações que a busca pode montar. Uma por linha, rodando as listas: é assim que o
     * Google mistura as opções aceitas — e é por isso que todo título precisa fazer sentido
     * sozinho, e não só em pares.
     *
     * @return array<int, array{titulo:string, titulo_extra:?string, descricao:string, cortada:string}>
     */
    private function combinacoes(int $quantas): array
    {
        $titulos = $this->opcionaveis($this->titulos);
        $descricoes = $this->opcionaveis($this->descricoes);

        if ($titulos === [] || $descricoes === []) {
            return [];
        }

        $combinacoes = [];
        $totalTitulos = count($titulos);
        $totalDescricoes = count($descricoes);
        $deslocamento = $this->semente;

        for ($n = 0; $n < $quantas; $n++) {
            $idxTitulo = ($n + $deslocamento) % $totalTitulos;
            $idxExtra = ($n + 1 + ($deslocamento * 2)) % $totalTitulos;
            if ($totalTitulos > 1 && $idxExtra === $idxTitulo) {
                $idxExtra = ($idxTitulo + 1) % $totalTitulos;
            }

            $principal = $titulos[$idxTitulo];
            $extra = $totalTitulos > 1 ? $titulos[$idxExtra] : null;
            $descricao = $descricoes[($n + $deslocamento) % $totalDescricoes];

            $combinacoes[] = [
                'titulo' => $principal,
                'titulo_extra' => $extra,
                'descricao' => $descricao,
                'cortada' => mb_substr($descricao, 0, 70),
            ];
        }

        return $combinacoes;
    }

    /**
     * Calcula a força estimada do anúncio responsivo com base nas diretrizes do Google Ads.
     *
     * @param  array<string, mixed>  $limites
     * @param  array<int, string>  $faltam
     * @return array{percentual:int, rotulo:string, tom:string, dica:string}
     */
    private function forcaDoAnuncio(array $limites, array $faltam): array
    {
        $titulosValidos = count(array_filter(
            $this->opcionaveis($this->titulos),
            fn (string $texto): bool => mb_strlen($texto) <= (int) $limites['caracteres_titulo']
        ));
        $descricoesValidas = count(array_filter(
            $this->opcionaveis($this->descricoes),
            fn (string $texto): bool => mb_strlen($texto) <= (int) $limites['caracteres_descricao']
        ));

        $pontos = 0;
        if ($titulosValidos >= 3) {
            $pontos += 25;
        }
        if ($titulosValidos >= 6) {
            $pontos += 20;
        }
        if ($titulosValidos >= 10) {
            $pontos += 15;
        }
        if ($descricoesValidas >= 1) {
            $pontos += 15;
        }
        if ($descricoesValidas >= 2) {
            $pontos += 15;
        }
        if ($descricoesValidas >= 3) {
            $pontos += 10;
        }

        if ($faltam !== [] && $pontos > 40) {
            $pontos = 40;
        }

        if ($faltam !== []) {
            return [
                'percentual' => $pontos,
                'rotulo' => 'Incompleto',
                'tom' => 'amber',
                'dica' => 'Corrija os itens pendentes abaixo para liberar a publicação.',
            ];
        }

        if ($pontos >= 85) {
            return [
                'percentual' => 100,
                'rotulo' => 'Excelente',
                'tom' => 'green',
                'dica' => 'Ótima variedade de títulos e descrições para o algoritmo testar.',
            ];
        }

        if ($pontos >= 60) {
            return [
                'percentual' => $pontos,
                'rotulo' => 'Bom',
                'tom' => 'green',
                'dica' => 'Adicione mais títulos (meta: 10+) ou descrições para chegar a Excelente.',
            ];
        }

        return [
            'percentual' => $pontos,
            'rotulo' => 'Razoável',
            'tom' => 'amber',
            'dica' => 'Passa nas travas mínimas, mas incluir 6+ títulos melhora a entrega.',
        ];
    }

    /**
     * Monta texto pronto para colar no Google Ads ou enviar para aprovação.
     */
    private function textoExportacao(): string
    {
        $titulos = $this->opcionaveis($this->titulos);
        $descricoes = $this->opcionaveis($this->descricoes);
        $linhas = [];

        if ($this->url_final !== '') {
            $linhas[] = 'URL FINAL: '.trim($this->url_final);
        }

        $caminho = $this->caminho();
        if ($caminho !== '') {
            $linhas[] = 'ROTAS: '.$this->dominio().'/'.$caminho;
        }

        if ($titulos !== []) {
            $linhas[] = "\nTÍTULOS:";
            foreach ($titulos as $i => $titulo) {
                $linhas[] = ($i + 1).'. '.$titulo;
            }
        }

        if ($descricoes !== []) {
            $linhas[] = "\nDESCRIÇÕES:";
            foreach ($descricoes as $i => $descricao) {
                $linhas[] = ($i + 1).'. '.$descricao;
            }
        }

        return implode("\n", $linhas);
    }

    /**
     * Modelos prontos específicos para Golden Rastreamento.
     *
     * @return array<string, array{nome:string, icone:string, url_final:string, rota1:string, rota2:string, titulos:array<int, string>, descricoes:array<int, string>}>
     */
    private function modelosDisponiveis(): array
    {
        return [
            'veicular' => [
                'nome' => 'Rastreamento Veicular',
                'icone' => 'fa-car',
                'url_final' => 'https://goldenrastreamento.com.br/loja',
                'rota1' => 'rastreamento',
                'rota2' => '24h',
                'titulos' => [
                    'Rastreamento Veicular 24h',
                    'Golden Rastreamento Oficial',
                    'Bloqueio Remoto pelo App',
                    'Instalação Rápida e Segura',
                    'Proteja Seu Carro Hoje',
                    'Central 24h em Todo Brasil',
                    'Sem Burocracia na Adesão',
                    'Planos a Partir de R$ 49,90',
                ],
                'descricoes' => [
                    'Acompanhe seu veículo em tempo real pelo celular com alertas e bloqueio imediato.',
                    'Tecnologia 4G com central 24h para carro, moto e utilitário. Peça sua instalação hoje.',
                    'Histórico de rotas, cerca eletrônica e suporte humano sempre que você precisar.',
                ],
            ],
            'frota' => [
                'nome' => 'Gestão de Frotas B2B',
                'icone' => 'fa-truck',
                'url_final' => 'https://goldenrastreamento.com.br/para-empresas',
                'rota1' => 'frotas',
                'rota2' => 'empresas',
                'titulos' => [
                    'Gestão de Frotas em Tempo Real',
                    'Reduza Custos da Sua Frota',
                    'Controle de Rotas e Paradas',
                    'Rastreador para Caminhões',
                    'Relatórios Completos no Painel',
                    'Solicite Proposta Comercial',
                    'Suporte Dedicado para Empresas',
                ],
                'descricoes' => [
                    'Monitore velocidade, ociosidade e trajetos da sua frota em um só painel web e app.',
                    'Mais segurança para cargas e motoristas com atendimento 24h e instalação ágil.',
                    'Condições especiais para frotas a partir de 3 veículos. Fale com um consultor.',
                ],
            ],
            'moto' => [
                'nome' => 'Rastreador para Motos',
                'icone' => 'fa-motorcycle',
                'url_final' => 'https://goldenrastreamento.com.br/loja',
                'rota1' => 'motos',
                'rota2' => 'protecao',
                'titulos' => [
                    'Rastreador para Moto 24h',
                    'Não Descarrega a Bateria',
                    'Bloqueio Instantâneo no App',
                    'Localização Precisa via GPS',
                    'Ideal para Entregas e Uso Diário',
                    'Instalação Discreta e Rápida',
                ],
                'descricoes' => [
                    'Equipamento compacto à prova d’água com baixo consumo de bateria e alerta de movimento.',
                    'Recupere sua moto com agilidade usando nossa central 24h e bloqueio pelo celular.',
                ],
            ],
        ];
    }

    /** O que o preview usa: texto aprovado, sem linha vazia. */
    private function opcionaveis(array $linhas): array
    {
        return collect($linhas)
            ->map(fn (string $texto): string => trim($texto))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{caracteres:int, tom:string}>
     */
    private function contadores(array $linhas, int $limite): array
    {
        return array_map(fn (string $texto): array => $this->contador($texto, $limite), array_values($linhas));
    }

    /**
     * O semáforo do contador. Verde até faltar um pedaço, laranja quando falta pouco, vermelho
     * quando o Google não aceita — a régua é do limite, não do tamanho do texto.
     *
     * @return array{caracteres:int, tom:string}
     */
    private function contador(string $texto, int $limite): array
    {
        $caracteres = mb_strlen(trim($texto));

        return [
            'caracteres' => $caracteres,
            'tom' => $caracteres > $limite ? 'estourado' : ($caracteres >= (int) ceil($limite * 0.85) ? 'perto' : 'ok'),
        ];
    }

    /** O domínio como a busca o escreve: sem protocolo, sem `www`, sem barra no fim. */
    private function dominio(): string
    {
        $url = trim($this->url_final);

        if ($url === '') {
            return 'exemplo.com.br';
        }

        return preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST))
            ?: preg_replace('#^https?://(www\.)?#i', '', $url) ?: $url;
    }

    /** As rotas de display, com as travas do Google já aplicadas: o que estoura não aparece. */
    private function caminho(): string
    {
        $limite = (int) config('google_ads.rsa.caracteres_rota');

        $rotas = collect([$this->rota1, $this->rota2])
            ->map(fn (string $rota): string => mb_substr(trim($rota, ' /'), 0, $limite))
            ->filter()
            ->take(2)
            ->implode('/');

        return $rotas === '' ? '' : $rotas;
    }

    /**
     * O que ainda falta para o Google aceitar o anúncio. São os mínimos dele: três títulos e
     * uma descrição, e nenhum campo estourado — a tela mostra antes de alguém descobrir no envio.
     *
     * @return array<int, string>
     */
    private function faltas(array $limites): array
    {
        $faltas = [];

        if (count($this->opcionaveis($this->titulos)) < (int) $limites['titulos_min']) {
            $faltas[] = "Escreva pelo menos {$limites['titulos_min']} títulos.";
        }

        if ($this->opcionaveis($this->descricoes) === []) {
            $faltas[] = "Escreva pelo menos {$limites['descricoes_min']} descrição.";
        }

        foreach (['titulos' => 'caracteres_titulo', 'descricoes' => 'caracteres_descricao'] as $campo => $chave) {
            foreach ($this->{$campo} as $linha) {
                if (mb_strlen(trim($linha)) > (int) $limites[$chave]) {
                    $faltas[] = 'Há texto maior do que o Google aceita ('.$limites[$chave].' caracteres).';

                    break;
                }
            }
        }

        foreach (['rota1', 'rota2'] as $rota) {
            if (mb_strlen(trim($this->{$rota})) > (int) $limites['caracteres_rota']) {
                $faltas[] = 'Cada rota de display tem no máximo '.$limites['caracteres_rota'].' caracteres.';

                break;
            }
        }

        // Regras de política do Google Ads para aprovação 100% automática sem reprovação:
        $todosTextos = array_merge($this->opcionaveis($this->titulos), $this->opcionaveis($this->descricoes));
        foreach ($todosTextos as $texto) {
            if (preg_match('/\b[A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{4,}\b/u', $texto)) {
                $faltas[] = 'Evite palavras inteiras em MAIÚSCULAS (o Google reprova por capitalização).';
                break;
            }
        }

        foreach ($todosTextos as $texto) {
            if (preg_match('/[!?\*]{2,}/', $texto) || preg_match('/\b(?:\(?\d{2}\)?\s*)?(?:9?\d{4}[-.\s]?\d{4}|0800[-.\s]?\d{3}[-.\s]?\d{4})\b/', $texto)) {
                $faltas[] = 'Remova pontuação repetida (!!/??) ou telefones do texto (o Google reprova automaticamente).';
                break;
            }
        }

        $titulosLimpos = array_map(static fn (string $t): string => mb_strtolower($t), $this->opcionaveis($this->titulos));
        if (count($titulosLimpos) !== count(array_unique($titulosLimpos))) {
            $faltas[] = 'Há títulos repetidos: varie os textos para manter força Excelente.';
        }

        return collect($faltas)->unique()->values()->all();
    }
}
