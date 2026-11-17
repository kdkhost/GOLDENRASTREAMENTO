<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link de recuperação de acesso ao painel administrativo.
 *
 * O endereço é temporário e assinado criptograficamente (URL::temporarySignedRoute),
 * garantindo validade estrita sem armazenar tokens no banco.
 */
class RedefinirSenhaEquipe extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $link,
        public int $minutos = 60
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperação de acesso ao painel — '.config('golden.marca.nome', 'Golden Rastreamento')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.redefinir-senha-equipe',
            with: [
                'user' => $this->user,
                'link' => $this->link,
                'minutos' => $this->minutos,
            ]
        );
    }
}
