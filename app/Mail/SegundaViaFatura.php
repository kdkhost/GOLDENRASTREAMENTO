<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A segunda via da fatura pedida no chat, entregue no e-mail que está na ficha.
 *
 * Uma linha manda nesta mensagem mais do que parece: ela só existe para um destinatário decidido
 * pelo banco. O endereço que a pessoa digitou na bolha serviu para localizar a conta, e o `Mail::to`
 * de quem envia recebe o `emailDeAcesso()` da ficha — é assim que um e-mail errado no chat vira
 * "não localizei sua conta", e nunca "a fatura do vizinho chegou na minha caixa".
 *
 * O corpo tem número, vencimento, valor e situação porque é a própria fatura resumida; nada além
 * disso sai daqui — contrato, placa e documento ficam no portal. O botão leva ao financeiro quando
 * a veiculação permite o link externo, e ao portal do cliente quando não permite: o cliente nunca
 * recebe um endereço que a casa decidiu não mostrar.
 */
class SegundaViaFatura extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $fatura  a fatura como `App\Services\Whmcs\Cliente::faturas()` a devolve
     */
    public function __construct(
        public Customer $cliente,
        public array $fatura,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Segunda via da fatura '.$this->fatura['numero'].' — '.config('golden.marca.nome'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.segunda-via-fatura', with: [
            'cliente' => $this->cliente,
            'fatura' => $this->fatura,
        ]);
    }
}
