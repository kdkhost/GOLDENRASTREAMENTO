<?php

namespace App\Services\Assistente\Provedores;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Contratos\PodeConversar;
use App\Services\Assistente\Contratos\PodeFalar;
use App\Services\Assistente\Contratos\PodeTranscrever;
use App\Services\Assistente\Excecoes\ChamadaFalhou;
use App\Services\Assistente\Resposta;

/**
 * O Google Gemini como atendente: conversa, vê imagem, lê documento, ouve áudio e fala.
 *
 * Uma rota só para tudo — `models/<modelo>:generateContent` — e é por isso que ele é o padrão da
 * casa numa instalação nova: uma chave e o assistente já sabe fazer as cinco coisas, sem o
 * administrador cadastrar modelo por modelo.
 *
 * As duas particularidades que o resto do sistema não precisa conhecer estão aqui:
 *
 * 1. Mídia vai dentro da própria fala, em `inlineData`, com o base64 do arquivo. O que fica no
 *    log é a marca do arquivo, não o corpo — `Provedor::marcasDe()`.
 * 2. A voz sai como PCM cru (`audio/L16;codec=pcm;rate=24000`). Um `.wav` tocável em qualquer
 *    navegador precisa de cabeçalho de 44 bytes na frente, e a hospedagem compartilhada não tem
 *    ffmpeg para converter. O cabeçalho é montado à mão, em `cabecalhoDeWav()`.
 */
class Gemini extends Provedor implements PodeConversar, PodeTranscrever, PodeFalar
{
    /** A voz do Gemini sai em PCM 16 bits, mono, a esta taxa — é o que o cabeçalho do WAV descreve. */
    private const TAXA_DE_AMOSTRAGEM = 24000;

    public static function id(): string
    {
        return 'gemini';
    }

    public function responder(Chamada $chamada): Resposta
    {
        $capacidade = $chamada->capacidade ?? 'conversa';
        $partes = [];

        foreach ($chamada->imagens as $peca) {
            $partes[] = $this->arquivoNaFala($peca);
        }

        foreach ($chamada->documentos as $peca) {
            $partes[] = $this->arquivoNaFala($peca);
        }

        foreach ($chamada->audios as $peca) {
            $partes[] = $this->arquivoNaFala($peca);
        }

        $partes[] = ['text' => $this->textoDaPergunta($chamada)];

        $corpo = [
            'systemInstruction' => ['parts' => [['text' => $chamada->ordem()]]],
            'contents' => array_merge($this->historico($chamada), [['role' => 'user', 'parts' => $partes]]),
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => (int) config('assistente.resposta.caracteres_max') * 2,
            ],
        ];

        $inicio = microtime(true);
        $pedido = $this->pedidoLogavel($chamada);

        try {
            $resposta = $this->post($this->endpoint('generateContent'), $corpo, $capacidade);
            $texto = $this->textoDe($resposta);
        } catch (ChamadaFalhou $falha) {
            $this->registrar($chamada, $capacidade, $pedido, null, $falha->getMessage(), $this->decorrido($inicio));

            throw $falha;
        }

        $tokensEntrada = (int) data_get($resposta, 'usageMetadata.promptTokenCount', 0) ?: null;
        $tokensSaida = (int) data_get($resposta, 'usageMetadata.candidatesTokenCount', 0) ?: null;
        $decorrido = $this->decorrido($inicio);

        $this->registrar($chamada, $capacidade, $pedido, $resposta, null, $decorrido, $tokensEntrada, $tokensSaida);

        return Resposta::deTexto($texto, static::id(), $this->modelo, $tokensEntrada, $tokensSaida, $decorrido);
    }

    /**
     * O Gemini lê áudio na mesma chamada da conversa: o arquivo vai em `inlineData` e a pergunta
     * é um pedido de transcrição. Por isto o provedor não precisa de rota própria de áudio nem de
     * um segundo modelo cadastrado.
     */
    public function transcrever(Chamada $chamada): Resposta
    {
        $peca = $chamada->audios[0] ?? null;

        if ($peca === null) {
            throw new ChamadaFalhou('O Gemini recebeu um pedido de transcrição sem áudio na chamada.');
        }

        $corpo = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    $this->arquivoNaFala($peca),
                    ['text' => 'Transcreva este áudio literalmente, em português do Brasil. '
                        .'Não resuma, não corrija e não responda ao que foi dito — devolva só o texto falado.'],
                ],
            ]],
            'generationConfig' => ['temperature' => 0],
        ];

        $inicio = microtime(true);
        $resposta = $this->post($this->endpoint('generateContent'), $corpo, 'transcricao');
        $texto = $this->textoDe($resposta);

        $this->registrar($chamada, 'transcricao', [
            'audio' => $this->marcasDe([$peca]),
            'modelo' => $this->modelo,
        ], $resposta, null, $this->decorrido($inicio));

        return Resposta::deTexto($texto, static::id(), $this->modelo, null, null, $this->decorrido($inicio));
    }

    /**
     * Texto entra, voz sai. O modelo é o da capacidade `voz` (os `_preview-tts` da lista), e a
     * única diferença para uma resposta comum é o `responseModalities` pedindo áudio e a voz
     * escolhida pelo painel.
     */
    public function falar(Chamada $chamada): Resposta
    {
        $corpo = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $chamada->pergunta]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => [
                    'voiceName' => $this->vozDaChamada($chamada),
                ]]],
            ],
        ];

        $inicio = microtime(true);
        $resposta = $this->post($this->endpoint('generateContent'), $corpo, 'voz');
        $bruto = (string) data_get($resposta, 'candidates.0.content.parts.0.inlineData.data');

        if ($bruto === '') {
            throw new ChamadaFalhou('O Gemini respondeu sem áudio.', $resposta);
        }

        $pcm = base64_decode($bruto, true) ?: '';
        $wav = $this->cabecalhoDeWav(strlen($pcm)).$pcm;

        $this->registrar($chamada, 'voz', [
            'texto' => $chamada->pergunta,
            'voz' => $this->vozDaChamada($chamada),
            'modelo' => $this->modelo,
        ], ['formato' => 'audio/wav'], null, $this->decorrido($inicio), null, null, strlen($wav));

        return Resposta::deAudio($wav, 'audio/wav', static::id(), $this->modelo, $this->decorrido($inicio));
    }

    private function endpoint(string $acao): string
    {
        return "{$this->base()}/models/{$this->modelo}:{$acao}?key={$this->chave}";
    }

    /** O arquivo vira uma parte da fala do usuário, com o base64 dentro do JSON. */
    private function arquivoNaFala(array $peca): array
    {
        return ['inlineData' => ['mimeType' => $peca['mime'], 'data' => base64_encode($this->ler($peca['caminho']))]];
    }

    /**
     * As falas anteriores, no papel que o Gemini espera: `user` e `model` alternando. O histórico
     * é o que faz o assistente lembrar o que o cliente respondeu duas mensagens atrás — sem ele,
     * cada pergunta seria a primeira.
     */
    private function historico(Chamada $chamada): array
    {
        $papeis = ['cliente' => 'user', 'equipe' => 'user', 'sistema' => null, 'assistente' => 'model'];
        $falas = [];

        foreach ($chamada->historico as $fala) {
            $papel = $papeis[$fala['autor']] ?? 'user';

            if ($papel === null || blank($fala['texto'])) {
                continue;
            }

            $falas[] = ['role' => $papel, 'parts' => [['text' => (string) $fala['texto']]]];
        }

        return $falas;
    }

    private function textoDe(array $resposta): string
    {
        $partes = (array) data_get($resposta, 'candidates.0.content.parts', []);
        $texto = trim(implode(' ', array_filter(array_map(
            static fn (array $parte): ?string => $parte['text'] ?? null,
            $partes
        ))));

        if ($texto !== '') {
            return $texto;
        }

        $bloqueio = data_get($resposta, 'promptFeedback.blockReason')
            ?: data_get($resposta, 'candidates.0.finishReason');

        throw new ChamadaFalhou('O Gemini devolveu resposta vazia'.($bloqueio ? " (motivo: {$bloqueio})" : '').'.', $resposta);
    }

    /**
     * Os 44 bytes que fazem de um PCM um arquivo tocável: magic `RIFF`, tamanho, `WAVE`, o bloco
     * `fmt ` descrevendo PCM 16 bits mono e, por fim, o bloco `data` com o áudio. Escrito à mão
     * porque não há conversor na hospedagem — e é o que devolve um `.wav` que toca no navegador
     * sem nenhum serviço externo.
     */
    private function cabecalhoDeWav(int $bytesDeAudio): string
    {
        $taxa = self::TAXA_DE_AMOSTRAGEM;
        $canais = 1;
        $bits = 16;
        $alinhamento = (int) ceil($bytesDeAudio / 2) * 2;

        return 'RIFF'.pack('V', 36 + $bytesDeAudio).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, $canais, $taxa, (int) ($taxa * $canais * $bits / 8), (int) ($canais * $bits / 8), $bits)
            .'data'.pack('V', $bytesDeAudio)
            .($alinhamento > $bytesDeAudio ? "\0" : '');
    }
}
