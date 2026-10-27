<?php

use App\Support\ChavesDeIntegracao;

/*
|--------------------------------------------------------------------------
| Como o sistema envia e-mail
|--------------------------------------------------------------------------
|
| Nada aqui é opcional nem some por falta de chave: o código dos disparos vive no sistema para
| sempre, e o que muda de servidor para servidor é só a credencial. Esta página é o plano de
| fundo; o painel (Sistema → Configurações → Chaves de integração → E-mail de disparo) passa na
| frente dela assim que alguém preenche qualquer campo, gravado no banco.
|
| O endereço que assina os envios segue a mesma lógica em três passos: o valor do painel, depois
| o `.env`, depois a caixa autenticada (`MAIL_USERNAME`) — porque num servidor de hospedagem o
| remetente costuma ter que ser uma conta que existe lá, e um `from` inventado volta como
| devolução. Só na falta dos três é que se chuta um `avisos@` sobre o domínio da aplicação.
*/

$remetente = env('MAIL_FROM_ADDRESS') ?: env('MAIL_USERNAME') ?: null;
$dominio_app = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            // Os nomes que o mundo usa (`tls`, `ssl`, a variável velha `MAIL_ENCRYPTION`) entram
            // aqui e saem na única forma que o transporte aceita. Sem essa tradução, um `.env` de
            // tutorial derruba todo disparo com `UnsupportedSchemeException`.
            'scheme' => ChavesDeIntegracao::esquema(env('MAIL_SCHEME', env('MAIL_ENCRYPTION'))),
            'url' => env('MAIL_URL'),
            // Sem padrão para o servidor: `127.0.0.1` disfarça uma configuração incompleta de
            // conexão legítima, e a tela de Configurações precisa poder dizer "falta o servidor".
            // A porta 587 é a do STARTTLS de hospedagem — a que quase todo painel de correio usa.
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL', 'email'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $remetente ?: 'avisos@'.$dominio_app,
        'name' => env('MAIL_FROM_NAME', config('golden.marca.nome')),
    ],

];
