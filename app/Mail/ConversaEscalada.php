<?php

namespace App\Mail;

use App\Models\Conversa;
use App\Models\ConversaMensagem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerta para a equipe: o assistente parou de responder uma conversa e deixou a pergunta em aberto.
 *
 * É o e-mail que fecha o ciclo da escalada — a caixa de entrada do painel só é útil para quem
 * olha para dentro dela, e o atendimento ao público não pode depender de alguém estar de plantão.
 * O motivo vai escrito porque a equipe precisa saber se faltou fonte, faltou chave ou o provedor
 * respondeu errado: os três têm consertos diferentes.
 */
class ConversaEscalada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Conversa $conversa,
        public string $motivo,
        public ConversaMensagem $pergunta,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Conversa do '.$this->conversa->canalLabel().' aguardando a equipe');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.conversa-escalada', with: [
            'conversa' => $this->conversa,
            'motivo' => $this->motivo,
            'pergunta' => $this->pergunta,
        ]);
    }
}
