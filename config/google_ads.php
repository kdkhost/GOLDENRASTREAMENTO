<?php

/*
|--------------------------------------------------------------------------
| Google Ads — leitura de campanhas no painel
|--------------------------------------------------------------------------
|
| Tudo que a integração pergunta ao Google vive aqui: as credenciais que chegam
| por `.env`, o transporte que a hospedagem suporta, o tempo de cache, os rótulos
| em português e as travas de caracteres do anúncio responsivo. Nenhum arquivo
| escreve texto de métrica ou de status por conta própria — a tela lê desta lista.
|
| As credenciais do OAuth ficam em `config('services.google_ads')`, que é onde o
| Socialite procura; aqui só o que é próprio da API do Ads.
*/

return [

    /*
    | O token de desenvolvedor é a credencial da *aplicação*, não do anunciante: ele nasce
    | junto com a conta MCC (Ferramentas e configurações → API do Google Ads) e é segredo.
    | Por isso o provider o lê sempre do ambiente e ele jamais aparece em view, JSON ou log.
    */
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),

    /*
    | O MCC pelo qual o painel entra — 10 dígitos, sem hífen. Deixar vazio é legítimo: aí a
    | conta conectada é consultada por si só, sem gerente. Quem tem o MCC consulta qualquer
    | conta da árvore com um token só.
    */
    'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),

    /*
    | Transporte da API. `rest` é o padrão da casa: o cliente oficial também fala gRPC, mas
    | gRPC pede a extensão `grpc` do PHP, e a hospedagem em que o sistema roda é compartilhada
    | — sem root e sem pecl, a extensão não entra. `rest` usa o Guzzle que já está no projeto
    | e faz as mesmas chamadas por HTTP/1.1. Numa máquina com a extensão, trocar para `grpc`
    | é uma linha de ambiente, nada de código.
    */
    'transporte' => env('GOOGLE_ADS_TRANSPORT', 'rest'),

    /* Versão da API que o código usa. O pacote traz V23..V26; subir a versão é trocar isto
       e o namespace — a camada de serviço é uma só. */
    'versao_api' => 'V26',

    /*
    | Onde a métrica sincronizada fica guardada. Vazio = o store padrão do app. A hospedagem
    | atual não tem servidor Redis (a extensão do PHP existe, o servidor não), então o cache
    | cai no store da aplicação; num servidor com Redis, GOOGLE_ADS_CACHE_STORE=redis acende
    | a fila inteira sem mexer em nada.
    */
    'cache_store' => env('GOOGLE_ADS_CACHE_STORE'),

    /* Tempo de vida da métrica em cache, em minutos. */
    'cache_minutos' => (int) env('GOOGLE_ADS_CACHE_MINUTOS', 30),

    /* Período puxado quando ninguém escolhe um: 30 dias, como o padrão do Google Ads. */
    'dias_padrao' => (int) env('GOOGLE_ADS_DIAS', 30),

    /* As três janelas que o painel oferece. */
    'periodos' => [7, 30, 90],

    /*
    | O Google devolve o dinheiro em micro (milionésimos da unidade da moeda da conta) e a
    | casa guarda dinheiro em centavos — é assim que `brl()` formata em todo o sistema. Este
    | é o fator que atravessa de um para o outro: micro / 10.000 = centavo.
    */
    'micros_por_centavo' => 10000,

    /*
    | Tradução dos status da campanha. A API entrega o código em inglês; a tela mostra
    | português. Lista fechada: status fora dela aparece com o próprio código, para o operador
    | ver que falta tradução em vez de ler uma etiqueta errada.
    */
    'status_campanha' => [
        'ENABLED' => 'Ativa',
        'PAUSED' => 'Pausada',
        'REMOVED' => 'Removida',
        'PENDING' => 'Pendente de aprovação',
        'DRAFT' => 'Rascunho',
        'UNSPECIFIED' => 'Não especificada',
        'UNKNOWN' => 'Desconhecida',
    ],

    /*
    | Os anúncios têm enumeração própria (`ad_group_ad.status`), cujos números coincidem com
    | os da campanha nos casos que importam — ativo, pausado, removido. O texto é outro porque
    | um "Ativa" na coluna de um anúncio leria errado; o que não estiver nesta lista sai com o
    | próprio código, como na campanha.
    */
    'status_anuncio' => [
        'ENABLED' => 'Ativo',
        'PAUSED' => 'Pausado',
        'REMOVED' => 'Removido',
        'ARCHIVED' => 'Arquivado',
        'PENDING' => 'Pendente de aprovação',
        'UNSPECIFIED' => 'Não especificado',
        'UNKNOWN' => 'Desconhecido',
    ],

    /*
    | Rótulo PT-BR de cada métrica lida da API. É a única lista de nomes de coluna do
    | painel: a tabela, os cartões de resumo e o CSV perguntam daqui.
    */
    'rotulos_metricas' => [
        'impressoes' => 'Impressões',
        'cliques' => 'Cliques',
        'ctr' => 'CTR',
        'cpc_medio' => 'CPC médio',
        'custo' => 'Custo',
        'conversoes' => 'Conversões',
        'valor_conversoes' => 'Valor das conversões',
        'roas' => 'ROAS',
        'status' => 'Situação',
        'campanha' => 'Campanha',
    ],

    /*
    | Travas do anúncio responsivo (RSA). São as do Google, não preferências da casa: quinze
    | títulos de até 30 caracteres, quatro descrições de até 90. O contador da tela lê daqui
    | para o editor e o preview não aceitarem números diferentes.
    */
    'rsa' => [
        'titulos_min' => 3,
        'titulos_max' => 15,
        'caracteres_titulo' => 30,
        'descricoes_min' => 1,
        'descricoes_max' => 4,
        'caracteres_descricao' => 90,
        'caracteres_rota' => 15,
        'combinacoes' => 3,
    ],

    /*
    | Escopo do consentimento. `adwords` é o único que a API aceita; os outros três estão aqui
    | porque o Google só devolve a identidade de quem conectou se o pedido abrir o perfil e o
    | e-mail — é assim que a tela mostra "conectada como fulano".
    */
    'escopos' => [
        'openid',
        'email',
        'profile',
        'https://www.googleapis.com/auth/adwords',
    ],
];
