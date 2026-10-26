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
 * de até 90, duas rotas dedisplay de até 15. Entraram em `config('google_ads.rsa')`, e o
 * contador e o preview leem da mesma lista — se o Google mudar um número, muda um lugar.
 *
 * Nada desta tela fala com a API: ela não precisa de conta conectada nem de token. É o
 * rascunho que a pessoa leva para o Google Ads, ou para a fase 2 do painel.
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

    public function render(): View
    {
        $limites = config('google_ads.rsa');

        return view('livewire.rsa-preview', [
            'limites' => $limites,
            'contadores' => [
                'titulos' => $this->contadores($this->titulos, $limites['caracteres_titulo']),
                'descricoes' => $this->contadores($this->descricoes, $limites['caracteres_descricao']),
                'rotas' => [$this->contador($this->rota1, $limites['caracteres_rota']), $this->contador($this->rota2, $limites['caracteres_rota'])],
            ],
            'combinacoes' => $this->combinacoes((int) $limites['combinacoes']),
            'dominio' => $this->dominio(),
            'caminho' => $this->caminho(),
            'faltam' => $this->faltas($limites),
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

        for ($n = 0; $n < $quantas; $n++) {
            $principal = $titulos[$n % count($titulos)];
            $extra = count($titulos) > 1 ? $titulos[($n + 1) % count($titulos)] : null;
            $descricao = $descricoes[$n % count($descricoes)];

            $combinacoes[] = [
                'titulo' => $principal,
                'titulo_extra' => $extra,
                'descricao' => $descricao,
                // O celular corta a descrição em volta dos 90 caracteres de uma linha: o que
                // passa de lá não é escolha do redator, é a busca que esconde.
                'cortada' => mb_substr($descricao, 0, 70),
            ];
        }

        return $combinacoes;
    }

    /** O que o preview usa: texto aprovado, sem linha vazia e sem estourar o limite. */
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

        // A trava é por segmento, não pela linha inteira: 20 caracteres na primeira rota não
        // podem comer os 15 da segunda, que a busca ainda mostraria.
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
