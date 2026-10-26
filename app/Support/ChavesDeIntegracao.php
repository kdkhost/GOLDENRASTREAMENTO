<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * As credenciais que o painel guarda no banco.
 *
 * A regra da casa cabe numa frase: **o que está gravado no banco manda; o `.env` é o plano de
 * fundo** de quem ainda não preencheu nada na tela. `aplicar()` sobrepõe os valores do banco a
 * `config()` no começo de cada requisição, então nenhum leitor precisa saber de onde o valor
 * veio — `config('google_ads.developer_token')` continua sendo a pergunta, e a resposta passa a
 * ser a que a pessoa deu em Configurações. É por isso também que não existe "reiniciar o
 * servidor" nem `config:clear` no meio-termo: salvou na tela, valeu na hora.
 *
 * O que é segredo (`'segredo' => true`) sai **cifrado** do banco e nunca volta para a tela: o
 * formulário mostra *se* está gravado, nunca *o que* está gravado. Os outros campos mostram o
 * valor, porque são endereços e números de conta — informação que o próprio site publica.
 *
 * Apagar um campo na tela apaga a linha do banco, e o `.env` volta a valer. É o mesmo princípio
 * do "voltar à foto original" das imagens do site: o painel não grava por cima do ambiente, ele
 * passa na frente dele.
 */
class ChavesDeIntegracao
{
    /** Para onde apontar quando falta uma chave: o caminho da tela, escrito uma vez só. */
    public const CAMINHO_NO_PAINEL = 'Sistema → Configurações → Chaves de integração';

    /** Como a tela chama cada origem. Nenhuma view escreve essas três palavras. */
    public const ROTULOS_ORIGEM = [
        'banco' => 'gravada no banco',
        'ambiente' => 'vem do .env',
        'ausente' => 'sem valor',
    ];

    /**
     * Cada grupo da tela de Configurações, com seus campos.
     *
     * `config` é o caminho que o valor passa a valer; `ambiente` é a variável de plano de fundo
     * correspondente, mostrada entre parênteses para quem administra o servidor reconhecer a
     * chave na hora; `segredo` cifra no banco e esconde na tela; `cast` é `int` para o que o
     * Laravel espera inteiro.
     */
    public const GRUPOS = [
        'google-ads' => [
            'rotulo' => 'Google Ads',
            'icone' => 'fa-brands fa-google',
            'descricao' => 'Conectam o painel à conta do anunciante. Ficam cifradas no banco e não aparecem em lugar nenhum depois de salvas.',
            'campos' => [
                [
                    'chave' => 'google_ads_developer_token',
                    'config' => 'google_ads.developer_token',
                    'ambiente' => 'GOOGLE_ADS_DEVELOPER_TOKEN',
                    'label' => 'Token de desenvolvedor',
                    'segredo' => true,
                    'dica' => 'Nasce na conta MCC: Google Ads → Ferramentas e configurações → API do Google Ads → Aplicação de teste.',
                ],
                [
                    'chave' => 'google_client_id',
                    'config' => 'services.google_ads.client_id',
                    'ambiente' => 'GOOGLE_CLIENT_ID',
                    'label' => 'ID do cliente OAuth',
                    'segredo' => true,
                    'dica' => 'Credencial do tipo "Aplicação da Web" no Google Cloud Console.',
                ],
                [
                    'chave' => 'google_client_secret',
                    'config' => 'services.google_ads.client_secret',
                    'ambiente' => 'GOOGLE_CLIENT_SECRET',
                    'label' => 'Segredo do cliente OAuth',
                    'segredo' => true,
                    'dica' => 'O par do ID acima. É o que assina o pedido de consentimento.',
                ],
                [
                    'chave' => 'google_redirect_uri',
                    'config' => 'services.google_ads.redirect',
                    'ambiente' => 'GOOGLE_REDIRECT_URI',
                    'label' => 'Endereço de volta do OAuth',
                    'opcional' => true,
                    'dica' => 'Deixe em branco para o painel usar o próprio endereço. Tem que ser o mesmo cadastrado no Google Cloud.',
                ],
                [
                    'chave' => 'google_ads_login_customer_id',
                    'config' => 'google_ads.login_customer_id',
                    'ambiente' => 'GOOGLE_ADS_LOGIN_CUSTOMER_ID',
                    'label' => 'Conta gerente (MCC)',
                    'opcional' => true,
                    'regras' => ['digits_between:9,12'],
                    'dica' => 'Dez dígitos, sem hífen. Vazio, cada conta conectada é consultada por si só.',
                ],
                [
                    'chave' => 'google_ads_cache_minutos',
                    'config' => 'google_ads.cache_minutos',
                    'ambiente' => 'GOOGLE_ADS_CACHE_MINUTOS',
                    'label' => 'Vida da janela em cache (minutos)',
                    'opcional' => true,
                    'cast' => 'int',
                    'regras' => ['integer', 'min:1', 'max:1440'],
                    'placeholder' => '30',
                    'dica' => 'Quanto tempo a janela sincronizada serve à tela antes de nova chamada ao Google.',
                ],
                [
                    'chave' => 'google_ads_dias',
                    'config' => 'google_ads.dias_padrao',
                    'ambiente' => 'GOOGLE_ADS_DIAS',
                    'label' => 'Período padrão da tela (dias)',
                    'opcional' => true,
                    'cast' => 'int',
                    'regras' => ['integer', 'min:1', 'max:365'],
                    'placeholder' => '30',
                    'dica' => 'A janela que a tela abre escolhendo — 7, 30 e 90 continuam disponíveis para quem consulta.',
                ],
            ],
        ],

        'avaliacoes' => [
            'rotulo' => 'Avaliações do Google',
            'icone' => 'fa-star',
            'descricao' => 'Lê as avaliações do ficha do Google para a tela de Depoimentos. Sem o token, a importação não roda — e o sistema não inventa avaliação.',
            'campos' => [
                [
                    'chave' => 'google_business_account',
                    'config' => 'golden.google_business.account',
                    'ambiente' => 'GOOGLE_BUSINESS_ACCOUNT',
                    'label' => 'Conta do Business Profile',
                    'opcional' => true,
                    'regras' => ['string', 'max:60'],
                    'dica' => 'Só o número, ou o endereço inteiro que a API usa: mybusinessapi.googleapis.com/v1/accounts/<número>.',
                ],
                [
                    'chave' => 'google_business_location',
                    'config' => 'golden.google_business.location',
                    'ambiente' => 'GOOGLE_BUSINESS_LOCATION',
                    'label' => 'Local (ficha)',
                    'opcional' => true,
                    'regras' => ['string', 'max:60'],
                    'dica' => 'O número da ficha, ou locations/<número>/reviews como está na API.',
                ],
                [
                    'chave' => 'google_business_token',
                    'config' => 'golden.google_business.token',
                    'ambiente' => 'GOOGLE_BUSINESS_TOKEN',
                    'label' => 'Token de acesso',
                    'segredo' => true,
                    'opcional' => true,
                    'dica' => 'Bearer de OAuth de uma conta que administra essa ficha. Renove quando expirar.',
                ],
            ],
        ],

        'email' => [
            'rotulo' => 'E-mail de disparo',
            'icone' => 'fa-envelope',
            'descricao' => 'O servidor SMTP que envia os avisos do painel e do portal. Vazio em cada campo, o que está no .env continua valendo — o código do e-mail nunca sai do sistema.',
            'campos' => [
                [
                    'chave' => 'mail_mailer',
                    'config' => 'mail.default',
                    'ambiente' => 'MAIL_MAILER',
                    'label' => 'Como o sistema envia',
                    'opcional' => true,
                    'regras' => ['in:smtp,sendmail,log'],
                    'opcoes' => ['smtp' => 'SMTP (servidor de correio)', 'sendmail' => 'sendmail da máquina', 'log' => 'Só gravar em storage/logs/email.log'],
                    'dica' => 'Escolher "só gravar" desliga o disparo de verdade — serve para testar sem incomodar ninguém.',
                ],
                [
                    'chave' => 'mail_host',
                    'config' => 'mail.mailers.smtp.host',
                    'ambiente' => 'MAIL_HOST',
                    'label' => 'Servidor SMTP',
                    'opcional' => true,
                    'placeholder' => 'mail.seudominio.com.br',
                ],
                [
                    'chave' => 'mail_port',
                    'config' => 'mail.mailers.smtp.port',
                    'ambiente' => 'MAIL_PORT',
                    'label' => 'Porta',
                    'opcional' => true,
                    'cast' => 'int',
                    'regras' => ['integer','min:1','max:65535'],
                    'placeholder' => '587',
                ],
                [
                    'chave' => 'mail_scheme',
                    'config' => 'mail.mailers.smtp.scheme',
                    'ambiente' => 'MAIL_SCHEME',
                    'label' => 'Criptografia',
                    'opcional' => true,
                    'regras' => ['in:tls,ssl'],
                    'opcoes' => ['tls' => 'STARTTLS (a mais comum)', 'ssl' => 'SSL/TLS direto'],
                    'dica' => 'Em branco, decide o .env. Não há "sem criptografia" aqui: um servidor de correio que fala texto puro na porta 25 costuma ser o que está errado.',
                ],
                [
                    'chave' => 'mail_username',
                    'config' => 'mail.mailers.smtp.username',
                    'ambiente' => 'MAIL_USERNAME',
                    'label' => 'Usuário',
                    'opcional' => true,
                    'placeholder' => 'disparos@seudominio.com.br',
                ],
                [
                    'chave' => 'mail_password',
                    'config' => 'mail.mailers.smtp.password',
                    'ambiente' => 'MAIL_PASSWORD',
                    'label' => 'Senha',
                    'segredo' => true,
                    'opcional' => true,
                    'dica' => 'Caixa de máquina, nunca o e-mail pessoal de alguém.',
                ],
                [
                    'chave' => 'mail_from_address',
                    'config' => 'mail.from.address',
                    'ambiente' => 'MAIL_FROM_ADDRESS',
                    'label' => 'Remetente',
                    'opcional' => true,
                    'type' => 'email',
                    'regras' => ['email','max:160'],
                    'placeholder' => 'avisos@seudominio.com.br',
                ],
                [
                    'chave' => 'mail_from_name',
                    'config' => 'mail.from.name',
                    'ambiente' => 'MAIL_FROM_NAME',
                    'label' => 'Nome que aparece',
                    'opcional' => true,
                    'placeholder' => 'Golden Rastreamento',
                ],
            ],
        ],
    ];

    /** O campo de uma chave, ou `null` se a chave não é de integração. */
    public static function campo(string $chave): ?array
    {
        foreach (self::GRUPOS as $grupo) {
            foreach ($grupo['campos'] as $campo) {
                if ($campo['chave'] === $chave) {
                    return $campo;
                }
            }
        }

        return null;
    }

    /** Todos os campos, achatados — a lista que a tela e o `aplicar()` percorrem. */
    public static function campos(): array
    {
        return collect(self::GRUPOS)
            ->flatMap(fn (array $grupo): array => $grupo['campos'])
            ->all();
    }

    /**
     * Joga o que está no banco sobre `config()`. Uma vez por requisição, antes de qualquer
     * leitura. O banco vazio (instalação recém-migrada, tabela que ainda não existe) não é erro:
     * é o estado em que o `.env` manda, e é assim que o sistema sobe antes do primeiro `migrate`.
     */
    public static function aplicar(): void
    {
        try {
            foreach (self::campos() as $campo) {
                $valor = self::doBanco($campo['chave']);

                if ($valor !== null) {
                    config([$campo['config'] => ($campo['cast'] ?? null) === 'int' ? (int) $valor : $valor]);
                }
            }
        } catch (Throwable) {
            // Sem tabela de configurações, sem sobreposição: o ambiente sozinho conduz.
        }
    }

    /** O valor gravado no banco, decifrado; `null` quando o painel não definiu nada. */
    public static function doBanco(string $chave): ?string
    {
        $guardado = Setting::get($chave);

        if ($guardado === null || $guardado === '') {
            return null;
        }

        $guardado = (string) $guardado;

        if (! self::eSegredo($chave)) {
            return $guardado;
        }

        try {
            return Crypt::decryptString($guardado);
        } catch (Throwable) {
            // Alguém escreveu texto puro direto no banco. Lê como está, em vez de quebrar a tela.
            return $guardado;
        }
    }

    /** O que o campo vale agora, vindo do banco ou do plano de fundo do ambiente. */
    public static function vigente(string $chave): ?string
    {
        $valor = self::doBanco($chave);

        if ($valor !== null) {
            return $valor;
        }

        $campo = self::campo($chave);

        return blank($campo) ? null : (string) config($campo['config']);
    }

    /** De onde veio o valor que está valendo — é o que a tela mostra ao lado do campo. */
    public static function origem(string $chave): string
    {
        return self::doBanco($chave) !== null ? 'banco' : (blank(self::vigente($chave)) ? 'ausente' : 'ambiente');
    }

    public static function eSegredo(string $chave): bool
    {
        return (bool) (self::campo($chave)['segredo'] ?? false);
    }

    /**
     * Grava um campo. Vazio apaga a linha do banco — e o `.env` volta a valer, que é o
     * "desfazer" honesto: o painel não escreve por cima do ambiente, passa na frente dele.
     *
     * @return bool se houve mudança
     */
    public static function guardar(string $chave, ?string $valor): bool
    {
        $campo = self::campo($chave);
        $valor = trim((string) $valor);

        if ($campo === null) {
            return false;
        }

        if (($campo['cast'] ?? null) === 'int' && $valor !== '') {
            $valor = (string) (int) $valor;
        }

        if ($valor === '') {
            Setting::forget($chave);

            return true;
        }

        $novo = $campo['segredo'] ?? false ? Crypt::encryptString($valor) : $valor;

        if ((string) Setting::get($chave) === $novo) {
            return false;
        }

        Setting::set($chave, $novo);

        return true;
    }

    /** As credenciais do Google Ads que ainda não têm valor em lugar nenhum. */
    public static function faltantesDoGoogleAds(): array
    {
        return collect(self::GRUPOS['google-ads']['campos'])
            ->filter(fn (array $campo): bool => blank(self::vigente($campo['chave'])))
            ->reject(fn (array $campo): bool => (bool) ($campo['opcional'] ?? false))
            ->map(fn (array $campo): string => $campo['label'].' ('.$campo['ambiente'].')')
            ->values()
            ->all();
    }
}
