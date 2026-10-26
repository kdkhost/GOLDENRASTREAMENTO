<?php

namespace App\Services\Assistente\Provedores;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Contratos\PodeConversar;
use App\Services\Assistente\Contratos\PodeFalar;
use App\Services\Assistente\Contratos\PodeTranscrever;
use App\Services\Assistente\Excecoes\ChamadaFalhou;
use App\Services\Assistente\Excecoes\CapacidadeNaoOferecida;
use App\Services\Assistente\Resposta;
use Illuminate\Support\Facades\Http;

/**
 * A OpenAI como atendente: conversa, vê imagem, transcreve áudio na rota própria e fala com voz.
 *
 * É o provedor de quem já tem chave da OpenAI e quer continuar usando — daí a camada multi-provedor
 * existir: o painel escolhe a capacidade de conversa no OpenAI e a leitura de documento no Gemini,
 * porque uma API de chat não come PDF, e isto é verdade do provedor, não do sistema.
 *
 * As três rotas têm três desenhos, e é por isso que os métodos não se parecem:
 * `/chat/completions` fala JSON, `/audio/transcriptions` recebe multipart e `/audio/speech`
 * devolve bytes. O log, esse, é o mesmo para as três.
 */
class OpenAi extends Provedor implements PodeConversar, PodeTranscrever, PodeFalar
{
    public static function id(): string
    {
        return 'openai';
    }

    public function responder(Chamada $chamada): Resposta
    {
        if ($chamada->documentos !== []) {
            throw new CapacidadeNaoOferecida($this->rotulo(), 'documento');
        }

        if ($chamada->audios !== []) {
            throw new CapacidadeNaoOferecida($this->rotulo(), 'transcricao');
        }

        $capacidade = $chamada->capacidade ?? 'conversa';
        $mensagens = [['role' => 'system', 'content' => $chamada->ordem()]];

        foreach ($chamada->historico as $fala) {
            if ($fala['autor'] === 'sistema' || blank($fala['texto'])) {
                continue;
            }

            $mensagens[] = [
                'role' => $fala['autor'] === 'assistente' ? 'assistant' : 'user',
                'content' => (string) $fala['texto'],
            ];
        }

        $mensagens[] = ['role' => 'user', 'content' => $this->conteudoDaPergunta($chamada)];

        $corpo = [
            'model' => $this->modelo,
            'messages' => $mensagens,
            'temperature' => 0.4,
            'max_tokens' => (int) config('assistente.resposta.caracteres_max') * 2,
        ];

        $inicio = microtime(true);
        $pedido = $this->pedidoLogavel($chamada);

        try {
            $resposta = $this->post("{$this->base()}/chat/completions", $corpo, $capacidade, $this->cabecalhos());
            $texto = $this->textoDe($resposta);
        } catch (ChamadaFalhou $falha) {
            $this->registrar($chamada, $capacidade, $pedido, null, $falha->getMessage(), $this->decorrido($inicio));

            throw $falha;
        }

        $tokensEntrada = (int) data_get($resposta, 'usage.prompt_tokens', 0) ?: null;
        $tokensSaida = (int) data_get($resposta, 'usage.completion_tokens', 0) ?: null;
        $decorrido = $this->decorrido($inicio);

        $this->registrar($chamada, $capacidade, $pedido, $resposta, null, $decorrido, $tokensEntrada, $tokensSaida);

        return Resposta::deTexto($texto, static::id(), $this->modelo, $tokensEntrada, $tokensSaida, $decorrido);
    }

    /**
     * O áudio do chat vai para a rota de transcrição, em multipart, com o pedido de português do
     * Brasil. A transcrição é o que a equipe lê depois: arquivo de áudio que ninguém transcreveu
     * não vira resposta, vira escalada.
     */
    public function transcrever(Chamada $chamada): Resposta
    {
        $peca = $chamada->audios[0] ?? null;

        if ($peca === null) {
            throw new ChamadaFalhou('O OpenAI recebeu um pedido de transcrição sem áudio na chamada.');
        }

        $inicio = microtime(true);

        $resposta = Http::withHeaders($this->cabecalhos())
            ->timeout($this->segundosDe('transcricao'))
            ->attach('file', $this->ler($peca['caminho']), $peca['nome'] ?? basename($peca['caminho']))
            ->post("{$this->base()}/audio/transcriptions", [
                'model' => $this->modelo,
                'language' => 'pt',
                'response_format' => 'json',
            ]);

        $corpo = (array) $resposta->json();

        if ($resposta->failed()) {
            $erro = 'O OpenAI respondeu '.$resposta->status().': '.$resposta->body();
            $this->registrar($chamada, 'transcricao', ['audio' => $this->marcasDe([$peca])], null, $erro, $this->decorrido($inicio));

            throw new ChamadaFalhou($erro, $corpo, $resposta->status());
        }

        $texto = trim((string) ($corpo['text'] ?? ''));

        if ($texto === '') {
            throw new ChamadaFalhou('O OpenAI devolveu transcrição vazia.', $corpo);
        }

        $this->registrar($chamada, 'transcricao', [
            'audio' => $this->marcasDe([$peca]),
            'modelo' => $this->modelo,
        ], $corpo, null, $this->decorrido($inicio));

        return Resposta::deTexto($texto, static::id(), $this->modelo, null, null, $this->decorrido($inicio));
    }

    /** A voz do OpenAI chega em MP3 pronto, sem cabeçalho para montar — é o que ela devolve. */
    public function falar(Chamada $chamada): Resposta
    {
        $corpo = [
            'model' => $this->modelo,
            'voice' => $this->vozDaChamada($chamada),
            'input' => $chamada->pergunta,
            'response_format' => 'mp3',
        ];

        $inicio = microtime(true);
        $bytes = $this->postPorBytes("{$this->base()}/audio/speech", $corpo, 'voz', $chamada, $this->cabecalhos());

        $this->registrar($chamada, 'voz', [
            'texto' => $chamada->pergunta,
            'voz' => $corpo['voice'],
            'modelo' => $this->modelo,
        ], ['formato' => 'audio/mpeg'], null, $this->decorrido($inicio), null, null, strlen($bytes));

        return Resposta::deAudio($bytes, 'audio/mpeg', static::id(), $this->modelo, $this->decorrido($inicio));
    }

    private function cabecalhos(): array
    {
        return ['Authorization' => 'Bearer '.$this->chave];
    }

    /**
     * O conteúdo da última fala: texto puro quando é conversa, e lista de partes quando há imagem
     * — o formato que o modelo de visão da OpenAI espera, com o arquivo dentro do `data:` URI.
     */
    private function conteudoDaPergunta(Chamada $chamada): array|string
    {
        $texto = $this->textoDaPergunta($chamada);

        if ($chamada->imagens === []) {
            return $texto;
        }

        $partes = [['type' => 'text', 'text' => $texto]];

        foreach ($chamada->imagens as $peca) {
            $partes[] = ['type' => 'image_url', 'image_url' => [
                'url' => 'data:'.$this->uriDeDados($peca['caminho'], $peca['mime']),
            ]];
        }

        return $partes;
    }

    private function textoDe(array $resposta): string
    {
        $texto = trim((string) data_get($resposta, 'choices.0.message.content'));

        if ($texto === '') {
            $recusa = data_get($resposta, 'choices.0.finish_reason');

            throw new ChamadaFalhou('O OpenAI devolveu resposta vazia'.($recusa ? " (motivo: {$recusa})" : '').'.', $resposta);
        }

        return $texto;
    }
}
