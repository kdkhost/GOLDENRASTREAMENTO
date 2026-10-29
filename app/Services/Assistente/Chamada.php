<?php

namespace App\Services\Assistente;

/**
 * O que se pede a uma IA, num objeto só.
 *
 * É a fronteira entre o atendimento e o provedor: a tela e o pipeline montam a chamada, o
 * provedor lê e devolve uma `Resposta`. Nada de array solto atravessando a camada — cada campo
 * tem nome, e um provedor novo se escreve olhando para esta lista.
 *
 * Mídia entra por caminho de disco (`storage/assistente/…`), nunca por base64: quem recebe é
 * quem sabe o tamanho que consegue ler, e o log da chamada precisa marcar o arquivo em vez de
 * guardar megabytes de texto codificado no banco.
 */
final class Chamada
{
    /**
     * @param  array<int, string>  $instrucoes  a ordem de conduta, linha a linha
     * @param  array<int, array{autor: string, texto: string}>  $historico  as falas anteriores
     * @param  array<int, array{caminho: string, mime: string, nome?: string, descricao?: string, texto?: string}>  $imagens
     * @param  array<int, array{caminho: string, mime: string, nome?: string, descricao?: string, texto?: string}>  $documentos
     * @param  array<int, array{caminho: string, mime: string, nome?: string, descricao?: string}>  $audios
     * @param  bool  $cortesia  a fala é só saudação — não pergunta fato, e pode ser respondida sem fonte
     */
    public function __construct(
        public string $pergunta,
        public array $instrucoes = [],
        public array $historico = [],
        public array $fontes = [],
        public array $imagens = [],
        public array $documentos = [],
        public array $audios = [],
        public ?int $conversaId = null,
        public ?int $userId = null,
        public ?string $capacidade = null,
        public ?string $voz = null,
        public bool $cortesia = false,
    ) {}

    /** A chamada tem mídia? É o que liga a capacidade de visão/documento no lugar da conversa pura. */
    public function temMidia(): bool
    {
        return $this->imagens !== [] || $this->documentos !== [] || $this->audios !== [];
    }

    /** Quantas peças de mídia vão na chamada — o log usa isto em vez de despejar os arquivos. */
    public function contarMidia(): int
    {
        return count($this->imagens) + count($this->documentos) + count($this->audios);
    }

    /** As instruções juntas, no texto que o provedor recebe como ordem de sistema. */
    public function ordem(): string
    {
        return implode("\n", array_filter($this->instrucoes));
    }

    /**
     * A ordem de mídia que a conversa leva como contexto: o que a imagem mostra e o que o
     * documento diz, já lidos por `visao` e `documento`. A resposta final sai de uma chamada de
     * conversa só, porque misturar os dois na mesma chamada faria o modelo de conversa precisar
     * saber ver — e ele pode ser um modelo de texto puro.
     */
    public function contextoDeMidia(): string
    {
        $linhas = [];

        foreach ([['imagem', $this->imagens], ['documento', $this->documentos], ['áudio', $this->audios]] as [$tipo, $pecas]) {
            foreach ($pecas as $peca) {
                $linhas[] = ucfirst($tipo).' ('.($peca['nome'] ?? basename($peca['caminho'])).'): '.($peca['descricao'] ?? '');
            }
        }

        return trim(implode("\n", array_filter($linhas, fn (string $l): bool => ! str_ends_with($l, ': '))));
    }
}
