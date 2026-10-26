<?php

namespace App\Services\Assistente;

/**
 * O que uma IA devolveu.
 *
 * Três saídas possíveis, e a diferença entre elas é o que o atendimento faz em seguida:
 * `respondeu()` é a fala para o cliente; `escalada()` é a máquina dizendo que parou — com o
 * motivo gravado (`sem_fonte`, `provedor_sem_chave`, `provedor_com_erro`), que é o que a
 * listagem de conversas mostra para a equipe. Uma resposta sem texto e sem motivo não existe:
 * os dois construtores privados obrigam quem escreve a decidir qual das duas é.
 *
 * Áudio vai em bytes e a escolha do endereço é de quem chama: o provedor devolve o que a API
 * entregou, quem decide onde o arquivo mora e com que nome é a camada de atendimento — porque é
 * ela que conhece a conversa, não o HTTP.
 */
final class Resposta
{
    private function __construct(
        public string $texto,
        public ?string $provedor,
        public ?string $modelo,
        public ?int $tokensEntrada,
        public ?int $tokensSaida,
        public int $duracaoMs,
        public ?string $audio,
        public ?string $audioMime,
        public ?string $motivoDeEscalada,
    ) {}

    public static function deTexto(
        string $texto,
        string $provedor,
        string $modelo,
        ?int $tokensEntrada = null,
        ?int $tokensSaida = null,
        int $duracaoMs = 0,
    ): self {
        return new self($texto, $provedor, $modelo, $tokensEntrada, $tokensSaida, $duracaoMs, null, null, null);
    }

    public static function deAudio(string $bytes, string $mime, string $provedor, string $modelo, int $duracaoMs = 0): self
    {
        return new self('', $provedor, $modelo, null, null, $duracaoMs, $bytes, $mime, null);
    }

    /** A máquina parou e chamou alguém. O texto é o que o cliente lê; o motivo é o que a equipe vê. */
    public static function escalada(string $motivo, string $texto): self
    {
        return new self($texto, null, null, null, null, 0, null, null, $motivo);
    }

    public function respondeu(): bool
    {
        return $this->motivoDeEscalada === null;
    }

    public function temAudio(): bool
    {
        return filled($this->audio);
    }

    /** Anexa o áudio falado a uma resposta de texto, sem mexer em nada do resto. */
    public function comAudio(string $bytes, string $mime): self
    {
        $this->audio = $bytes;
        $this->audioMime = $mime;

        return $this;
    }
}
