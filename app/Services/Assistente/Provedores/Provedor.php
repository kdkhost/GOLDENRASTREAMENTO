<?php

namespace App\Services\Assistente\Provedores;

use App\Models\AssistenteApiLog;
use App\Services\Assistente\Chamada;
use App\Services\Assistente\Excecoes\ChamadaFalhou;
use App\Services\Assistente\Midia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * O que todo provedor de IA tem em comum: uma chave, um endereço, um prazo e uma caixa-preta.
 *
 * Esta classe não decide modelo nem capacidade — isso é da `Fabrica`, que lê a escolha do painel.
 * Ela só tira dos filhos o trabalho de sempre: montar o endereço, respeitar o prazo que a
 * hospedagem permite, ler o arquivo do disco público e **registrar a chamada**.
 *
 * O registro é a parte que ninguém deve errar. Mídia nunca entra em claro no log: um PDF de
 * 8 MB em base64 são 11 milhões de caracteres numa coluna de texto, e a equipe não precisa ver o
 * arquivo para auditar a resposta — precisa ver que era um PDF de 231 KB chamado `nota.pdf`, que
 * continua no disco e tem endereço na mensagem.
 */
abstract class Provedor
{
    public function __construct(protected string $chave, protected string $modelo) {}

    /** O código do provedor nas listas de config — `gemini`, `openai`. */
    abstract public static function id(): string;

    public function rotulo(): string
    {
        return (string) (config('assistente.provedores')[static::id()]['rotulo'] ?? static::id());
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    protected function base(): string
    {
        return rtrim((string) (config('assistente.provedores')[static::id()]['base'] ?? ''), '/');
    }

    protected function segundosDe(string $capacidade): int
    {
        return (int) (config('assistente.capacidades')[$capacidade]['segundos'] ?? 45);
    }

    /** O arquivo que o chat mandou, em bytes crus, lido do disco público. */
    protected function ler(string $caminho): string
    {
        $conteudo = (string) Storage::disk('public')->get($caminho);

        return $conteudo;
    }

    protected function tamanhoDe(string $caminho): int
    {
        return (int) Storage::disk('public')->size($caminho);
    }

    /**
     * A marca da mídia para o log: nome, tipo e tamanho. É o que a coluna `request_json` guarda
     * no lugar do arquivo — o resto já está em `conversas_mensagens`, com endereço.
     *
     * @param  array<int, array{caminho: string, mime: string, nome?: string}>  $pecas
     * @return array<int, array{nome: string, mime: string, bytes: int}>
     */
    protected function marcasDe(array $pecas): array
    {
        return array_map(fn (array $peca): array => [
            'nome' => (string) ($peca['nome'] ?? basename($peca['caminho'])),
            'mime' => (string) $peca['mime'],
            'bytes' => $this->tamanhoDe($peca['caminho']),
            'descricao' => $peca['descricao'] ?? null,
        ], $pecas);
    }

    /**
     * A linha da caixa-preta. Escrever log não pode derrubar atendimento: se a tabela falhou, a
     * conversa continua — o erro de log se perde em vez de virar a tela quebrada para o cliente.
     */
    protected function registrar(
        Chamada $chamada,
        string $capacidade,
        array $pedido,
        ?array $resposta = null,
        ?string $erro = null,
        int $duracaoMs = 0,
        ?int $tokensEntrada = null,
        ?int $tokensSaida = null,
        int $audioBytes = 0,
    ): void {
        try {
            AssistenteApiLog::create([
                'capacidade' => $capacidade,
                'provedor' => static::id(),
                'modelo' => $this->modelo,
                'user_id' => $chamada->userId,
                'conversa_id' => $chamada->conversaId,
                'request_json' => $this->podar($pedido),
                'response_json' => $this->podar($resposta ?? ['audio_bytes' => $audioBytes]),
                'error_message' => $erro === null ? null : mb_substr($erro, 0, 1000),
                'duracao_ms' => $duracaoMs,
                'tokens_entrada' => $tokensEntrada,
                'tokens_saida' => $tokensSaida,
                'audio_bytes' => $audioBytes ?: null,
            ]);
        } catch (Throwable) {
            // Sem log a conversa segue; a caixa-preta é para depois, não é o atendimento.
        }
    }

    /**
     * Guarda o corpo da chamada sem estourar a coluna: texto longo vira princípio mais tamanho.
     * Uma transcrição de 40 páginas não precisa ser lida duas vezes no painel para se auditar.
     */
    private function podar(array $bruto): array
    {
        array_walk_recursive($bruto, function (mixed &$valor): void {
            if (is_string($valor) && mb_strlen($valor) > 900) {
                $valor = mb_substr($valor, 0, 300).' … ['.mb_strlen($valor).' caracteres]';
            }
        });

        return $bruto;
    }

    /**
     * POST com o prazo da capacidade. Devolve o corpo em array quando o provedor respondeu 2xx e
     * atira `ChamadaFalhou` com o texto dele quando não — é esse texto que resolve a discussão.
     */
    protected function post(string $url, array $corpo, string $capacidade, array $cabecalhos = []): array
    {
        $resposta = Http::withHeaders($cabecalhos)
            ->timeout($this->segundosDe($capacidade))
            ->post($url, $corpo);

        if ($resposta->failed()) {
            throw new ChamadaFalhou(
                $this->rotulo().' respondeu '.$resposta->status().': '.$resposta->body(),
                $resposta->json(),
                $resposta->status(),
            );
        }

        return (array) $resposta->json();
    }

    /** Igual ao `post()`, mas a resposta é binária — é assim que chega a voz de um provedor. */
    protected function postPorBytes(string $url, array $corpo, string $capacidade, Chamada $chamada, array $cabecalhos = []): string
    {
        $inicio = microtime(true);
        $resposta = Http::withHeaders($cabecalhos)
            ->timeout($this->segundosDe($capacidade))
            ->post($url, $corpo);

        if ($resposta->failed()) {
            $erro = $this->rotulo().' respondeu '.$resposta->status().': '.$resposta->body();
            $this->registrar($chamada, $capacidade, ['corpo' => $corpo], null, $erro, $this->decorrido($inicio));

            throw new ChamadaFalhou($erro, null, $resposta->status());
        }

        $bytes = $resposta->body();
        $limite = Midia::bytesDaRespostaEmAudio();

        if (strlen($bytes) > $limite) {
            throw new ChamadaFalhou($this->rotulo().' devolveu áudio maior que os '.$limite.' bytes que a casa aceita.');
        }

        return $bytes;
    }

    /**
     * O `data:` URI que alguns provedores usam para receber arquivo dentro do JSON: o tipo vem
     * antes da vírgula, o base64 depois — nada de arquivo em claro no log, porque o log guarda a
     * marca do arquivo, não o corpo.
     */
    protected function uriDeDados(string $caminho, string $mime): string
    {
        return $mime.';base64,'.base64_encode($this->ler($caminho));
    }

    /**
     * A pergunta com o contexto em cima: o que a mídia mostrou e as fontes de conhecimento — a
     * lista que autoriza o assistente a responder. Os dois provedores montam a mesma coisa, então
     * a montagem é da classe base, e um provedor novo herda o texto em vez de reescrevê-lo.
     */
    protected function textoDaPergunta(Chamada $chamada): string
    {
        $contexto = $chamada->contextoDeMidia();

        return implode("\n\n", array_filter([
            $contexto !== '' ? "O que a mídia enviada mostra:\n".$contexto : '',
            $chamada->fontes !== [] ? $this->blocoDeFontes($chamada->fontes) : '',
            $chamada->pergunta,
        ]));
    }

    /**
     * As fontes entram numeradas, com procedência e data, porque é isto que transforma "responda"
     * em "responda só com o que está aqui". O número é o que a resposta cita de volta quando o
     * painel quer conferir.
     */
    protected function blocoDeFontes(array $fontes): string
    {
        $linhas = ['Fontes do conhecimento da empresa (use somente isto):'];

        foreach (array_values($fontes) as $i => $fonte) {
            $texto = preg_replace('/\s+/', ' ', (string) ($fonte['texto'] ?? ''));
            $linhas[] = '['.($i + 1).'] '.$fonte['titulo'].' — '.$texto.' (fonte: '.$fonte['procedencia'].')';
        }

        return implode("\n", $linhas);
    }

    /** A voz chega como `provedor:nome`; cada API só quer o nome. */
    protected function vozDaChamada(Chamada $chamada): string
    {
        $voz = (string) ($chamada->voz ?: config('assistente.voz_padrao'));

        return str_contains($voz, ':') ? explode(':', $voz, 2)[1] : $voz;
    }

    protected function decorrido(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }

    /**
     * O pedido como o log o guarda: as instruções, o histórico curto, a pergunta, os títulos das
     * fontes e a **marca** das mídias. Nenhum provedor escreve esta lista — é uma só para todos, e
     * é o que torna comparável uma chamada de janeiro com uma de dezembro.
     */
    protected function pedidoLogavel(Chamada $chamada): array
    {
        return [
            'modelo' => $this->modelo,
            'instrucoes' => $chamada->ordem(),
            'historico' => array_slice($chamada->historico, -6),
            'pergunta' => $chamada->pergunta,
            'fontes' => array_column($chamada->fontes, 'titulo'),
            'imagens' => $this->marcasDe($chamada->imagens),
            'documentos' => $this->marcasDe($chamada->documentos),
            'audios' => $this->marcasDe($chamada->audios),
        ];
    }
}
