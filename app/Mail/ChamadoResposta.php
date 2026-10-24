<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nova mensagem dentro de um chamado. Serve para os dois lados — o cliente respondendo pela
 * equipe, e a equipe respondendo pelo portal — porque o corpo é o mesmo: quem escreveu, o
 * que escreveu e um link que leva direto à conversa de quem vai receber.
 */
class ChamadoResposta extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $chamado,
        public TicketMessage $mensagem,
        public string $link,
        public string $chamada,
    ) {}

    public function envelope(): Envelope
    {
        // autor() com parêntese: sem eles o Eloquent lê `autor` como relação e o aviso
        // inteiro estoura antes de sair — Mail::fake não constrói o mailable, então só o
        // render de verdade pega isso.
        return new Envelope(subject: 'Chamado '.$this->chamado->numero.': '.$this->mensagem->autor().' respondeu');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.chamado-resposta', with: [
            'chamado' => $this->chamado,
            'mensagem' => $this->mensagem,
            'link' => $this->link,
            'chamada' => $this->chamada,
        ]);
    }
}
