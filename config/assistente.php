<?php

/*
|--------------------------------------------------------------------------
| Assistente do painel — provedores, capacidades e limites
|--------------------------------------------------------------------------
|
| É a fonte única de tudo que o bot pergunta ao mundo exterior: quais provedores de IA o
| sistema sabe usar, o que cada um deles entrega por capacidade, que modelo está rodando hoje
| em cada capacidade, o que o chat aceita receber, quanto tempo uma chamada tem e como se
| escala quando não há fonte. Nenhuma view, serviço ou comando escreve um nome de modelo, um
| endereço de API ou um limite por conta própria — todos perguntam daqui.
|
| As chaves (`chaves`) têm plano de fundo no `.env` e manda o banco: a tela de
| Sistema → Configurações → Chaves de integração grava por cima (`ChavesDeIntegracao::aplicar()`),
| então trocar de provedor ou de modelo é escolha de painel, não de servidor. Um provedor sem
| chave não é erro: a capacidade dele fica desligada, a tela diz qual falta, e o atendimento
| continua pelas mãos da equipe — o código nunca sai do sistema por falta de credencial.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | O que o assistente está ligado hoje
    |--------------------------------------------------------------------------
    | Duas chaves por casa, uma por provedor. São segredos de API (o token que cobra contas),
    | cifram no banco e nunca voltam para a tela depois de gravados.
    */
    'chaves' => [
        'gemini' => env('ASSISTENTE_GEMINI_KEY'),
        'openai' => env('ASSISTENTE_OPENAI_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provedores conhecidos
    |--------------------------------------------------------------------------
    | O endereço de cada API e como a tela o chama. Acrescentar um provedor novo é escrever
    | aqui, criar a classe em `App\Services\Assistente\Provedores` e dizer o que ela entrega
    | em `capacidades` — o resto do sistema não muda.
    |
    | `modelo_por_capacidade` é o que a fábrica usa para responder "este provedor sabe fazer
    | isto?" antes de gastar uma chamada.
    */
    'provedores' => [
        'gemini' => [
            'rotulo' => 'Google Gemini',
            'base' => 'https://generativelanguage.googleapis.com/v1beta',
            'dica' => 'Um endereço só para conversar, ver imagem, ler documento, transcrever áudio e falar.',
        ],
        'openai' => [
            'rotulo' => 'OpenAI',
            'base' => 'https://api.openai.com/v1',
            'dica' => 'Conversa e visão no chat, áudio na rota própria de transcrição, voz na de síntese.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacidades — o que o assistente faz, uma a uma
    |--------------------------------------------------------------------------
    | Cada linha é uma pergunta que o sistema sabe fazer a uma IA. O administrador escolhe o
    | provedor e o modelo *por capacidade*: a conversa pode rodar no Gemini porque é o que lê
    | PDF, e a voz no OpenAI porque a voz escolhida está lá.
    |
    | `modelos` lista o que a tela oferece (código => rótulo em português); `padrao` é a rodada
    | de quem ainda não escolheu nada; `segundos` é o prazo da chamada — compartilhado sem
    | CPU sobrando, uma IA que demora mais que isto já não é resposta para quem está no chat.
    | `texto` é o que se manda junto com a mídia (a pergunta para o áudio, o que olhar na foto).
    */
    'capacidades' => [

        'conversa' => [
            'rotulo' => 'Conversar com o cliente',
            'descricao' => 'A resposta que sai no chat. É a capacidade principal: todo o resto existe para alimentá-la.',
            'padrao' => 'gemini/gemini-2.5-flash',
            'segundos' => 45,
            'modelos' => [
                'gemini' => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash — rápido, lê imagem, documento e áudio',
                    'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite — o mais barato, para volume',
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro — resposta mais longa e raciocínio',
                ],
                'openai' => [
                    'gpt-4o-mini' => 'GPT-4o mini — barato, lê imagem',
                    'gpt-4o' => 'GPT-4o — mais capricho na resposta',
                    'gpt-4.1-mini' => 'GPT-4.1 mini — equilíbrio entre custo e contexto',
                ],
            ],
        ],

        'visao' => [
            'rotulo' => 'Ler imagem',
            'descricao' => 'Foto do equipamento, print da tela, nota fiscal. O texto que o provedor devolve é o que entra na conversa.',
            'padrao' => 'gemini/gemini-2.5-flash',
            'segundos' => 60,
            'modelos' => [
                'gemini' => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro — para imagem detalhada',
                ],
                'openai' => [
                    'gpt-4o-mini' => 'GPT-4o mini',
                    'gpt-4o' => 'GPT-4o',
                ],
            ],
        ],

        'documento' => [
            'rotulo' => 'Ler documento',
            'descricao' => 'PDF, contrato, orçamento. Onde o provedor não lê o arquivo, a capacidade fica desligada — e a tela avisa em vez de mandar um texto vazio.',
            'padrao' => 'gemini/gemini-2.5-flash',
            'segundos' => 90,
            'modelos' => [
                'gemini' => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro — documento longo',
                ],
            ],
        ],

        'transcricao' => [
            'rotulo' => 'Transcrever áudio',
            'descricao' => 'O cliente manda voz, o sistema entende. A transcrição fica gravada na mensagem — é a prova do que foi dito.',
            'padrao' => 'gemini/gemini-2.5-flash',
            'segundos' => 60,
            'modelos' => [
                'gemini' => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash — áudio direto na conversa',
                ],
                'openai' => [
                    'whisper-1' => 'Whisper — transcrição pura',
                    'gpt-4o-transcribe' => 'GPT-4o Transcribe — mais preciso em ruído',
                ],
            ],
        ],

        'voz' => [
            'rotulo' => 'Responder em áudio',
            'descricao' => 'A resposta escrita sai falada, na voz que o painel escolher. A clonagem da voz do administrador é um passo depois deste, e só entra com consentimento gravado.',
            'padrao' => 'openai/gpt-4o-mini-tts',
            'segundos' => 60,
            'modelos' => [
                'gemini' => [
                    'gemini-2.5-flash-preview-tts' => 'Gemini 2.5 Flash TTS',
                    'gemini-2.5-pro-preview-tts' => 'Gemini 2.5 Pro TTS',
                ],
                'openai' => [
                    'gpt-4o-mini-tts' => 'GPT-4o mini TTS',
                    'tts-1' => 'TTS-1 — a mais estável',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | A rodada de cada capacidade
    |--------------------------------------------------------------------------
    | O que o painel escolheu, no formato `provedor/modelo`. Vazio em todos os níveis — banco,
    | ambiente e escolha — é `capacidades.<capacidade>.padrao` que conduz, e é assim que o
    | sistema atende na primeira instalação sem ninguém ter configurado nada.
    |
    | Escrever aqui no `.env` é atalho de quem administra o servidor; o painel grava por cima
    | no banco, e o banco manda. Trocar de provedor no meio do mês é uma linha na tela de
    | chaves, não uma deploy.
    */
    'rodada' => [
        'conversa' => env('ASSISTENTE_MODELO_CONVERSA'),
        'visao' => env('ASSISTENTE_MODELO_VISAO'),
        'documento' => env('ASSISTENTE_MODELO_DOCUMENTO'),
        'transcricao' => env('ASSISTENTE_MODELO_TRANSCRICAO'),
        'voz' => env('ASSISTENTE_MODELO_VOZ'),
    ],

    /*
    |--------------------------------------------------------------------------
    | As vozes que a tela oferece
    |--------------------------------------------------------------------------
    | Uma linha por provedor, no formato `provedor:voz`, porque a voz só existe dentro do
    | provedor que fala. O valor vigente vive em `assistente.voz_padrao` e é o que o chat usa
    | quando a persona não escolheu uma própria.
    */
    'vozes' => [
        'openai' => [
            'alloy' => 'Alloy — neutra',
            'echo' => 'Echo — grave',
            'fable' => 'Fable — British, levemente informal',
            'onyx' => 'Onyx — profunda',
            'nova' => 'Nova — clara, boa para atendimento',
            'shimmer' => 'Shimmer — feminina',
        ],
        'gemini' => [
            'Kore' => 'Kore — firme',
            'Puck' => 'Puck — animada',
            'Zephyr' => 'Zephyr — suave',
            'Charon' => 'Charon — grave',
            'Leda' => 'Leda — jovem',
            'Orus' => 'Orus — locução',
        ],
    ],

    'voz_padrao' => env('ASSISTENTE_VOZ', 'openai:nova'),

    /*
    |--------------------------------------------------------------------------
    | O que o chat aceita receber
    |--------------------------------------------------------------------------
    | Quatro interruptores, todos do painel. São permissão, não capacidade técnica: o provedor
    | sabe ler PDF e mesmo assim o administrador pode não querer arquivo no chat público.
    | Trocar isto na tela não exige mexer em código nem reiniciar nada.
    */
    'permissoes' => [
        'audio_entrada' => (bool) env('ASSISTENTE_AUDIO_ENTRADA', true),
        'imagem' => (bool) env('ASSISTENTE_IMAGEM', true),
        'documento' => (bool) env('ASSISTENTE_DOCUMENTO', false),
        'audio_saida' => (bool) env('ASSISTENTE_AUDIO_SAIDA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de mídia
    |--------------------------------------------------------------------------
    | A hospedagem é compartilhada: arquivo grande no servidor e chamada de IA longa são as
    | duas maneiras de derrubar um atendimento. Os tetos abaixo são a defesa, e a tela de chaves
    | os edita em megabyte — que é a unidade com que se pensa um anexo. A troca de megabyte por
    | byte acontece num lugar só, na classe `Midia`, que lê estas chaves para o upload aceito e
    | para a resposta em áudio que o painel manda.
    */
    'midia' => [
        'audio_segundos_max' => (int) env('ASSISTENTE_AUDIO_SEGUNDOS', 180),
        'audio_mb_max' => (float) env('ASSISTENTE_AUDIO_MB', 4),
        'imagem_mb_max' => (float) env('ASSISTENTE_IMAGEM_MB', 4),
        'documento_mb_max' => (float) env('ASSISTENTE_DOCUMENTO_MB', 8),
        'resposta_audio_mb_max' => (float) env('ASSISTENTE_RESPOSTA_AUDIO_MB', 8),
        'imagem_extensoes' => ['jpg', 'jpeg', 'png', 'webp'],
        'audio_extensoes' => ['webm', 'ogg', 'mp3', 'm4a', 'wav'],
        'documento_extensoes' => ['pdf', 'txt', 'csv', 'docx'],
        'pasta' => 'assistente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Responder e escalar
    |--------------------------------------------------------------------------
    | A regra que manda no atendimento: o assistente só responde o que tem fonte. `so_com_fonte`
    | ligado é o que faz a máquina se calar diante de uma pergunta sem lastro e chamar a
    | equipe, em vez de inventar preço ou prazo. `minimo_de_fonte` é o piso de confiança da
    | busca léxica — abaixo dele, conta como fonte nenhuma.
    */
    'resposta' => [
        'so_com_fonte' => (bool) env('ASSISTENTE_SO_COM_FONTE', true),
        'minimo_de_fonte' => (float) env('ASSISTENTE_MINIMO_FONT', 0.34),
        'itens_de_fonte' => (int) env('ASSISTENTE_ITENS_FONTE', 5),
        'caracteres_max' => (int) env('ASSISTENTE_RESPOSTA_MAX', 900),
        'historico_mensagens' => (int) env('ASSISTENTE_HISTORICO', 12),
        'escala_para' => 'equipe',
        'avisar_por_email' => (bool) env('ASSISTENTE_AVISO_EMAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aprender da internet
    |--------------------------------------------------------------------------
    | Lista fechada de domínios: o robô não sai por aí. Cada item aprendido guarda de onde
    | veio e quando foi lido, porque resposta sem procedência é a mesma coisa que chute.
    | A periodicidade está em dias porque o cron da hospedagem roda de minuto em minuto e é
    | mais barato consultar esta chave no comando do que escrever a agenda em dois lugares.
    */
    'aprendizado' => [
        'dominios' => ['goldenrastreamento.com.br', 'www.goldenrastreamento.com.br'],
        'a_cada_dias' => (int) env('ASSISTENTE_LEITURA_DIAS', 7),
        'paginas_por_leitura' => (int) env('ASSISTENTE_LEITURA_PAGINAS', 40),
        'caracteres_por_pagina' => (int) env('ASSISTENTE_LEITURA_CARACTERES', 12_000),
        'ligar' => (bool) env('ASSISTENTE_LEITURA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | A persona, nos padrões de quem ainda não preencheu a tela
    |--------------------------------------------------------------------------
    | Os valores iniciais da ficha do assistente (tabela `assistente`). Depois do primeiro
    | salvamento em /admin/assistente é a ficha que manda — aqui está só o ponto de partida,
    | e é daqui que a tela lê o placeholder.
    */
    'persona' => [
        'nome' => 'Ana',
        'cargo' => 'Assistente virtual da Golden Rastreamento',
        'saudacao' => 'Olá! Sou a Ana, assistente da Golden Rastreamento. Posso ajudar com plano, instalação, cobertura ou o status do seu aparelho.',
        'tom' => 'amigavel',
        'regras' => "Responda em português do Brasil, com frases curtas.\nNunca invente preço, prazo, cobertura ou especificação técnica: sem fonte, diga que vai chamar alguém da equipe.\nNão prometa instalação, bloqueio ou garantia em nome da empresa.\nNão peça documento, senha ou dados de pagamento pelo chat.",
    ],

    /*
    |--------------------------------------------------------------------------
    | Os textos da bolha flutuante
    |--------------------------------------------------------------------------
    | O que o visitante lê no botão e dentro do balão, antes de qualquer resposta. O painel
    | edita cada um na ficha; em branco, vale o padrão. `:nome` na dica vira o nome da ficha.
    | A posição não é escolha: o robô fica logo acima do WhatsApp, no canto do site e do funil.
    */
    'bolha' => [
        'dica' => 'Falar com :nome',
        'status' => 'Respondendo agora',
        'placeholder' => 'Escreva sua dúvida…',
        'humano' => 'Prefero falar com uma pessoa →',
        'icone' => 'fa-comments',
    ],

    /* Os ícones que a ficha oferece quando não há rosto enviado. */
    'icones_bolha' => [
        'fa-comments' => 'Balões de conversa',
        'fa-headset' => 'Atendimento com fone',
        'fa-robot' => 'Robô',
        'fa-user-tie' => 'Consultor de vendas',
    ],

    /* Os tons de fala que a tela oferece; a persona guarda um código e a view pede o rótulo. */
    'tons' => [
        'amigavel' => 'Amigável',
        'formal' => 'Formal',
        'direto' => 'Direto e técnico',
        'comercial' => 'Comercial',
    ],

    /*
    |--------------------------------------------------------------------------
    | Vocabulário das conversas
    |--------------------------------------------------------------------------
    | Canal (de onde a conversa chegou), status (em que pé está) e autor e tipo de cada fala.
    | São as listas fechadas das tabelas `conversas` e `conversas_mensagens`; o selo colorido
    | de cada uma vive em `golden.tons_status`, nunca aqui.
    */
    'canais' => [
        'site' => 'Site',
        'funil' => 'Página de funil',
        'chamado' => 'Chamado do portal',
    ],

    'status_conversa' => [
        'aberta' => 'Aberta',
        'aguardando_equipe' => 'Aguardando a equipe',
        'resolvida' => 'Resolvida',
        'fechada' => 'Fechada',
    ],

    'autores' => [
        'cliente' => 'Cliente',
        'assistente' => 'Assistente',
        'equipe' => 'Equipe',
        'sistema' => 'Sistema',
    ],

    'tipos_mensagem' => [
        'texto' => 'Texto',
        'audio' => 'Áudio',
        'imagem' => 'Imagem',
        'arquivo' => 'Arquivo',
    ],

    'origens_conhecimento' => [
        'painel' => 'Escrito pelo administrador',
        'internet' => 'Lido da internet',
        'site' => 'Página do site',
        'faq' => 'FAQ do site',
        'funil' => 'Página de funil',
    ],
];
