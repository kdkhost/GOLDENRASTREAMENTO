<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable genérico usado pela fila anti-spam e pelos previews/testes de templates.
 *
 * Aceita HTML completo (já envolvido pelo layout responsivo separado) e
 * anexos em memória — como a fatura PDF gerada cirurgicamente.
 */
class TemplateCustomizadoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{nome: string, mime?: string, conteudo: string}>  $anexosBinarios
     */
    public function __construct(
        public string $assuntoCustomizado,
        public string $corpoHtmlCompleto,
        public ?string $corpoTextoAlternativo = null,
        public array $anexosBinarios = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assuntoCustomizado);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->corpoHtmlCompleto);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $lista = [];

        foreach ($this->anexosBinarios as $anexo) {
            if (! is_array($anexo) || empty($anexo['nome']) || ! isset($anexo['conteudo'])) {
                continue;
            }

            $mime = (string) ($anexo['mime'] ?? 'application/pdf');
            $nome = (string) $anexo['nome'];
            $conteudo = (string) $anexo['conteudo'];

            $lista[] = Attachment::fromData(fn (): string => $conteudo, $nome)->withMime($mime);
        }

        return $lista;
    }
}
