<?php

namespace App\Mail;

use App\Models\Testimonial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerta para a equipe: um cliente escreveu o próprio depoimento pelo portal.
 *
 * O texto nunca vai direto para o ar, então o e-mail é a fila de aprovação: leva a prévia e o
 * link já filtrado pelos pendentes, para a equipe decidir sem procurar a ficha pelo nome.
 */
class DepoimentoRecebido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Testimonial $depoimento) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novo depoimento de '.$this->depoimento->nome.' aguardando aprovação');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.depoimento-recebido', with: ['depoimento' => $this->depoimento]);
    }
}
