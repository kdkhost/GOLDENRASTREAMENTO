<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertaEmpresaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tipo,
        public string $titulo,
        public ?string $texto = null,
        public ?string $url = null,
        public array $dados = [],
    ) {}

    public function envelope(): Envelope
    {
        $prefixo = match ($this->tipo) {
            'lead_novo' => '🎯 [Novo Lead]',
            'pedido_novo' => '💰 [Nova Venda]',
            'depoimento_novo' => '⭐ [Novo Depoimento]',
            'coleta_solicitada' => '🚚 [Nova Coleta]',
            'entrega_atualizada' => '📦 [Rastreio de Entrega]',
            'chamado_aberto', 'chamado_respondido' => '🎫 [Suporte]',
            'acesso_suspeito' => '🛡️ [Segurança]',
            default => '🔔 [Alerta Golden]',
        };

        return new Envelope(
            subject: "{$prefixo} {$this->titulo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.alerta-empresa',
            with: [
                'tipo' => $this->tipo,
                'titulo' => $this->titulo,
                'texto' => $this->texto,
                'url' => $this->url,
                'dados' => $this->dados,
            ],
        );
    }
}
