<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerta para a equipe: chegou um chamado. Vai com a prévia da primeira mensagem e o link
 * direto da ficha no painel, para ninguém precisar caçar o cliente pelo nome.
 */
class ChamadoEquipe extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $chamado) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novo chamado '.$this->chamado->numero.' — '.$this->chamado->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.chamado-equipe', with: ['chamado' => $this->chamado]);
    }
}
