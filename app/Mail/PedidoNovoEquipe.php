<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerta interno de venda nova (loja_email_equipe). Traz o essencial para a equipe começar
 * o atendimento sem abrir o painel.
 */
class PedidoNovoEquipe extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $pedido) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Venda nova: pedido '.$this->pedido->numero);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pedido-equipe', with: ['pedido' => $this->pedido]);
    }
}
