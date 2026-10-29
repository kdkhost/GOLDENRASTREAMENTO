<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * O aviso de que alguém tentou entrar na conta de uma pessoa do painel e não conseguiu.
 *
 * Sai uma única vez por janela de bloqueio — nunca a cada erro de digitação, porque aviso demais
 * é aviso que se aprende a ignorar. Vai para o dono da conta, não para a equipe: quem precisa saber
 * que o próprio acesso está sendo testado por fora é a pessoa que tem a senha, e é ela que troca.
 *
 * A mensagem não tem link de "proteger minha conta". Um e-mail desses é o disfarce preferido de
 * phishing, e um criminoso que acerte o endereço de um vendedor receberia exatamente a carta que
 * manda clicar num endereço qualquer. O caminho é o de sempre: abrir o painel pelo endereço que a
 * pessoa já conhece e ver a tela Segurança do acesso.
 */
class TentativasDeLogin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $usuario,
        public string $ip,
        public string $agente,
        public int $tentativas,
        public int $bloqueio_minutos,
        public string $registro_em,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tentativas de acesso ao painel: '.$this->usuario->email);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tentativas-de-login', with: [
            'nome' => $this->usuario->name,
            'email' => $this->usuario->email,
            'ip' => $this->ip,
            'agente' => $this->agente,
            'tentativas' => $this->tentativas,
            'bloqueio' => intdiv($this->bloqueio_minutos, 60) > 0
                ? round($this->bloqueio_minutos / 60).' hora(s)'
                : $this->bloqueio_minutos.' minutos',
            'registro_em' => $this->registro_em,
        ]);
    }
}
