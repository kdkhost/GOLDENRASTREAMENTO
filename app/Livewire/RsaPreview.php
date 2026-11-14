<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

/**
 * O editor e o mock do anúncio responsivo (RSA) do Google Ads.
 *
 * O Google não tem API que devolve uma prévia do anúncio: quem escreve o criativo decide no
 * escuro e só vê o resultado depois de aprovado. Esta tela fecha esse buraco com o que o
 * mercado faz — um mock em HTML do que a busca desenha, com as três combinações de título e
 * descrição que o próprio Google sorteia entre as opções aceitas.
 *
 * As travas não são preferências daqui: quinze títulos de até 30 caracteres, quatro descrições
 * de até 90, duas rotas de display de até 15. Entraram em `config('google_ads.rsa')`, e o
 * contador e o preview leem da mesma lista — se o Google mudar um número, muda um lugar.
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

    public function render(): View
    {
        $limites = config('google_ads.rsa');
        $faltam = $this->faltas($limites);

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
                // O celular corta a descrição em volta dos 70-90 caracteres na prévia compacta.
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

        return collect($faltas)->unique()->values()->all();
    }
}
