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
            'alt' => 'Central de monitoramento Golden com mapas de frota em três telas',
            'largura' => 1600, 'altura' => 914, 'dimensao' => '1600 × 914 px', 'proporcao' => '16:9',
            'dica' => 'Abre o site: a central de monitoramento. Escurecida por cima, use foto clara no centro.',
        ],
        'home_plataforma' => [
            'pagina' => 'Home', 'titulo' => 'Tela da plataforma', 'padrao' => 'img/home/plataforma-monitor.jpg',
            'alt' => 'Plataforma Golden de monitoramento aberta em um computador',
            'largura' => 1200, 'altura' => 960, 'dimensao' => '1200 × 960 px', 'proporcao' => '5:4',
            'dica' => 'Print do monitoramento em um computador, na seção escuro.',
        ],
        'home_equipamento' => [
            'pagina' => 'Home', 'titulo' => 'Rastreador J16', 'padrao' => 'img/home/rastreador-j16.jpg',
            'alt' => 'Rastreador veicular J16 4G/2G com chicote de instalação',
            'largura' => 1000, 'altura' => 750, 'dimensao' => '1000 × 750 px', 'proporcao' => '4:3',
            'dica' => 'Foto do equipamento com o chicote. Também aparece em /solucoes.',
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

    // Menu do site (header + gaveta mobile). Itens com 'grupo' recebem filhos
    // montados pelo AppServiceProvider: 'loja' usa as categorias do catálogo e
    // 'institucional' usa as páginas publicadas no CMS.
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
