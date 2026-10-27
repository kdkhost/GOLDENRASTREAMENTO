<?php

namespace App\Services\Mail;

use App\Mail\TesteDeDisparo;
use App\Support\ChavesDeIntegracao;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * A prova do disparo: uma mensagem de verdade, pelo transporte que o painel configurou.
 *
 * Existe porque toda tela que salva credencial precisa responder à pergunta que o administrador
 * faz logo depois de salvar: *saiu?* O WHMCS já tinha a sonda dele; o e-mail não tinha nenhuma, e
 * a resposta de "não saiu" demorava dias para chegar — pelo cliente que esperou o recibo do
 * chamado e nunca o recebeu.
 *
 * Ela não simula nada. Usa `Mail::to()->send()` com um mailable da casa, que é exatamente o
 * caminho do link de senha do portal e da segunda via da fatura: envelope, remetente global,
 * template Blade, transporte escolhido em Configurações. Uma prova que abrisse só a conexão SMTP
 * diria "a porta abre" e deixaria passar o template quebrado e o remetente recusado — os dois
 * erros que mais acontecem numa hospedagem compartilhada.
 *
 * O envio é **síncrono**, ao contrário dos avisos do portal, que entram em fila. Não é descuido:
 * fila consumida pelo cron diria "a mensagem entrou", nunca "ela chegou" — e quem apertou o botão
 * está ali, na frente da tela, esperando a resposta que decide se pode ir embora.
 *
 * O que a pessoa digita é o **destino** da prova, nunca uma credencial nem o endereço de um
 * cliente: o botão existe para conferir a máquina, e correspondência de prova na caixa de quem
 * paga confundiria os dois lados.
 */
class Sonda
{
    /** Os transportes que atravessam a máquina. Os demais (log, array) só gravam em lugar nenhum. */
    private const SAEM = ['smtp', 'sendmail', 'failover', 'roundrobin'];

    /** Há o que testar? Sem transporte de verdade, ou sem servidor, o botão não serve para nada. */
    public function configurado(): bool
    {
        $transporte = $this->transporte();

        if (! in_array($transporte, self::SAEM, true)) {
            return false;
        }

        // `smtp` sem servidor é o único caso em que há credencial e não há para onde mandar.
        return $transporte !== 'smtp' || filled((string) config('mail.mailers.smtp.host'));
    }

    /** O que falta para o disparo sair — em frases que a tela possa listar. */
    public function faltando(): array
    {
        $transporte = $this->transporte();

        if (! in_array($transporte, self::SAEM, true)) {
            return ['um transporte que dispare para fora — hoje o painel está usando "'.$transporte.'"'];
        }

        return ChavesDeIntegracao::faltantesDoEmail();
    }

    /**
     * O resumo técnico que a tela mostra antes do botão e que vai dentro da mensagem de prova. É
     * o que a pessoa precisa conferir para saber se o valor gravado é o que ela quis dizer: porta
     * 465 com STARTTLS, por exemplo, é o erro de SMTP mais comum do mundo e se vê aqui.
     *
     * @return array<string, string>
     */
    public function viagem(): array
    {
        $smtp = (array) config('mail.mailers.smtp');

        return [
            'Transporte' => $this->transporte(),
            'Servidor' => (string) (filled($smtp['host'] ?? null) ? $smtp['host'] : '— sem servidor —'),
            'Porta' => (string) ($smtp['port'] ?? '—'),
            'Criptografia' => $this->esquema((string) ($smtp['scheme'] ?? '')),
            'Usuário' => (string) (filled($smtp['username'] ?? null) ? $smtp['username'] : '— sem autenticação —'),
            'Remetente' => $this->remetente(),
        ];
    }

    /**
     * Dispara a mensagem de prova e traduz o que aconteceu.
     *
     * @return array{estado: string, titulo: string, texto: string}
     */
    public function sonda(string $destino): array
    {
        $destino = trim($destino);

        if (! $this->configurado()) {
            return [
                'estado' => 'sem_disparo',
                'titulo' => 'Ainda não há o que testar.',
                'texto' => 'Falta '.implode(', ', $this->faltando()).'. Complete em Configurações → Chaves de integração → E-mail de disparo; o valor passa a valer na hora, sem tocar no servidor.',
            ];
        }

        $viagem = $this->viagem();

        try {
            Mail::to($destino)->send(new TesteDeDisparo($destino, $viagem, now()->format('d/m/Y H:i')));
        } catch (Throwable $falha) {
            return $this->descreve($falha, $destino, $viagem);
        }

        return [
            'estado' => 'ok',
            'titulo' => 'O disparo está no ar.',
            'texto' => 'O servidor aceitou a mensagem para '.$destino.' pelo transporte '.$viagem['Transporte'].'. Abra a caixa de entrada para conferir — se ela chegou, o link de senha do portal e a segunda via da fatura saem pelo mesmo caminho.',
        ];
    }

    /**
     * O erro vira frase de painel. Os três grupos abaixo são os únicos modos de falha que existem
     * numa hospedagem compartilhada: a porta não abre (DNS, firewall, criptografia na porta
     * errada), a porta abre e o servidor recusa (credencial, remetente, relay), ou abriu e a
     * framework esbarrou em outra coisa. A mensagem original vai sempre junto, porque quem
     * administra o servidor pensa em código de resposta, não em prosa.
     *
     * @param  array<string, string>  $viagem
     * @return array{estado: string, titulo: string, texto: string}
     */
    public function descreve(Throwable $falha, string $destino, array $viagem): array
    {
        $diz = Str::lower($falha->getMessage());

        $tecnico = $viagem['Servidor'].':'.$viagem['Porta'].' com '.$viagem['Criptografia'].
            ', remetente '.$viagem['Remetente'].'. Detalhe: '.$falha->getMessage();

        if ($this->contem($diz, ['could not be established', 'unable to connect', 'connection refused',
            'timed out', 'timeout', 'getaddrinfo', 'name or service not known', 'stream_socket_client',
            'no connection', 'ssl', 'certificate'])) {
            return [
                'estado' => 'sem_resposta',
                'titulo' => 'O servidor de correio não atendeu.',
                'texto' => 'Nenhuma conexão se abriu com '.$tecnico.' Confira a porta junto com a criptografia (465 pede SSL, 587 pede STARTTLS) e se a hospedagem libera conexões de saída.',
            ];
        }

        if ($this->contem($diz, ['authenticate', 'auth', '535', 'invalid credentials', 'username',
            'password', 'relay', '550', '553', 'not permitted', 'sender', 'mailbox unavailable',
            'access denied'])) {
            return [
                'estado' => 'recusada',
                'titulo' => 'O servidor abriu e recusou o envio.',
                'texto' => 'A conexão com '.$tecnico.' Credencial revogada, remetente que não é a conta autenticada ou relay bloqueado são os motivos mais comuns — e o detalhe acima diz qual dos três.',
            ];
        }

        return [
            'estado' => 'falhou',
            'titulo' => 'A mensagem não saiu.',
            'texto' => 'O transporte '.$viagem['Transporte'].' devolveu um erro inesperado para '.$destino.'. '.$tecnico,
        ];
    }

    /** @param array<int, string> $partes */
    private function contem(string $texto, array $partes): bool
    {
        foreach ($partes as $parte) {
            if (str_contains($texto, $parte)) {
                return true;
            }
        }

        return false;
    }

    private function transporte(): string
    {
        return (string) config('mail.default');
    }

    /** O endereço que assina os envios, já resolvido — é o que o servidor de destino vai ver. */
    private function remetente(): string
    {
        $endereco = (string) config('mail.from.address');

        return $endereco !== '' ? $endereco.' ('.config('mail.from.name').')' : '— sem remetente —';
    }

    /** Como a tela chama o modo de criptografia, na língua de quem administra. */
    private function esquema(string $scheme): string
    {
        return match (ChavesDeIntegracao::esquema($scheme)) {
            'smtps' => 'SSL/TLS direto',
            // `smtp` não é "sem criptografia": é a conexão limpa que vira criptografada no
            // STARTTLS, sempre que o servidor anuncia — que é o caso de toda hospedagem hoje.
            'smtp' => 'STARTTLS',
            null => 'a porta decide (465 = SSL direto, o resto = STARTTLS)',
        };
    }
}
