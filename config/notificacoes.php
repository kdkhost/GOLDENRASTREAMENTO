<?php

/*
|--------------------------------------------------------------------------
| Central de notificações
|--------------------------------------------------------------------------
| O catálogo dos avisos que nascem dentro do painel. Cada tipo é uma linha deste
| array: é aqui que mora o rótulo, o ícone, o tom do selo, para quem o aviso vai e
| por quantos dias ele aguenta sem ser lido. Uma categoria nova de aviso entra por
| aqui, sem migration e sem view nova — a página dinâmica já sabe montar a ficha de
| qualquer tipo a partir do que a linha carrega.
|
| Nada disto é cor decidida dentro de uma view: o tom vem daqui e passa pelo mesmo
| `tom_por_status()` do resto do painel, então o selo do sino, o da listagem e o da
| ficha são sempre o mesmo verde, o mesmo âmbar, o mesmo vermelho.
|
| O e-mail não passa por aqui. Os avisos por e-mail continuam saindo exatamente onde
| sempre saíram; esta central é o canal de dentro do painel, e um não substitui o
| outro.
*/

return [

    // O sino mostra estas e diz "ver todas" para o resto. Um sino com trinta linhas
    // não é um sino, é uma lista, e a pessoa para de abrir.
    'sino_limite' => 8,

    // Quantas notificações a consulta do JSON do sino devolve. O JavaScript roda a
    // cada volta do relógio na página, então o teto é curto de propósito.
    'sino_json_limite' => 12,

    // Os intervalos do sino no navegador, em segundos. Duas chaves porque as duas
    // perguntas são diferentes: com a aba visível a pessoa quer ver na hora; com a aba
    // escondida o painel não tem por que bater na porta do servidor.
    'sino_intervalo_ms' => ['visivel' => 45000, 'escondida' => 300000],

    // A retenção das duas pontas da poda, em dias. O painel grava no banco
    // (`notificacoes_retencao_dias`, `notificacoes_expiracao_dias`) e estes números
    // são o plano de fundo: valem enquanto o banco não disser nada.
    'retencao_dias_padrao' => (int) env('NOTIFICACOES_RETENCAO_DIAS', 30),
    'expiracao_dias_padrao' => (int) env('NOTIFICACOES_EXPIRACAO_DIAS', 15),

    // O teto de uma notificação sem dono: quantas linhas uma pessoa acumula antes de
    // a listagem começar a truncar. É o mesmo critério de todo o painel, em itens por
    // página, e não um número novo inventado para cá.
    'lista_limite' => 200,

    /*
    |--------------------------------------------------------------------------
    | O catálogo
    |--------------------------------------------------------------------------
    | 'audiencia' é quem recebe: 'equipe' são os papéis de painel (admin e vendedor),
    | 'admin' é só o administrador. A escolha segue a mesma régua do menu: o que é da
    | operação diária não pertence só a quem pode configurar a plataforma.
    |
    | 'rota' é a rota nomeada que a ficha abre — o parâmetro chega na hora do disparo,
    | porque é o id do objeto, não uma constante daqui.
    |
    | 'retencao_dias' e 'expiracao_dias' quando presentes mandam sobre os padrões
    | acima, para o tipo que envelhece rápido (a métrica murcha, o chamado não).
    */
    'tipos' => [
        'lead_novo' => [
            'rotulo' => 'Lead novo',
            'icone' => 'fa-user-plus',
            'tom' => 'gold',
            'audiencia' => 'equipe',
            'rota' => 'admin.leads.show',
        ],
        'pedido_novo' => [
            'rotulo' => 'Pedido novo',
            'icone' => 'fa-receipt',
            'tom' => 'blue',
            'audiencia' => 'equipe',
            'rota' => 'admin.pedidos.show',
        ],
        'chamado_aberto' => [
            'rotulo' => 'Chamado aberto',
            'icone' => 'fa-life-ring',
            'tom' => 'amber',
            'audiencia' => 'equipe',
            'rota' => 'admin.chamados.show',
        ],
        // O cliente respondeu: é o aviso que tira o chamado da fila de quem já trata
        // dele, e por isso tem tipo próprio em vez de dividir o selo do chamado novo.
        'chamado_respondido' => [
            'rotulo' => 'Resposta do cliente',
            'icone' => 'fa-reply',
            'tom' => 'teal',
            'audiencia' => 'equipe',
            'rota' => 'admin.chamados.show',
        ],
        'depoimento_novo' => [
            'rotulo' => 'Depoimento novo',
            'icone' => 'fa-quote-left',
            'tom' => 'violet',
            'audiencia' => 'admin',
            'rota' => 'admin.conteudo.depoimentos.index',
        ],
        'conversa_escalada' => [
            'rotulo' => 'Conversa com a equipe',
            'icone' => 'fa-comments',
            'tom' => 'amber',
            'audiencia' => 'equipe',
            'rota' => 'admin.assistente.conversas.show',
        ],
        'conta_portal' => [
            'rotulo' => 'Conta do portal aberta',
            'icone' => 'fa-user-shield',
            'tom' => 'violet',
            'audiencia' => 'admin',
            'rota' => 'admin.clientes.show',
        ],
        'ads_desconectado' => [
            'rotulo' => 'Conta de Google Ads desconectada',
            'icone' => 'fa-triangle-exclamation',
            'tom' => 'red',
            'audiencia' => 'admin',
            'rota' => 'admin.google-ads.index',
            // A métrica velha é pior que nenhuma: o aviso não pode ficar mofando duas
            // semanas no sino depois que a conta já reconectou.
            'expiracao_dias' => 5,
        ],
        'cron_falhou' => [
            'rotulo' => 'Tarefa agendada falhou',
            'icone' => 'fa-clock',
            'tom' => 'red',
            'audiencia' => 'equipe',
            'rota' => 'admin.crons.show',
        ],
        'cron_pausado_demais' => [
            'rotulo' => 'Agenda sem batimento',
            'icone' => 'fa-power-off',
            'tom' => 'red',
            'audiencia' => 'admin',
            'rota' => 'admin.crons.index',
            'expiracao_dias' => 3,
        ],
    ],

    // Os papéis de painel que a audiência 'equipe' alcança. A mesma lista de
    // User::temPapelDePainel(), escrita aqui porque o catálogo de notificação é uma
    // fonte só: mudar um papel de atendimento muda quem é avisado, em um lugar.
    'papeis_equipe' => ['admin', 'vendedor'],

    // O teto de caracteres do corpo na listagem. A ficha mostra o texto inteiro.
    'texto_maximo' => 500,
];
