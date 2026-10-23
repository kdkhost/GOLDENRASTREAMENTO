<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso ao cliente de que o chamado entrou na fila: número, assunto e o link do portal onde
 * a conversa continua — o e-mail nunca é o filhote da conversa, só a porta de volta.
 */
class ChamadoRecebido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $chamado) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Chamado '.$this->chamado->numero.' aberto — '.config('golden.marca.nome'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.chamado-recebido', with: ['chamado' => $this->chamado]);
    }
}
