<?php

namespace App\Mail;

use App\Models\GoogleAdsAccount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A conta de Google Ads de um usuário parou de sincronizar porque o Google não aceita mais a
 * credencial guardada (token revogado, senha trocada, consentimento expirado).
 *
 * Não é aviso de rotina: sem reconectar, o painel fica mostrando a última métrica sincronizada
 * e para ali. O e-mail leva direto ao botão "Conectar conta Google Ads".
 */
class ContaGoogleAdsDesconectada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GoogleAdsAccount $conta,
        public User $usuario,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Conta Google Ads desconectada: '.$this->conta->nomeTela());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.conta-google-ads-desconectada', with: [
            'conta' => $this->conta,
            'usuario' => $this->usuario,
            'link' => route('admin.google-ads.index'),
        ]);
    }
}
