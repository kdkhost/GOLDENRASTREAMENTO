<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PedidoEnviadoRastreio extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $pedido) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Seu pedido {$this->pedido->numero} foi enviado! Acompanhe o rastreamento — ".config('golden.marca.nome')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.pedido-enviado-rastreio',
            with: [
                'pedido' => $this->pedido,
                'codigo' => $this->pedido->codigo_rastreio,
                'transportadora' => $this->pedido->transportadoraLabel(),
                'urlRastreio' => $this->pedido->rastreioUrl(),
            ]
        );
    }
}
