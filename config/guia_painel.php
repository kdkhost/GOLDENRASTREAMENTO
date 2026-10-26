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
                ['tipo' => 'texto', 'conteudo' => 'A barra da esquerda traz cinco grupos — Comercial, Vendas, Atendimento, Conteúdo do site e Sistema. Cada grupo abre e fecha; o grupo da página em que você está já nasce aberto, e abrir outro fecha o anterior. Quem é vendedor vê os três primeiros; "Conteúdo do site" e "Sistema" são do administrador.'],
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
                ['tipo' => 'texto', 'conteudo' => 'Com os quatro prontos, preencha o `.env` do servidor. Faltando qualquer um, a tela diz **qual** falta, pelo nome, em vez de quebrar — e o comando de sincronização responde o mesmo.'],
                ['tipo' => 'codigo', 'linhas' => [
                    'GOOGLE_ADS_DEVELOPER_TOKEN=seu-token',
                    'GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com',
                    'GOOGLE_CLIENT_SECRET=seu-segredo',
                    'GOOGLE_REDIRECT_URI="${APP_URL}/auth/google-ads/callback"',
                    'GOOGLE_ADS_LOGIN_CUSTOMER_ID=1234567890   # o número da MCC, sem hífen; pode ficar vazio',
                ]],
                ['tipo' => 'passos', 'titulo' => 'Depois de salvar o .env', 'itens' => [
                    ['icone' => 'fa-broom', 'titulo' => 'Limpar a configuração', 'texto' => 'php artisan config:clear. Sem isso o Laravel continua lendo o arquivo antigo.'],
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
                    ['A integração ainda não tem credenciais', 'Faltam valores no `.env` deste servidor; o cartão lista quais.'],
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
            'resumo' => 'As fotografias por página e a abertura (título, subtítulo, etiqueta) de cada uma.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Imagens do site guarda uma linha por arquivo, organizada pela página onde aparece; trocar a foto é subir a nova no lugar da antiga.',
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
                    'A importação do Google pede a chave em `GOOGLE_PLACES_API_KEY`; sem ela a tela oferece só as outras duas origens.',
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
                    ['icone' => 'fa-pen', 'titulo' => 'Meus dados', 'texto' => 'Corrige o cadastro sozinho — telefone, endereço e o que for pedido na entrega.'],
                ]],
                ['tipo' => 'aviso', 'tom' => 'gold', 'conteudo' => 'Todo aviso do portal — chamado aberto, resposta da equipe, recibo, recuperação de senha — entra em fila e sai pelo agendador. Sem o cron do servidor, a mensagem espera na tabela `jobs`.'],
            ],
        ],

        /* ---------------------------------------------------------------- Sistema */

        [
            'id' => 'configuracoes',
            'grupo' => 'Sistema',
            'titulo' => 'Configurações',
            'icone' => 'fa-sliders',
            'rota' => 'admin.settings.index',
            'resumo' => 'O que é decisão de negócio fica aqui, não em arquivo: contato, rastreamento, WhatsApp e loja.',
            'blocos' => [
                ['tipo' => 'lista', 'itens' => [
                    'Gerais — nome do site, WhatsApp, telefone, e-mail, endereço, cidade e CEP.',
                    'Rastreamento — ligar ou desligar a medição própria e as etiquetas externas: Google Analytics (`ga_measurement_id`), Meta Pixel e GTM. Deixar tudo vazio é legítimo: a atribuição do sistema não depende de nenhum dos três.',
                    'WhatsApp — a caixa multicanal do botão flutuante: título, subtítulo e um número por canal, cada um ligável separadamente.',
                    'Loja — liga e desliga a vitrine, e guarda frete, corte do frete grátis, retirada, chave Pix e o e-mail que recebe o alerta de venda.',
                ]],
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
            'resumo' => 'O que existe fora das telas e faz o sistema funcionar: arquivo .env, fila de e-mail e o cron.',
            'blocos' => [
                ['tipo' => 'texto', 'conteudo' => 'O `.env` guarda o que é segredo deste servidor: banco, e-mail de disparo e as credenciais do Google Ads. Ele nunca vai para o repositório — o modelo é o `.env.example`. Toda mudança nele pede `php artisan config:clear`.'],
                ['tipo' => 'lista', 'itens' => [
                    'SMTP — o que faz o recado sair. Faltando credencial, o código continua pronto: falta a chave, não o recurso.',
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
        'admin.contratos.' => 'contratos',
        'admin.conteudo.paginas.' => 'paginas',
        'admin.conteudo.imagens.' => 'imagens',
        'admin.conteudo.textos.' => 'imagens',
        'admin.conteudo.depoimentos.' => 'depoimentos',
        'admin.conteudo.videos.' => 'videos',
        'admin.conteudo.faqs.' => 'faqs',
        'admin.settings.' => 'configuracoes',
        'admin.usuarios.' => 'usuarios',
        'admin.activities.' => 'auditoria',
    ],
];
