<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link de recuperação de acesso do portal. O endereço é assinado e expira (ver
 * PortalAuthController::esquecerSenha), então não existe tabela de token nem senha à
 * espera no banco: o que chega ao e-mail é uma chave temporária.
 */
class RedefinirSenhaPortal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Customer $cliente, public string $link, public int $minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperação de acesso ao portal — '.config('golden.marca.nome'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.redefinir-senha-portal', with: [
            'cliente' => $this->cliente,
            'link' => $this->link,
            'minutos' => $this->minutos,
        ]);
    }
}
