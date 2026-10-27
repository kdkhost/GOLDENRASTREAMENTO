<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A mensagem do botão "Testar o disparo" da tela de Configurações.
 *
 * Existe um motivo para ela ser um mailable de verdade e não um `Mail::raw`: é o mesmo caminho
 * que os avisos do portal percorrem — envelope, `from` global, template Blade, transporte
 * configurado. Se esta chega, chega o link de senha; se ela volta, o problema é do servidor, não
 * de uma tela. O corpo mostra os valores que a máquina usou para enviar, porque quem configura o
 * SMTP precisa conferir na mensagem o que a tela mostrou, e não de memória.
 *
 * Ela nunca carrega dado de cliente nenhum: é prova de transporte, não correspondência.
 */
class TesteDeDisparo extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $viagem  transporte, servidor, porta, criptografia e remetente usados
     */
    public function __construct(
        public string $destino,
        public array $viagem,
        public string $disparado_em,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Teste de disparo — '.config('golden.marca.nome'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.teste-de-disparo', with: [
            'destino' => $this->destino,
            'viagem' => $this->viagem,
            'disparado_em' => $this->disparado_em,
        ]);
    }
}
