<?php

return [
    'marca' => [
        'nome' => 'Golden Rastreamento',
        'slogan' => 'Rastreamento e gestão veicular',
        'cidade' => 'Três Pontas · Minas Gerais',
    ],

    'whatsapp' => env('GOLDEN_WHATSAPP', '5535988880000'),
    'email' => env('GOLDEN_EMAIL', 'comercial@goldenrastreamento.com.br'),

    'beneficios' => [
        ['icone' => 'fa-location-crosshairs', 'titulo' => 'Localização em tempo real', 'texto' => 'Veja onde seu veículo está a qualquer momento.'],
        ['icone' => 'fa-route', 'titulo' => 'Histórico de trajetos', 'texto' => 'Consulte percursos, paradas e quilometragem.'],
        ['icone' => 'fa-lock', 'titulo' => 'Bloqueio remoto', 'texto' => 'Comando pelo aplicativo quando compatível e instalado.'],
        ['icone' => 'fa-draw-polygon', 'titulo' => 'Cercas virtuais', 'texto' => 'Receba alertas de entrada e saída de áreas.'],
        ['icone' => 'fa-chart-column', 'titulo' => 'Relatórios completos', 'texto' => 'Velocidade, uso, rotas e mais para a sua gestão.'],
        ['icone' => 'fa-screwdriver-wrench', 'titulo' => 'Gestão de manutenções', 'texto' => 'Controle preventivo por data ou quilometragem.'],
    ],

    'segmentos' => [
        ['icone' => 'fa-taxi', 'titulo' => 'Motoristas de aplicativo', 'texto' => 'Mais segurança e controle no dia a dia.'],
        ['icone' => 'fa-car', 'titulo' => 'Veículos particulares', 'texto' => 'Mais proteção para você e sua família.'],
        ['icone' => 'fa-van-shuttle', 'titulo' => 'Frotas empresariais', 'texto' => 'Gestão completa e mais produtividade.'],
        ['icone' => 'fa-key', 'titulo' => 'Locadoras de veículos', 'texto' => 'Controle, eficiência e redução de custos.'],
        ['icone' => 'fa-tractor', 'titulo' => 'Operações rurais', 'texto' => 'Acompanhamento incluso em áreas remotas.'],
    ],

    'kits' => [
        [
            'id' => 'kit-5',
            'eyebrow' => 'Frota Essencial',
            'titulo' => '5 veículos',
            'destaque' => false,
            'itens' => ['Kit com 5 rastreadores J16 4G/2G', 'Chips M2M inclusos', 'Acesso à plataforma web e app'],
            'botao' => 'Quero essa solução',
        ],
        [
            'id' => 'kit-10',
            'eyebrow' => 'Frota Profissional',
            'titulo' => '10 veículos',
            'destaque' => true,
            'rotulo' => 'Mais escolhido',
            'itens' => ['Kit com 10 rastreadores J16 4G/2G', 'Chips M2M inclusos', 'Acesso à plataforma web e app'],
            'botao' => 'Quero essa solução',
        ],
        [
            'id' => 'kit-10-plus',
            'eyebrow' => 'Frota Personalizada',
            'titulo' => '10+ veículos',
            'destaque' => false,
            'itens' => ['Quantidade sob consulta', 'Condições especiais', 'Proposta individualizada'],
            'botao' => 'Falar com especialista',
        ],
    ],

    'passos' => [
        ['n' => '01', 'icone' => 'fa-cart-shopping', 'titulo' => 'Escolha seu kit', 'texto' => 'Selecione a quantidade de veículos.'],
        ['n' => '02', 'icone' => 'fa-box-open', 'titulo' => 'Receba o equipamento', 'texto' => 'Rastreadores com chips M2M.'],
        ['n' => '03', 'icone' => 'fa-wrench', 'titulo' => 'Faça a instalação', 'texto' => 'Com profissional de sua confiança, por sua conta.'],
        ['n' => '04', 'icone' => 'fa-chart-line', 'titulo' => 'Comece a usar', 'texto' => 'Ative e acompanhe tudo pela plataforma.'],
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
