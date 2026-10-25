<?php

return [
    'marca' => [
        'nome' => 'Golden Rastreamento',
        'slogan' => 'Rastreamento e monitoramento veicular, sua segurança nossa prioridade',
        'cidade' => 'Três Pontas · Minas Gerais',
    ],

    /*
    |--------------------------------------------------------------------------
    | Imagens do site (CMS)
    |--------------------------------------------------------------------------
    | Cada fotografia exposta no site é um "slot" daqui, organizado por página.
    | 'padrao' é o arquivo empacotado no repositório; quando o painel envia uma
    | imagem nova (Admin → Conteúdo → Imagens do site), ela passa a valer e o
    | arquivo do pacote vira apenas o fallback. As views nunca citam caminho de
    | imagem: chamam site_img('slot'), que decide entre o enviado e o padrão.
    |
    | largura/altura alimentam os atributos do <img> (evitam o pulo de layout);
    | dimensao/proporcao são a orientação que o campo de envio repete; alt é a
    | descrição alternativa que vai no <img>, também editável no painel.
    | 'padrao' null é a foto que ainda não existe: a seção só aparece no ar
    | depois que o painel envia um arquivo (é o caso dos cartazes de página).
    */
    'imagens' => [
        'marca_logo' => [
            'pagina' => 'Todo o site', 'titulo' => 'Logo', 'padrao' => 'img/logo-golden.png',
            'alt' => 'Golden Rastreamento',
            'largura' => 512, 'altura' => 512, 'dimensao' => '512 × 512 px', 'proporcao' => '1:1',
            'dica' => 'Aparece no topo, no rodapé, no menu do painel e no compartilhamento.',
        ],
        'favicon' => [
            'pagina' => 'Todo o site', 'titulo' => 'Favicon', 'padrao' => 'img/favicon.png',
            'alt' => 'Símbolo da Golden Rastreamento',
            'largura' => 64, 'altura' => 64, 'dimensao' => '64 × 64 px', 'proporcao' => '1:1',
            'dica' => 'A mesma nos dois lugares: site público, painel e tela de acesso.',
        ],
        'site_compartilhar' => [
            'pagina' => 'Todo o site', 'titulo' => 'Cartão de compartilhamento', 'padrao' => 'img/logo-golden.png',
            'alt' => 'Golden Rastreamento',
            'largura' => 1200, 'altura' => 630, 'dimensao' => '1200 × 630 px', 'proporcao' => '1,91:1',
            'dica' => 'A imagem que o WhatsApp e as redes mostram ao compartilhar um link.',
        ],

        'home_hero' => [
            'pagina' => 'Home', 'titulo' => 'Foto de fundo do herói', 'padrao' => 'img/home/hero-monitoramento.jpg',
            'alt' => 'Analista da Golden acompanhando os veículos no mapa em dois monitores',
            'largura' => 1600, 'altura' => 914, 'dimensao' => '1600 × 914 px', 'proporcao' => '16:9',
            'dica' => 'Abre o site: a central de monitoramento. Escurecida por cima, use foto clara no centro.',
        ],
        'home_plataforma' => [
            'pagina' => 'Home · Aplicativo e plataforma', 'titulo' => 'Foto de fundo da plataforma', 'padrao' => 'img/home/plataforma-monitor.jpg',
            'alt' => 'Plataforma Golden de monitoramento aberta em um computador sobre uma mesa de madeira',
            'largura' => 1600, 'altura' => 914, 'dimensao' => '1600 × 914 px', 'proporcao' => '16:9',
            'dica' => 'Faixa em largura total: o computador fica à direita e o texto, sobre o lado esquerdo escuro.',
        ],
        'home_equipamento' => [
            'pagina' => 'Home · Rastreador J16', 'titulo' => 'Foto de fundo do rastreador', 'padrao' => 'img/home/rastreador-j16.jpg',
            'alt' => 'Rastreador veicular J16 4G/2G com chicote de instalação sobre uma mesa de madeira',
            'largura' => 1600, 'altura' => 914, 'dimensao' => '1600 × 914 px', 'proporcao' => '16:9',
            'dica' => 'Faixa em largura total: o equipamento fica à esquerda e o texto, sobre o lado direito escuro.',
        ],
        'home_cta' => [
            'pagina' => 'Home', 'titulo' => 'Foto da chamada final', 'padrao' => 'img/home/estrada-por-do-sol.jpg',
            'alt' => 'Caminhão em estrada ao pôr do sol',
            'largura' => 1600, 'altura' => 914, 'dimensao' => '1600 × 914 px', 'proporcao' => '16:9',
            'dica' => 'Última faixa do site, sob o texto de contato.',
        ],

        'segmento_motorista' => [
            'pagina' => 'Home · Para quem é', 'titulo' => 'Motoristas de aplicativo', 'padrao' => 'img/home/segmento-motorista.jpg',
            'alt' => 'Motorista de aplicativo no carro com o celular no suporte',
            'largura' => 720, 'altura' => 540, 'dimensao' => '720 × 540 px', 'proporcao' => '4:3',
        ],
        'segmento_particular' => [
            'pagina' => 'Home · Para quem é', 'titulo' => 'Veículos particulares', 'padrao' => 'img/home/segmento-particular.jpg',
            'alt' => 'Carro particular estacionado em frente a uma casa',
            'largura' => 720, 'altura' => 540, 'dimensao' => '720 × 540 px', 'proporcao' => '4:3',
        ],
        'segmento_frota' => [
            'pagina' => 'Home · Para quem é', 'titulo' => 'Frotas empresariais', 'padrao' => 'img/home/segmento-frota.jpg',
            'alt' => 'Frota de veículos utilitários da empresa em pátio',
            'largura' => 720, 'altura' => 540, 'dimensao' => '720 × 540 px', 'proporcao' => '4:3',
        ],
        'segmento_locadora' => [
            'pagina' => 'Home · Para quem é', 'titulo' => 'Locadoras de veículos', 'padrao' => 'img/home/segmento-locadora.jpg',
            'alt' => 'Fileira de carros de locadora prontos para retirada',
            'largura' => 720, 'altura' => 540, 'dimensao' => '720 × 540 px', 'proporcao' => '4:3',
        ],
        'segmento_rural' => [
            'pagina' => 'Home · Para quem é', 'titulo' => 'Operações rurais', 'padrao' => 'img/home/segmento-rural.jpg',
            'alt' => 'Trator e caminhão em lavoura na zona rural',
            'largura' => 720, 'altura' => 540, 'dimensao' => '720 × 540 px', 'proporcao' => '4:3',
        ],

        // Cartaz de cada página fixa: não existe arquivo no pacote, então o fundo
        // fotográfico só entra no site quando o painel envia a foto da página.
        'hero_solucoes' => [
            'pagina' => 'Soluções', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Veículo monitorado pela plataforma Golden',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
            'dica' => 'Fica atrás do título da página, escurecido. Sem envio, o fundo é o degradê padrão.',
        ],
        'hero_segmentos' => [
            'pagina' => 'Para quem é', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Motoristas e gestores de frota em seus veículos',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_planos' => [
            'pagina' => 'Planos e kits', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Kits de rastreadores J16 prontos para envio',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_como_funciona' => [
            'pagina' => 'Como funciona', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Instalador posicionando o rastreador sob o painel do veículo',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_contato' => [
            'pagina' => 'Contato', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Equipe comercial da Golden atendendo pelo WhatsApp',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_faq' => [
            'pagina' => 'Dúvidas frequentes', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Atendente da Golden respondendo dúvidas de clientes',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_videos' => [
            'pagina' => 'Vídeos tutoriais', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Cliente assistindo a um tutorial do aplicativo Golden no celular',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
        'hero_privacidade' => [
            'pagina' => 'Política de Privacidade', 'titulo' => 'Cartaz do topo da página', 'padrao' => null,
            'alt' => 'Escudo de proteção sobre dados do veículo',
            'largura' => 1600, 'altura' => 720, 'dimensao' => '1600 × 720 px', 'proporcao' => '20:9',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Páginas fixas do site
    |--------------------------------------------------------------------------
    | A abertura (cartaz) de cada página do site, editável em
    | Admin → Conteúdo → Textos das páginas: o padrão mora aqui e a edição do
    | painel entra por cima, na chave paginas_site.<chave>.<campo>. Deixar um
    | campo em branco no painel é o mesmo que apagar a chave — volta o padrão.
    | A home fica fora: a copy dela é composta no layout aprovado (com <em> e
    | quebras), e o que se gerencia dela são as fotos.
    */
    'paginas_site' => [
        'solucoes' => [
            'rota' => 'site.solucoes', 'nome' => 'Soluções', 'rotulo' => 'Soluções',
            'eyebrow' => 'Funcionalidades',
            'titulo' => 'Tudo o que a sua operação precisa',
            'subtitulo' => 'Autorrastreamento e gestão em um só lugar. Você acompanha os veículos e utiliza as ferramentas disponível na plataforma.',
        ],
        'segmentos' => [
            'rota' => 'site.segmentos', 'nome' => 'Para quem é', 'rotulo' => 'Para quem é',
            'eyebrow' => null,
            'titulo' => 'Soluções para quem faz o negócio acontecer',
            'subtitulo' => 'Atendemos desde motoristas que utilizam o próprio veículo até empresas com grandes frotas, sempre com soluções adequadas a cada tipo de operação.',
        ],
        'planos' => [
            'rota' => 'site.planos', 'nome' => 'Planos e kits', 'rotulo' => 'Planos',
            'eyebrow' => null,
            'titulo' => 'Escolha o tamanho da sua frota',
            'subtitulo' => 'Equipamentos e mensalidade informados separadamente na proposta comercial. A instalação é por conta do cliente.',
        ],
        'como_funciona' => [
            'rota' => 'site.como-funciona', 'nome' => 'Como funciona', 'rotulo' => 'Como funciona',
            'eyebrow' => null,
            'titulo' => 'Da aquisição ao controle, de forma simples',
            'subtitulo' => 'Quatro passos para colocar sua frota ou veículo na plataforma Golden.',
        ],
        'faq' => [
            'rota' => 'site.faq', 'nome' => 'Dúvidas frequentes', 'rotulo' => 'Dúvidas',
            'eyebrow' => null,
            'titulo' => 'Perguntas que mais recebemos',
            'subtitulo' => 'Tire suas dúvidas sobre funcionamento, instalação, planos e muito mais.',
        ],
        'videos' => [
            'rota' => 'site.videos', 'nome' => 'Vídeos tutoriais', 'rotulo' => 'Vídeos',
            'eyebrow' => null,
            'titulo' => 'Aprenda a usar a Golden em vídeo',
            'subtitulo' => 'Tutoriais curtos do aplicativo, da plataforma, das cercas virtuais e das faturas.',
        ],
        'contato' => [
            'rota' => 'site.contato', 'nome' => 'Contato', 'rotulo' => 'Contato',
            'eyebrow' => null,
            'titulo' => 'Vamos encontrar a solução para seus veículos?',
            'subtitulo' => 'Solicite uma proposta para sua frota ou fale com nossa equipe. Retornamos rapidamente.',
        ],
        'privacidade' => [
            'rota' => 'site.privacidade', 'nome' => 'Política de Privacidade', 'rotulo' => 'Privacidade',
            'eyebrow' => null,
            'titulo' => 'Política de Privacidade',
            'subtitulo' => 'Compromisso com a proteção dos seus dados pessoais, conforme a LGPD (Lei nº 13.709/2018).',
        ],
    ],

    'contato' => [
        'endereco' => 'R. Pref. Olinto Réis Campos, 104 - Centro',
        'cidade_uf' => 'Três Pontas - MG',
        'cep' => '37185-016',
        'telefones' => ['(19) 99710-1483', '(19) 97139-5129'],
    ],

    'whatsapp' => env('GOLDEN_WHATSAPP', '5519997101483'),
    'email' => env('GOLDEN_EMAIL', 'comercial@goldenrastreamento.com.br'),

    // Página de listagem do painel: quantos registros cabem em uma página. As 12 listagens
    // paginam por aqui; nenhuma tela escolhe número próprio.
    'itens_por_pagina' => 20,

    // Cor de cada status do painel, agrupada por domínio. Os tons são os utilitários .tone-*
    // do tema, que já têm par no claro e no escuro. Nenhuma view decide cor: ela pergunta
    // o tom pela função tom_por_status(), que lê esta lista.
    'tons_status' => [
        'funil' => [
            'novo' => 'blue', 'contatado' => 'amber', 'proposta' => 'violet',
            'vendido' => 'green', 'perdido' => 'red',
        ],
        'cliente' => ['prospecto' => 'violet', 'ativo' => 'green', 'inativo' => 'red'],
        'pedido' => [
            'pendente' => 'amber', 'aguardando_pagamento' => 'gold', 'pago' => 'blue',
            'separando' => 'teal', 'enviado' => 'violet', 'entregue' => 'green', 'cancelado' => 'red',
        ],
        'usuario' => ['ativo' => 'green', 'inativo' => 'red'],
        // Chamado do portal (Ticket::STATUS): o painel e o cliente usam o mesmo selo.
        'chamado' => [
            'aberto' => 'amber', 'em_analise' => 'blue', 'aguardando_cliente' => 'gold',
            'resolvido' => 'green', 'fechado' => 'neutral',
        ],
        // Prioridade do chamado: urgente vermelha, baixa neutra (mesma régua dos dois lados).
        'prioridade' => ['baixa' => 'neutral', 'normal' => 'blue', 'alta' => 'gold', 'urgente' => 'red'],
        // Contrato do cliente (Contract::STATUS): rascunho ainda não é documento.
        'contrato' => ['rascunho' => 'neutral', 'assinado' => 'blue', 'vigente' => 'green', 'encerrado' => 'red'],
        // Estado do estoque, calculado por Product::estadoEstoque() — o painel e a vitrine
        // usam a mesma régua: sem controle, zerado, abaixo do mínimo ou abastecido.
        'estoque' => ['livre' => 'neutral', 'zerado' => 'red', 'baixo' => 'gold', 'ok' => 'green'],
        // Movimentação do estoque (StockMovement::TIPOS): o saldo subiu, desceu ou foi conferido.
        'movimento' => ['entrada' => 'green', 'saida' => 'red', 'ajuste' => 'gold'],
        // Selo de cada fotografia do site (SiteContentController::imagens): qual arquivo está no ar.
        'slot_imagem' => ['enviada' => 'gold', 'pacote' => 'neutral', 'ausente' => 'neutral'],
        // Categoria da galeria de vídeos (config 'video_categorias'): o mesmo tom veste o selo
        // do painel e o ponto de cor do cartão no site.
        'video' => [
            'aplicativo' => 'blue', 'plataforma' => 'teal', 'frotas' => 'violet',
            'bloqueio' => 'amber', 'financeiro' => 'green',
        ],
        // Origem da visita/lead, resolvida por Atribuidor::classificar() a partir das chaves
        // de 'canais' logo abaixo. O tom é o mesmo no cartão de atribuição e na ficha do lead.
        'canal' => [
            'anuncio' => 'red', 'rede_social' => 'blue', 'busca_organica' => 'green', 'busca_ia' => 'gold',
            'indicacao' => 'violet', 'email' => 'teal', 'parceiro' => 'teal',
            'direto' => 'neutral', 'indexador' => 'neutral', 'desconhecido' => 'neutral',
        ],
        'fallback' => 'neutral',
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribuição de tráfego (de onde veio cada clique, view e cadastro)
    |--------------------------------------------------------------------------
    | Uma régua só para o classificador (App\Services\Atribuidor), o painel
    | /admin/atribuicao e os selos do lead. Nada de rede social ou buscador fica
    | escrito dentro de um controller: entra por aqui.
    */

    // Os canais que o sistema sabe identificar. 'pago' separa tráfego comprado de
    // tráfego gratuito na hora de somar; 'convencional' é o que entra nos KPIs de
    // crescimento — robô de indexador visita o site, mas não é um cliente.
    'canais' => [
        'anuncio' => ['rotulo' => 'Anúncio pago', 'icone' => 'fa-bullhorn', 'pago' => true, 'valido' => true],
        'rede_social' => ['rotulo' => 'Rede social', 'icone' => 'fa-share-nodes', 'pago' => false, 'valido' => true],
        'busca_organica' => ['rotulo' => 'Busca orgânica', 'icone' => 'fa-magnifying-glass', 'pago' => false, 'valido' => true],
        'busca_ia' => ['rotulo' => 'Busca por IA', 'icone' => 'fa-robot', 'pago' => false, 'valido' => true],
        'indicacao' => ['rotulo' => 'Indicação', 'icone' => 'fa-handshake-angle', 'pago' => false, 'valido' => true],
        'email' => ['rotulo' => 'E-mail', 'icone' => 'fa-envelope', 'pago' => false, 'valido' => true],
        'parceiro' => ['rotulo' => 'Site parceiro', 'icone' => 'fa-link', 'pago' => false, 'valido' => true],
        'direto' => ['rotulo' => 'Direto', 'icone' => 'fa-location-crosshairs', 'pago' => false, 'valido' => true],
        'indexador' => ['rotulo' => 'Robô indexador', 'icone' => 'fa-robot', 'pago' => false, 'valido' => false],
        'desconhecido' => ['rotulo' => 'Não identificado', 'icone' => 'fa-circle-question', 'pago' => false, 'valido' => true],
    ],

    // utm_medium que provam tráfego comprado (e a rede quando o utm_source não diz).
    'meios_pagos' => [
        'cpc' => 'google', 'ppc' => 'google', 'paid' => null, 'paidsearch' => null, 'paid-search' => null,
        'paidsocial' => null, 'paid_social' => null, 'paid-social' => null, 'paidmedia' => null,
        'display' => null, 'banner' => null, 'ads' => null, 'ad' => null, 'video_ads' => null,
        'shopping' => 'google', 'mlm' => null, 'paid_video' => null, 'paidorganic' => null,
    ],

    // utm_medium de rede social não paga. O que tiver 'paid' no meio vira anúncio da mesma rede.
    'meios_sociais' => [
        'social' => null, 'organic-social' => null, 'organic_social' => null, 'social-network' => null,
        'socialnet' => null, 'reels' => 'instagram', 'shorts' => 'youtube', 'stories' => 'instagram',
        'post' => null, 'status' => 'whatsapp', 'whatsapp-status' => 'whatsapp', 'pin' => 'pinterest',
        'tweet' => 'x', 'newsletter_social' => null, 'groups' => 'facebook',
    ],

    // utm_medium de busca gratuita e de indicação.
    'meios_busca' => ['organic', 'organic-search', 'organic_search', 'seo', 'search', 'natural', 'web-search'],
    'meios_ia' => ['ai-search', 'ai_search', 'ai', 'llm', 'chatbot', 'answer-engine', 'answer_engine', 'geo', 'gen-ai', 'genai'],
    'meios_email' => ['email', 'e-mail', 'newsletter', 'ml', 'mailing-list', 'mailing_list', 'crm'],
    'meios_indicacao' => ['referral', 'refer', 'referencia', 'indicacao', 'indicated', 'word-of-mouth', 'wom', 'influencer', 'creator', 'ambassador'],

    // Identificadores de clique que as plataformas de anúncio colocam na URL. Ter um
    // destes na landing é a prova mais forte de que o clique veio de um anúncio pago.
    'ids_clique' => [
        'gclid' => 'google', 'gbraid' => 'google', 'wbraid' => 'google', 'dclid' => 'google', 'gaplid' => 'google',
        'msclkid' => 'bing', 'ttclid' => 'tiktok', 'twclid' => 'x', 'yclid' => 'yahoo',
        'li_fat_id' => 'linkedin', 'igsh' => 'instagram', 'igshid' => 'instagram', 'fbclid' => 'facebook',
        'epik' => 'pinterest', 'sccid' => 'snapchat', 'arc_cid' => null, 'oemcid' => 'google',
    ],

    // Redes sociais por trecho de host do referer / do utm_source.
    'redes_sociais' => [
        'facebook' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook', 'messenger' => 'messenger',
        'instagram' => 'instagram', 'tiktok' => 'tiktok', 'linkedin' => 'linkedin',
        'twitter' => 'x', 'x.com' => 'x', 't.co' => 'x', 'threads' => 'threads',
        'youtube' => 'youtube', 'youtu.be' => 'youtube', 'pinterest' => 'pinterest',
        'whatsapp' => 'whatsapp', 'wa.me' => 'whatsapp', 'telegram' => 'telegram', 't.me' => 'telegram',
        'reddit' => 'reddit', 'snapchat' => 'snapchat', 'discord' => 'discord', 'kwai' => 'kwai',
        'snap' => 'snapchat', 'vk.com' => 'vk',
    ],

    // Buscadores clássicos — orgânicos enquanto não houver utm_medium pago.
    'buscadores' => [
        'google' => 'google', 'bing' => 'bing', 'duckduckgo' => 'duckduckgo', 'ecosia' => 'ecosia',
        'brave' => 'brave', 'yandex' => 'yandex', 'sogou' => 'sogou', 'baidu' => 'baidu',
        'startpage' => 'startpage', 'searx' => 'searx', 'mojeek' => 'mojeek', 'qwant' => 'qwant',
        'ask' => 'ask', 'aol' => 'aol', 'naver' => 'naver', 'seznam' => 'seznam',
    ],

    // Assistentes de IA de onde o usuário pode ter clicado num link (tráfego humano,
    // hoje um canal que anúncios nenhum cobre).
    'ia_humanos' => [
        'chatgpt' => 'chatgpt', 'chat.openai' => 'chatgpt', 'openai.com' => 'chatgpt',
        'perplexity' => 'perplexity', 'pplx' => 'perplexity',
        'gemini' => 'gemini', 'bard' => 'gemini', 'ai.google' => 'gemini',
        'copilot' => 'copilot',
        'claude' => 'claude', 'anthropic' => 'claude',
        'meta.ai' => 'meta_ai', 'llama' => 'meta_ai',
        'grok' => 'grok', 'deepseek' => 'deepseek', 'lechat' => 'mistral', 'mistral' => 'mistral',
        'poe.com' => 'poe', 'huggingface' => 'huggingchat', 'cohere' => 'cohere',
        'you.com' => 'you', 'andisearch' => 'andi', 'kagi.com' => 'kagi', 'character.ai' => 'character',
        'genspark' => 'genspark', 'manus.im' => 'manus', 'devin.ai' => 'devin',
    ],

    // Robôs que leem o site. Separados em dois grupos porque o comportamento é diferente:
    // 'ia_usuarios' é a IA acessando um link que um humano pediu (conta como busca por IA);
    // 'indexadores' varrem a web sozinhos (não são gente, ficam fora dos KPIs).
    'ia_usuarios' => ['ChatGPT-User', 'Claude-User', 'Claude-Web', 'Perplexity-User'],
    'indexadores' => [
        'GPTBot', 'OAIBot', 'OAI-SearchBot', 'ChatGPT-Bot', 'ClaudeBot', 'anthropic-ai', 'PerplexityBot',
        'Googlebot', 'Google-Other', 'Google-Extended', 'Bingbot', 'adidxbot', 'DuckAssistBot',
        'Baiduspider', 'YandexBot', 'Sogou', 'Exabot', 'facebot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'Slackbot',
        'Discordbot', 'WhatsApp', 'TelegramBot', 'Applebot', 'Pinterestbot', 'Snapbot', 'Amazonbot',
        'Bytespider', 'PetalBot', 'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'DataForSeoBot',
        'ImagesBot', 'BLEXBot', 'serpstatbot', 'CCBot', 'YouBot', 'Mojilebot', 'AwarioBot',
        // 'WhatsApp' aparece aqui e mesmo assim o clique dentro do app conta: o Atribuidor
        // reconhece o navegador embutido (Mozilla + WhatsApp) como gente antes de olhar esta lista.
        'Scrapy', 'curl', 'wget', 'python-requests', 'python-urllib', 'Go-http-client', 'java/', 'okhttp',
        'node-fetch', 'axios', 'PostmanRuntime', 'HTTPie', 'headless', 'PhantomJS', 'Zabbix', 'UptimeRobot',
        'PRTG', 'Pingdom', 'StatusCake', 'Hyperuptime', 'site24x7',
    ],

    // Janela de sessão, retenção e limites do registro de tráfego + os eventos que a
    // gente sabe medir. O beacon do site e o painel lêem esta mesma lista.
    'rastreio' => [
        'sessao_minutos' => 30,      // sem atividade por esse tempo = visita nova
        'dias_retencao' => 365,      // podado por php artisan rastreio:podar
        'max_eventos' => 200,        // teto por visita; um pageview infinito não incha a tabela
        'cookie' => 'golden_visitor',
        'eventos' => [
            'view' => ['rotulo' => 'Viu a página', 'icone' => 'fa-eye'],
            'cta' => ['rotulo' => 'Clicou em uma chamada', 'icone' => 'fa-hand-pointer'],
            'telefone' => ['rotulo' => 'Ligou', 'icone' => 'fa-phone'],
            'whatsapp' => ['rotulo' => 'Abriu o WhatsApp', 'icone' => 'fa-brands fa-whatsapp'],
            'email' => ['rotulo' => 'Escreveu para', 'icone' => 'fa-envelope'],
            'link_externo' => ['rotulo' => 'Saiu para um link externo', 'icone' => 'fa-arrow-up-right-from-square'],
            'form_inicio' => ['rotulo' => 'Começou o formulário', 'icone' => 'fa-keyboard'],
            'lead' => ['rotulo' => 'Cadastro enviado', 'icone' => 'fa-user-plus'],
            'kit' => ['rotulo' => 'Escolheu um kit', 'icone' => 'fa-boxes-stacked'],
            'video' => ['rotulo' => 'Abriu um tutorial em vídeo', 'icone' => 'fa-play'],
            'sacola' => ['rotulo' => 'Adicionou à sacola', 'icone' => 'fa-basket-shopping'],
            'checkout' => ['rotulo' => 'Foi ao checkout', 'icone' => 'fa-credit-card'],
            'pedido' => ['rotulo' => 'Pedido criado', 'icone' => 'fa-circle-check'],
        ],
    ],

    // Como cada rede identificada aparece na tela. O slug vem do Atribuidor; sem esta
    // linha o painel mostraria "meta_ai" para o cliente.
    'redes_rotulos' => [
        'google' => 'Google', 'google-ads' => 'Google Ads', 'bing' => 'Bing / Microsoft',
        'duckduckgo' => 'DuckDuckGo', 'ecosia' => 'Ecosia', 'brave' => 'Brave', 'yandex' => 'Yandex',
        'sogou' => 'Sogou', 'baidu' => 'Baidu', 'startpage' => 'Startpage', 'searx' => 'Searx',
        'mojeek' => 'Mojeek', 'qwant' => 'Qwant', 'ask' => 'Ask', 'aol' => 'AOL', 'naver' => 'Naver',
        'seznam' => 'Seznam', 'yahoo' => 'Yahoo',
        'chatgpt' => 'ChatGPT', 'gemini' => 'Gemini', 'perplexity' => 'Perplexity',
        'copilot' => 'Copilot', 'claude' => 'Claude', 'meta_ai' => 'Meta AI', 'grok' => 'Grok',
        'deepseek' => 'DeepSeek', 'mistral' => 'Le Chat (Mistral)', 'poe' => 'Poe',
        'huggingchat' => 'HuggingChat', 'cohere' => 'Cohere', 'you' => 'You.com',
        'andi' => 'Andi Search', 'kagi' => 'Kagi', 'character' => 'Character.AI',
        'genspark' => 'Genspark', 'manus' => 'Manus', 'devin' => 'Devin',
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'x' => 'X (Twitter)',
        'threads' => 'Threads', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest',
        'telegram' => 'Telegram', 'reddit' => 'Reddit', 'snapchat' => 'Snapchat',
        'discord' => 'Discord', 'kwai' => 'Kwai', 'vk' => 'VK', 'meta' => 'Meta (Facebook + Instagram)',
        'msn' => 'MSN', 'apple' => 'Apple', 'tiktok-ads' => 'TikTok Ads',
    ],

    // Dispositivo (Visit::dispositivo) — rótulo e ícone para o painel.
    'dispositivos' => [
        'celular' => ['rotulo' => 'Celular', 'icone' => 'fa-mobile-screen'],
        'tablet' => ['rotulo' => 'Tablet', 'icone' => 'fa-tablet-screen-button'],
        'computador' => ['rotulo' => 'Computador', 'icone' => 'fa-desktop'],
        'robô' => ['rotulo' => 'Robô', 'icone' => 'fa-robot'],
        'desconhecido' => ['rotulo' => 'Não informado', 'icone' => 'fa-circle-question'],
    ],

    'parceiros_m2m' => ['Vivo', 'Claro', 'Algar', 'TIM'],
    'pagamentos' => ['Cartão', 'Boleto bancário'],
    'equipamento' => 'Rastreador J16 4G/2G',

    // Caixa multicanal de WhatsApp do site (botão flutuante). Cada canal abre o
    // wa.me já com a mensagem pronta; 'numero' vazio usa o número principal do
    // painel. Tudo pode ser sobrescrito em Admin → Configurações.
    'whatsapp_box' => [
        'titulo' => 'Central de Atendimento Golden',
        'subtitulo' => 'Atendimento humano · escolha o canal',
        'abertura' => 'Olá, Golden! Vim pelo site e preciso de atendimento.',
        'canais' => [
            [
                'id' => 'emergencia',
                'icone' => 'fa-shield-halved',
                'nome' => 'Emergência & Central 24h',
                'desc' => 'Roubo, furto, sinistro ou bloqueio imediato',
                'msg' => 'Olá, Golden! É uma EMERGÊNCIA com meu veículo e preciso da Central 24h.',
            ],
            [
                'id' => 'comercial',
                'icone' => 'fa-tags',
                'nome' => 'Comercial & Novos Planos',
                'desc' => 'Cotação de kit e mensalidade para carro, moto ou frota',
                'msg' => 'Olá, Golden! Quero uma cotação de rastreamento para meu veículo.',
            ],
            [
                'id' => 'suporte',
                'icone' => 'fa-headset',
                'nome' => 'Suporte Técnico & Aplicativo',
                'desc' => 'Dúvidas no app, instalação, sinal GPS ou bloqueio remoto',
                'msg' => 'Olá, Golden! Preciso de suporte técnico do rastreador ou do aplicativo.',
            ],
            [
                'id' => 'financeiro',
                'icone' => 'fa-file-invoice-dollar',
                'nome' => 'Faturamento & Mensalidade',
                'desc' => 'Boleto, segunda via e atualização cadastral',
                'msg' => 'Olá, Golden! Preciso de auxílio com minha mensalidade ou fatura.',
            ],
        ],
    ],

    'beneficios' => [
        ['icone' => 'fa-location-crosshairs', 'titulo' => 'Localização em tempo real', 'texto' => 'Veja onde seu veículo está a qualquer momento.'],
        ['icone' => 'fa-route', 'titulo' => 'Histórico de trajetos', 'texto' => 'Consulte percursos, paradas e quilometragem.'],
        ['icone' => 'fa-lock', 'titulo' => 'Bloqueio remoto', 'texto' => 'Comando pelo aplicativo quando compatível e instalado.'],
        ['icone' => 'fa-draw-polygon', 'titulo' => 'Cercas virtuais', 'texto' => 'Receba alertas de entrada e saída de áreas.'],
        ['icone' => 'fa-chart-column', 'titulo' => 'Relatórios completos', 'texto' => 'Velocidade, uso, rotas e mais para a sua gestão.'],
        ['icone' => 'fa-screwdriver-wrench', 'titulo' => 'Gestão de manutenções', 'texto' => 'Controle preventivo por data ou quilometragem.'],
    ],

    'segmentos' => [
        ['icone' => 'fa-taxi', 'titulo' => 'Motoristas de aplicativo', 'texto' => 'Mais segurança e controle no dia a dia.', 'slot' => 'segmento_motorista'],
        ['icone' => 'fa-car', 'titulo' => 'Veículos particulares', 'texto' => 'Mais proteção para você e sua família.', 'slot' => 'segmento_particular'],
        ['icone' => 'fa-van-shuttle', 'titulo' => 'Frotas empresariais', 'texto' => 'Gestão completa e mais produtividade.', 'slot' => 'segmento_frota'],
        ['icone' => 'fa-key', 'titulo' => 'Locadoras de veículos', 'texto' => 'Controle, eficiência e redução de custos.', 'slot' => 'segmento_locadora'],
        ['icone' => 'fa-tractor', 'titulo' => 'Operações rurais', 'texto' => 'Acompanhamento incluso em áreas remotas.', 'slot' => 'segmento_rural'],
    ],

    // Bloco do equipamento J16: usado na home e em /solucoes a partir de uma única fonte.
    'equipamento_info' => [
        'eyebrow' => 'Rastreador J16 4G/2G',
        'titulo' => 'Equipamento confiável e homologado pela Anatel.',
        'destaque' => 'Anatel',
        'texto' => 'O J16 é uma solução completa para o seu veículo, com conectividade 4G e 2G, chip M2M e recursos avançados de segurança e gestão. Compatível com ampla variedade de modelos.',
        'slot' => 'home_equipamento',
        'checks' => [
            'Homologação Anatel',
            'Conectividade 4G/2G (multioperadora)',
            'Kit com rastreador, chip, relé e chicote',
            'Compatível com ampla variedade de modelos',
        ],
    ],

    'kits' => [
        [
            'id' => 'kit-5',
            'eyebrow' => 'Frota Essencial',
            'numero' => '5',
            'titulo' => '5 veículos',
            'destaque' => false,
            'itens' => ['Kit com 5 rastreadores J16 4G/2G', 'Chips M2M inclusos', 'Acesso à plataforma web e app'],
            'botao' => 'Quero essa solução',
        ],
        [
            'id' => 'kit-10',
            'eyebrow' => 'Frota Profissional',
            'numero' => '10',
            'titulo' => '10 veículos',
            'destaque' => true,
            'rotulo' => 'Mais escolhido',
            'itens' => ['Kit com 10 rastreadores J16 4G/2G', 'Chips M2M inclusos', 'Acesso à plataforma web e app'],
            'botao' => 'Quero essa solução',
        ],
        [
            'id' => 'kit-10-plus',
            'eyebrow' => 'Frota Personalizada',
            'numero' => '10+',
            'titulo' => '10+ veículos',
            'destaque' => false,
            'itens' => ['Quantidade sob consulta', 'Condições especiais', 'Proposta individualizada'],
            'botao' => 'Falar com especialista',
        ],
    ],

    'passos' => [
        ['icone' => 'fa-cart-shopping', 'titulo' => 'Escolha seu kit', 'texto' => 'Selecione a quantidade de veículos.'],
        ['icone' => 'fa-box-open', 'titulo' => 'Receba o equipamento', 'texto' => 'Rastreadores com chips M2M.'],
        ['icone' => 'fa-wrench', 'titulo' => 'Faça a instalação', 'texto' => 'Com profissional de sua confiança, por sua conta.'],
        ['icone' => 'fa-chart-line', 'titulo' => 'Comece a usar', 'texto' => 'Ative e acompanhe tudo pela plataforma.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Categorias da galeria de vídeos
    |--------------------------------------------------------------------------
    | Uma régua só para o filtro da página pública, o seletor do painel e o rótulo
    | que aparece no cartão. A chave é o valor gravado em videos.categoria: trocar o
    | 'rotulo' é seguro, trocar a chave deixa órfãos os vídeos já cadastrados.
    */
    'video_categorias' => [
        'aplicativo' => ['rotulo' => 'Aplicativo no celular', 'icone' => 'fa-mobile-screen-button'],
        'plataforma' => ['rotulo' => 'Plataforma no computador', 'icone' => 'fa-laptop'],
        'frotas' => ['rotulo' => 'Frotas e carros de aluguel', 'icone' => 'fa-truck'],
        'bloqueio' => ['rotulo' => 'Bloqueio e âncora', 'icone' => 'fa-lock'],
        'financeiro' => ['rotulo' => 'Financeiro e faturas', 'icone' => 'fa-file-invoice-dollar'],
    ],

    // Menu do site (header + gaveta mobile). Itens com 'grupo' recebem filhos
    // montados pelo AppServiceProvider: 'loja' usa as categorias do catálogo e
    // 'institucional' usa as páginas publicadas no CMS. 'conta' não tem rota fixa:
    // quem já entrou no portal vai para o início, quem não vai, para o login.
    'nav' => [
        ['titulo' => 'Soluções', 'rota' => 'site.solucoes', 'grupo' => 'solucoes', 'filhos' => [
            ['titulo' => 'Funcionalidades da plataforma', 'rota' => 'site.solucoes'],
            ['titulo' => 'Para quem é', 'rota' => 'site.segmentos'],
            ['titulo' => 'Como funciona', 'rota' => 'site.como-funciona'],
        ]],
        ['titulo' => 'Planos e kits', 'rota' => 'site.planos'],
        ['titulo' => 'Loja', 'rota' => 'site.loja.index', 'grupo' => 'loja'],
        ['titulo' => 'Institucional', 'grupo' => 'institucional'],
        ['titulo' => 'Contato', 'rota' => 'site.contato'],
        ['titulo' => 'Área do cliente', 'grupo' => 'conta'],
    ],

    // Menu do painel (sidebar). Item com 'filhos' vira grupo de treeview: o clique abre
    // e fecha o grupo, e o accordion do AdminLTE mantém um só aberto por vez.
    // 'prefixo' acende o item em qualquer rota do recurso (show, edit, estoque...),
    // 'admin' esconde de quem não é administrador, 'bolha' é a chave de
    // AppServiceProvider::pendenciasDoPainel(), 'tom' usa os utilitários .tone-* e
    // 'desligada' é a bandeira que, quando falsa, mostra o aviso e esmaece o item.
    'menu_painel' => [
        ['titulo' => 'Dashboard', 'rota' => 'admin.dashboard', 'prefixo' => 'admin.dashboard', 'icone' => 'fa-gauge-high'],

        ['titulo' => 'Comercial', 'icone' => 'fa-chart-simple', 'filhos' => [
            ['titulo' => 'Leads', 'rota' => 'admin.leads.index', 'prefixo' => 'admin.leads.', 'icone' => 'fa-user-plus', 'bolha' => 'leads_novos', 'tom' => 'gold'],
            ['titulo' => 'Clientes', 'rota' => 'admin.clientes.index', 'prefixo' => 'admin.clientes.', 'icone' => 'fa-users'],
            ['titulo' => 'Campanhas', 'rota' => 'admin.campanhas.index', 'prefixo' => 'admin.campanhas.', 'icone' => 'fa-bullhorn'],
            ['titulo' => 'Atribuição de tráfego', 'rota' => 'admin.atribuicao.index', 'prefixo' => 'admin.atribuicao.', 'icone' => 'fa-diagram-project'],
        ]],

        ['titulo' => 'Vendas', 'icone' => 'fa-cart-shopping', 'filhos' => [
            ['titulo' => 'Pedidos', 'rota' => 'admin.pedidos.index', 'prefixo' => 'admin.pedidos.', 'icone' => 'fa-receipt', 'bolha' => 'pedidos_abertos', 'tom' => 'blue'],
            ['titulo' => 'Produtos e estoque', 'rota' => 'admin.catalogo.produtos.index', 'prefixo' => 'admin.catalogo.produtos.', 'icone' => 'fa-boxes-stacked', 'admin' => true],
            ['titulo' => 'Categorias', 'rota' => 'admin.catalogo.categorias.index', 'prefixo' => 'admin.catalogo.categorias.', 'icone' => 'fa-tags', 'admin' => true],
            ['titulo' => 'Fornecedores', 'rota' => 'admin.catalogo.fornecedores.index', 'prefixo' => 'admin.catalogo.fornecedores.', 'icone' => 'fa-truck', 'admin' => true],
            ['titulo' => 'Loja no site', 'rota' => 'site.loja.index', 'prefixo' => 'site.loja.', 'icone' => 'fa-store', 'externo' => true, 'desligada' => 'loja_ativa'],
        ]],

        ['titulo' => 'Atendimento', 'icone' => 'fa-headset', 'filhos' => [
            ['titulo' => 'Chamados', 'rota' => 'admin.chamados.index', 'prefixo' => 'admin.chamados.', 'icone' => 'fa-life-ring', 'bolha' => 'chamados_abertos', 'tom' => 'amber'],
            ['titulo' => 'Contratos', 'rota' => 'admin.contratos.index', 'prefixo' => 'admin.contratos.', 'icone' => 'fa-file-signature'],
        ]],

        ['titulo' => 'Conteúdo do site', 'icone' => 'fa-file-lines', 'admin' => true, 'filhos' => [
            ['titulo' => 'Páginas', 'rota' => 'admin.conteudo.paginas.index', 'prefixo' => 'admin.conteudo.paginas.', 'icone' => 'fa-newspaper'],
            ['titulo' => 'Imagens do site', 'rota' => 'admin.conteudo.imagens.index', 'prefixo' => 'admin.conteudo.imagens.', 'icone' => 'fa-image'],
            ['titulo' => 'Textos das páginas', 'rota' => 'admin.conteudo.textos.index', 'prefixo' => 'admin.conteudo.textos.', 'icone' => 'fa-heading'],
            ['titulo' => 'Depoimentos', 'rota' => 'admin.conteudo.depoimentos.index', 'prefixo' => 'admin.conteudo.depoimentos.', 'icone' => 'fa-quote-left'],
            ['titulo' => 'Vídeos tutoriais', 'rota' => 'admin.conteudo.videos.index', 'prefixo' => 'admin.conteudo.videos.', 'icone' => 'fa-play'],
            ['titulo' => 'FAQs', 'rota' => 'admin.conteudo.faqs.index', 'prefixo' => 'admin.conteudo.faqs.', 'icone' => 'fa-circle-question'],
        ]],

        ['titulo' => 'Sistema', 'icone' => 'fa-gear', 'admin' => true, 'filhos' => [
            ['titulo' => 'Configurações', 'rota' => 'admin.settings.index', 'prefixo' => 'admin.settings.', 'icone' => 'fa-sliders'],
            ['titulo' => 'Usuários', 'rota' => 'admin.usuarios.index', 'prefixo' => 'admin.usuarios.', 'icone' => 'fa-user-shield'],
            ['titulo' => 'Auditoria', 'rota' => 'admin.activities.index', 'prefixo' => 'admin.activities.', 'icone' => 'fa-shield-halved'],
        ]],
    ],

    // Menu do portal do cliente. Mesma árvore, mesmo renderizador (MenuPainel + a sidebar do
    // AdminLTE) e mesma organização do painel: o cliente vê a área dele com a cara do painel,
    // só que com as rotas dele. As bolhas vêm de AppServiceProvider::pendenciasDoPortal(),
    // que conta o que está no nome do cliente, não o total da empresa.
    'menu_portal' => [
        ['titulo' => 'Início', 'rota' => 'portal.inicio', 'prefixo' => 'portal.inicio', 'icone' => 'fa-house'],

        ['titulo' => 'Pedidos', 'rota' => 'portal.pedidos', 'prefixo' => 'portal.pedidos', 'icone' => 'fa-box-open', 'bolha' => 'pedidos_abertos', 'tom' => 'blue'],

        ['titulo' => 'Atendimento', 'icone' => 'fa-headset', 'filhos' => [
            ['titulo' => 'Meus chamados', 'rota' => 'portal.chamados', 'prefixo' => 'portal.chamados', 'icone' => 'fa-life-ring', 'bolha' => 'chamados_abertos', 'tom' => 'amber'],
            ['titulo' => 'Abrir chamado', 'rota' => 'portal.chamados.criar', 'icone' => 'fa-plus'],
        ]],

        ['titulo' => 'Contratos', 'rota' => 'portal.contratos', 'prefixo' => 'portal.contratos', 'icone' => 'fa-file-signature'],

        ['titulo' => 'Deixar um depoimento', 'rota' => 'portal.depoimento', 'prefixo' => 'portal.depoimento', 'icone' => 'fa-quote-left'],

        ['titulo' => 'Minha conta', 'icone' => 'fa-user-gear', 'filhos' => [
            ['titulo' => 'Meus dados', 'rota' => 'portal.dados', 'prefixo' => 'portal.dados', 'icone' => 'fa-id-card'],
        ]],

        ['titulo' => 'Loja', 'rota' => 'site.loja.index', 'prefixo' => 'site.loja.', 'icone' => 'fa-store', 'externo' => true, 'desligada' => 'loja_ativa'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Depoimentos do Google Business Profile
    |--------------------------------------------------------------------------
    | As três peças vêm do .env da instalação — nunca do banco e nunca do
    | repositório: ACCOUNT é `accounts/<id>` (ou o resource completo), LOCATION
    | é `locations/<id>` e TOKEN é o Bearer de uma conta que administra o perfil
    | (Business Profile API v4, escopo mybusiness.businessinformation). Sem elas
    | o painel mostra "não configurado" e a importação não roda; os depoimentos
    | digitados no painel e enviados pelo portal seguem funcionando, e o
    | carrossel mescla as fontes que existirem.
    */
    'google_business' => [
        'account' => env('GOOGLE_BUSINESS_ACCOUNT', ''),
        'location' => env('GOOGLE_BUSINESS_LOCATION', ''),
        'token' => env('GOOGLE_BUSINESS_TOKEN', ''),
        'limite' => (int) env('GOOGLE_BUSINESS_LIMITE', 50),
        'nota_minima' => (int) env('GOOGLE_BUSINESS_NOTA_MINIMA', 4),
    ],

    'faq' => [
        ['q' => 'O rastreador funciona em qualquer veículo?', 'a' => 'O J16 4G/2G é compatível com a maioria dos veículos leves, motos e linha pesada. Confirme a compatibilidade no atendimento.'],
        ['q' => 'A instalação é feita pela Golden?', 'a' => 'Não. A instalação é contratada e paga pelo cliente diretamente ao profissional de sua confiança.'],
        ['q' => 'Preciso pagar mensalidade?', 'a' => 'Sim. A conectividade M2M e o acesso à plataforma têm cobrança recorrente. Os valores vigentes são informados na proposta.'],
        ['q' => 'Como funciona o bloqueio remoto?', 'a' => 'O bloqueio é um recurso compatível quando o rastreador é instalado com o chicote/relé adequado.'],
        ['q' => 'Qual a cobertura do chip M2M?', 'a' => 'Cobertura das operadoras nacionais com rede 4G/2G, conforme a região de uso.'],
        ['q' => 'Consigo acessar pelo celular?', 'a' => 'Sim. A plataforma tem acesso web e aplicativo para acompanhar seus veículos.'],
    ],

    'perfis' => [
        'motorista' => 'Motorista profissional / app',
        'empresa' => 'Empresa / frota',
        'locadora' => 'Locadora / prestador',
        'rural' => 'Operação rural',
        'outro' => 'Outro',
    ],
];
