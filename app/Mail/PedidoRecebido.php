<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmação para o cliente: número do pedido, resumo da compra e o link com token para
 * acompanhar a jornada sem precisar de login.
 */
class PedidoRecebido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $pedido) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pedido '.$this->pedido->numero.' recebido — '.config('golden.marca.nome'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pedido-recebido', with: ['pedido' => $this->pedido]);
    }
}
