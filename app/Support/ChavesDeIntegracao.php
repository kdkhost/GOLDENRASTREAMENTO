<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Assistente\Provedores\Fabrica;
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
     * chave na hora; `segredo` cifra no banco e esconde na tela; `cast` diz o tipo que o config
     * espera — `int`, `float`, `bool` (a tela mostra Sim/Não) ou `lista` (a tela recebe separado
     * por vírgula, o config quer array); `opcoes` transforma o campo em seletor e, sem `regras`
     * escritas à mão, vira também a lista fechada que o validador aceita.
     *
     * É método, não constante, por causa do assistente: as opções dos seletores de modelo e de voz
     * vêm de `config/assistente.php` pela mão da `Fabrica`, e uma lista escrita à mão aqui seria
     * uma segunda fonte para o mesmo modelo — a tela e a chamada passariam a discordar.
     *
     * @return array<string, array{rotulo: string, icone: string, descricao: string, campos: array}>
     */
    public static function grupos(): array
    {
        return [
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
                    [
                        'chave' => 'google_business_limite',
                        'config' => 'golden.google_business.limite',
                        'ambiente' => 'GOOGLE_BUSINESS_LIMITE',
                        'label' => 'Avaliações por rodada',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:50'],
                        'placeholder' => '50',
                        'dica' => 'A API entrega até 50 por página — 50 já lê o perfil inteiro de uma vez.',
                    ],
                    [
                        'chave' => 'google_business_nota_minima',
                        'config' => 'golden.google_business.nota_minima',
                        'ambiente' => 'GOOGLE_BUSINESS_NOTA_MINIMA',
                        'label' => 'Nota mínima para virar depoimento',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:5'],
                        'placeholder' => '4',
                        'dica' => 'Nota baixa é chamado, não vitrine: de 4 para cima entra no carrossel.',
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

            'assistente-chaves' => [
                'rotulo' => 'Assistente — chave de cada provedor',
                'icone' => 'fa-robot',
                'descricao' => 'Uma chave por casa de IA. Ficam cifradas no banco e não voltam para a tela depois de salvas. Sem chave, a capacidade daquele provedor fica desligada e o atendimento continua pela equipe — o sistema não morre por falta de credencial.',
                'campos' => [
                    [
                        'chave' => 'assistente_gemini_key',
                        'config' => 'assistente.chaves.gemini',
                        'ambiente' => 'ASSISTENTE_GEMINI_KEY',
                        'label' => 'Chave do Google Gemini',
                        'segredo' => true,
                        'opcional' => true,
                        'dica' => 'Nasce em AI Studio → Obter chave (o mesmo endereço serve para API Key do Google Cloud).',
                    ],
                    [
                        'chave' => 'assistente_openai_key',
                        'config' => 'assistente.chaves.openai',
                        'ambiente' => 'ASSISTENTE_OPENAI_KEY',
                        'label' => 'Chave da OpenAI',
                        'segredo' => true,
                        'opcional' => true,
                        'dica' => 'Nasce em platform.openai.com → API keys. É a única que tem as vozes da lista.',
                    ],
                ],
            ],

            'assistente-modelos' => [
                'rotulo' => 'Assistente — modelo por capacidade',
                'icone' => 'fa-microchip',
                'descricao' => 'Cada capacidade escolhe o seu provedor e o seu modelo: a conversa pode rodar no Gemini porque lê PDF, e a voz no OpenAI porque a voz escolhida está lá. Em branco, vale o padrão da casa — que já atende sozinha.',
                'campos' => [
                    self::campoRodada('conversa', 'Conversar com o cliente', 'ASSISTENTE_MODELO_CONVERSA'),
                    self::campoRodada('visao', 'Ler imagem do chat', 'ASSISTENTE_MODELO_VISAO'),
                    self::campoRodada('documento', 'Ler documento do chat', 'ASSISTENTE_MODELO_DOCUMENTO'),
                    self::campoRodada('transcricao', 'Transcrever áudio', 'ASSISTENTE_MODELO_TRANSCRICAO'),
                    self::campoRodada('voz', 'Responder em áudio', 'ASSISTENTE_MODELO_VOZ'),
                    [
                        'chave' => 'assistente_voz_padrao',
                        'config' => 'assistente.voz_padrao',
                        'ambiente' => 'ASSISTENTE_VOZ',
                        'label' => 'Voz das respostas',
                        'opcional' => true,
                        'opcoes' => Fabrica::opcoesDeVoz(),
                        'dica' => 'A persona pode ter a voz própria na tela do assistente; esta é a voz de quem não escolheu.',
                    ],
                ],
            ],

            'assistente-chat' => [
                'rotulo' => 'Assistente — o que o chat aceita',
                'icone' => 'fa-comments',
                'descricao' => 'Permissão, não capacidade técnica: o provedor sabe ler PDF e mesmo assim o administrador pode não querer arquivo no chat público. Os tetos abaixo são a defesa de uma hospedagem compartilhada.',
                'campos' => [
                    self::campoSimNao(
                        'assistente_audio_entrada',
                        'assistente.permissoes.audio_entrada',
                        'ASSISTENTE_AUDIO_ENTRADA',
                        'O cliente pode mandar áudio',
                        'A gravação aparece no chat e é transcrita antes de virar pergunta.'
                    ),
                    self::campoSimNao(
                        'assistente_imagem',
                        'assistente.permissoes.imagem',
                        'ASSISTENTE_IMAGEM',
                        'O cliente pode mandar imagem',
                        'Foto do equipamento, print da tela, nota fiscal — lida pelo modelo de visão.'
                    ),
                    self::campoSimNao(
                        'assistente_documento',
                        'assistente.permissoes.documento',
                        'ASSISTENTE_DOCUMENTO',
                        'O cliente pode mandar documento',
                        'Só liga com um modelo de leitura de documento escolhido acima; sem ele, a tela avisa em vez de responder vazio.'
                    ),
                    self::campoSimNao(
                        'assistente_audio_saida',
                        'assistente.permissoes.audio_saida',
                        'ASSISTENTE_AUDIO_SAIDA',
                        'O assistente pode responder em áudio',
                        'A resposta escrita sai falada na voz escolhida. Ligado custa por caractere; o texto continua vindo junto.'
                    ),
                    self::campoSimNao(
                        'assistente_so_com_fonte',
                        'assistente.resposta.so_com_fonte',
                        'ASSISTENTE_SO_COM_FONTE',
                        'Só responder o que tem fonte',
                        'É a regra do atendimento: sem lastro no conhecimento aprendido, a máquina se cala e chama a equipe em vez de inventar preço ou prazo.'
                    ),
                    [
                        'chave' => 'assistente_audio_segundos',
                        'config' => 'assistente.midia.audio_segundos_max',
                        'ambiente' => 'ASSISTENTE_AUDIO_SEGUNDOS',
                        'label' => 'Áudio recebido — duração máxima (segundos)',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:600'],
                        'placeholder' => '180',
                    ],
                    self::campoMegaBytes(
                        'assistente_audio_mb',
                        'assistente.midia.audio_mb_max',
                        'ASSISTENTE_AUDIO_MB',
                        'Áudio recebido — tamanho máximo (MB)'
                    ),
                    self::campoMegaBytes(
                        'assistente_imagem_mb',
                        'assistente.midia.imagem_mb_max',
                        'ASSISTENTE_IMAGEM_MB',
                        'Imagem recebida — tamanho máximo (MB)'
                    ),
                    self::campoMegaBytes(
                        'assistente_documento_mb',
                        'assistente.midia.documento_mb_max',
                        'ASSISTENTE_DOCUMENTO_MB',
                        'Documento recebido — tamanho máximo (MB)'
                    ),
                    self::campoMegaBytes(
                        'assistente_resposta_audio_mb',
                        'assistente.midia.resposta_audio_mb_max',
                        'ASSISTENTE_RESPOSTA_AUDIO_MB',
                        'Áudio enviado — tamanho máximo (MB)'
                    ),
                    [
                        'chave' => 'assistente_resposta_max',
                        'config' => 'assistente.resposta.caracteres_max',
                        'ambiente' => 'ASSISTENTE_RESPOSTA_MAX',
                        'label' => 'Resposta — tamanho máximo (caracteres)',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:120', 'max:4000'],
                        'placeholder' => '900',
                        'dica' => 'No chat, resposta comprida é resposta que ninguém lê. Este teto vai escrito na ordem do assistente.',
                    ],
                    [
                        'chave' => 'assistente_historico',
                        'config' => 'assistente.resposta.historico_mensagens',
                        'ambiente' => 'ASSISTENTE_HISTORICO',
                        'label' => 'Falas de histórico levadas a cada chamada',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:2', 'max:40'],
                        'placeholder' => '12',
                        'dica' => 'As últimas falas da conversa que o modelo enxerga antes de responder.',
                    ],
                    [
                        'chave' => 'assistente_minimo_fonte',
                        'config' => 'assistente.resposta.minimo_de_fonte',
                        'ambiente' => 'ASSISTENTE_MINIMO_FONT',
                        'label' => 'Piso de confiança da busca (0 a 1)',
                        'opcional' => true,
                        'cast' => 'float',
                        'regras' => ['numeric', 'min:0', 'max:1'],
                        'placeholder' => '0.34',
                        'dica' => 'O quanto a pergunta precisa cobrir de um item de conhecimento para ele contar como fonte. Subir deixa o assistente mais tímido e mais seguro.',
                    ],
                    [
                        'chave' => 'assistente_itens_fonte',
                        'config' => 'assistente.resposta.itens_de_fonte',
                        'ambiente' => 'ASSISTENTE_ITENS_FONTE',
                        'label' => 'Itens de conhecimento levados por resposta',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:12'],
                        'placeholder' => '5',
                    ],
                ],
            ],

            'assistente-aprendizado' => [
                'rotulo' => 'Assistente — aprender da internet',
                'icone' => 'fa-globe',
                'descricao' => 'Lista fechada de domínios: o robô não sai por aí. Cada item aprendido guarda de onde veio e quando foi lido, porque resposta sem procedência é a mesma coisa que chute.',
                'campos' => [
                    self::campoSimNao(
                        'assistente_leitura',
                        'assistente.aprendizado.ligar',
                        'ASSISTENTE_LEITURA',
                        'Ler as páginas permitidas',
                        'Desligado, o robô não visita endereço nenhum — o conhecimento fica só no que o administrador escreveu.'
                    ),
                    [
                        'chave' => 'assistente_dominios',
                        'config' => 'assistente.aprendizado.dominios',
                        'ambiente' => 'ASSISTENTE_DOMINIOS',
                        'label' => 'Domínios permitidos',
                        'opcional' => true,
                        'cast' => 'lista',
                        'regras' => ['string', 'max:255'],
                        'placeholder' => 'goldenrastreamento.com.br, www.goldenrastreamento.com.br',
                        'dica' => 'Separados por vírgula, só o endereço — sem https e sem caminho.',
                    ],
                    [
                        'chave' => 'assistente_leitura_dias',
                        'config' => 'assistente.aprendizado.a_cada_dias',
                        'ambiente' => 'ASSISTENTE_LEITURA_DIAS',
                        'label' => 'Reler a cada quantos dias',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:90'],
                        'placeholder' => '7',
                    ],
                    [
                        'chave' => 'assistente_leitura_paginas',
                        'config' => 'assistente.aprendizado.paginas_por_leitura',
                        'ambiente' => 'ASSISTENTE_LEITURA_PAGINAS',
                        'label' => 'Páginas por leitura',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1', 'max:200'],
                        'placeholder' => '40',
                    ],
                    [
                        'chave' => 'assistente_leitura_caracteres',
                        'config' => 'assistente.aprendizado.caracteres_por_pagina',
                        'ambiente' => 'ASSISTENTE_LEITURA_CARACTERES',
                        'label' => 'Caracteres aproveitados por página',
                        'opcional' => true,
                        'cast' => 'int',
                        'regras' => ['integer', 'min:1000', 'max:60000'],
                        'placeholder' => '12000',
                    ],
                ],
            ],
        ];
    }

    /** Como a tela chama as duas choices de um interruptor. Escrita uma vez, lida dez. */
    private const SIM_OU_NAO = ['1' => 'Sim', '0' => 'Não'];

    /**
     * O seletor de rodada de uma capacidade. As opções vêm da `Fabrica`, que as lê de
     * `config/assistente.php` — por isso grupos() é método: a tela e a chamada de HTTP só podem
     * oferecer o mesmo modelo, e um segundo listar aqui desandaria na primeira troca de plano.
     */
    private static function campoRodada(string $capacidade, string $label, string $ambiente): array
    {
        return [
            'chave' => 'assistente_rodada_'.$capacidade,
            'config' => 'assistente.rodada.'.$capacidade,
            'ambiente' => $ambiente,
            'label' => $label,
            'opcional' => true,
            'opcoes' => Fabrica::opcoesPara($capacidade),
            'dica' => 'Em branco, vale o padrão da casa: '.config("assistente.capacidades.{$capacidade}.padrao").'.',
        ];
    }

    /** Um interruptor do assistente — select, porque "sem valor" é a terceira opção que o .env ocupa. */
    private static function campoSimNao(string $chave, string $config, string $ambiente, string $label, string $dica): array
    {
        return [
            'chave' => $chave,
            'config' => $config,
            'ambiente' => $ambiente,
            'label' => $label,
            'opcional' => true,
            'cast' => 'bool',
            'opcoes' => self::SIM_OU_NAO,
            'dica' => $dica,
        ];
    }

    /** Um teto de mídia, em megabyte — a unidade com que se pensa um anexo. */
    private static function campoMegaBytes(string $chave, string $config, string $ambiente, string $label): array
    {
        return [
            'chave' => $chave,
            'config' => $config,
            'ambiente' => $ambiente,
            'label' => $label,
            'opcional' => true,
            'cast' => 'float',
            'regras' => ['numeric', 'min:0.5', 'max:100'],
            'dica' => 'Aceita vírgula ou ponto. O teto é medido antes de o arquivo subir: passou disto, a pessoa lê o motivo no chat e nada vai ao servidor.',
        ];
    }

    /** O campo de uma chave, ou `null` se a chave não é de integração. */
    public static function campo(string $chave): ?array
    {
        foreach (self::grupos() as $grupo) {
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
        return collect(self::grupos())
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
                    config([$campo['config'] => self::converte($valor, $campo)]);
                }
            }
        } catch (Throwable) {
            // Sem tabela de configurações, sem sobreposição: o ambiente sozinho conduz.
        }
    }

    /**
     * As regras de validação de um campo, todas daqui. O controle de Configurações só soma
     * `nullable` por cima — é o que mantém "campo vazio devolve o valor ao `.env`" e a lista
     * fechada de um seletor escritas num lugar só, em vez de em três telas.
     *
     * @return array<int, string>
     */
    public static function regras(array $campo): array
    {
        if (isset($campo['regras'])) {
            return $campo['regras'];
        }

        if (isset($campo['opcoes'])) {
            return ['in:'.implode(',', array_keys($campo['opcoes']))];
        }

        return match ($campo['cast'] ?? null) {
            'int' => ['integer', 'min:0', 'max:2147483647'],
            'float' => ['numeric', 'min:0'],
            'bool' => ['in:0,1'],
            default => ['string', 'max:255'],
        };
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

        if (blank($campo)) {
            return null;
        }

        $fundo = config($campo['config']);

        // Uma lista vale no ambiente como array; a tela lê e escreve separado por vírgula.
        return is_array($fundo) ? implode(', ', $fundo) : (string) $fundo;
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
     * O valor entra normalizado pelo `cast` declarado, porque é a string do banco que o
     * `vigente()` devolve à tela: gravar "007" ou " 4,5 " por escrito faria o campo mostrar no
     * dia seguinte algo diferente do que a pessoa digitou.
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

        if ($valor !== '' && isset($campo['cast'])) {
            $valor = self::normaliza($valor, $campo['cast']);
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

    /** A string canônica do banco para o tipo declarado — o avesso do `converte()`. */
    private static function normaliza(string $valor, string $cast): string
    {
        return match ($cast) {
            'int' => (string) (int) $valor,
            'float' => (string) (float) str_replace(',', '.', $valor),
            'bool' => $valor === '1' || strtolower($valor) === 'true' ? '1' : '0',
            'lista' => implode(', ', self::lista($valor)),
            default => $valor,
        };
    }

    /** O valor do banco na forma que o `config()` daquele campo espera. */
    private static function converte(string $valor, array $campo): mixed
    {
        return match ($campo['cast'] ?? null) {
            'int' => (int) $valor,
            'float' => (float) str_replace(',', '.', $valor),
            'bool' => $valor === '1' || strtolower($valor) === 'true',
            'lista' => self::lista($valor),
            default => $valor,
        };
    }

    /** "a, b , c" vira `['a','b','c']`: sem espaço, sem vazio, sem repetido, reindexado. */
    private static function lista(string $valor): array
    {
        $itens = array_map(trim(...), explode(',', $valor));

        return array_values(array_unique(array_filter($itens, fn (string $item): bool => $item !== '')));
    }

    /** As credenciais do Google Ads que ainda não têm valor em lugar nenhum. */
    public static function faltantesDoGoogleAds(): array
    {
        return collect(self::grupos()['google-ads']['campos'])
            ->filter(fn (array $campo): bool => blank(self::vigente($campo['chave'])))
            ->reject(fn (array $campo): bool => (bool) ($campo['opcional'] ?? false))
            ->map(fn (array $campo): string => $campo['label'].' ('.$campo['ambiente'].')')
            ->values()
            ->all();
    }

    /**
     * As chaves das avaliações do Google que ainda não têm valor.
     *
     * Aqui não há `reject` de opcional como no Google Ads: as três são opcionais para *salvar*
     * — o painel aceita meia configuração enquanto a pessoa procura o número da ficha — mas
     * nenhuma leitura acontece sem conta, local e token. Quem chama usa a lista para dizer
     * exatamente o que falta, em vez de mandar procurar três nomes no `.env`.
     */
    public static function faltantesDasAvaliacoes(): array
    {
        return collect(self::grupos()['avaliacoes']['campos'])
            ->filter(fn (array $campo): bool => blank(self::vigente($campo['chave'])))
            ->map(fn (array $campo): string => $campo['label'].' ('.$campo['ambiente'].')')
            ->values()
            ->all();
    }
}
