<?php

/*
|--------------------------------------------------------------------------
| Guia de uso do painel
|--------------------------------------------------------------------------
|
| A documentação que quem opera o sistema lê: a tela *Guia de uso* do painel, o
| cartão "Como conectar" da tela de Google Ads e o link discreto que aparece no
| cabeçalho de cada tela. Uma fonte só — o texto não é copiado para duas views.
|
| Cada seção tem `id` (a âncora usada nos links), `grupo` (a coluna da esquerda),
| `titulo`, `icone`, `resumo` e os blocos. Os tipos de bloco são:
|
|   texto   um parágrafo;                       ['tipo' => 'texto', 'conteudo' => '...']
|   passos  sequência sem número — ícone, título e texto em cada linha;
|   lista   itens soltos;
|   codigo  linhas monoespaçadas (comando, .env);
|   tabela  cabeçalho e linhas;
|   links   os endereços onde se busca cada credencial — `rotulo` + `url`;
|   aviso   a frase que fica marcada à esquerda — `tom` usa os tokens do tema.
|
| 'rota' é a tela que a seção documenta: a página oferece o botão "Abrir a tela"
| a partir dela, e é por isso que ela é conferida em teste — guia que aponta para
| rota renomeada é guia desmentido pelo sistema.
|
| Nada aqui recebe número. Um passo é ícone + título + texto; a ordem já está na
| lista.
*/

return [

    /*
    | A ordem das seções na tela é a ordem desta lista. Os grupos da coluna da
    | esquerda aparecem na ordem em que surgem aqui.
    */
    'secoes' => [

        /* ---------------------------------------------------------------- Começando */

        [
            'id' => 'comeco',
            'grupo' => 'Começando',
            'titulo' => 'O que é este painel',
            'icone' => 'fa-house',
            'resumo' => 'Uma casa só para o comercial, as vendas, o atendimento, o conteúdo do site e o sistema.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'O menu lateral traz cinco grupos — Comercial, Vendas, Atendimento, Conteúdo do site e Sistema. Cada grupo abre e fecha; o grupo da página em que você está já nasce aberto, e abrir outro fecha o anterior. Quem é vendedor vê os três primeiros; "Conteúdo do site" e "Sistema" são do administrador.'],
                ['tipo' => 'lista', 'itens' => [
                    'Dashboard — o resumo do dia: leads novos, pedidos abertos, chamados abertos, tráfego e vendas.',
                    'Todo lugar é a mesma régua: vinte registros por página, filtro em cima, tabela no meio, paginação embaixo.',
                    'O recado de tudo que você salva sai no canto superior direito (toastr), e nenhuma exclusão acontece sem confirmar antes.',
                    'O tema claro e escuro é o mesmo do site: troca no cabeçalho e vale para todas as telas.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'blue', 'conteudo' => 'Este guia é uma tela do painel, não um arquivo fora do sistema. Se algo aqui divergir do que a tela faz, a tela tem razão — e vale avisar quem mantém o código.'],
            ],
        ],

        [
            'id' => 'rotina',
            'grupo' => 'Começando',
            'titulo' => 'A rotina do dia',
            'icone' => 'fa-mug-hot',
            'resumo' => 'O caminho que a maioria faz toda manhã, na ordem em que rende mais.',
            'blocos' => [
                ['tipo' => 'passos', 'itens' => [
                    ['icone' => 'fa-user-plus', 'titulo' => 'Leads novos', 'texto' => 'Comercial → Leads, filtro "Novo". Cada linha tem telefone, e-mail, origem e o que a pessoa escreveu. Responda e marque como "Contatado" — o selo colorido é o estado do negócio, não uma anotação livre.'],
                    ['icone' => 'fa-file-signature', 'titulo' => 'Proposta enviada', 'texto' => 'Mude para "Proposta" quando enviar. Ao fechar, "Vendido" com o valor: o lead se transforma em cliente e a ficha ganha o link do cliente.'],
                    ['icone' => 'fa-life-ring', 'titulo' => 'Chamados abertos', 'texto' => 'Atendimento → Chamados. Responder aqui notifica o cliente por e-mail na mesma hora.'],
                    ['icone' => 'fa-receipt', 'titulo' => 'Pedidos', 'texto' => 'Vendas → Pedidos, filtro "Abertos". Confirme pagamento, avance para "Separando" e depois "Enviado"; "Entregue" fecha o ciclo.'],
                    ['icone' => 'fa-chart-simple', 'titulo' => 'Números', 'texto' => 'Comercial → Atribuição conta de onde veio o tráfego; Google Ads, se a conta estiver conectada, mostra o que o anúncio custou e rendeu.'],
                ]],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'Perdido também é resposta. Um lead que não vai fechar marca "Perdido" com o motivo na nota: é o que faz a taxa de conversão do período dizer a verdade.'],
            ],
        ],

        /* ---------------------------------------------------------------- Comercial */

        [
            'id' => 'leads',
            'grupo' => 'Comercial',
            'titulo' => 'Leads: do formulário ao cliente',
            'icone' => 'fa-user-plus',
            'rota' => 'admin.leads.index',
            'resumo' => 'Toda contato que chega pelo site, pelo funil ou pela loja vira um lead com origem, estado e histórico.',
            'blocos' => [
                ['tipo' => 'tabela', 'cabecalho' => ['Estado', 'Quando usar'], 'linhas' => [
                    ['Novo', 'Chegou e ninguém tocou ainda. É o que acende a bolha no menu.'],
                    ['Contatado', 'Você já respondeu ou ligou.'],
                    ['Proposta', 'Orçamento enviado, esperando a decisão.'],
                    ['Vendido', 'Fechou. Informe o valor: o lead vira cliente.'],
                    ['Perdido', 'Não vai fechar — e por quê, na nota.'],
                ]],
                ['tipo' => 'lista', 'itens' => [
                    'A ficha do lead abre a jornada inteira: primeiro toque, último toque e a linha do tempo da visita antes de você atender.',
                    'Notas são a linha do tempo da negociação — data, autor e texto; nada substitui o estado.',
                    'Editar o telefone, o e-mail ou o perfil do lead é feito na própria ficha, sem formulário separado.',
                    '**Exportar CSV** escreve os leads que estão na tela, uma coluna por campo e as colunas em ordem alfabética. O arquivo usa ponto e vírgula e traz o BOM do UTF-8, então abre no Excel em português já com as colunas certas.',
                    'Excluir é do administrador, e só depois de confirmar.',
                ]],
            ],
        ],

        [
            'id' => 'clientes',
            'grupo' => 'Comercial',
            'titulo' => 'Clientes e interações',
            'icone' => 'fa-users',
            'rota' => 'admin.clientes.index',
            'resumo' => 'O CRM: a ficha de quem já compra, com histórico, contrato e chamado no mesmo lugar.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Um lead convertido vira cliente automaticamente; cliente também pode ser cadastrado à mão.',
                    'Estados: Prospecto (ainda não paga), Ativo, Inativo.',
                    'Cada interação é uma linha do tempo — ligação, visita, e-mail, WhatsApp — e é o que a equipe lê antes de falar com o cliente.',
                    'O pedido e o chamado do cliente aparecem na própria ficha; não é preciso caçar em outra tela.',
                    '**Ver a conta do cliente** — o botão no topo da ficha abre o portal daquela pessoa na tela do painel, exatamente como ela vê, e em somente leitura. É a forma de conferir o que o cliente está vendo sem pedir a senha dele.',
                    '**Revincular ao financeiro** — o número do WHMCS gravado na ficha é o que manda nas visitas ao portal; nenhuma delas volta a conferir o e-mail. Quando a conta é recriada lá, ou quando o e-mail muda numa das pontas, este botão refaz a busca pelo e-mail de acesso de agora: o que o financeiro confirma é gravado na hora, o que ele não confirma **não apaga nada** — a ficha ganha uma pendência e o vínculo velho só se desfaz se você apertar o botão vermelho que aparece embaixo. Uma falha de conversa não muda nada nesta ficha.',
                ]],
            ],
        ],

        [
            'id' => 'campanhas',
            'grupo' => 'Comercial',
            'titulo' => 'Campanhas do site',
            'icone' => 'fa-bullhorn',
            'rota' => 'admin.campanhas.index',
            'resumo' => 'A lista que dá nome ao tráfego: cada campanha que você roda no Google, nas redes ou por link direto.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Cadastrar a campanha aqui é o que faz a atribuição mostrar "Black Friday frota" em vez de um utm_campaign cru. O lead guarda a campanha de que veio, a tabela de campanhas mostra quantos leads ela rendeu, e o Google Ads mostra o que ela custou — são as duas metades da mesma conta.'],
                ['tipo' => 'lista', 'itens' => [
                    'Ativar e desativar uma campanha é um clique; desativada some dos filtros novos, mas o histórico fica.',
                    'O nome da campanha casa com o `utm_campaign` das URLs. Escreva igual nos dois lugares.',
                ]],
            ],
        ],

        [
            'id' => 'atribuicao',
            'grupo' => 'Comercial',
            'titulo' => 'Atribuição: de onde veio cada visita',
            'icone' => 'fa-diagram-project',
            'rota' => 'admin.atribuicao.index',
            'resumo' => 'O sistema registra a jornada sem depender do Google Analytics — os sinais vêm da própria URL.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Canal (anúncio pago, rede social, busca, IA, direto, indicação), rede, campanha, página e dispositivo — cada um com a sua tabela.',
                    'Os identificadores que o sistema lê sozinhos: `utm_*`, `gclid` (Google), `fbclid` (Meta), `ttclid` (TikTok), `msclkid` (Microsoft), `li_fat_id` (LinkedIn), `yclid` (Yandex).',
                    'O cookie `golden_visitor` dura noventa dias, é de primeira parte e não guarda dado pessoal; o IP entra como hash.',
                    'Robô de índice — GPTBot, ClaudeBot, o crawler do Google — entra no registro mas fica fora de todo número. O navegador embutido do WhatsApp e do Instagram conta como gente, porque foi lá que o clique aconteceu.',
                    '**Exportar CSV** traz as visitas do período que está na tela, com o mesmo cabeçalho em português da tabela e as colunas em ordem alfabética — os rótulos `utm_*` aparecem como UTM fonte, UTM meio e UTM campanha.',
                ]],
                ['tipo' => 'codigo', 'linhas' => [
                    'php artisan rastreio:podar            # guarda um ano (golden.rastreio.dias_retencao)',
                    'php artisan rastreio:podar --dias=90  # período menor, quando preciso',
                ]],
                ['tipo' => 'aviso', 'tom' => 'amber', 'conteudo' => 'Acrescentar uma rede nova é editar a lista em `config/golden.php` (`canais`, `meios_pagos`, `meios_sociais`, `meios_ia`…), não o código.'],
            ],
        ],

        [
            'id' => 'google-ads',
            'grupo' => 'Comercial',
            'titulo' => 'Google Ads no painel',
            'icone' => 'fa-google',
            'rota' => 'admin.google-ads.index',
            'resumo' => 'Cada pessoa conecta a própria conta de anunciante e vê impressões, cliques, custo, conversões, CTR e ROAS — em português.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'A tela nunca chama a API do Google numa requisição. Ela lê a janela de cache que a fila preenche; se a janela está vazia, enfileira a sincronização e mostra "Carregando…", perguntando de novo a cada três segundos. Um F5 rápido, portanto, não custa chamada de rede nem estoura a cota da API.'],
                ['tipo' => 'passos', 'titulo' => 'Quatro passos que só existem no Google', 'itens' => [
                    ['icone' => 'fa-sitemap', 'titulo' => 'Conta gerente (MCC)', 'texto' => 'Em Google Ads → Ferramentas e configurações → Estrutura de contas, crie a conta gerente e anexe as contas de anunciante. Anote o número dela: entra sem hífen, como 1234567890.'],
                    ['icone' => 'fa-key', 'titulo' => 'Token de desenvolvedor', 'texto' => 'Em Ferramentas e configurações → API do Google Ads → Aplicação de teste, registre a aplicação e peça o token. Ele sai como Basic Access, e isso basta. É segredo da aplicação, não do anunciante: quem anuncia não precisa pedir o próprio.'],
                    ['icone' => 'fa-shield-halved', 'titulo' => 'Tela de consentimento e chave OAuth', 'texto' => 'No Google Cloud Console, crie a tela de consentimento (Externo), uma credencial do tipo Aplicação da Web e cadastre como URI de redirecionamento exatamente o endereço que o painel usa: https://SEU_DOMINIO/auth/google-ads/callback.'],
                    ['icone' => 'fa-plug', 'titulo' => 'Ativar a API', 'texto' => 'Ainda no projeto, ative a "Google Ads API". Sem isso o consentimento aparece e a chamada falha.'],
                ]],
                ['tipo' => 'texto', 'conteudo' => 'Com os quatro prontos, preencha as chaves em **Sistema → Configurações → Chaves de integração**. É o painel que manda: o que for gravado ali vale na hora, sem tocar no servidor, sem limpar configuração nem reiniciar nada. O `.env` deste servidor existe só como plano de fundo, para o que o banco não tiver.'],
                ['tipo' => 'lista', 'itens' => [
                    'Token de desenvolvedor, ID do cliente OAuth e segredo do cliente são **segredos**: cifram no banco e não voltam a aparecer na tela — o formulário mostra **se** estão gravados, nunca **o que** está gravado.',
                    'Endereço de volta do OAuth e conta gerente (MCC) são os dois opcionais. Deixe em branco para o painel usar o próprio endereço e para consultar cada conta conectada por si só.',
                    'Campo em branco é o painel se retirando do caminho: aquele valor volta a obedecer ao `.env`. Para tirar um segredo já gravado, use a chave "apagar e voltar ao .env" que aparece ao lado dele.',
                    'Faltando qualquer uma, a tela diz **qual** falta, pelo nome, e aponta onde se preenche — em vez de quebrar. O comando de sincronização responde o mesmo.',
                ]],
                ['tipo' => 'tabela', 'cabecalho' => ['O nome na tela', 'A variável de plano de fundo'], 'linhas' => [
                    ['Token de desenvolvedor', 'GOOGLE_ADS_DEVELOPER_TOKEN'],
                    ['ID do cliente OAuth', 'GOOGLE_CLIENT_ID'],
                    ['Segredo do cliente OAuth', 'GOOGLE_CLIENT_SECRET'],
                    ['Endereço de volta do OAuth', 'GOOGLE_REDIRECT_URI — tem de ser o mesmo cadastrado no Google Cloud: ${APP_URL}/auth/google-ads/callback'],
                    ['Conta gerente (MCC)', 'GOOGLE_ADS_LOGIN_CUSTOMER_ID — pode ficar vazio'],
                ]],
                ['tipo' => 'passos', 'titulo' => 'Depois de salvar as chaves', 'itens' => [
                    ['icone' => 'fa-arrow-right-to-bracket', 'titulo' => 'Conectar a conta', 'texto' => 'Comercial → Google Ads → "Conectar conta Google Ads". O Google pergunta em que conta daquele login entrar e quais permissões dar; ao voltar, a tela já mostra o nome, o identificador e a moeda da conta.'],
                    ['icone' => 'fa-arrows-rotate', 'titulo' => 'Rodar a primeira sincronização', 'texto' => 'php artisan google-ads:sync (todas as contas ativas) — ou só uma: --conta=3 --dias=7. A partir daí o agendamento cuida sozinho, a cada quinze minutos.'],
                    ['icone' => 'fa-filter', 'titulo' => 'Usar a tela', 'texto' => 'Período de sete, trinta ou noventa dias, busca pelo nome e filtro por situação. "Atualizar agora" enfileira uma sincronização nova; a tabela se refaz sozinha quando os números chegam.'],
                ]],
                ['tipo' => 'lista', 'itens' => [
                    'Um administrador opera as conexões da equipe — a agência trabalha nas contas dos clientes. Qualquer outro papel vê só as que ele conectou.',
                    'Reconectar a mesma conta atualiza o registro existente; não nasce conta duplicada.',
                    'O token de renovação fica cifrado no banco, e o token de desenvolvedor não aparece em tela, JSON ou log — nunca.',
                    'Pausar e ativar campanha têm confirmação antes e passam por uma operação simulada (`validate_only`) antes da escrita de verdade.',
                ]],
                ['tipo' => 'tabela', 'cabecalho' => ['O que a tela diz', 'O que fazer'], 'linhas' => [
                    ['Ligar o Google Ads — o cartão de abertura', 'Ele diz quantas chaves faltam, lista os nomes e leva direto a Configurações → Chaves de integração; com as chaves no lugar, o mesmo cartão oferece "Conectar conta Google Ads".'],
                    ['Carregando… por mais de dois minutos', 'Veja se o cron do agendador está ativo e se a fila anda: php artisan queue:work --stop-when-empty.'],
                    ['A última sincronização não terminou', 'O cartão marca desde quando e o motivo; "Tentar de novo" enfileira outra.'],
                    ['Conta desconectada, com e-mail para o dono', 'O Google recusou a credencial — token revogado ou senha da conta alterada. Reconecte pela tela.'],
                ]],
            ],
        ],

        [
            'id' => 'criativo',
            'grupo' => 'Comercial',
            'titulo' => 'Criativo do anúncio responsivo',
            'icone' => 'fa-wand-magic-sparkles',
            'rota' => 'admin.google-ads.criativo',
            'resumo' => 'Escrever o anúncio com as travas do Google no campo e a prévia do que a busca desenha.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Até quinze títulos de trinta caracteres e até quatro descrições de noventa; o contador de cada campo fica verde, âmbar perto do limite e vermelho quando estoura.',
                    'Duas rotas de display de até quinze caracteres cada, e a URL final.',
                    'A prévia mostra três combinações de título e descrição — as que o próprio Google sorteia entre as opções aceitas —, com "Patrocinado", o destino em verde e o título em azul.',
                    'Desktop e celular cortam o texto de jeitos diferentes: o botão troca as duas telas, e "mais" abre o resto da descrição.',
                    'A lista "Antes de levar ao Google" avisa o que está faltando — é o que a aprovação recusa.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'blue', 'conteudo' => 'Não existe API oficial de prévia do anúncio. Esta tela é o mock em HTML que o mercado usa, e funciona sem conta conectada: é o rascunho que você leva para o Google Ads.'],
            ],
        ],

        [
            'id' => 'funil',
            'grupo' => 'Comercial',
            'titulo' => 'Páginas de funil',
            'icone' => 'fa-bullseye',
            'rota' => 'admin.funil.index',
            'resumo' => 'A página de campanha que não entra no menu nem na busca: só chega quem recebeu o link.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'O conteúdo é composto por blocos — herói, benefícios, prova social, formulário — e cada tipo oferece os próprios campos.',
                    'Visibilidade "Invisível na busca" é o padrão; com "Aparece nos buscadores" a página entra no índice.',
                    'Cada visita e cada envio de formulário é contado na própria tela, e o lead nasce já amarrado ao clique que o trouxe (com o valor do clique, quando a URL traz `gclid`).',
                    'Rascunho, No ar e Pausada são os três estados; publicar é o que a mostra no link.',
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- Vendas */

        [
            'id' => 'loja',
            'grupo' => 'Vendas',
            'titulo' => 'Loja no site',
            'icone' => 'fa-store',
            'resumo' => 'O botão que abre ou fecha a vitrine pública, sem tocar em código.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Vendas → Loja no site abre a vitrine numa aba nova. Desligada, o item do menu fica esmaecido com a marca "desligada" e a loja sai do site — o catálogo continua existindo no painel.'],
                ['tipo' => 'lista', 'itens' => [
                    'As regras comerciais — frete, frete grátis acima de um valor, retirada, chave Pix e o e-mail que recebe o alerta de venda — ficam em Sistema → Configurações, no cartão da loja.',
                    'Formas de pagamento hoje: cartão e boleto bancário.',
                ]],
            ],
        ],

        [
            'id' => 'pedidos',
            'grupo' => 'Vendas',
            'titulo' => 'Pedidos',
            'icone' => 'fa-receipt',
            'rota' => 'admin.pedidos.index',
            'resumo' => 'Tudo que a loja vendeu, do "pendente" ao "entregue".',
            'blocos' => [
                ['tipo' => 'tabela', 'cabecalho' => ['Estado', 'O que fazer'], 'linhas' => [
                    ['Pendente', 'Alguém abriu o checkout e não concluiu. Vale um contato.'],
                    ['Aguardando pagamento', 'Boleto emitido ou cartão por confirmar.'],
                    ['Pago', 'O dinheiro entrou; hora de separar o equipamento.'],
                    ['Separando', 'Montando o kit do cliente.'],
                    ['Enviado', 'Saiu para entrega; informe a data.'],
                    ['Entregue', 'Fechou. O cliente vê o mesmo selo no portal.'],
                    ['Cancelado', 'Não vai mais — e o motivo fica na ficha.'],
                ]],
                ['tipo' => 'lista', 'itens' => [
                    'A bolha "abertos" do menu conta pendente, aguardando pagamento, pago e separando — o que ainda espera a equipe.',
                    'Cada pedido é ligado ao cliente e ao lead de origem, na mesma tela.',
                ]],
            ],
        ],

        [
            'id' => 'produtos',
            'grupo' => 'Vendas',
            'titulo' => 'Produtos, estoque, categorias e fornecedores',
            'icone' => 'fa-boxes-stacked',
            'rota' => 'admin.catalogo.produtos.index',
            'resumo' => 'O catálogo que a vitrine pública mostra — preço, foto, equipamento e quanto tem na prateleira.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'O selo de estoque avisa em baixo antes de faltar; a tela de estoque registra entrada e saída com data e responsável.',
                    'Categorias e fornecedores organizam a vitrine e a compra; ambos têm a própria listagem.',
                    'A foto do produto é a mesma em todo lugar: enviada pelo campo de arrastar-e-soltar, com preview antes de salvar.',
                    'Preço entra em reais e é guardado em centavos — nada de vírgula dupla.',
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- Atendimento */

        [
            'id' => 'chamados',
            'grupo' => 'Atendimento',
            'titulo' => 'Chamados',
            'icone' => 'fa-life-ring',
            'rota' => 'admin.chamados.index',
            'resumo' => 'O que o cliente abre pelo portal e a equipe responde, na mesma linha do tempo.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Estados: Aberto, Em análise, Aguardando cliente, Resolvido, Fechado. Prioridade: baixa, normal, alta, urgente.',
                    'Responder aqui envia o aviso por e-mail ao cliente no mesmo momento.',
                    'A bolha do menu conta aberto e em análise — o que depende da equipe.',
                    'Um chamado vive ligado a um cliente; a ficha do cliente mostra os dele.',
                ]],
            ],
        ],

        [
            'id' => 'contratos',
            'grupo' => 'Atendimento',
            'titulo' => 'Contratos',
            'icone' => 'fa-file-signature',
            'rota' => 'admin.contratos.index',
            'resumo' => 'O documento que o cliente lê no portal — rascunho não é documento.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Estados: Rascunho (só a equipe vê), Assinado, Vigente, Encerrado.',
                    'O contrato é do cliente ou da adesão ao plano; no portal ele aparece na aba "Contratos" do próprio cliente.',
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- Assistente */

        [
            'id' => 'assistente',
            'grupo' => 'Atendimento',
            'titulo' => 'Assistente virtual',
            'icone' => 'fa-robot',
            'rota' => 'admin.assistente.persona',
            'resumo' => 'O nome, o rosto, o tom e as regras de quem atende na caixa do WhatsApp.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Existe um assistente só, e esta tela edita a ficha dele. O que a máquina recebe como ordem é montado em cima deste preenchimento: nome, cargo, saudação, tom de fala e as regras escritas por você — que entram na ordem exatamente como foram escritas, sem reescrita.'],
                ['tipo' => 'lista', 'itens' => [
                    'Onde ele atende são quatro interruptores: ligado, no site, na página de funil e no chat do chamado. Desligado daqui, o robô não abre a boca naqueles lugares — e a equipe continua vendo a conversa.',
                    'O tom de fala padrão é **Descolado e bem-humorado** — natural, atencioso e sem clichê de robô. A lista oferece também Amigável, Formal, Direto e técnico e Comercial.',
                    'As regras já vêm escritas para o que importa: acolher primeiro quem está em emergência, vender sem empurrar, não inventar preço nem prazo, não pedir senha, código de acesso nem número de cartão, não ler em voz alta dado de cadastro de ninguém (a conferência de identidade é decisão do sistema, não da máquina de falar) e voltar à equipe quando o assunto fugir.',
                    'O nome antes do assunto: a primeira resposta da bolha é a saudação seguida do pedido do nome, e a máquina aproveita o nome dito em qualquer fala — "me chamo Ana", "sou a Maria", "quem fala é o Carlos". Depois de `Vezes que o nome é pedido` esgotado (padrão 3, na tela de chaves), quem não quis se identificar é atendido normalmente, sem nome. O interruptor **Perguntar o nome antes de conversar** desliga o pedido inteiro, e a frase do pedido se edita nesta mesma ficha.',
                    'E-mail, número solto, saudação e respostas como "sou cliente" nunca viram nome: são dados ou frases, não vocativos.',
                    'A resposta a quem disse só o nome é a frase **Resposta a quem disse o nome**, com `:nome` no lugar do nome dito (vale só o primeiro nome). Ela sai na hora, sem consulta a provedor nenhum — apresentar-se não é pergunta, e mandar a apresentação à IA faria a máquina responder "não entendi" e chamar a equipe para atender quem acabou de se apresentar. Quem chega com assunto pela frente ("Maria, quero a segunda via") continua direto para a ação.',
                    'A frase do pedido do nome entra na ficha e vale para a bolha inteira; o que a pessoa respondeu nunca é gravado como credencial de nada.',
                    'O atendimento personalizado tem porta: enquanto a máquina não tem nome **e** e-mail do cadastro, o que mexe na ficha da pessoa — segunda via de fatura, qualquer ação no financeiro — não abre. O assunto geral continua sendo respondido na hora; o pedido que ficou na fila retoma sozinho assim que o endereço fecha, sem a pessoa repetir nada.',
                    'O e-mail é pedido com a frase **Pergunta pelo e-mail**, e a confirmação com **Resposta a quem disse o e-mail**. Passado `Vezes que o e-mail é pedido` (padrão 3, na tela de chaves), a conversa segue sem e-mail e o pedido personalizado vai para a equipe em vez de virar formulário infinito. O interruptor **Exigir nome e e-mail antes de mexer na conta da pessoa** desliga a porta inteira — a conferência de segurança dos dados da ficha não depende dele e continua valendo igual.',
                    'No fim de um atendimento de verdade — a ação concluída ou a despedida escrita no chat — a máquina pede a nota de 1 a 5 com a frase **Pedido de avaliação**, uma única vez por conversa. A pessoa toca nas estrelas da bolha ou escreve o número: os dois caminhos gravam as mesmas colunas, e a resposta é a frase **Agradecimento pela nota**. Quem já foi assumido por um humano não recebe pesquisa — a nota avaliaria quem não atendeu.',
                    'Quem encerra ("obrigado, era isso") é reconhecido por regra, antes de qualquer consulta ao provedor: com a pesquisa ainda aberta, a fala vira o pedido de nota; com a nota já dada, sai a frase **Despedida**, com `:nome`. Despedida não é pergunta, e mandá-la à IA terminaria em "não sei responder, chamei a equipe" — chamando a equipe para fechar um atendimento já concluído.',
                    'O rosto entra por arrastar e soltar, quadrado (96 × 96 px). Sem arquivo, o site desenha a inicial do nome, do mesmo jeito do carrossel de depoimentos.',
                    'A voz das respostas em áudio só lista o que os provedores com chave ligada sabem falar; em branco, vale a voz padrão da casa.',
                    'A bolha de atendimento tem texto próprio na mesma ficha: a dica ao passar o mouse (com `:nome`), o selo de status, o texto do campo, o link para falar com uma pessoa e o ícone de quem ainda não tem rosto. Em branco vale o padrão da casa.',
                    'No canto do site e da página de funil, o assistente é um dos atendentes da caixa do WhatsApp: ele aparece no topo da lista, com nome, cargo e rosto da ficha, e a escolha dele abre a conversa no próprio site, no lugar da caixa.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'Ficha preenchida não basta: sem chave de provedor em `Sistema → Configurações → Chaves de integração`, o assistente atende pedindo a equipe. O cartão "Ele consegue responder?" diz qual capacidade ainda está manca.'],
            ],
        ],

        [
            'id' => 'assistente-conhecimento',
            'grupo' => 'Atendimento',
            'titulo' => 'O que o assistente sabe',
            'icone' => 'fa-book-open',
            'rota' => 'admin.assistente.conhecimento.index',
            'resumo' => 'A base que a máquina lê antes de responder — e a prova de onde veio cada resposta.',
            'blocos' => [
                ['tipo' => 'passos', 'itens' => [
                    ['icone' => 'fa-graduation-cap', 'titulo' => 'Comece pela base da casa', 'texto' => 'O botão **Ensinar o básico da Golden** acrescenta o que falta: 16 assuntos escritos a partir do site (rastreadores, instalação, bloqueio, planos, plataforma, portal e financeiro) e o catálogo montado com os produtos ativos da loja. Número de WhatsApp, e-mail, servidores e endereço do portal entram preenchidos com o que está no painel.'],
                    ['icone' => 'fa-pen', 'titulo' => 'Escreva a resposta, não a pergunta', 'texto' => 'O campo "Resposta" é o texto que a máquina usa como fonte. O "Assunto" é o que a busca interna acha quando o cliente pergunta.'],
                    ['icone' => 'fa-tags', 'titulo' => 'Ajude a busca com os assuntos', 'texto' => 'Palavras separadas por vírgula: bloqueio, sinal, plataforma, preço. É por elas e pelo título que o item entra na resposta.'],
                    ['icone' => 'fa-link', 'titulo' => 'Diga a fonte', 'texto' => 'Endereço de onde o texto foi tirado. A equipe lê isto para conferir o que a máquina respondeu.'],
                ]],
                ['tipo' => 'lista', 'itens' => [
                    'Item oculto não entra na resposta — serve para despublicar um preço sem apagar o histórico.',
                    'Ensinar de novo nunca apaga o que você editou: o botão só acrescenta o que falta e regrava o catálogo, porque preço de produto muda no painel. Para voltar um texto ao original, use o comando `php artisan assistente:ensinar --atualizar`; a personalidade só volta com `--personalidade`.',
                    'O que a leitura automática traz da internet chega aqui com origem, endereço e data de leitura; a chave da fonte impede que a mesma página seja aprendida duas vezes.',
                    'A busca é feita sobre o texto gravado nesta tela e nas páginas permitidas — nada de índice vetorial, que é o que permite o atendimento funcionar na hospedagem compartilhada.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'amber', 'conteudo' => 'Resposta sem fonte é chute. Com o interruptor "Só responder o que tem fonte" ligado, o que não estiver aqui ou nas páginas lidas faz a máquina chamar a equipe em vez de inventar preço, prazo ou cobertura.'],
            ],
        ],

        [
            'id' => 'assistente-conversas',
            'grupo' => 'Atendimento',
            'titulo' => 'Conversas do site',
            'icone' => 'fa-comments',
            'rota' => 'admin.assistente.conversas.index',
            'resumo' => 'A fila dos visitantes anônimos que abriram a bolha — o equivalente, no site, da fila de chamados.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Quem tem conta no portal fala pelo chamado, e aquela fila continua sendo a dos chamados. Esta tela é o outro lado: o visitante que ainda é ninguém para o sistema. A conversa nasce anônima, pode virar lead no meio e virar chamado depois, sempre com o histórico junto.'],
                ['tipo' => 'lista', 'itens' => [
                    'Aguardando a equipe é âmbar no menu porque é o que chama gente: a máquina parou por não ter fonte, por não ter conseguido ler a mídia, porque o cliente pediu uma pessoa ou porque nenhum provedor estava ligado.',
                    'Responder aqui assume a conversa: a fala sai assinada pelo seu login e a máquina sai de cena no resto do fio — o cliente nunca recebe duas respostas da mesma pergunta.',
                    'Sob a resposta da máquina, a lista de fontes diz item por item de onde ela tirou aquilo.',
                    'Áudio, imagem e documento aparecem na fala com o que foi transcrito por baixo, quando a transcrição foi ligada na tela de chaves.',
                    'Robô de busca não vê a bolha e não abre conversa: o índice do Google continua recebendo o site, não uma fila de perguntas fictícias.',
                    'Quando a máquina escala, a equipe recebe a conversa por e-mail e o visitante vê exatamente a frase de escalada — sem promessa de preço, prazo ou cobertura que não está na base.',
                    'Ação em curso aparece no topo da conversa em português: o que a máquina está fazendo (**Segunda via da fatura**), em que passo (**esperando o e-mail do cadastro** ou **conferindo os dados da ficha**), quantos dos erros aceitos já foram gastos e a hora em que a conferência vence. É linha de estado, não de transcrição: **o que a pessoa respondeu nunca aparece aqui**, nem na ficha do cliente nem no e-mail de escala — a resposta é comparada com o banco e descartada na mesma hora.',
                    'Ação não é IA: quem reconhece o pedido, confere o dado e dispara o e-mail é o código da casa, com frases fixas. Uma conversa que escala sem nenhuma chave de provedor ligada é a prova de que foi a máquina de estado que atendeu.',
                    'Escalada por segurança tem motivo próprio na fila: **a ficha não tinha dado para conferir**, **a conferência de dados não passou** e **ação do assistente não pôde ser concluída** dizem à equipe de onde retomar — e em nenhum desses caminhos algo foi enviado.',
                    'Cadastro feito pela bolha amarra a conversa ao lead: a ficha do lead traz o resumo do que o visitante já contou, e ninguém reescreve a própria dor.',
                    'A nota do atendimento aparece na ficha da conversa — `5/5`, `pedida, sem nota` ou `não pedida` — e o e-mail do cadastro, quando ele foi dito, é o que amarra a conversa à ficha do cliente. Estrela tocada na bolha e número escrito no chat gravam a mesma coluna: não há duas avaliações de uma conversa só.',
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- Conteúdo */

        [
            'id' => 'paginas',
            'grupo' => 'Conteúdo do site',
            'titulo' => 'Páginas do site',
            'icone' => 'fa-newspaper',
            'rota' => 'admin.conteudo.paginas.index',
            'resumo' => 'O texto e a ordem das páginas públicas, mudados do painel em vez do código.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Cada página cadastrada aqui responde pela URL dela no site, com título, SEO e corpo. A lista diz o que está publicado, e "Ver" abre exatamente o que o visitante vê.'],
            ],
        ],

        [
            'id' => 'imagens',
            'grupo' => 'Conteúdo do site',
            'titulo' => 'Imagens e textos do site',
            'icone' => 'fa-image',
            'rota' => 'admin.conteudo.imagens.index',
            'resumo' => 'As fotografias do site divididas por área — identidade, Home e os cartazes das páginas — e a abertura de cada página.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'A tela abre em três abas: **Identidade** (logo, favicon e o cartão que as redes mostram ao compartilhar um link), **Home** (as faixas da página inicial, na ordem em que aparecem) e **Cartazes das páginas** (o topo de cada página do site). Cada aba mostra quantas fotos guarda.',
                    'No topo, os quatro contadores dizem o estado do site inteiro: quantas fotos existem, quantas vêm do painel, quantas ainda são a foto do pacote e quantas estão sem imagem no ar.',
                    'Cada ficha traz a foto que está no ar na moldura do card — clique nela para ver em tamanho grande, com o link do arquivo e da página —, a medida em pixels, o selo do que está valendo e o link **Ver esta foto no site**. Na aba dos cartazes, o nome da página no alto do card diz onde cada foto entra.',
                    'Trocar a foto é soltar a nova no lugar da antiga; **Voltar à foto original do pacote** apaga o envio e devolve o arquivo instalado, junto com o texto alternativo registrado.',
                    'Textos das páginas corrigem a abertura — rótulo, eyebrow, título e subtítulo — sem tocar no corpo.',
                    'O site só mostra o que existe aqui: nenhuma view escreve caminho de imagem por conta própria.',
                ]],
            ],
        ],

        [
            'id' => 'depoimentos',
            'grupo' => 'Conteúdo do site',
            'titulo' => 'Depoimentos',
            'icone' => 'fa-quote-left',
            'rota' => 'admin.conteudo.depoimentos.index',
            'resumo' => 'A prova social do site e do funil, com três origens possíveis.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Escrito pela equipe, importado do perfil do Google Business ou enviado pelo próprio cliente pelo portal.',
                    'A importação do Google pede três valores em Sistema → Configurações → Chaves de integração → Avaliações do Google (conta, ficha e token). Sem eles a tela oferece só as outras duas origens — e o sistema não inventa avaliação.',
                    'Só o que está aprovado aparece no site — nada vai para o ar sozinho.',
                    'O carrossel é um só em todo o sistema: mesma folha, mesmo arraste, mesmos pontos.',
                ]],
            ],
        ],

        [
            'id' => 'videos',
            'grupo' => 'Conteúdo do site',
            'titulo' => 'Vídeos tutoriais',
            'icone' => 'fa-play',
            'rota' => 'admin.conteudo.videos.index',
            'resumo' => 'O acervo que vira a página /videos do site.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Cada vídeo entra por categoria e endereço; o painel guarda o identificador e a página pública desenha a miniatura.',
                    'Nada toca sozinho: o play é o da casa, e o vídeo abre na própria página.',
                    'A galeria pública pagina doze por vez — menos cartões acima da dobra, menos requisição no primeiro carregamento.',
                ]],
            ],
        ],

        [
            'id' => 'faqs',
            'grupo' => 'Conteúdo do site',
            'titulo' => 'Perguntas frequentes',
            'icone' => 'fa-circle-question',
            'rota' => 'admin.conteudo.faqs.index',
            'resumo' => 'As perguntas que caem no acordeão da página /faq.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Pergunta, resposta e ordem. O que estiver cadastrado aqui aparece no site e no bloco de dúvidas das páginas de funil.'],
            ],
        ],

        /* ---------------------------------------------------------------- Cliente */

        [
            'id' => 'portal',
            'grupo' => 'Portal do cliente',
            'titulo' => 'O que o cliente vê',
            'icone' => 'fa-user-tie',
            'resumo' => 'A casa do cliente no mesmo domínio: /portal — ele nunca recebe acesso ao painel.',
            'blocos' => [
                ['tipo' => 'passos', 'itens' => [
                    ['icone' => 'fa-id-card', 'titulo' => 'Criar a conta', 'texto' => 'O cliente se cadastra com o próprio e-mail. A senha dele vive na conta dele e não dá acesso a nada do painel.'],
                    ['icone' => 'fa-box', 'titulo' => 'Pedidos', 'texto' => 'Ele acompanha o estado de cada compra com o mesmo selo colorido que a equipe vê.'],
                    ['icone' => 'fa-life-ring', 'titulo' => 'Chamados', 'texto' => 'Abre, acompanha e responde; o que ele escreve chega na tela da equipe, e a resposta volta por e-mail.'],
                    ['icone' => 'fa-file-signature', 'titulo' => 'Contratos', 'texto' => 'Lê os contratos publicados no nome dele.'],
                    ['icone' => 'fa-file-invoice-dollar', 'titulo' => 'Faturas e serviços', 'texto' => 'Vê os serviços contratados e as faturas direto do financeiro (WHMCS), com o botão para pagar ou pegar a segunda via.'],
                    ['icone' => 'fa-pen', 'titulo' => 'Meus dados', 'texto' => 'Corrige o cadastro sozinho — telefone, endereço e o que for pedido na entrega — e a mesma senha passa a valer no financeiro.'],
                ]],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'O financeiro só aparece com as três credenciais em **Sistema → Configurações → Chaves de integração → WHMCS**: endereço, identificador e segredo da API. Sem elas as telas explicam que a área está em preparação e não quebram. O e-mail de acesso não é espelhado: ele é o login do financeiro, e trocá-lo por aqui poderia trancar o cliente do outro lado.'],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'O que passa por essa conexão é decisão da casa, e se muda em **Sistema → Configurações → Financeiro (WHMCS)**: cada tela tem o seu interruptor, mais o dos serviços encerrados, das faturas pagas, dos grupos exibidos, do link que leva o cliente para fora do portal e do espelho de cadastro e senha. Desligada, a tela do portal diz "esta área está fechada" com o caminho de volta para o início — e nenhuma chamada sai do servidor para o financeiro.'],
                ['tipo' => 'texto', 'conteudo' => 'A equipe pode entrar na conta de um cliente sem pedir a senha dele. O botão **Ver a conta do cliente**, no topo da ficha, abre o portal daquela pessoa numa sessão só de leitura: as mesmas telas, os mesmos filtros de veiculação, nada de formulário gravando. Uma barra amarela no alto avisa em cuja conta você está, quem abriu e a que hora a janela fecha.'],
                ['tipo' => 'lista', 'itens' => [
                    'Sair é o botão **Sair da conta do cliente** da própria barra, e devolve o administrador à ficha — a sessão do painel nunca é derrubada.',
                    'A janela tem prazo (quarenta e cinco minutos, por padrão) e vence sozinha: passado o prazo, a sessão do portal cai e ninguém fica navegando de conta emprestada.',
                    'Toda escrita está bloqueada dentro da janela, inclusive a que alguém tentasse por formulário esquecido no HTML: a tentativa não grava, devolve um aviso dizendo que nada foi gravado, e fica registrada na auditoria.',
                    'O cliente de verdade, com a própria senha, continua gravando na conta dele — a barreira é da janela de supervisão, não do portal. E quem entra com a própria senha não herda a barra: o login limpa o marcador.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'blue', 'conteudo' => 'A supervisão liga e desliga no mesmo lugar das outras veiculações — **Sistema → Configurações → Financeiro (WHMCS)** — junto do prazo da janela. Desligada, o botão some da ficha, a rota responde 403 e uma janela já aberta se fecha na próxima requisição.'],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'Todo aviso do portal — chamado aberto, resposta da equipe, recibo, recuperação de senha — entra em fila e sai pelo agendador. Sem o cron do servidor, a mensagem espera na tabela `jobs`.'],
            ],
        ],

        /* ---------------------------------------------------------------- Sistema */

        [
            'id' => 'notificacoes',
            'grupo' => 'Sistema',
            'titulo' => 'Central de notificações',
            'icone' => 'fa-bell',
            'rota' => 'admin.notificacoes.index',
            'resumo' => 'O que o sistema precisa contar à equipe: o sino do topo, a página com o histórico e uma ficha para cada aviso.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Cada aviso nasce de um fato do sistema — chamado aberto, resposta do cliente, pedido pago, tarefa do cron que falhou — e chega para quem tem acesso ao painel. O sino do topo mostra o que ainda não foi lido; abrir a notificação a retira do sino e a mantém na página Notificações, com o histórico inteiro.'],
                ['tipo' => 'lista', 'itens' => [
                    'O sino consulta a central de tempos em tempos e fica quieto quando a aba está escondida — nada de requisição em segundo plano à toa.',
                    'Cada aviso tem a própria ficha: a mensagem inteira, os dados que a geraram e o botão que leva ao registro de origem — o chamado, o pedido, a campanha, a tarefa do cron.',
                    'Marcar como lidas limpa o sino de uma vez, sem apagar nada: o que foi lido continua listado, com a data da leitura.',
                    'Preferências — cada tipo de aviso pode ser desligado, e o tipo desligado não gera aviso para quem desligou. O administrador define também por quantos dias a central guarda uma notificação e quando ela passa a vencida.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'blue', 'conteudo' => 'A central é o canal de dentro do painel, e não substitui o e-mail: o mesmo fato que vira notificação é o que entra na fila para o destinatário. Desligar o aviso aqui silencia o sino, não a caixa de entrada.'],
            ],
        ],

        [
            'id' => 'configuracoes',
            'grupo' => 'Sistema',
            'titulo' => 'Configurações',
            'icone' => 'fa-sliders',
            'rota' => 'admin.settings.index',
            'resumo' => 'O que é decisão de negócio e o que é credencial fica aqui, não em arquivo — cada assunto na sua aba: contato, acessos do site, loja, WhatsApp, medição, financeiro (WHMCS) e chaves de integração.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'A tela é dividida em abas, uma por assunto — Contato e endereço, Acessos do site, Loja virtual, WhatsApp, Medição, Financeiro (WHMCS) e Chaves de integração. Cada aba tem o seu botão de salvar e grava só o que está nela: salvar os acessos não toca no contato, e desligar a medição não apaga nenhuma chave.',
                    'Contato e endereço — nome do site, WhatsApp, telefone, e-mail, endereço, cidade, CEP e **fuso horário**. O nome do site e o WhatsApp são os únicos obrigatórios, e só nesta aba. O fuso é o relógio de tudo (a hora em cada fala da Ana, as datas do painel, do portal e dos e-mails): escolha o do seu estado, porque a hospedagem acorda em UTC e o chat sai três horas adiantado para quem escreve do Brasil. Em branco, vale o `APP_TIMEZONE` do servidor.',
                    'Acessos do site — para onde levam os acessos externos (o financeiro antigo e os dois servidores de rastreamento) e os textos do submenu do ícone de login e do botão **Rastreamento** do cabeçalho. O portal do cliente não aparece aqui: é rota interna e acompanha a sessão.',
                    'Loja virtual — liga e desliga a vitrine, e guarda frete, corte do frete grátis, retirada, chave Pix e o e-mail que recebe o alerta de venda.',
                    'WhatsApp — a caixa multicanal do botão flutuante inteira, item por item: título, subtítulo, selo de status, dica do botão, rodapé e mensagem de abertura; e, em cada canal, nome, descrição, ícone, número, mensagem pronta e o interruptor. Campo em branco devolve aquele item ao texto padrão da casa.',
                    'Medição e consentimento — ligar ou desligar a medição própria e as etiquetas externas: Google Analytics (`ga_measurement_id`), Meta Pixel e GTM. Deixar tudo vazio é legítimo: a atribuição do sistema não depende de nenhum dos três.',
                    'Financeiro (WHMCS) — diz o estado da conexão (se as três credenciais estão no lugar e qual endereço o painel consulta de verdade), oferece a **sonda**: informe o e-mail de um cliente e a tela responde em português o que o financeiro disse — conta confirmada, conta inexistente, credencial recusada, erro de HTTP ou servidor mudo. Na mesma aba ficam os interruptores do que a conexão veicula: quais telas do portal estão abertas, se serviço encerrado e fatura paga entram na lista, quais grupos de produtos aparecem, se o cliente pode saltar para o financeiro e se o portal espelha cadastro e senha lá. Interruptor desligado não é botão escondido: a requisição nem é feita, e o item sai do menu do portal. Nesta mesma aba moram ainda a licença de **supervisão** — o botão da ficha que abre o portal de um cliente em modo leitura — e o prazo da janela, e o interruptor da **segunda via pelo chat**: ligado, quem pedir a via da fatura na conversa é atendido pela máquina, depois de conferir os dados da ficha e sempre para o e-mail cadastrado; desligado, o assunto volta a ser o portal e a equipe, e nenhuma chamada ao WHMCS acontece por causa do chat.',
                    'Chaves de integração — Google Ads, Avaliações do Google, E-mail de disparo, WHMCS (financeiro e serviços) e o assistente (chave, modelo, chat e aprendizado), cada grupo na sua aba interna. Na aba **E-mail de disparo** estão as seis chaves do correio e o card **Testar o disparo agora**, que manda uma mensagem de verdade pelo transporte da tela e responde em português o que aconteceu. Na aba **Assistente** estão o pedido do nome (ligar/desligar e quantas vezes ele é perguntado) e a régua da conferência de segurança: os dados que a ficha precisa confirmar antes de qualquer envio (`documento`, `nascimento`, `placas`, `telefone`, `cep`, na ordem em que são perguntados), quantos acertos liberam o envio, quantas respostas erradas são aceitas e por quantos minutos a conferência fica de pé. É o que se preenche nesta tela, nunca no `.env`: o valor grava no banco (cifrado quando é segredo) e vale na hora, sem limpar configuração nem reiniciar o servidor.',
                ]],
            ],
        ],

        [
            'id' => 'crons',
            'grupo' => 'Sistema',
            'titulo' => 'Crons e a agenda',
            'icone' => 'fa-clock',
            'rota' => 'admin.crons.index',
            'resumo' => 'A agenda do sistema configurada pela tela: a hospedagem tem uma linha só, e é daqui que sai o que ela executa.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'O servidor tem **uma** linha no cron da hospedagem, e ela não muda: chama `php artisan schedule:run` a cada minuto. O que roda depois disso é decidido nesta tela — incluir, editar, pausar, excluir e rodar agora, sem tocar no cPanel.'],
                ['tipo' => 'lista', 'itens' => [
                    'Cada tarefa escolhe um comando registrado no `artisan` e uma frequência — de minuto a minuto, de hora em hora, diária, semanal ou mensal. As flags ficam num campo separado e voltam a compor a mesma linha de comando.',
                    'O histórico guarda cada execução com origem, duração, saída e erro — o botão Rodar agora deixa o mesmo rastro do cron, mudando só a origem.',
                    'Uma tarefa pausada sai da agenda na hora. Ligar "uma por vez" impede que a execução de hoje encontre a de ontem ainda rodando.',
                    'As tarefas que o sistema traz prontas podem ser pausadas e reagendadas, mas não apagadas: apagar o worker da fila é o jeito mais silencioso de perder todo e-mail do portal, porque nada quebra — só para de sair.',
                    'A batida do agendador fica no topo da tela. Se o cron da hospedagem parar, é ali que o aviso aparece antes de qualquer tarefa atrasar.',
                ]],
                ['tipo' => 'aviso', 'tom' => 'amber', 'conteudo' => 'O comando de fila não aceita Rodar agora: ele prende a tela por dezenas de segundos para fazer o que o próprio minuto faria. Espere a volta seguinte ou rode `queue:work --stop-when-empty` pelo terminal.'],
            ],
        ],

        [
            'id' => 'usuarios',
            'grupo' => 'Sistema',
            'titulo' => 'Usuários e papéis',
            'icone' => 'fa-user-shield',
            'rota' => 'admin.usuarios.index',
            'resumo' => 'Quem entra no painel, com o que cada papel pode mexer.',
            'blocos' => [
                ['tipo' => 'tabela', 'cabecalho' => ['Papel', 'O que alcança'], 'linhas' => [
                    ['Administrador', 'Tudo, inclusive Conteúdo do site, Sistema, exclusões e catálogo.'],
                    ['Vendedor', 'Comercial, Vendas e Atendimento. Não vê as áreas restritas, nem apaga registro.'],
                ]],
                ['tipo' => 'lista', 'itens' => [
                    'Um usuário desativado perde o acesso sem perder o histórico do que fez.',
                    'Quem esqueceu a senha recupera pelo próprio link da entrada; o e-mail sai pela fila.',
                    'A senha nunca aparece em tela, log ou repositório — é o primeiro "não" deste sistema.',
                ]],
            ],
        ],

        [
            'id' => 'auditoria',
            'grupo' => 'Sistema',
            'titulo' => 'Auditoria',
            'icone' => 'fa-shield-halved',
            'rota' => 'admin.activities.index',
            'resumo' => 'O registro do que aconteceu: quem fez, o quê e quando.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Configuração salva, loja ligada, lead convertido, chamado respondido, conta do Google Ads desconectada — cada uma dessas ações deixa uma linha aqui, com autor e data. É por onde se começa quando algo mudou e ninguém lembra quem mudou.'],
            ],
        ],

        [
            'id' => 'ambiente',
            'grupo' => 'Sistema',
            'titulo' => 'Ambiente, e-mail e agendador',
            'icone' => 'fa-server',
            'resumo' => 'O que existe fora das telas e faz o sistema funcionar: o plano de fundo do .env, a fila de e-mail e o cron.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'Todo valor configurável se preenche nas telas, e o que o painel grava fica no banco — com os segredos cifrados. O `.env` deste servidor é o **plano de fundo**: ele conduz apenas o que o banco não tem. Ele nunca vai para o repositório, e o modelo é o `.env.example`. Mudou o `.env`, o `php artisan config:clear` continua sendo do servidor; quem usa o painel não limpa nada, porque salvar na tela vale na hora.'],
                ['tipo' => 'lista', 'itens' => [
                    'SMTP — o que faz o recado sair. A credencial se preenche em Sistema → Configurações → Chaves de integração → E-mail de disparo, e a mesma aba tem o **Testar o disparo agora**: a tela mostra transporte, servidor, porta, criptografia, usuário e remetente, e manda uma mensagem de verdade para o endereço que você digitar — o mesmo caminho do link de senha do portal e da segunda via da fatura, não apenas a conexão. Faltando chave, o código continua pronto: falta a credencial, não o recurso.',
                    'Criptografia — a porta decide o nome: 465 pede SSL desde o abraço (`smtps`), 587 pede STARTTLS (`smtp`). Os nomes velhos (`tls`, `ssl`, `MAIL_ENCRYPTION`) continuam aceitos no plano de fundo e são traduzidos uma única vez, porque um nome desconhecido ali não é aviso: é o erro que derruba todo disparo.',
                    'Fila — todo aviso entra em fila, e um único `queue:work --stop-when-empty` por minuto drena o que está preso. Não use worker residente nem `queue:listen` em hospedagem compartilhada.',
                    'Agendador — uma linha no cron do cPanel toca as tarefas: sincronizar Google Ads a cada quinze minutos, podar o rastreio toda terça e drenar a fila a cada minuto.',
                ]],
                ['tipo' => 'codigo', 'linhas' => [
                    '* * * * * cd ~/public_html && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1',
                    '',
                    'php artisan queue:work --stop-when-empty   # consome o que está preso e sai',
                    'php artisan queue:retry all                 # devolve o que falhou para a fila',
                ]],
                ['tipo' => 'aviso', 'tom' => 'amber', 'conteudo' => 'O caminho do PHP da linha acima é o do site, não o `php` do PATH da conta — que costuma ser mais velho. Para descobrir o certo: ea-php83 -r \'echo PHP_VERSION;\''],
            ],
        ],

        [
            'id' => 'diagnostico',
            'grupo' => 'Sistema',
            'titulo' => 'Quando algo não sai',
            'icone' => 'fa-screwdriver-wrench',
            'resumo' => 'Onde olhar, nesta ordem, antes de abrir um chamado para quem mantém o código.',
            'blocos' => [
                ['tipo' => 'passos', 'itens' => [
                    ['icone' => 'fa-eye', 'titulo' => 'Leia o que a tela diz', 'texto' => 'O painel foi feito para nomear o problema: falta credencial, período, conta ou permissão. O recado vermelho diz qual variável, qual registro, qual janela.'],
                    ['icone' => 'fa-list-check', 'titulo' => 'Auditoria', 'texto' => 'Sistema → Auditoria mostra a última vez que alguém mexeu naquilo.'],
                    ['icone' => 'fa-envelope-circle-check', 'titulo' => 'Fila de e-mail', 'texto' => 'Recado que não chegou está parado em `jobs`; o que estourou ao ser montado foi para `failed_jobs`, com o erro por extenso — corrigida a causa, `queue:retry all` recoloca na fila.'],
                    ['icone' => 'fa-database', 'titulo' => 'Log da API', 'texto' => 'Para o Google Ads, cada chamada fica em `google_ads_api_logs` com requisição, resposta e erro.'],
                    ['icone' => 'fa-file-lines', 'titulo' => 'Arquivo de log', 'texto' => 'Em `storage/logs`, o Laravel escreve o excepcionado que não virou recado na tela.'],
                ]],
                ['tipo' => 'aviso', 'tom' => 'blue', 'conteudo' => 'Nada disso exige senha de banco, SSH ou acesso ao servidor. As telas foram desenhadas para que a operação inteira — inclusive ver o que falhou — aconteça dentro do painel.'],
            ],
        ],
    ],

    /*
    | O link discreto do cabeçalho: qual seção do guia documenta cada tela. A chave é o começo
    | do nome da rota e ganha o prefixo mais longo que casar — assim o Google Ads pode apontar
    | para "google-ads" e a tela do criativo, que mora embaixo dele, para "criativo".
    | Uma rota ausente desta lista simplesmente não mostra o link; nada quebra.
    */
    'rotas' => [
        'admin.dashboard' => 'comeco',
        'admin.leads.' => 'leads',
        'admin.clientes.' => 'clientes',
        'admin.campanhas.' => 'campanhas',
        'admin.atribuicao.' => 'atribuicao',
        'admin.google-ads.criativo' => 'criativo',
        'admin.google-ads.' => 'google-ads',
        'admin.funil.' => 'funil',
        'admin.pedidos.' => 'pedidos',
        'admin.catalogo.' => 'produtos',
        'admin.chamados.' => 'chamados',
        // Os três nomes começam com 'admin.assistente.'; o mapa pega o prefixo mais longo,
        // então cada tela lê a própria seção em vez de cair na ficha.
        'admin.assistente.persona' => 'assistente',
        'admin.assistente.conhecimento.' => 'assistente-conhecimento',
        'admin.assistente.conversas.' => 'assistente-conversas',
        'admin.contratos.' => 'contratos',
        'admin.conteudo.paginas.' => 'paginas',
        'admin.conteudo.imagens.' => 'imagens',
        'admin.conteudo.textos.' => 'imagens',
        'admin.conteudo.depoimentos.' => 'depoimentos',
        'admin.conteudo.videos.' => 'videos',
        'admin.conteudo.faqs.' => 'faqs',
        'admin.notificacoes.' => 'notificacoes',
        'admin.settings.' => 'configuracoes',
        'admin.crons.' => 'crons',
        'admin.usuarios.' => 'usuarios',
        'admin.activities.' => 'auditoria',
    ],
];
