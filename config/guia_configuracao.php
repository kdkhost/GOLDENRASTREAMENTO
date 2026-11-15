<?php

/*
|--------------------------------------------------------------------------
| O passo a passo de cada item configurável
|--------------------------------------------------------------------------
|
| Toda tela de configuração tem o seu ícone de ajuda: um círculo miúdo ao lado do rótulo que
| abre a gaveta lateral com o que aquele item é, em que ordem configurá-lo e **onde** buscar a
| credencial ou o token. O texto mora aqui, uma fonte só, e a view não conhece nenhuma frase.
|
| A chave é o nome do campo (`mail_host`, `google_ads_developer_token`) ou um nome próprio para
| um cartão inteiro (`whmcs-veiculacao`, `whatsapp_box`) — campo e cartão consultam o mesmo
| registro. Item ausente desta lista simplesmente não mostra ícone nenhum; nada quebra.
|
| Cada entrada tem:
|   titulo  o cabeçalho da gaveta (em branco, vale o rótulo do campo);
|   onde    uma frase dizendo o que o item é — abre a gaveta;
|   passos  a sequência, sem número: `icone`, `titulo`, `texto`, na ordem em que se faz;
|   links   para onde a pessoa vai buscar aquilo: `rotulo` e `url` (externo) ou `rota` (uma tela
|           deste sistema, resolvida pelo nome quando a gaveta é desenhada);
|   nota    o cuidado que vale deixar marcado (opcional).
|
| Nas `url`, `{host}`, `{whmcs}` e `{whatsapp}` são resolvidos por `App\Support\GuiaConfiguracao`
| com o que o painel já sabe. Um endereço que ainda depende de algo que ninguém preencheu (o
| WHMCS, por exemplo) não é mostrado: link que não leva a lugar nenhum é pior do que link ausente.
|
| Nenhum passo leva número — a ordem já está na lista, e um "1." sobre uma lista ordenada é
| enfeite, não orientação.
*/

return [

    'itens' => [

        /* ---------------------------------------------------------------- Contato e endereço */

        'site_nome' => [
            'onde' => 'É a assinatura da casa: o nome que o site, o rodapé, o painel e todo e-mail disparado usam para se apresentar.',
            'passos' => [
                ['icone' => 'fa-id-card', 'titulo' => 'Escreva como a cliente te chama', 'texto' => 'Use o nome pelo qual a pessoa já te procura, com o mesmo tamanho que aparece na fachada. Abreviação interna não serve de nome de empresa.'],
                ['icone' => 'fa-eye', 'titulo' => 'Veja onde ele aparece', 'texto' => 'Rodapé do site, o "De:" do e-mail quando o campo de nome do remetente está vazio, o título de cada tela do portal e a linha de marca do painel.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Trocou, conferiu', 'texto' => 'Salve e recarregue o site numa aba ao lado. O valor vale na hora, sem limpar cache e sem tocar no servidor.'],
            ],
            'links' => [
                ['rotulo' => 'Ver o site com este nome', 'rota' => 'site.home'],
            ],
        ],

        'contato_whatsapp' => [
            'onde' => 'O número que atende: é dele que saem o botão flutuante do site, os links de conversa e o texto que a Ana manda quando alguém pede gente.',
            'passos' => [
                ['icone' => 'fa-hashtag', 'titulo' => 'Escreva no formato internacional', 'texto' => 'DDI, DDD e número, só dígitos: 5519997101483. Sem espaço, sem traço, sem o `+` — é assim que o link de conversa entende.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Confira o link antes de salvar', 'texto' => 'Abra a conversa de teste abaixo e mande uma mensagem para você mesmo. Se o WhatsApp abre, o botão do site abre também.'],
                ['icone' => 'fa-layer-group', 'titulo' => 'Canais diferentes têm número próprio', 'texto' => 'Cada atendente da caixa multicanal pode apontar para outra linha, em WhatsApp → Atendentes humanos. Deixando o campo vazio, aquele canal usa este número principal.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir conversa de teste neste número', 'url' => 'https://wa.me/{whatsapp}'],
            ],
        ],

        'contato_email' => [
            'onde' => 'A caixa que o público vê no rodapé e no formulário — e a que o painel sugere como destino de toda mensagem de prova.',
            'passos' => [
                ['icone' => 'fa-envelope', 'titulo' => 'Prefira caixa de função, não de pessoa', 'texto' => 'comercial@ ou contato@ na sua empresa. A caixa de um funcionário muda de dono e o site fica apontando para quem já não atende.'],
                ['icone' => 'fa-box-open', 'titulo' => 'Abra a caixa na hospedagem', 'texto' => 'Se o endereço ainda não existe, crie antes de publicar: e-mail que não existe devolve toda resposta para o remetente.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Disparar é outra história', 'texto' => 'Esta caixa não é a que envia. O servidor de saída, o usuário e a senha ficam em Chaves de integração → E-mail de disparo.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Configurar o disparo', 'rota' => 'admin.settings.index'],
            ],
        ],

        'fuso_horario' => [
            'onde' => 'O relógio da casa: a hora que aparece em cada fala da Ana, em toda data do painel, do portal, dos e-mails e dos relatórios.',
            'passos' => [
                ['icone' => 'fa-earth-americas', 'titulo' => 'Escolha o estado que atende', 'texto' => 'America/Sao_Paulo para o horário de Brasília. É escolha, não efeito do servidor.'],
                ['icone' => 'fa-server', 'titulo' => 'Nunca deixe a hospedagem decidir', 'texto' => 'O servidor acorda em UTC. Sem escolha gravada, o chat sai três horas adiantado para quem escreve do Brasil.'],
                ['icone' => 'fa-circle-check', 'titulo' => 'O que já estava gravado foi corrigido', 'texto' => 'Ao escolher o fuso, o sistema atrasou o histórico que havia sido escrito no relógio de UTC. Nada precisa ser refeito à mão.'],
            ],
            'links' => [
                ['rotulo' => 'Lista oficial de fusos horários (PHP)', 'url' => 'https://www.php.net/manual/pt_BR/timezones.america.php'],
                ['rotulo' => 'Conferir horários no painel', 'rota' => 'admin.settings.index'],
            ],
            'nota' => 'Em branco, vale o `APP_TIMEZONE` deste servidor — o que funciona enquanto ninguém decide, e é justamente o que deixa o relógio adiantado.',
        ],

        /* ---------------------------------------------------------------- Acessos do site */

        'acessos_externos' => [
            'titulo' => 'Acessos externos do site',
            'onde' => 'São as portas que o site abre para fora: os servidores de rastreamento e o financeiro antigo, cada um com o seu nome e o seu ícone no menu da conta.',
            'passos' => [
                ['icone' => 'fa-link', 'titulo' => 'Copie o endereço como o servidor dele', 'texto' => 'Inclua o `https://` e a porta quando existir. O painel não adivinha o protocolo, e um endereço sem ele não abre.'],
                ['icone' => 'fa-up-right-from-square', 'titulo' => 'Teste numa aba nova', 'texto' => 'Todo acesso abre fora do site. Se a página pedir login próprio, é o esperado: o endereço é da outra plataforma.'],
                ['icone' => 'fa-font', 'titulo' => 'Dê o nome que o cliente reconhece', 'texto' => 'O rótulo é o que a pessoa lê no modal; o endereço fica escondido no link. Nome de servidor interno confunde mais do que ajuda.'],
            ],
            'links' => [
                ['rotulo' => 'Ver o botão de acessos no site', 'rota' => 'site.home'],
            ],
        ],

        'acesso_servidor_url' => [
            'onde' => 'O endereço de uma das plataformas de rastreamento — a porta que o botão dourado do site abre para o cliente.',
            'passos' => [
                ['icone' => 'fa-keyboard', 'titulo' => 'Abra a plataforma e copie a barra de endereço', 'texto' => 'Entre com a sua conta e copie o endereço tal como o navegador mostra, inclusive a porta quando ela aparecer.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Prefira o endereço com cadeado', 'texto' => '`https://` em vez de IP com porta, sempre que a plataforma oferecer. Em celular, endereço solto sem cadeado costuma ser bloqueado.'],
                ['icone' => 'fa-eye', 'titulo' => 'Confira no site', 'texto' => 'Salve, abra o site e toque no botão de acesso. O modal deve listar o nome que você deu em seguida.'],
            ],
            'links' => [
                ['rotulo' => 'Testar abertura no site', 'rota' => 'site.home'],
            ],
        ],

        'acesso_financeiro_url' => [
            'onde' => 'A entrada do financeiro antigo, para o caso de o cliente ainda precisar mexer em algo que o portal do cliente não cobre.',
            'passos' => [
                ['icone' => 'fa-file-invoice-dollar', 'titulo' => 'Pegue o endereço de login', 'texto' => 'É a página de entrada do sistema financeiro, não o endereço de uma fatura específica.'],
                ['icone' => 'fa-code-compare', 'titulo' => 'Não confunda com o WHMCS do portal', 'texto' => 'O que o portal consulta fica em Chaves de integração → WHMCS. Este campo aqui é só o link que o site mostra no menu da conta.'],
                ['icone' => 'fa-power-off', 'titulo' => 'Sem endereço, sem link', 'texto' => 'Deixe em branco quando o portal já dá conta do financeiro: o item sai do menu e nada aparece quebrado.'],
            ],
            'links' => [
                ['rotulo' => 'Configurar a conexão do portal', 'rota' => 'admin.settings.index'],
            ],
        ],

        'acessos_textos' => [
            'onde' => 'As palavras do caminho até as plataformas: o botão dourado, o ícone de conta, o título do modal e a frase de ajuda que fecha a janela.',
            'passos' => [
                ['icone' => 'fa-font', 'titulo' => 'Escreva com as palavras do balcão', 'texto' => 'O rótulo do botão é o que a pessoa procura no site. Nome de sistema interno ("tracking_v2") não é nome de botão.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Campo vazio devolve o padrão', 'texto' => 'Cada frase tem o texto da casa atrás de si: apagando, aquela linha volta a ser a padrão sem afetar as outras.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Leia no celular antes de fechar', 'texto' => 'Rótulo longo quebra o menu em três linhas e empurra os botões. Meio centena de caracteres já é demais para uma linha.'],
            ],
            'links' => [
                ['rotulo' => 'Ver textos no cabeçalho do site', 'rota' => 'site.home'],
            ],
            'nota' => 'Estes campos são texto, não credencial: ninguém precisa ir a lugar nenhum buscar o que vai escrever aqui.',
        ],

        /* ---------------------------------------------------------------- Loja */

        'loja_ligar' => [
            'titulo' => 'Ligar e desligar a loja',
            'onde' => 'Um interruptor: com a loja ligada, as páginas `/loja` estão no ar e no menu; desligada, saem do site e somem do menu.',
            'passos' => [
                ['icone' => 'fa-store', 'titulo' => 'Monte o catálogo primeiro', 'texto' => 'Vendas → Produtos, com preço e foto. Abrir a vitrine vazia é o convite mais rápido para o visitante ir embora.'],
                ['icone' => 'fa-plug', 'titulo' => 'Ligue e abra a vitrine', 'texto' => 'Use o botão "Ver a loja no site" no alto desta tela. O preço, o frete e o Pix que aparecem são os desta aba.'],
                ['icone' => 'fa-power-off', 'titulo' => 'Desligar não apaga nada', 'texto' => 'Os pedidos já feitos continuam no painel e a entrega segue. É a vitrine que fecha, não o estoque.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a loja no site', 'rota' => 'site.loja.index'],
                ['rotulo' => 'Cadastrar produtos', 'rota' => 'admin.catalogo.produtos.index'],
            ],
        ],

        'regras_da_loja' => [
            'onde' => 'O combinado comercial da vitrine: quanto custa levar, quando o frete sai por conta da casa e quem é avisado a cada venda.',
            'passos' => [
                ['icone' => 'fa-truck-fast', 'titulo' => 'Escolha uma das duas mãos do frete', 'texto' => 'Frete combinado liga o "conversar antes de pagar" e desliga os dois campos de valor. Fixo cobra o valor digitado e libera o corte da frete grátis.'],
                ['icone' => 'fa-scale-balanced', 'titulo' => 'Combine a retirada na loja', 'texto' => 'Só ligue "retirada na loja" se alguém for atender no endereço publicado no rodapé.'],
                ['icone' => 'fa-money-bill-wave', 'titulo' => 'Publique a chave Pix do recebedor', 'texto' => 'É a chave que cai na conta da empresa, não a de um sócio: o comprovante que o cliente anexa é conferido contra ela.'],
                ['icone' => 'fa-envelope-circle-check', 'titulo' => 'Diga quem acorda com a venda', 'texto' => 'O e-mail da equipe recebe o alerta de cada pedido novo. Caixa de pessoa some de férias; caixa de função, não.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a loja no site', 'rota' => 'site.loja.index'],
                ['rotulo' => 'Ver carrinho da loja', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'loja_pix_chave' => [
            'onde' => 'A chave Pix exibida no checkout, para quem prefere pagar adiantado em vez de usar o link do banco.',
            'passos' => [
                ['icone' => 'fa-building-columns', 'titulo' => 'Abra o internet banking do recebedor', 'texto' => 'Entre na conta que recebe, não na que paga, e procure o menu Pix.'],
                ['icone' => 'fa-key', 'titulo' => 'Escolha ou gere a chave', 'texto' => 'CPF/CNPJ, e-mail, telefone ou chave aleatória. A aleatória é a única que não publica dado pessoal nem telefone da empresa.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Teste com valor mínimo', 'texto' => 'Mande R$ 0,01 para a chave antes de publicá-la. É o único teste que prova que a chave cai na conta certa.'],
                ['icone' => 'fa-paste', 'titulo' => 'Cole exatamente como o banco mostra', 'texto' => 'Nada de espaços. E-mail em minúsculas e CNPJ sem máscara são as duas trocas que mais geram Pix devolvido.'],
            ],
            'links' => [
                ['rotulo' => 'Testar checkout da loja', 'rota' => 'site.loja.checkout'],
            ],
            'nota' => 'Chave de PIX não é segredo: ela é publicada no site. O que não pode aparecer é a senha do banco — nada deste sistema pede isso.',
        ],

        'loja_email_equipe' => [
            'onde' => 'A caixa que recebe o alerta de cada venda nova, no minuto em que o pedido cai.',
            'passos' => [
                ['icone' => 'fa-box-open', 'titulo' => 'Crie a caixa na hospedagem', 'texto' => 'Uma caixa de função (vendas@, pedidos@) compartilhada por quem atende, com o login do SMTP próprio se ela for disparar.'],
                ['icone' => 'fa-inbox', 'titulo' => 'Olhe a caixa de lixo também', 'texto' => 'Na primeira semana, confira se o alerta caiu no spam; ajustar o remetente aqui não resolve problema de reputação do servidor.'],
                ['icone' => 'fa-users', 'titulo' => 'Mudou o turno, mudou a caixa', 'texto' => 'Este campo é o único lugar onde o destino do alerta é decidido; nenhum e-mail de venda é escrito em código.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
            ],
        ],

        'loja_livre' => [
            'titulo' => 'Frete combinado / a combinar',
            'onde' => 'Quando ligado, a loja não cobra valor fixo de frete e indica que o custo será combinado diretamente com a equipe de atendimento.',
            'passos' => [
                ['icone' => 'fa-comments', 'titulo' => 'Atendimento personalizado', 'texto' => 'Ideal para pedidos de frotas ou grandes volumes onde a cotação varia conforme a transportadora.'],
                ['icone' => 'fa-lock', 'titulo' => 'Trava os campos fixos', 'texto' => 'Ao ligar o frete combinado, os campos de Frete fixo e Frete grátis ficam desabilitados automaticamente.'],
            ],
            'links' => [
                ['rotulo' => 'Simular no carrinho da loja', 'rota' => 'site.loja.carrinho'],
                ['rotulo' => 'Abrir atendimento no WhatsApp', 'url' => 'https://wa.me/{whatsapp}'],
            ],
        ],

        'loja_retirada' => [
            'titulo' => 'Permitir retirada na empresa',
            'onde' => 'Habilita a opção para o cliente retirar os produtos presencialmente na sede da Golden Rastreamento com custo zero de frete.',
            'passos' => [
                ['icone' => 'fa-store', 'titulo' => 'Retirada presencial imediata', 'texto' => 'O cliente visualiza o endereço da matriz no checkout e pode buscar o rastreador já configurado e testado.'],
                ['icone' => 'fa-handshake', 'titulo' => 'Zero custo de frete', 'texto' => 'A opção de retirada sempre aparece como R$ 0,00 (Grátis) na simulação e no carrinho.'],
            ],
            'links' => [
                ['rotulo' => 'Ver endereço configurado no site', 'rota' => 'site.contato'],
                ['rotulo' => 'Testar retirada no checkout', 'rota' => 'site.loja.checkout'],
            ],
        ],

        'loja_frete' => [
            'titulo' => 'Frete fixo padrão (R$)',
            'onde' => 'Valor fixo cobrado por envio quando o frete dinâmico dos Correios estiver desativado ou como taxa base da loja.',
            'passos' => [
                ['icone' => 'fa-tag', 'titulo' => 'Defina o valor base', 'texto' => 'Digite o valor em reais (ex: 29,90) cobrado para pedidos que não utilizam cálculo dinâmico.'],
                ['icone' => 'fa-cart-shopping', 'titulo' => 'Aplicação no carrinho', 'texto' => 'Este valor é adicionado ao subtotal do carrinho até que o cliente atinja a faixa de frete grátis.'],
            ],
            'links' => [
                ['rotulo' => 'Simular frete no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'loja_frete_gratis_acima' => [
            'titulo' => 'Frete grátis acima de (R$)',
            'onde' => 'Valor mínimo de compras no pedido para isenção total do frete.',
            'passos' => [
                ['icone' => 'fa-gift', 'titulo' => 'Incentivo de compra', 'texto' => 'Defina um valor (ex: 997,00). Compras que atinjam ou superem este total recebem frete grátis automaticamente.'],
                ['icone' => 'fa-calculator', 'titulo' => 'Barra de progresso no carrinho', 'texto' => 'O cliente vê em tempo real quanto falta para atingir a entrega gratuita (somente após informar o CEP).'],
            ],
            'links' => [
                ['rotulo' => 'Ver barra de frete grátis no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_logistica' => [
            'titulo' => 'Logística dos Correios (Com e Sem Contrato & Coleta)',
            'onde' => 'Central de logística para cálculo automático de SEDEX/PAC, despacho com ou sem contrato corporativo, e coleta na sede da Golden.',
            'passos' => [
                ['icone' => 'fa-truck-fast', 'titulo' => 'Ligue o cálculo dinâmico', 'texto' => 'Ative o interruptor para que o site consulte as tarifas oficiais dos Correios para o CEP do comprador.'],
                ['icone' => 'fa-file-contract', 'titulo' => 'Informe se possui contrato', 'texto' => 'Com contrato ativo, a loja aplica descontos corporativos nas tarifas e você pode cadastrar as credenciais CWS.'],
                ['icone' => 'fa-truck-ramp-box', 'titulo' => 'Solicitação de coleta', 'texto' => 'Permite que a expedição solicite coleta direta na sede da empresa com 1 clique no pedido.'],
                ['icone' => 'fa-envelope-circle-check', 'titulo' => 'Notificações automáticas', 'texto' => 'O cliente recebe o código e acompanha o rastreio em tempo real dentro do site da Golden.'],
            ],
            'links' => [
                ['rotulo' => 'Portal CWS Correios (APIs)', 'url' => 'https://cws.correios.com.br/'],
                ['rotulo' => 'Correios Empresas (Contrato)', 'url' => 'https://empresas.correios.com.br/'],
                ['rotulo' => 'Testar cálculo no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_ativo' => [
            'titulo' => 'Ativar cálculo dinâmico Correios',
            'onde' => 'Calcula frete e prazos em tempo real no carrinho, no checkout e na página de produto.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligue para cálculo ao vivo', 'texto' => 'O cliente informa o CEP e o sistema calcula SEDEX e PAC instantaneamente.'],
                ['icone' => 'fa-location-dot', 'titulo' => 'Origem configurada', 'texto' => 'O cálculo usa o CEP de Origem da expedição da Golden Rastreamento.'],
            ],
            'links' => [
                ['rotulo' => 'Testar cálculo no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_possui_contrato' => [
            'titulo' => 'Empresa possui contrato com os Correios',
            'onde' => 'Aplica tarifas corporativas com desconto e habilita a integração com os serviços CWS / SIGEP Web.',
            'passos' => [
                ['icone' => 'fa-percent', 'titulo' => 'Tarifas reduzidas', 'texto' => 'Empresas com contrato Correios Fácil ou CWS contam com descontos de até 25% nas postagens.'],
                ['icone' => 'fa-key', 'titulo' => 'Credenciais CWS', 'texto' => 'Preencha o Código Administrativo, Cartão de Postagem e Token CWS para faturamento corporativo.'],
            ],
            'links' => [
                ['rotulo' => 'Portal Correios Empresas', 'url' => 'https://empresas.correios.com.br/'],
                ['rotulo' => 'Portal CWS (Tokens)', 'url' => 'https://cws.correios.com.br/'],
            ],
        ],

        'correios_coleta_empresa' => [
            'titulo' => 'Coleta na empresa (Golden Rastreamento)',
            'onde' => 'Define que a transportadora/Correios retiram as encomendas diretamente na sede da Golden.',
            'passos' => [
                ['icone' => 'fa-truck-ramp-box', 'titulo' => 'Botão no painel', 'texto' => 'Habilita o botão "Solicitar Coleta na Empresa" na tela de detalhes de cada pedido aprovado.'],
                ['icone' => 'fa-bell', 'titulo' => 'Avisos multicanal', 'texto' => 'Dispara e-mail e notificação no celular para o administrador e expedição organizarem os pacotes.'],
            ],
            'links' => [
                ['rotulo' => 'Portal Correios Empresas', 'url' => 'https://empresas.correios.com.br/'],
            ],
        ],

        'correios_cep_origem' => [
            'titulo' => 'CEP de Origem / Expedição',
            'onde' => 'O CEP da sede ou centro de distribuição da Golden Rastreamento de onde partem as encomendas.',
            'passos' => [
                ['icone' => 'fa-map-pin', 'titulo' => 'Informe o CEP completo', 'texto' => 'Digite o CEP com 8 dígitos (ex: 37190-000) da matriz em Três Pontas/MG.'],
                ['icone' => 'fa-route', 'titulo' => 'Ponto de partida do frete', 'texto' => 'A distância e os prazos são calculados a partir deste endereço.'],
            ],
            'links' => [
                ['rotulo' => 'Consultar CEP oficial nos Correios', 'url' => 'https://buscacepinter.correios.com.br/'],
            ],
        ],

        'correios_prazo_adicional' => [
            'titulo' => 'Prazo adicional (dias úteis)',
            'onde' => 'Dias adicionais somados à estimativa dos Correios para cobrir tempo de montagem, testes de bancada e expedição.',
            'passos' => [
                ['icone' => 'fa-screwdriver-wrench', 'titulo' => 'Preparação do rastreador', 'texto' => 'Rastreadores veiculares exigem inserção do chip M2M, testes de sinal e homologação antes do envio.'],
                ['icone' => 'fa-calendar-check', 'titulo' => 'Transparência ao cliente', 'texto' => 'Adicionar 1 ou 2 dias evita cobranças de prazos irreais por parte do comprador.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir prazos no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_taxa_manuseio' => [
            'titulo' => 'Taxa de manuseio / embalagem (R$)',
            'onde' => 'Valor fixo adicionado ao cálculo do frete para amortizar caixas, lacres invioláveis e embalagens.',
            'passos' => [
                ['icone' => 'fa-box', 'titulo' => 'Custo de embalagem', 'texto' => 'Informe centavos ou reais caso queira somar o custo de embalagem ao frete pago pelo cliente.'],
                ['icone' => 'fa-ban', 'titulo' => 'Deixe 0.00 para não cobrar', 'texto' => 'Com 0.00, o cliente paga somente a tarifa líquida da transportadora.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir valor final no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_sedex_ativo' => [
            'titulo' => 'Habilitar modalidade SEDEX',
            'onde' => 'Exibe a opção de envio expresso prioritário dos Correios no carrinho e checkout.',
            'passos' => [
                ['icone' => 'fa-bolt', 'titulo' => 'Entrega expressa', 'texto' => 'Garante entregas em 1 a 3 dias úteis para a maioria das cidades brasileiras.'],
                ['icone' => 'fa-check', 'titulo' => 'Prioridade de expedição', 'texto' => 'Pedidos com SEDEX recebem prioridade na bancada de testes e envio.'],
            ],
            'links' => [
                ['rotulo' => 'Ver opções de frete na loja', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_pac_ativo' => [
            'titulo' => 'Habilitar modalidade PAC',
            'onde' => 'Exibe a opção de envio econômico dos Correios para todo o território nacional.',
            'passos' => [
                ['icone' => 'fa-coins', 'titulo' => 'Custo acessível', 'texto' => 'Tarifa menor voltada para clientes que priorizam economia em relação ao prazo.'],
                ['icone' => 'fa-location-crosshairs', 'titulo' => 'Rastreamento completo', 'texto' => 'Mesmo econômico, o PAC oferece acompanhamento de cada movimentação passo a passo.'],
            ],
            'links' => [
                ['rotulo' => 'Ver opções de frete na loja', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'correios_codigo_administrativo' => [
            'titulo' => 'Código Administrativo Correios',
            'onde' => 'Código de 8 dígitos atribuído ao seu contrato corporativo dos Correios.',
            'passos' => [
                ['icone' => 'fa-file-invoice', 'titulo' => 'Consulte no contrato', 'texto' => 'Encontrado no documento de ativação do contrato comercial Correios Empresas.'],
                ['icone' => 'fa-id-card', 'titulo' => 'Identificação do cliente', 'texto' => 'Identifica sua conta jurídica junto aos servidores dos Correios.'],
            ],
            'links' => [
                ['rotulo' => 'Portal CWS Correios (Contratos)', 'url' => 'https://cws.correios.com.br/'],
                ['rotulo' => 'Correios Empresas', 'url' => 'https://empresas.correios.com.br/'],
            ],
        ],

        'correios_cartao_postagem' => [
            'titulo' => 'Cartão de Postagem Correios',
            'onde' => 'Número de 10 dígitos do cartão de postagem que valida o faturamento a faturar.',
            'passos' => [
                ['icone' => 'fa-credit-card', 'titulo' => 'Localize no portal CWS', 'texto' => 'Disponível no menu Meus Contratos > Cartões de Postagem no portal dos Correios.'],
                ['icone' => 'fa-barcode', 'titulo' => 'Validação das etiquetas', 'texto' => 'Necessário para emissão e chancela de etiquetas de postagem.'],
            ],
            'links' => [
                ['rotulo' => 'Portal CWS Correios (Cartões)', 'url' => 'https://cws.correios.com.br/'],
            ],
        ],

        'correios_usuario' => [
            'titulo' => 'Usuário CWS / SIGEP Web',
            'onde' => 'Nome de usuário para autenticação na API dos Correios.',
            'passos' => [
                ['icone' => 'fa-user-lock', 'titulo' => 'Acesse o portal CWS', 'texto' => 'cws.correios.com.br — utilize o ID Correios da empresa para gerar o acesso.'],
                ['icone' => 'fa-keyboard', 'titulo' => 'Digite o login de API', 'texto' => 'Cole exatamente o usuário autorizado para emissão de chaves.'],
            ],
            'links' => [
                ['rotulo' => 'Acessar Portal CWS Correios', 'url' => 'https://cws.correios.com.br/'],
                ['rotulo' => 'Documentação de APIs Correios', 'url' => 'https://www.correios.com.br/atendimento/developers'],
            ],
        ],

        'correios_senha' => [
            'titulo' => 'Senha / Token de Acesso CWS',
            'onde' => 'Chave de segurança ou token de API para autenticação eletrônica.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => 'Gere o token no portal', 'texto' => 'Em Gestão de Acesso a APIs no portal CWS, crie uma chave de API para o sistema.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Armazenamento seguro', 'texto' => 'O token é protegido no banco de dados e nunca exposto a terceiros.'],
            ],
            'links' => [
                ['rotulo' => 'Gerar Token no Portal CWS', 'url' => 'https://cws.correios.com.br/'],
            ],
        ],

        'correios_instrucoes_coleta' => [
            'titulo' => 'Instruções de Coleta na Empresa',
            'onde' => 'Orientações práticas de logística para os funcionários e para o carteiro/motorista na sede da Golden.',
            'passos' => [
                ['icone' => 'fa-clock', 'titulo' => 'Horário e local', 'texto' => 'Ex: "Coletas diárias das 14h às 16h na portaria da matriz (Rua Exemplo, 123)".'],
                ['icone' => 'fa-phone', 'titulo' => 'Telefone da expedição', 'texto' => 'Inclua o ramal ou WhatsApp do encarregado de conferir e entregar os pacotes.'],
            ],
            'links' => [
                ['rotulo' => 'Ver endereço de contato no site', 'rota' => 'site.contato'],
            ],
        ],

        'mercado_livre_card' => [
            'titulo' => 'Integração Mercado Livre / Mercado Envios',
            'onde' => 'Canal alternativo de logística para quem deseja comercializar ou expedir através da rede do Mercado Livre.',
            'passos' => [
                ['icone' => 'fa-bag-shopping', 'titulo' => 'Mercado Envios', 'texto' => 'Permite disponibilizar pontos de coleta e entrega intermediada pelo Mercado Livre.'],
                ['icone' => 'fa-link', 'titulo' => 'Link da loja oficial', 'texto' => 'Conduz clientes interessados diretamente aos anúncios homologados da Golden.'],
            ],
            'links' => [
                ['rotulo' => 'Central de Envios (Mercado Livre)', 'url' => 'https://www.mercadolivre.com.br/vendas/envios'],
                ['rotulo' => 'Meus anúncios no Mercado Livre', 'url' => 'https://www.mercadolivre.com.br/anuncios/lista'],
            ],
        ],

        'mercado_livre_envio_ativo' => [
            'titulo' => 'Habilitar envio pelo Mercado Livre',
            'onde' => 'Mostra aos compradores a modalidade Mercado Envios com garantia e entrega rápida.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ative o canal', 'texto' => 'Ligue o interruptor para disponibilizar a opção aos compradores na loja.'],
                ['icone' => 'fa-store', 'titulo' => 'Preencha o link oficial', 'texto' => 'Informe logo abaixo o endereço da sua loja no Mercado Livre para direcionar o cliente corretamente.'],
            ],
            'links' => [
                ['rotulo' => 'Central de Envios (Mercado Livre)', 'url' => 'https://www.mercadolivre.com.br/vendas/envios'],
                ['rotulo' => 'Ver opções na loja', 'rota' => 'site.loja.index'],
            ],
        ],

        'mercado_livre_link_loja' => [
            'titulo' => 'Link da Loja no Mercado Livre',
            'onde' => 'Endereço da página oficial de produtos da Golden no Mercado Livre.',
            'passos' => [
                ['icone' => 'fa-user-check', 'titulo' => 'Acesse sua conta de vendedor', 'texto' => 'Entre no Mercado Livre e abra a página pública da sua loja ou lista de anúncios.'],
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Copie o link público completo', 'texto' => 'Cole a URL com `https://` (ex: https://www.mercadolivre.com.br/loja/golden-rastreamento).'],
            ],
            'links' => [
                ['rotulo' => 'Abrir meus anúncios no Mercado Livre', 'url' => 'https://www.mercadolivre.com.br/anuncios/lista'],
            ],
        ],

        'mercado_livre_instrucoes' => [
            'titulo' => 'Instruções do Mercado Envios',
            'onde' => 'Texto explicativo sobre como o comprador recebe o código de rastreio e prazo pelo Mercado Livre.',
            'passos' => [
                ['icone' => 'fa-circle-info', 'titulo' => 'Orientações ao cliente', 'texto' => 'Explique em 1 ou 2 frases como funciona o envio (ex: "Envio gerenciado com código e garantia do Mercado Livre via pontos de coleta").'],
                ['icone' => 'fa-eye', 'titulo' => 'Confira na loja', 'texto' => 'Após salvar, abra o carrinho/checkout para validar a leitura do texto pelo cliente.'],
            ],
            'links' => [
                ['rotulo' => 'Central de Envios (Mercado Livre)', 'url' => 'https://www.mercadolivre.com.br/vendas/envios'],
                ['rotulo' => 'Conferir no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'contato_oficial' => [
            'titulo' => 'Contato oficial da empresa',
            'onde' => 'Define WhatsApp, telefone, e-mail e horário de atendimento exibidos no site e nos alertas.',
            'passos' => [
                ['icone' => 'fa-phone', 'titulo' => '1. Preencha o WhatsApp no padrão internacional', 'texto' => 'Use DDI + DDD + número sem espaços (ex.: 5537999999999) para os links wa.me funcionarem em qualquer aparelho.'],
                ['icone' => 'fa-envelope', 'titulo' => '2. Informe o e-mail institucional', 'texto' => 'Aparece no rodapé do site, formulários de contato e rodapé dos e-mails transacionais.'],
                ['icone' => 'fa-clock', 'titulo' => '3. Defina o horário de atendimento', 'texto' => 'Salve e confira no rodapé e na página de Contato do site.'],
            ],
            'links' => [
                ['rotulo' => 'Testar WhatsApp configurado', 'url' => 'https://wa.me/{whatsapp}'],
                ['rotulo' => 'Ver página de Contato no site', 'rota' => 'site.contato'],
            ],
        ],

        'endereco_empresa' => [
            'titulo' => 'Endereço da empresa',
            'onde' => 'Endereço exibido no rodapé, página de contato e opção de retirada presencial na loja.',
            'passos' => [
                ['icone' => 'fa-magnifying-glass', 'titulo' => '1. Confira o CEP oficial', 'texto' => 'Valide o CEP e o logradouro oficial no Busca CEP dos Correios para evitar divergências de entrega/coleta.'],
                ['icone' => 'fa-location-dot', 'titulo' => '2. Preencha completo e salve', 'texto' => 'Informe rua, número, bairro, cidade/UF e CEP para orientar clientes e coletas.'],
            ],
            'links' => [
                ['rotulo' => 'Consultar CEP nos Correios', 'url' => 'https://buscacepinter.correios.com.br/'],
                ['rotulo' => 'Ver endereço no site', 'rota' => 'site.contato'],
            ],
        ],

        'loja_regras' => [
            'titulo' => 'Regras Comerciais & Pagamento',
            'onde' => 'Controla frete fixo padrão, meta de frete grátis, retirada na sede, chave Pix e alertas de venda.',
            'passos' => [
                ['icone' => 'fa-truck', 'titulo' => '1. Defina frete fixo, combinado ou frete grátis', 'texto' => 'No carrinho, o sistema exibe "Calcule pelo CEP" até o cliente informar um CEP válido.'],
                ['icone' => 'fa-qrcode', 'titulo' => '2. Cadastre a chave Pix da empresa', 'texto' => 'Exibida no checkout para pagamentos instantâneos; deixe vazio se usar apenas gateway.'],
                ['icone' => 'fa-envelope', 'titulo' => '3. Informe o e-mail da equipe', 'texto' => 'Toda nova venda dispara alerta imediato para essa caixa.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a loja no site', 'rota' => 'site.loja.index'],
                ['rotulo' => 'Testar checkout', 'rota' => 'site.loja.checkout'],
            ],
        ],

        'logistica_transportadoras' => [
            'titulo' => 'Logística & Transportadoras',
            'onde' => 'Centraliza as integrações de frete em tempo real (Correios, Melhor Envio e Mercado Envios).',
            'passos' => [
                ['icone' => 'fa-layer-group', 'titulo' => '1. Escolha a transportadora na sub-aba', 'texto' => 'Alterne entre Correios, Melhor Envio e Mercado Envios e preencha os parâmetros desejados.'],
                ['icone' => 'fa-shield-halved', 'titulo' => '2. Fallback automático', 'texto' => 'Se o Melhor Envio estiver ativo e falhar temporariamente, o sistema recorre automaticamente aos Correios.'],
                ['icone' => 'fa-cart-shopping', 'titulo' => '3. Salve e simule no carrinho', 'texto' => 'Após salvar ao final da página, teste um CEP real no carrinho da loja.'],
            ],
            'links' => [
                ['rotulo' => 'Painel Melhor Envio', 'url' => 'https://melhorenvio.com.br/painel'],
                ['rotulo' => 'Portal CWS Correios', 'url' => 'https://cws.correios.com.br/'],
                ['rotulo' => 'Simular frete no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'rastreio_wonca_token' => [
            'titulo' => 'Chave API de Rastreamento Universal (Wonca / Seu Rastreio)',
            'onde' => 'Permite consultar a movimentação real de códigos de rastreio dos Correios e outras transportadoras na página /rastreamento mesmo sem contrato CWS.',
            'passos' => [
                ['icone' => 'fa-user-plus', 'titulo' => '1. Crie uma conta gratuita na Wonca / Seu Rastreio', 'texto' => 'Acesse o portal Wonca Labs ou Seu Rastreio e gere sua chave de API (Apikey) no plano gratuito ou contratado.'],
                ['icone' => 'fa-key', 'titulo' => '2. Cole a Apikey e salve', 'texto' => 'Cole a chave no campo e clique em salvar. Por segurança, o campo fica oculto após salvo.'],
                ['icone' => 'fa-location-dot', 'titulo' => '3. Teste na página de rastreamento', 'texto' => 'Consulte um código real em /rastreamento para acompanhar todo o trajeto em tempo real.'],
            ],
            'links' => [
                ['rotulo' => 'Portal Wonca (API Rastreio)', 'url' => 'https://wonca.com.br/'],
                ['rotulo' => 'Seu Rastreio', 'url' => 'https://seurastreio.com.br/'],
                ['rotulo' => 'Abrir página de Rastreamento', 'rota' => 'site.rastreamento'],
            ],
        ],

        'mercado_livre_access_token' => [
            'titulo' => 'Access Token API Oficial Mercado Livre',
            'onde' => 'Token OAuth da API oficial do Mercado Livre para consultar o status e histórico de envios (Shipments) do Mercado Envios em tempo real.',
            'passos' => [
                ['icone' => 'fa-code', 'titulo' => '1. Acesse o DevCenter do Mercado Livre', 'texto' => 'Crie ou abra sua aplicação no Mercado Livre Developers e gere um Access Token OAuth com permissão de leitura de envios.'],
                ['icone' => 'fa-key', 'titulo' => '2. Cole o Access Token e salve', 'texto' => 'Cole o token no campo e salve. O campo fica em branco depois de salvo por segurança.'],
                ['icone' => 'fa-truck', 'titulo' => '3. Consulte pelo ID da remessa (Shipment ID)', 'texto' => 'Com o Mercado Envios ativo, informe o ID numérico da remessa ou código do pedido em /rastreamento.'],
            ],
            'links' => [
                ['rotulo' => 'Mercado Livre DevCenter', 'url' => 'https://developers.mercadolivre.com.br/devcenter'],
                ['rotulo' => 'Documentação API Shipments', 'url' => 'https://developers.mercadolivre.com.br/pt_br/gerenciamento-de-envios'],
            ],
        ],

        'melhor_envio_ativo' => [
            'titulo' => 'Ativar cálculo via Melhor Envio',
            'onde' => 'Habilita cotações em tempo real pela API v2 do Melhor Envio no carrinho e checkout.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => '1. Gere e cole o Token da API v2', 'texto' => 'Antes de ligar, gere o Token de acesso no painel do Melhor Envio e cole no campo Token abaixo.'],
                ['icone' => 'fa-toggle-on', 'titulo' => '2. Ative e salve', 'texto' => 'O sistema passa a consultar Correios, Jadlog, Azul Cargo e outras transportadoras habilitadas na sua conta.'],
            ],
            'links' => [
                ['rotulo' => 'Gerar Token no Melhor Envio', 'url' => 'https://melhorenvio.com.br/painel/gerenciar/tokens'],
                ['rotulo' => 'Testar cálculo no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'melhor_envio_sandbox' => [
            'titulo' => 'Modo Sandbox (Homologação)',
            'onde' => 'Direciona as requisições para sandbox.melhorenvio.com.br em vez do ambiente de produção.',
            'passos' => [
                ['icone' => 'fa-flask', 'titulo' => '1. Use somente em testes', 'texto' => 'Ligue quando estiver usando um token criado em sandbox.melhorenvio.com.br.'],
                ['icone' => 'fa-rocket', 'titulo' => '2. Desligue para produção', 'texto' => 'Tokens reais da conta oficial exigem esta chave desligada.'],
            ],
            'links' => [
                ['rotulo' => 'Tokens Sandbox (Melhor Envio)', 'url' => 'https://sandbox.melhorenvio.com.br/painel/gerenciar/tokens'],
                ['rotulo' => 'Tokens Produção (Melhor Envio)', 'url' => 'https://melhorenvio.com.br/painel/gerenciar/tokens'],
            ],
        ],

        'melhor_envio_token' => [
            'titulo' => 'Token de Acesso (API v2 Melhor Envio)',
            'onde' => 'Bearer Token pessoal/aplicativo gerado no painel do Melhor Envio.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => '1. Acesse Integrações → Tokens de acesso', 'texto' => 'Entre na sua conta Melhor Envio, clique em Integrações → Permissões/Tokens e clique em "Gerar novo token".'],
                ['icone' => 'fa-check-double', 'titulo' => '2. Marque as permissões de cotação', 'texto' => 'Habilite o escopo de cálculo de fretes (shipping-calculate) e copie o token gerado.'],
                ['icone' => 'fa-lock', 'titulo' => '3. Cole e salve uma vez', 'texto' => 'Depois de salvo, o campo fica em branco por segurança — só preencha novamente se quiser trocar o token.'],
            ],
            'links' => [
                ['rotulo' => 'Gerar Token (Produção)', 'url' => 'https://melhorenvio.com.br/painel/gerenciar/tokens'],
                ['rotulo' => 'Gerar Token (Sandbox)', 'url' => 'https://sandbox.melhorenvio.com.br/painel/gerenciar/tokens'],
                ['rotulo' => 'Documentação API v2 Melhor Envio', 'url' => 'https://docs.melhorenvio.com.br/reference/calculo-de-fretes'],
            ],
        ],

        'melhor_envio_cep_origem' => [
            'titulo' => 'CEP de Origem (Melhor Envio)',
            'onde' => 'CEP de onde os pacotes da Golden Rastreamento saem para cálculo de distância e prazo.',
            'passos' => [
                ['icone' => 'fa-map-pin', 'titulo' => '1. Informe o CEP de postagem', 'texto' => 'Digite os 8 dígitos do CEP da matriz ou centro de distribuição (ex.: 37190000).'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => '2. Fallback automático', 'texto' => 'Se ficar vazio, o sistema usa automaticamente o CEP de origem configurado nos Correios.'],
            ],
            'links' => [
                ['rotulo' => 'Buscar CEP nos Correios', 'url' => 'https://buscacepinter.correios.com.br/'],
                ['rotulo' => 'Endereços no Melhor Envio', 'url' => 'https://melhorenvio.com.br/painel/enderecos'],
            ],
        ],

        'melhor_envio_peso_kg' => [
            'titulo' => 'Peso padrão por item (kg)',
            'onde' => 'Peso médio utilizado na cotação da embalagem na API do Melhor Envio (ex.: 0.35 kg).',
            'passos' => [
                ['icone' => 'fa-weight-hanging', 'titulo' => '1. Pese o kit completo', 'texto' => 'Considere rastreador, chicote, caixa e proteção em quilogramas usando ponto decimal (mínimo 0.05 kg).'],
                ['icone' => 'fa-calculator', 'titulo' => '2. Multiplicado pela quantidade', 'texto' => 'No carrinho, o sistema soma o peso proporcional aos itens do pedido.'],
            ],
            'links' => [
                ['rotulo' => 'Regras de cubagem e peso (Melhor Envio)', 'url' => 'https://docs.melhorenvio.com.br/reference/calculo-de-fretes'],
            ],
        ],

        'melhor_envio_largura_cm' => [
            'titulo' => 'Largura padrão da caixa (cm)',
            'onde' => 'Largura mínima exigida pelas transportadoras (11 cm a 200 cm).',
            'passos' => [
                ['icone' => 'fa-ruler-horizontal', 'titulo' => '1. Meça a caixa fechada', 'texto' => 'Informe a largura em centímetros (mínimo 11 cm; recomendado 16 cm para caixas de rastreadores).'],
                ['icone' => 'fa-floppy-disk', 'titulo' => '2. Salve e valide no carrinho', 'texto' => 'Medidas fora dos limites das transportadoras podem ocultar modalidades na cotação.'],
            ],
            'links' => [
                ['rotulo' => 'Limites de dimensões (Melhor Envio)', 'url' => 'https://docs.melhorenvio.com.br/reference/calculo-de-fretes'],
            ],
        ],

        'melhor_envio_altura_cm' => [
            'titulo' => 'Altura padrão da caixa (cm)',
            'onde' => 'Altura do pacote para cubagem (2 cm a 200 cm).',
            'passos' => [
                ['icone' => 'fa-ruler-vertical', 'titulo' => '1. Informe a altura em cm', 'texto' => 'Recomendado 8 cm para embalagens compactas da loja (mínimo 2 cm).'],
                ['icone' => 'fa-box', 'titulo' => '2. Atenção à cubagem', 'texto' => 'Evite informar altura maior do que a caixa real para não encarecer o frete cotado.'],
            ],
            'links' => [
                ['rotulo' => 'Documentação de cálculo (Melhor Envio)', 'url' => 'https://docs.melhorenvio.com.br/reference/calculo-de-fretes'],
            ],
        ],

        'melhor_envio_comprimento_cm' => [
            'titulo' => 'Comprimento padrão da caixa (cm)',
            'onde' => 'Comprimento mínimo aceito pelos Correios/transportadoras (16 cm a 200 cm).',
            'passos' => [
                ['icone' => 'fa-ruler', 'titulo' => '1. Informe o comprimento em cm', 'texto' => 'Mínimo aceito é 16 cm; recomendado 22 cm para acomodar equipamento, chicote e manual.'],
                ['icone' => 'fa-check', 'titulo' => '2. Teste com um CEP', 'texto' => 'Salve e confira no carrinho se as opções da transportadora aparecem normalmente.'],
            ],
            'links' => [
                ['rotulo' => 'Testar cotação no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'melhor_envio_prazo_adicional' => [
            'titulo' => 'Prazo adicional (Melhor Envio)',
            'onde' => 'Dias úteis somados ao prazo retornado pela transportadora (preparo, testes e postagem).',
            'passos' => [
                ['icone' => 'fa-calendar-plus', 'titulo' => '1. Defina os dias de preparo', 'texto' => 'Informe quantos dias úteis a equipe precisa para configurar o rastreador e postar (ex.: 1 ou 2).'],
                ['icone' => 'fa-clock', 'titulo' => '2. Soma automática', 'texto' => 'O valor é somado ao prazo exibido ao cliente no carrinho e checkout.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir prazo exibido no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'melhor_envio_taxa_adicional' => [
            'titulo' => 'Taxa adicional / embalagem (Melhor Envio)',
            'onde' => 'Valor fixo em reais somado ao frete cotado para cobrir caixa, plástico-bolha e coleta.',
            'passos' => [
                ['icone' => 'fa-box', 'titulo' => '1. Informe o valor ou deixe 0.00', 'texto' => 'Deixe 0.00 para repassar exatamente a tarifa retornada pelo Melhor Envio.'],
                ['icone' => 'fa-coins', 'titulo' => '2. Aplicação transparente', 'texto' => 'O adicional é embutido automaticamente no valor final de cada modalidade retornada.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir tarifas no carrinho', 'rota' => 'site.loja.carrinho'],
            ],
        ],

        'melhor_envio_servicos' => [
            'titulo' => 'IDs de serviços (Melhor Envio)',
            'onde' => 'Lista opcional de IDs numéricos separados por vírgula para restringir modalidades.',
            'passos' => [
                ['icone' => 'fa-filter', 'titulo' => '1. Deixe vazio ou liste os IDs', 'texto' => 'Exemplos: 1 = PAC, 2 = SEDEX, 3 = Jadlog .Package, 4 = Jadlog .Com, 17 = Mini Envios.'],
                ['icone' => 'fa-list-check', 'titulo' => '2. Consulte os IDs na API', 'texto' => 'Deixe em branco para exibir todas as transportadoras ativas na sua conta Melhor Envio.'],
            ],
            'links' => [
                ['rotulo' => 'Lista de serviços e IDs (Melhor Envio)', 'url' => 'https://docs.melhorenvio.com.br/reference/listar-servicos'],
                ['rotulo' => 'Transportadoras no painel Melhor Envio', 'url' => 'https://melhorenvio.com.br/painel/gerenciar/transportadoras'],
            ],
        ],

        'medicao_consentimento' => [
            'titulo' => 'Medição, Pixels & Consentimento',
            'onde' => 'Configura Google Tag Manager, GA4, Google Ads, Meta Pixel e banner LGPD de cookies.',
            'passos' => [
                ['icone' => 'fa-chart-line', 'titulo' => '1. Copie apenas os IDs oficiais', 'texto' => 'Informe os códigos (GTM-..., G-..., AW-..., Pixel ID) sem colar scripts HTML inteiros.'],
                ['icone' => 'fa-shield-halved', 'titulo' => '2. Ative a medição e revise a Política', 'texto' => 'Ligue a chave principal de medição somente após publicar a Política de Privacidade no site.'],
            ],
            'links' => [
                ['rotulo' => 'Google Tag Manager', 'url' => 'https://tagmanager.google.com/'],
                ['rotulo' => 'Google Analytics 4', 'url' => 'https://analytics.google.com/'],
                ['rotulo' => 'Google Ads', 'url' => 'https://ads.google.com/'],
                ['rotulo' => 'Meta Events Manager', 'url' => 'https://business.facebook.com/events_manager2/list/pixel'],
            ],
        ],

        'whmcs_teste' => [
            'titulo' => 'Testar conexão com WHMCS',
            'onde' => 'Executa uma chamada real à API do WHMCS configurado para confirmar credenciais e IP liberado.',
            'passos' => [
                ['icone' => 'fa-floppy-disk', 'titulo' => '1. Salve URL, Identifier e Secret', 'texto' => 'Sempre que alterar qualquer credencial do WHMCS, clique em Salvar antes de testar.'],
                ['icone' => 'fa-satellite-dish', 'titulo' => '2. Informe um e-mail real e teste', 'texto' => 'Digite o e-mail de um cliente cadastrado no WHMCS e clique no botão de sondagem.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
                ['rotulo' => 'Lista de clientes no WHMCS', 'url' => '{whmcs}/admin/clients.php'],
            ],
        ],

        'email_teste' => [
            'titulo' => 'Testar envio de e-mail',
            'onde' => 'Envia um e-mail de diagnóstico imediato usando o remetente e SMTP salvos.',
            'passos' => [
                ['icone' => 'fa-at', 'titulo' => '1. Informe um destinatário seu', 'texto' => 'Digite seu e-mail de teste e clique em Enviar teste.'],
                ['icone' => 'fa-paper-plane', 'titulo' => '2. Leia o diagnóstico', 'texto' => 'Confirma se o servidor SMTP aceita autenticação e entrega mensagens sem cair em erro.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail (cPanel)', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Webmail do domínio', 'url' => 'https://{host}:2083/webmail'],
            ],
        ],

        'ambiente_sistema' => [
            'titulo' => 'Ambiente & Limpeza de Cache',
            'onde' => 'Mostra versão do PHP/Laravel, modo de execução e permite limpar caches direto pelo painel.',
            'passos' => [
                ['icone' => 'fa-broom', 'titulo' => '1. Limpe o cache após alterações', 'texto' => 'Use o botão "Limpar cache" no topo de Configurações ou no menu do usuário sempre que atualizar regras ou textos.'],
                ['icone' => 'fa-server', 'titulo' => '2. Confira o estado do servidor', 'texto' => 'Verifique aqui se o ambiente está em produção, o fuso horário ativo e a versão do PHP.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir o site público', 'rota' => 'site.home'],
                ['rotulo' => 'Abrir guia completo do painel', 'rota' => 'admin.guia.index'],
            ],
        ],

        'preloader_config' => [
            'titulo' => 'Preloader Personalizado Golden',
            'onde' => 'Controla a animação de carregamento inicial no site público e no painel administrativo.',
            'passos' => [
                ['icone' => 'fa-sliders', 'titulo' => '1. Ative ou desative por ambiente', 'texto' => 'Use os interruptores independentes para Site Público e Painel Admin.'],
                ['icone' => 'fa-palette', 'titulo' => '2. Personalize estilo, título e cor', 'texto' => 'Escolha entre Radar GPS, Órbita Dourada ou Pulso Minimalista, ajuste o texto e salve.'],
            ],
            'links' => [
                ['rotulo' => 'Visualizar no Site Público', 'rota' => 'site.home'],
                ['rotulo' => 'Visualizar na Loja', 'rota' => 'site.loja.index'],
            ],
        ],

        'preloader_site_ativo' => [
            'titulo' => 'Preloader no Site Público',
            'onde' => 'Exibe a animação Golden ao navegar entre páginas públicas; ações AJAX na loja/carrinho não disparam o preloader.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => '1. Ligue ou desligue conforme preferência', 'texto' => 'Ativado, exibe a transição suave ao abrir páginas do site.'],
                ['icone' => 'fa-globe', 'titulo' => '2. Confira no site', 'texto' => 'Salve e abra a página inicial ou a loja para testar a velocidade de abertura.'],
            ],
            'links' => [
                ['rotulo' => 'Testar na Home do site', 'rota' => 'site.home'],
            ],
        ],

        'preloader_admin_ativo' => [
            'titulo' => 'Preloader no Painel Admin',
            'onde' => 'Exibe a animação de transição ao abrir ou recarregar páginas administrativas.',
            'passos' => [
                ['icone' => 'fa-user-shield', 'titulo' => '1. Controle independente', 'texto' => 'Pode ficar desligado no painel mesmo enquanto permanece ativo para visitantes do site.'],
                ['icone' => 'fa-floppy-disk', 'titulo' => '2. Salve e recarregue', 'texto' => 'Após salvar, pressione F5 para validar imediatamente o comportamento.'],
            ],
            'links' => [
                ['rotulo' => 'Recarregar Configurações', 'rota' => 'admin.settings.index'],
            ],
        ],

        'preloader_estilo' => [
            'titulo' => 'Estilo da animação do Preloader',
            'onde' => 'Define o visual exibido durante o carregamento (Radar GPS, Órbita Dourada ou Pulso Minimalista).',
            'passos' => [
                ['icone' => 'fa-wand-magic-sparkles', 'titulo' => '1. Selecione o estilo desejado', 'texto' => 'Radar GPS traz leitura de rastreamento; Órbita Dourada e Pulso são ainda mais minimalistas.'],
                ['icone' => 'fa-eye', 'titulo' => '2. Teste ao vivo', 'texto' => 'Salve e navegue para outra página para ver a animação aplicada.'],
            ],
            'links' => [
                ['rotulo' => 'Ver resultado no site', 'rota' => 'site.home'],
            ],
        ],

        'preloader_titulo' => [
            'titulo' => 'Título exibido no Preloader',
            'onde' => 'Texto principal exibido abaixo da animação (padrão: Golden Rastreamento).',
            'passos' => [
                ['icone' => 'fa-font', 'titulo' => '1. Digite um texto curto', 'texto' => 'Use o nome da empresa ou uma frase curta de até 80 caracteres.'],
                ['icone' => 'fa-check', 'titulo' => '2. Salve e confira', 'texto' => 'O texto aparece centralizado logo abaixo do ícone animado.'],
            ],
            'links' => [
                ['rotulo' => 'Ver resultado no site', 'rota' => 'site.home'],
            ],
        ],

        'preloader_cor' => [
            'titulo' => 'Cor de destaque do Preloader',
            'onde' => 'Cor principal dos anéis, pulso e brilho da animação (padrão dourado #d4a017).',
            'passos' => [
                ['icone' => 'fa-palette', 'titulo' => '1. Escolha no seletor de cor', 'texto' => 'Clique na amostra de cor para ajustar ao tom desejado ou mantenha #d4a017.'],
                ['icone' => 'fa-floppy-disk', 'titulo' => '2. Salve para aplicar', 'texto' => 'A cor atualiza tanto os anéis animados quanto o destaque visual da tela de carregamento.'],
            ],
            'links' => [
                ['rotulo' => 'Ver resultado no site', 'rota' => 'site.home'],
            ],
        ],

        'celular_ativo' => [
            'titulo' => 'Alertas Instantâneos no Celular',
            'onde' => 'Ativa o despacho de avisos em tempo real com som e vibração para o smartphone do administrador.',
            'passos' => [
                ['icone' => 'fa-bell', 'titulo' => 'Receba tudo na hora', 'texto' => 'Novos leads, vendas aprovadas, novos depoimentos, pedidos de coleta e entregas passo a passo.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Escolha o canal', 'texto' => 'Configure abaixo seu Telegram Bot gratuito ou Webhook de WhatsApp para receber as mensagens.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir BotFather no Telegram', 'url' => 'https://t.me/BotFather'],
                ['rotulo' => 'Central de Notificações', 'rota' => 'admin.notificacoes.index'],
            ],
        ],

        'telegram_token' => [
            'titulo' => 'Token do Bot do Telegram',
            'onde' => 'Chave de autorização do seu bot no Telegram para envio de alertas push gratuitos.',
            'passos' => [
                ['icone' => 'fa-robot', 'titulo' => 'Abra o @BotFather no Telegram', 'texto' => 'Pesquise por @BotFather no app do Telegram e envie a mensagem /newbot.'],
                ['icone' => 'fa-pen', 'titulo' => 'Dê um nome e username', 'texto' => 'Exemplo: Nome "Golden Alertas" e username "golden_alertas_bot".'],
                ['icone' => 'fa-key', 'titulo' => 'Copie o Token gerado', 'texto' => 'O BotFather fornecerá um token no formato 123456789:ABCdefGhIJK... Cole neste campo.'],
                ['icone' => 'fa-play', 'titulo' => 'Dê /start no seu bot', 'texto' => 'Abra uma conversa com o bot criado e clique em Iniciar (Start).'],
            ],
            'links' => [
                ['rotulo' => 'Criar bot no @BotFather', 'url' => 'https://t.me/BotFather'],
                ['rotulo' => 'Documentação de Bots do Telegram', 'url' => 'https://core.telegram.org/bots#how-do-i-create-a-bot'],
            ],
            'nota' => 'O Telegram é 100% gratuito, não tem limite de mensagens e vibra no celular imediatamente em cada evento.',
        ],

        'telegram_chat_id' => [
            'titulo' => 'Chat ID do Telegram',
            'onde' => 'Identificador numérico do seu usuário pessoal ou de um grupo no Telegram.',
            'passos' => [
                ['icone' => 'fa-id-badge', 'titulo' => 'Consulte no @userinfobot', 'texto' => 'No Telegram, envie qualquer mensagem para o @userinfobot. Ele responderá seu ID (ex: 987654321).'],
                ['icone' => 'fa-users', 'titulo' => 'Para alertar grupo da equipe', 'texto' => 'Adicione o bot ao grupo da empresa e informe o ID do grupo (número negativo, ex: -100123456789).'],
                ['icone' => 'fa-vial', 'titulo' => 'Teste com 1 clique', 'texto' => 'Após salvar, use o botão "Testar Alerta no Celular" para validar.'],
            ],
            'links' => [
                ['rotulo' => 'Descobrir Chat ID no @userinfobot', 'url' => 'https://t.me/userinfobot'],
                ['rotulo' => 'Preferências de Notificações', 'rota' => 'admin.notificacoes.preferencias'],
            ],
        ],

        'webhook_celular' => [
            'titulo' => 'Webhook Móvel / WhatsApp API',
            'onde' => 'Endpoint HTTP para integrar com gateways de WhatsApp (Evolution API, Z-API), N8N, Make ou Discord.',
            'passos' => [
                ['icone' => 'fa-network-wired', 'titulo' => 'Crie o webhook na sua API', 'texto' => 'Configure a URL de destino no seu servidor de mensagens ou fluxo de automação.'],
                ['icone' => 'fa-code', 'titulo' => 'Payload JSON completo', 'texto' => 'O sistema envia evento, título, texto detalhado, dados do cliente e link do painel.'],
            ],
            'links' => [
                ['rotulo' => 'Preferências de Notificações', 'rota' => 'admin.notificacoes.preferencias'],
            ],
        ],

        'email_ativo' => [
            'titulo' => 'Alertas por E-mail da Empresa',
            'onde' => 'Ativa o envio de e-mails transacionais instantâneos para a equipe e diretoria a cada acontecimento.',
            'passos' => [
                ['icone' => 'fa-envelope-open-text', 'titulo' => '1. Configure o SMTP primeiro', 'texto' => 'Certifique-se de que o servidor SMTP na aba E-mail está configurado e testado.'],
                ['icone' => 'fa-toggle-on', 'titulo' => '2. Ative os disparos', 'texto' => 'Dispara e-mails ricos no layout preto e dourado da Golden com todos os detalhes da ocorrência.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail (cPanel)', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Preferências de Notificações', 'rota' => 'admin.notificacoes.preferencias'],
            ],
        ],

        'email_destinatarios' => [
            'titulo' => 'Destinatários de Alertas por E-mail',
            'onde' => 'Lista de e-mails que receberão as notificações de leads, pedidos, coletas, depoimentos e entregas.',
            'passos' => [
                ['icone' => 'fa-at', 'titulo' => 'Informe os e-mails separados por vírgula', 'texto' => 'Ex: diretoria@goldenrastreamento.com.br, expedicao@goldenrastreamento.com.br.'],
                ['icone' => 'fa-inbox', 'titulo' => 'Caixas de equipe', 'texto' => 'Prefira e-mails de departamento (vendas@, suporte@) para que toda a equipe acompanhe.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Teste de envio', 'texto' => 'Use o botão "Testar Alerta por E-mail" para conferir a formatação na sua caixa de entrada.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciar caixas na hospedagem', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Preferências de Notificações', 'rota' => 'admin.notificacoes.preferencias'],
            ],
        ],

        'retencao' => [
            'titulo' => 'Guardar notificações lidas por (dias)',
            'onde' => 'Prazo de expiração para limpeza automática das notificações já visualizadas no painel.',
            'passos' => [
                ['icone' => 'fa-broom', 'titulo' => 'Limpeza de banco', 'texto' => 'Notificações lidas após este período são podadas periodicamente para não acumular espaço no banco.'],
                ['icone' => 'fa-calendar', 'titulo' => 'Recomendação', 'texto' => 'Manter entre 30 e 90 dias garante histórico recente sem pesar no painel.'],
            ],
            'links' => [
                ['rotulo' => 'Ver notificações no painel', 'rota' => 'admin.notificacoes.index'],
            ],
        ],

        'expiracao' => [
            'titulo' => 'Apagar não lidas depois de (dias)',
            'onde' => 'Prazo máximo de vida para alertas antigos que nunca foram abertos pelo usuário.',
            'passos' => [
                ['icone' => 'fa-clock-rotate-left', 'titulo' => '1. Defina o prazo máximo', 'texto' => 'Informe em dias quanto tempo alertas não abertos permanecem no sino (ex.: 90 dias).'],
                ['icone' => 'fa-broom', 'titulo' => '2. Poda automática', 'texto' => 'Evita que notificações esquecidas fiquem acumulando no contador do sino.'],
            ],
            'links' => [
                ['rotulo' => 'Ver notificações no painel', 'rota' => 'admin.notificacoes.index'],
            ],
        ],

        /* ---------------------------------------------------------------- WhatsApp */

        'whatsapp_box' => [
            'titulo' => 'A caixa multicanal do WhatsApp',
            'onde' => 'O botão do canto do site: com a caixa ligada, ela abre com os atendentes listados e a mensagem pronta por assunto; desligada, o botão vai direto ao número principal.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligue quando houver mais de um assunto', 'texto' => 'Uma linha só atendendo tudo não precisa de caixa: o link direto é mais curto. A caixa vale quando vendas, suporte e financeiro têm gente diferente.'],
                ['icone' => 'fa-address-card', 'titulo' => 'Dê nome e descrição a cada canal', 'texto' => 'Quem abre a caixa escolhe pelo que a linha faz: "Suporte técnico", "Financeiro e faturas". Nome de setor interno não ajuda o visitante.'],
                ['icone' => 'fa-font', 'titulo' => 'Escreva a mensagem pronta', 'texto' => 'É o texto que já vai digitado na conversa. Comece pelo que o cliente diria, não pelo que a empresa quer dizer.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Teste no celular', 'texto' => 'O que abre a caixa no computador é o que chama no bolso. Confira a dica do botão flutuante e o rodapé antes de fechar.'],
            ],
            'links' => [
                ['rotulo' => 'Testar no site público', 'rota' => 'site.home'],
                ['rotulo' => 'Abrir conversa no WhatsApp principal', 'url' => 'https://wa.me/{whatsapp}'],
            ],
            'nota' => 'O assistente virtual não é um destes canais: a ficha dele, com a saudação e as falas da bolha, fica em Atendimento → Assistente virtual.',
        ],

        /* ---------------------------------------------------------------- Medição */

        'tracking_enabled' => [
            'onde' => 'A chave-mestra da medição externa: ligada, o site carrega as etiquetas preenchidas abaixo; desligada, nenhuma delas sai — e a medição própria do sistema continua.',
            'passos' => [
                ['icone' => 'fa-file-shield', 'titulo' => 'Publique a política antes de ligar', 'texto' => 'Conteúdo do site → Páginas, com a Política de Privacidade no ar, e o banner de consentimento aceito pelo visitante. Medição sem isso é problema de conformidade, não de marketing.'],
                ['icone' => 'fa-key', 'titulo' => 'Preencha ao menos um identificador', 'texto' => 'Ligar sem nenhum ID não mede nada: o carregamento das etiquetas só acontece com o identificador correspondente no lugar.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Confira na própria ferramenta', 'texto' => 'Depois de salvar, abra a etiqueta que ligou (GA4, Pixel ou GTM) na aba "tempo real" e navegue no site. Ela precisa mostrar a visita chegando.'],
                ['icone' => 'fa-power-off', 'titulo' => 'Desligar é legítimo', 'texto' => 'A atribuição do painel — de onde veio cada lead — é medida pelo próprio sistema e não depende de nenhuma destas três ferramentas.'],
            ],
            'links' => [
                ['rotulo' => 'Google Analytics 4', 'url' => 'https://analytics.google.com/'],
                ['rotulo' => 'Google Tag Manager', 'url' => 'https://tagmanager.google.com/'],
                ['rotulo' => 'Gerenciador de Eventos Meta', 'url' => 'https://business.facebook.com/events_manager2/list/pixel'],
            ],
        ],

        'google_ads_tag_id' => [
            'onde' => 'A tag do Google Ads (ID da tag / Pixel de conversão). Tem o formato `AW-` seguido de números (ex: `AW-123456789`). Rastreia visitantes em todo o site para alimentar públicos e otimizar campanhas.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra sua conta do Google Ads', 'texto' => 'Acesse ads.google.com com a conta que gerencia as campanhas da Golden.'],
                ['icone' => 'fa-bullseye', 'titulo' => 'Acesse Ferramentas → Metas / Conversões', 'texto' => 'No menu superior ou lateral, clique em Ferramentas e configurações → Conversões (ou Resumo de Metas) → Tag do Google.'],
                ['icone' => 'fa-tag', 'titulo' => 'Copie o ID da Tag', 'texto' => 'Localize o ID da tag no formato `AW-XXXXXXXXXX`. Cole neste campo sem espaços.'],
                ['icone' => 'fa-check', 'titulo' => 'Verifique o status', 'texto' => 'Após salvar com a medição ativada, a tag passa a carregar em todas as páginas do site.'],
            ],
            'links' => [
                ['rotulo' => 'Google Ads', 'url' => 'https://ads.google.com/'],
                ['rotulo' => 'Configurar a tag do Google', 'url' => 'https://support.google.com/google-ads/answer/7548399'],
            ],
        ],

        'google_ads_conversion_label' => [
            'onde' => 'O rótulo da ação de conversão de Leads gerada no Google Ads. É disparado automaticamente sempre que um visitante envia o formulário de proposta ou contato no site.',
            'passos' => [
                ['icone' => 'fa-bullseye', 'titulo' => 'Crie uma Ação de Conversão', 'texto' => 'Em Google Ads → Metas → Resumo → Nova ação de conversão → Site. Crie uma ação com categoria "Envio de formulário de lead".'],
                ['icone' => 'fa-code', 'titulo' => 'Obtenha o rótulo de conversão', 'texto' => 'Na etapa de instalação da tag, visualize o snippet de evento ou selecione a opção do Gerenciador de tags. Copie o Rótulo (ex: `AbC-D_ef123` ou `AW-XXXXXXXXXX/AbC-D_ef123`).'],
                ['icone' => 'fa-floppy-disk', 'titulo' => 'Cole e salve', 'texto' => 'Cole o rótulo neste campo. O sistema enviará o evento de conversão sempre que um lead preencher o formulário.'],
            ],
            'links' => [
                ['rotulo' => 'Ações de conversão no Google Ads', 'url' => 'https://support.google.com/google-ads/answer/6095821'],
            ],
        ],

        'ga_measurement_id' => [
            'onde' => 'O identificador que liga o site à propriedade do Google Analytics 4. Tem o formato `G-` seguido de letras e números.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra a propriedade do Analytics', 'texto' => 'Entre com a conta que administra a propriedade — cria-se uma de graça se ainda não existir.'],
                ['icone' => 'fa-chart-simple', 'titulo' => 'Crie o fluxo de dados do site', 'texto' => 'Administrar → Propriedade → Fluxos de dados → Web, com o endereço do site no nome e no URL. Sem o fluxo criado, não há o que copiar.'],
                ['icone' => 'fa-tag', 'titulo' => 'Copie a ID de medição', 'texto' => 'Aparece no alto da tela do fluxo, em "Tag do Google: ID de medição", com o formato `G-XXXXXXXXXX`. Cole sem espaços.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Prove que está entrando', 'texto' => 'Relatórios → Tempo real, navegue no site e veja a visita aparecer em segundos.'],
            ],
            'links' => [
                ['rotulo' => 'Google Analytics', 'url' => 'https://business.google.com/analytics'],
                ['rotulo' => 'Criar uma conta do Analytics', 'url' => 'https://marketingplatform.google.com/about/analytics/'],
            ],
        ],

        'meta_pixel_id' => [
            'onde' => 'O número do pixel do Meta (Facebook e Instagram), que conta ao gerenciador de anúncios o que aconteceu depois do clique.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra o Gerenciador de Eventos', 'texto' => 'Pela conta Business que administra as campanhas — o pixel criado no perfil pessoal não enxerga o gerenciador da empresa.'],
                ['icone' => 'fa-plug', 'titulo' => 'Conecte uma origem de dados Web', 'texto' => 'Gerenciar fontes de dados → Adicionar → Web, informe o endereço do site e escolha "Meta Pixel" na instalação assistida.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'Copie o ID do pixel', 'texto' => 'Aparece no nome da fonte, em `XXXXXXXXXXXXXXX`, só dígitos. É o que vai neste campo.'],
                ['icone' => 'fa-puzzle-piece', 'titulo' => 'Se a instalação é por GTM, não duplique', 'texto' => 'O pixel pode vir pelo Google Tag Manager. Quando vier por lá, deixe este campo vazio: duas cópias contam cada evento duas vezes.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciador de Eventos do Meta', 'url' => 'https://business.facebook.com/events_manager2/list/pixel'],
                ['rotulo' => 'Documentação do Meta Pixel', 'url' => 'https://developers.facebook.com/docs/meta-pixel/'],
            ],
        ],

        'gtm_id' => [
            'onde' => 'O contêiner do Google Tag Manager, quando as etiquetas do site são administradas por ele em vez de preenchidas uma a uma.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra o Tag Manager', 'texto' => 'Entre com a conta Google da empresa e crie um contêiner do tipo Web, com o endereço do site como nome.'],
                ['icone' => 'fa-tag', 'titulo' => 'Copie o ID do contêiner', 'texto' => 'Fica no alto da tela do contêiner, em `GTM-XXXXXX`.'],
                ['icone' => 'fa-sitemap', 'titulo' => 'Monte as etiquetas dentro do GTM', 'texto' => 'GA4 e Meta Pixel, se vierem por aqui, são configuradas no contêiner — e os campos desta tela ficam vazios para não contar duas vezes.'],
                ['icone' => 'fa-upload', 'titulo' => 'Publique', 'texto' => 'Sem apertar "Enviar" no contêiner, nada muda no site, por mais que as etiquetas estejam prontas lá dentro.'],
            ],
            'links' => [
                ['rotulo' => 'Google Tag Manager', 'url' => 'https://tagmanager.google.com/'],
                ['rotulo' => 'Documentação de contêineres Web', 'url' => 'https://developers.google.com/tag-platform/tag-manager/web'],
            ],
        ],

        /* ---------------------------------------------------------------- Financeiro (WHMCS) */

        'whmcs' => [
            'titulo' => 'Como ligar o portal ao financeiro',
            'onde' => 'Três credenciais abrem a porta: o endereço do WHMCS, o identificador da API e o segredo. Faltando uma, o portal explica ao cliente que a área está em preparação — nada quebra.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Entre na administração do WHMCS', 'texto' => 'O endereço costuma ser o do financeiro seguido de `/admin`. É na conta do administrador do financeiro, não numa conta de cliente.'],
                ['icone' => 'fa-gear', 'titulo' => 'Abra a tela de API', 'texto' => 'Configurações → Configurações do sistema → API. Em versões mais antigas, o endereço direto é `/admin/configapi.php`.'],
                ['icone' => 'fa-key', 'titulo' => 'Crie a credencial e copie os dois valores', 'texto' => 'Identificador e segredo aparecem uma única vez, na criação. O segredo não pode ser relido depois — se perdeu, crie de novo.'],
                ['icone' => 'fa-paste', 'titulo' => 'Cole no painel e salve', 'texto' => 'Chaves de integração → WHMCS: endereço, identificador e segredo. O segredo é cifrado no banco e nunca mais volta a aparecer aqui.'],
                ['icone' => 'fa-satellite-dish', 'titulo' => 'Sonde com um cliente de verdade', 'texto' => 'Nesta aba, informe o e-mail de uma conta do financeiro e aperte "Sondar". A tela responde em português se a credencial abriu a porta.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
                ['rotulo' => 'Documentação da API do WHMCS', 'url' => 'https://docs.whmcs.com/Authentication'],
                ['rotulo' => 'Preencher as credenciais', 'rota' => 'admin.settings.index'],
            ],
        ],

        'whmcs_url' => [
            'onde' => 'A raiz do financeiro que o portal vai consultar.',
            'passos' => [
                ['icone' => 'fa-globe', 'titulo' => 'Informe só a raiz', 'texto' => 'Até o domínio e a pasta da instalação: `https://financeiro.seudominio.com.br`. O painel completa o caminho do arquivo de API sozinho.'],
                ['icone' => 'fa-code', 'titulo' => 'Só escreva o caminho completo se a pasta foi renomeada', 'texto' => 'Instalação com o arquivo de API fora do padrão pede o endereço inteiro, terminando em `.php`.'],
                ['icone' => 'fa-circle-check', 'titulo' => 'Confira na sonda', 'texto' => 'Endereço errado responde erro de HTTP ou servidor mudo; endereço certo responde se a conta existe.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
                ['rotulo' => 'Documentação da API do WHMCS', 'url' => 'https://docs.whmcs.com/Authentication'],
            ],
        ],

        'whmcs_identifier' => [
            'onde' => 'A metade pública do par que abre a API do financeiro — quem a chamada diz ser.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Entre na administração do WHMCS', 'texto' => 'Endereço do financeiro seguido de `/admin`, com o login de administrador.'],
                ['icone' => 'fa-gear', 'titulo' => 'Crie a credencial de API', 'texto' => 'Configurações → Configurações do sistema → API → criar credencial. Dê um nome que diga que é o portal do cliente.'],
                ['icone' => 'fa-paste', 'titulo' => 'Copie agora, junto com o segredo', 'texto' => 'Os dois aparecem uma vez só, no momento da criação. Depois disto, a tela do WHMCS mostra apenas que a credencial existe.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
            ],
        ],

        'whmcs_secret' => [
            'onde' => 'A metade secreta do par: é a senha da API, e não a senha de um ser humano.',
            'passos' => [
                ['icone' => 'fa-user-shield', 'titulo' => 'Crie uma credencial só para o portal', 'texto' => 'Não reutilize a chave de um módulo de faturamento ou de outro sistema: se precisar fechar o portal, fecharia junto outra coisa.'],
                ['icone' => 'fa-clipboard-check', 'titulo' => 'Cole assim que aparecer', 'texto' => 'O WHMCS mostra o segredo uma única vez, na criação. Não há como consultá-lo de novo depois.'],
                ['icone' => 'fa-lock', 'titulo' => 'Salve e esqueça', 'texto' => 'Aqui ele é cifrado no banco e some da tela. Para trocar, gere outra credencial no WHMCS e cole por cima.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
                ['rotulo' => 'Documentação: credenciais de API do WHMCS', 'url' => 'https://docs.whmcs.com/Authentication'],
                ['rotulo' => 'Colar no painel', 'rota' => 'admin.settings.index'],
            ],
            'nota' => 'Se este valor vazar, o caminho é revogar a credencial na tela de API do WHMCS e criar outra — apagar aqui só devolve o sistema ao `.env` deste servidor.',
        ],

        'whmcs_timeout' => [
            'onde' => 'O tempo que o portal espera o financeiro responder antes de dizer que a área está indisponível.',
            'passos' => [
                ['icone' => 'fa-stopwatch', 'titulo' => 'Doze segundos é o teto razoável', 'texto' => 'O cliente não fica olhando tela branca: passado o prazo, a tela avisa em português e o resto do portal continua funcionando.'],
                ['icone' => 'fa-train-subway', 'titulo' => 'Aumente só se o financeiro for lento', 'texto' => 'Instalação grande, em servidor compartilhado ou atrás de VPN responde devagar. Um número alto protege a consulta e castiga a paciência.'],
                ['icone' => 'fa-satellite-dish', 'titulo' => 'Descubra com a sonda', 'texto' => 'Se a prova de conexão demora e falha, o problema é de endereço ou de disponibilidade — não deste número.'],
            ],
            'links' => [
                ['rotulo' => 'Sondar a conexão nesta tela', 'rota' => 'admin.settings.index'],
            ],
        ],

        'whmcs_limite' => [
            'onde' => 'Quantos serviços e faturas cada consulta ao financeiro traz de uma vez.',
            'passos' => [
                ['icone' => 'fa-layer-group', 'titulo' => 'Cinquenta cobre uma conta comum', 'texto' => 'É o padrão da casa e chega para quem tem alguns veículos rastreados e o histórico do ano.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'O limite é por consulta, não por cliente', 'texto' => 'Quem tem mais itens do que o limite vê os primeiros e a tela continua paginada pelo que o financeiro devolve.'],
                ['icone' => 'fa-gauge', 'titulo' => 'Número alto custa tela pesada', 'texto' => 'Subir para 200 só faz sentido em conta de revenda, com muitos contratos; para o cliente comum, é lista longa na tela do celular.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir o portal do cliente', 'rota' => 'portal.inicio'],
            ],
        ],

        'whmcs_rotulos_campos' => [
            'onde' => 'Como cada campo do serviço aparece na tela de quem contratou, em vez do nome de sistema que o financeiro usa por dentro.',
            'passos' => [
                ['icone' => 'fa-signature', 'titulo' => 'O nome do campo nasce no financeiro', 'texto' => 'Quem cadastra o produto no WHMCS escreve o nome de uma palavra, sem acento e muitas vezes em inglês: DescricaoDoveiculo, FornecedorChip, SIMCARD. É esse nome que a consulta devolve, e ele chegava ao cliente do jeito que nasceu.'],
                ['icone' => 'fa-equals', 'titulo' => 'Uma linha por campo', 'texto' => 'Escreva nomeDoSistema=Como aparece. O que estiver aqui troca o rótulo na hora, na tela do portal e na ficha do painel — é uma regra só, desenhada nos dois lugares.'],
                ['icone' => 'fa-spell-check', 'titulo' => 'O que não está na lista não fica feio', 'texto' => 'Campo sem mapa aparece com as palavras separadas, e rótulo que já chega escrito como frase passa do jeito que está. Nenhum nome some da tela por falta de mapa.'],
            ],
            'links' => [
                ['rotulo' => 'Escrever os rótulos nesta tela', 'rota' => 'admin.settings.index'],
                ['rotulo' => 'Ver a tela do cliente', 'rota' => 'portal.servicos'],
            ],
        ],

        'whmcs_campo_documento' => [
            'onde' => 'O número do campo personalizado em que o financeiro guarda o CPF ou o CNPJ do cliente.',
            'passos' => [
                ['icone' => 'fa-hashtag', 'titulo' => 'O número vem do financeiro', 'texto' => 'Cada instalação numera os campos do seu jeito. Este valor é o número que a consulta devolve, e não o rótulo que aparece na tela do financeiro.'],
                ['icone' => 'fa-magnifying-glass', 'titulo' => 'Confira antes de puxar', 'texto' => 'É este número que o botão da ficha lê. Trocado, o documento chega em branco e a ficha continua como estava — sem erro na tela, só sem dado.'],
                ['icone' => 'fa-copy', 'titulo' => 'O botão nunca apaga', 'texto' => 'O que a equipe já escreveu na ficha continua lá. O financeiro só completa o que está em branco, e a ficha avisa o que veio e o que ficou de fora.'],
            ],
            'links' => [
                ['rotulo' => 'Escrever o número nesta tela', 'rota' => 'admin.settings.index'],
            ],
        ],

        'whmcs_campo_nascimento' => [
            'onde' => 'O número do campo personalizado em que o financeiro guarda a data de nascimento.',
            'passos' => [
                ['icone' => 'fa-calendar-days', 'titulo' => 'A data é lida como se escreve no Brasil', 'texto' => 'O sistema aceita dia/mês/ano, ano/mês/dia e o formato corrido, e confere se a data existe de verdade antes de gravar.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'Cada instalação numera à sua moda', 'texto' => 'O número de um financeiro não vale para outro. Ao trocar de instalação, confira o mapa antes de puxar de novo.'],
                ['icone' => 'fa-circle-info', 'titulo' => 'Data que não é de gente não entra', 'texto' => 'Ano anterior a 1900 ou data no futuro é descartada em silêncio: a ficha fica sem a data em vez de mostrar um erro de digitação do financeiro.'],
            ],
            'links' => [
                ['rotulo' => 'Escrever o número nesta tela', 'rota' => 'admin.settings.index'],
            ],
        ],

        'whmcs_campo_plataforma' => [
            'onde' => 'O número do campo personalizado que diz em qual servidor o cliente está hospedado — o que a ficha mostra como plataforma.',
            'passos' => [
                ['icone' => 'fa-server', 'titulo' => 'É o servidor do rastreador', 'texto' => 'Normalmente vem escrito como servidor 3, servidor 5. É o que a equipe precisa saber para abrir o painel certo do rastreador.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'O número é do financeiro', 'texto' => 'Vale o que a consulta devolve, e não o nome do campo na tela dele. Um número trocado traz o servidor de outro assunto, ou nada.'],
                ['icone' => 'fa-pen-to-square', 'titulo' => 'A ficha mostra e a edição corrige', 'texto' => 'O valor puxado aparece no cartão da ficha e pode ser ajustado à mão na tela de edição, sem sobrescrever o que veio do financeiro.'],
            ],
            'links' => [
                ['rotulo' => 'Escrever o número nesta tela', 'rota' => 'admin.settings.index'],
            ],
        ],

        'whmcs_campo_usuario' => [
            'onde' => 'O número do campo personalizado que guarda o login do cliente na plataforma de rastreamento.',
            'passos' => [
                ['icone' => 'fa-user-gear', 'titulo' => 'É login, não é senha', 'texto' => 'A ficha guarda o nome de acesso e nunca a senha: credencial de rastreamento não fica gravada no sistema.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'É o número que a consulta devolve', 'texto' => 'Não adianta copiar o nome do campo da tela do financeiro: o que vale aqui é o número que a consulta traz junto com a ficha.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Em branco é resposta, não falha', 'texto' => 'Muita conta usa o e-mail do cadastro como usuário. Sem valor no financeiro, a ficha mantém o que a equipe escreveu e avisa o que ficou de fora.'],
            ],
            'links' => [
                ['rotulo' => 'Escrever o número nesta tela', 'rota' => 'admin.settings.index'],
            ],
        ],

        'sonda_email' => [
            'onde' => 'A prova de fogo da conexão: uma chamada de verdade ao financeiro, na frente de quem aperta o botão.',
            'passos' => [
                ['icone' => 'fa-envelope-open-text', 'titulo' => 'Escolha uma conta que existe', 'texto' => 'Use o e-mail de um cliente seu, ou a conta que você mesmo abriu para testar. A conta inexistente é o segundo resultado mais útil — ela diz que a porta abriu e quem errou foi o cadastro.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Sonde e leia a resposta', 'texto' => 'A tela diz, em português, o que aconteceu: conta confirmada, conta inexistente, credencial recusada, erro de HTTP ou servidor sem resposta.'],
                ['icone' => 'fa-list-check', 'titulo' => 'Cada resposta tem um conserto', 'texto' => 'Credencial recusada volta para as duas de API; erro de HTTP, para o endereço; servidor mudo, para o tempo de espera e para a disponibilidade do financeiro.'],
            ],
            'links' => [
                ['rotulo' => 'Lista de clientes no WHMCS', 'url' => '{whmcs}/admin/clients.php'],
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
            ],
        ],

        'sonda_email_destino' => [
            'onde' => 'A caixa que recebe a mensagem de prova de disparo — o mesmo caminho do link de senha do portal.',
            'passos' => [
                ['icone' => 'fa-at', 'titulo' => 'Digite a sua própria caixa', 'texto' => 'Isto é teste de transporte, não correspondência: nunca mande prova para cliente.'],
                ['icone' => 'fa-inbox', 'titulo' => 'Olhe também o spam', 'texto' => 'Mensagem que chega no lixo eletrônico quer dizer que o transporte funcionou e a reputação do servidor é que precisa de ajuste.'],
                ['icone' => 'fa-stopwatch', 'titulo' => 'Espere a fila', 'texto' => 'O disparo sai pela fila do agendador. Se o cron estiver parado, a mensagem fica esperando na tabela de tarefas em vez de falhar.'],
            ],
            'links' => [
                ['rotulo' => 'Webmail do domínio', 'url' => 'https://{host}:2083/webmail'],
            ],
        ],

        'whmcs-veiculacao' => [
            'onde' => 'Decisão de negócio, não credencial: o que o cliente vê, o que o portal escreve no financeiro e se a equipe pode entrar na conta de um cliente.',
            'passos' => [
                ['icone' => 'fa-table-list', 'titulo' => 'Abra só as telas que você mantém', 'texto' => 'Serviços, faturas, segunda via pelo chat e cadastro espelhado têm interruptor próprio. Tela fechada responde "esta área está fechada" e não faz nenhuma chamada ao financeiro.'],
                ['icone' => 'fa-filter', 'titulo' => 'Filtre os grupos exibidos', 'texto' => 'Em branco, tudo aparece. Cheio, só os grupos com o nome escrito aqui — o resto some da lista do portal.'],
                ['icone' => 'fa-door-open', 'titulo' => 'Decida sobre o salt para fora', 'texto' => 'O link que leva o cliente ao financeiro para pagar é escolha sua: desligado, o pagamento continua sendo combinado com a equipe.'],
                ['icone' => 'fa-user-check', 'titulo' => 'Pondere o espelho de cadastro e senha', 'texto' => 'Ligado, o que o cliente corrige no portal escreve no financeiro. É mão única: senha trocada lá não volta para cá.'],
                ['icone' => 'fa-clock', 'titulo' => 'Dê prazo à janela de supervisão', 'texto' => 'O número de minutos que o botão "Ver a conta do cliente" fica aberto. Passou o prazo, a sessão de leitura cai sozinha.'],
            ],
            'links' => [
                ['rotulo' => 'Grupos de produtos no WHMCS', 'url' => '{whmcs}/admin/configproductgroups.php'],
                ['rotulo' => 'Abrir portal do cliente', 'rota' => 'portal.inicio'],
            ],
            'nota' => 'Interruptor desligado não é botão escondido: a requisição nem é feita, e o item sai do menu do portal.',
        ],

        'whmcs_veicula_servicos' => [
            'onde' => 'Se o cliente vê "Meus serviços" no portal — os rastreadores e planos contratados, lidos do financeiro.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligado, a consulta passa a ser feita', 'texto' => 'Cada abertura da tela é uma chamada ao WHMCS pela credencial da aba Chaves.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligado, a tela sai do menu', 'texto' => 'O item some da navegação do portal e nenhuma chamada é feita — o cliente fica com chamados, pedidos e contratos.'],
                ['icone' => 'fa-circle-question', 'titulo' => 'Quando faz sentido fechar', 'texto' => 'Quem prefere que o serviço apareça só no sistema de rastreamento, ou está preparando a migração do financeiro.'],
            ],
            'links' => [
                ['rotulo' => 'Ver a tela de serviços no portal', 'rota' => 'portal.servicos'],
            ],
        ],

        'whmcs_veicula_faturas' => [
            'onde' => 'Se o cliente vê "Minhas faturas" no portal, com o que está em aberto e o vencimento.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'É por aqui que a mensalidade se paga sozinha', 'texto' => 'Com a tela ligada, o cliente vê o valor e o botão que leva ao financeiro para pagar.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligada, a cobrança volta a ser falada', 'texto' => 'Nada quebra: a equipe combinaria o pagamento por WhatsApp ou e-mail, como antes.'],
                ['icone' => 'fa-list-check', 'titulo' => 'Combine com o link externo', 'texto' => 'Mostrar a lista sem deixar sair pela tela é opção abaixo, e serve justamente para quem quer só o espelho da fatura.'],
            ],
            'links' => [
                ['rotulo' => 'Ver a tela de faturas no portal', 'rota' => 'portal.faturas'],
            ],
        ],

        'whmcs_veicula_servicos_encerrados' => [
            'onde' => 'Se serviços encerrados, cancelados ou em análise aparecem na lista do portal.',
            'passos' => [
                ['icone' => 'fa-ban', 'titulo' => 'O padrão é mostrar só o que está ativo', 'texto' => 'Encerrado não é contrato em andamento, e misturar os dois confunde mais do que informa.'],
                ['icone' => 'fa-hourglass-half', 'titulo' => 'Suspenso e pendente continuam dentro', 'texto' => 'Desses o cliente ainda deve, e somá-los da lista seria esconder uma cobrança.'],
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligue para virar histórico', 'texto' => 'Quem quer que o portal seja o arquivo completo do que já foi contratado liga este interruptor.'],
            ],
            'links' => [
                ['rotulo' => 'Ver serviços no portal do cliente', 'rota' => 'portal.servicos'],
            ],
        ],

        'whmcs_veicula_faturas_pagas' => [
            'onde' => 'Se faturas já pagas e reembolsadas entram na lista do portal.',
            'passos' => [
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligado, a tela é um lembrete de pagamento', 'texto' => 'Ficam as que ainda pedem algo — a lista mais curta é a que o cliente abre para resolver.'],
                ['icone' => 'fa-archive', 'titulo' => 'Ligado, a tela é o histórico', 'texto' => 'Útil quando o cliente pede a relação do ano para a contabilidade e prefere buscá-la sozinho.'],
            ],
            'links' => [
                ['rotulo' => 'Ver faturas no portal do cliente', 'rota' => 'portal.faturas'],
            ],
        ],

        'whmcs_veicula_link_externo' => [
            'onde' => 'Se o portal deixa o cliente saltar para o financeiro — os botões "Pagar" e "Abrir" e o link da fatura.',
            'passos' => [
                ['icone' => 'fa-up-right-from-square', 'titulo' => 'Ligado, o pagamento é por conta do cliente', 'texto' => 'O portal mostra o valor e abre o financeiro na conta dele, no navegador, em outra aba.'],
                ['icone' => 'fa-headset', 'titulo' => 'Desligado, quem cobra é a equipe', 'texto' => 'O portal mostra a lista sem levar ninguém para fora, e o pagamento é combinado no atendimento.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Isto não muda a segurança', 'texto' => 'O link é o do financeiro, com o login dele mesmo. O painel nunca passa a senha do cliente adiante.'],
            ],
            'links' => [
                ['rotulo' => 'Endereço do financeiro', 'url' => '{whmcs}'],
            ],
        ],

        'whmcs_veicula_segunda_via' => [
            'onde' => 'Se a Ana pode enviar a segunda via da fatura pelo próprio chat, depois de conferir os dados da ficha.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligado, o pedido é atendido pela máquina', 'texto' => 'Quem pede a via da fatura na conversa recebe no e-mail cadastrado, sem esperar gente.'],
                ['icone' => 'fa-user-shield', 'titulo' => 'A conferência de dados continua valendo', 'texto' => 'A identidade é confirmada antes do envio, e o envio só acontece para o e-mail que já está na ficha.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligado, nenhuma chamada por causa do chat', 'texto' => 'O assunto volta a ser só o portal e a equipe: a máquina explica como pedir e escala para gente.'],
            ],
            'links' => [
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
                ['rotulo' => 'Ver conversas do assistente', 'rota' => 'admin.assistente.conversas.index'],
            ],
            'nota' => 'Depende das três credenciais do WHMCS estarem preenchidas: sem conexão, a segunda via não tem de onde sair.',
        ],

        'whmcs_veicula_espelhar_dados' => [
            'onde' => 'Se o cadastro que o cliente corrige no portal é copiado para o financeiro.',
            'passos' => [
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Ligado, uma correção vale nos dois', 'texto' => 'Endereço, telefone e documentos digitados no portal escrevem na conta do WHMCS.'],
                ['icone' => 'fa-database', 'titulo' => 'A ficha do CRM é gravada de qualquer jeito', 'texto' => 'Este interruptor só manda a cópia para fora; o registro daqui não depende dele.'],
                ['icone' => 'fa-hand', 'titulo' => 'Desligado, alguém digita lá', 'texto' => 'Quem mantém o financeiro à mão, por outro sistema, desliga para não ser sobrescrito.'],
            ],
            'links' => [
                ['rotulo' => 'Clientes no WHMCS', 'url' => '{whmcs}/admin/clients.php'],
                ['rotulo' => 'Abrir portal do cliente', 'rota' => 'portal.inicio'],
            ],
            'nota' => 'É mão única: o que muda no WHMCS não volta para cá, e a ficha do financeiro continua podendo ser editada lá.',
        ],

        'whmcs_veicula_espelhar_senha' => [
            'onde' => 'Se a senha nova que o cliente define no portal também troca a senha do financeiro.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligado, uma senha só nos dois lugares', 'texto' => 'Quem recupera a senha no portal entra no financeiro com a mesma senha, sem recuperar de novo.'],
                ['icone' => 'fa-key', 'titulo' => 'Desligado, são duas contas com duas senhas', 'texto' => 'O cliente passa a ter a senha do portal e a do site de pagamento — e é a equipe que ouve "não lembro a do financeiro".'],
                ['icone' => 'fa-lock', 'titulo' => 'Nem uma opção grava a senha em lugar nenhum', 'texto' => 'O sistema nunca vê a senha: no espelho, o pedido de troca é enviado ao WHMCS com a senha nova que o próprio cliente digitou.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir portal do cliente', 'rota' => 'portal.inicio'],
                ['rotulo' => 'Tela de API do seu WHMCS', 'url' => '{whmcs}/admin/configapi.php'],
            ],
        ],

        'whmcs_veicula_grupos' => [
            'onde' => 'A lista de grupos de serviços que o portal exibe. Vazio mostra todos.',
            'passos' => [
                ['icone' => 'fa-tags', 'titulo' => 'Copie o nome como o WHMCS o escreve', 'texto' => 'Configurações do sistema → Grupos de produtos, no financeiro. A comparação é pelo nome exato.'],
                ['icone' => 'fa-comma', 'titulo' => 'Separe por vírgula', 'texto' => 'Rastreamento, Localização, Manutenção — e cada grupo que não estiver aqui some da lista do portal.'],
                ['icone' => 'fa-eye', 'titulo' => 'Conira pela tela de serviços', 'texto' => 'Abra o portal com uma conta que tem serviço nos grupos filtrados e nos que ficaram de fora.'],
            ],
            'links' => [
                ['rotulo' => 'Grupos de produtos do seu WHMCS', 'url' => '{whmcs}/admin/configproductgroups.php'],
                ['rotulo' => 'Tela de serviços do portal', 'rota' => 'portal.servicos'],
            ],
        ],

        'whmcs_veicula_supervisao' => [
            'onde' => 'Se a equipe pode abrir a conta de um cliente no portal, em modo leitura, a partir da ficha dele.',
            'passos' => [
                ['icone' => 'fa-door-open', 'titulo' => 'Ligado, aparece o botão na ficha do cliente', 'texto' => 'Comercial → Clientes → "Ver a conta do cliente": a pessoa entra nas mesmas telas do portal, com os mesmos filtros desta aba.'],
                ['icone' => 'fa-hand', 'titulo' => 'Nada ali grava', 'texto' => 'Não se abre chamado, não se faz pedido, não se mexe em dado nem em senha. É leitura, e a barra no topo lembra isso.'],
                ['icone' => 'fa-user-secret', 'titulo' => 'Desligado, o botão some e a sessão é recusada', 'texto' => 'Nenhuma janela de supervisão é aceita, inclusive as que estavam abertas.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a lista de clientes', 'rota' => 'admin.clientes.index'],
            ],
            'nota' => 'Toda entrada em modo leitura fica registrada com quem entrou e quando saiu — a supervisão é auditável por definição.',
        ],

        'whmcs_veicula_supervisao_minutos' => [
            'onde' => 'Quantos minutos a janela de supervisão fica aberta antes de cair sozinha.',
            'passos' => [
                ['icone' => 'fa-hourglass', 'titulo' => 'Dez minutos chega para conferir uma tela', 'texto' => 'É o tempo de olhar fatura, serviço e chamado, e o fim da conversa "esqueci a conta do cliente aberta".'],
                ['icone' => 'fa-stopwatch', 'titulo' => 'Vencido o prazo, a sessão sai', 'texto' => 'A tela do outro lado volta para a entrada do portal, e nada precisa ser encerrado à mão.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Precisa de mais? Entre de novo', 'texto' => 'A janela é reaberta com um clique na ficha — alongar o prazo não é o caminho.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir lista de clientes', 'rota' => 'admin.clientes.index'],
            ],
        ],

        /* ---------------------------------------------------------------- Chaves: Google Ads */

        'google-ads' => [
            'onde' => 'O par OAuth e o nível de acesso nascem no Google Cloud, a conta gerente nasce no Google Ads e a conexão em si acontece no painel. O antigo token de desenvolvedor foi aposentado pelo Google em 09/09/2026.',
            'passos' => [
                ['icone' => 'fa-sitemap', 'titulo' => 'Tenha a conta gerente (MCC)', 'texto' => 'Em Google Ads → Ferramentas e configurações → Estrutura de contas, crie a gerente e anexe as contas de anunciante. O número dela entra sem hífen.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Crie o par OAuth no Google Cloud', 'texto' => 'Tela de consentimento (tipo Externo) e uma credencial "Aplicação da Web". O ID e o segredo que nascem ali vão para os dois campos abaixo.'],
                ['icone' => 'fa-plug', 'titulo' => 'Ative a API do Google Ads no projeto', 'texto' => 'Sem ativar, o consentimento aparece e a chamada falha. É a etapa mais esquecida.'],
                ['icone' => 'fa-gauge-high', 'titulo' => 'Peça o nível de acesso do projeto', 'texto' => 'Na página Google Ads API do projeto: Explorer para começar, Basic quando o volume pedir. É o projeto da chave OAuth que carrega o nível — não existe mais token para pedir.'],
                ['icone' => 'fa-arrow-right-to-bracket', 'titulo' => 'Conecte a conta no painel', 'texto' => 'Com os campos salvos, Comercial → Google Ads → "Conectar conta Google Ads" faz o Google perguntar em que conta entrar.'],
            ],
            'links' => [
                ['rotulo' => 'Google Cloud Console — credenciais', 'url' => 'https://console.cloud.google.com/apis/credentials'],
                ['rotulo' => 'Ativar a API do Google Ads', 'url' => 'https://console.cloud.google.com/apis/library/googleads.googleapis.com'],
                ['rotulo' => 'Níveis de acesso da API', 'url' => 'https://developers.google.com/google-ads/api/docs/api-policy/access-levels'],
            ],
        ],

        'google_ads_developer_token' => [
            'onde' => 'Campo legado. O Google aposentou os tokens de desenvolvedor em 09/09/2026: um valor já gravado continua sendo enviado e o servidor o ignora. Quem autoriza a chamada hoje é o projeto do Google Cloud que criou a chave OAuth.',
            'passos' => [
                ['icone' => 'fa-circle-info', 'titulo' => 'Instalação nova? Deixe vazio', 'texto' => 'Sem token de desenvolvedor a integração funciona. O que libera o acesso é o nível do projeto no Google Cloud, ao lado da chave OAuth.'],
                ['icone' => 'fa-clock-rotate-left', 'titulo' => 'Já tinha um token?', 'texto' => 'Pode continuar gravado sem prejuízo: ele segue no cabeçalho e é ignorado. Quando o Google passar a recusá-lo, esvaziar o campo resolve.'],
                ['icone' => 'fa-cloud', 'titulo' => 'Onde está o acesso agora', 'texto' => 'No Google Cloud Console, na página Google Ads API do projeto: peça o nível Explorer para começar e Basic quando o volume pedir.'],
            ],
            'links' => [
                ['rotulo' => 'Google Cloud Console — Google Ads API', 'url' => 'https://console.cloud.google.com/apis/api/googleads.googleapis.com'],
                ['rotulo' => 'Documentação: níveis de acesso', 'url' => 'https://developers.google.com/google-ads/api/docs/api-policy/access-levels'],
            ],
        ],

        'google_client_id' => [
            'onde' => 'O nome público da sua aplicação no Google, criado no Cloud Console junto com o segredo que fica abaixo.',
            'passos' => [
                ['icone' => 'fa-shield-halved', 'titulo' => 'Primeiro, a tela de consentimento', 'texto' => 'APIs e serviços → Tela de consentimento OAuth → Externo, com o endereço do seu domínio nas origens autorizadas. Sem ela, o botão de criar credencial falha.'],
                ['icone' => 'fa-key', 'titulo' => 'Crie a credencial', 'texto' => 'Credenciais → Criar credencial → ID de cliente OAuth → tipo Aplicação da Web.'],
                ['icone' => 'fa-crosshairs', 'titulo' => 'Cadastre o endereço de volta', 'texto' => 'Em "URIs de redirecionamento autorizadas", escreva exatamente o que o campo abaixo mostra: o endereço do seu painel terminado em `/auth/google-ads/callback`. Diferença de barra ou de `http` já basta para o Google recusar.'],
                ['icone' => 'fa-paste', 'titulo' => 'Copie o ID', 'texto' => 'Aparece como `XXXX.apps.googleusercontent.com`. Este não é segredo; o que é segredo é o par dele, no campo seguinte.'],
            ],
            'links' => [
                ['rotulo' => 'Credenciais do Google Cloud', 'url' => 'https://console.cloud.google.com/apis/credentials'],
                ['rotulo' => 'Tela de consentimento', 'url' => 'https://console.cloud.google.com/apis/credentials/consent'],
            ],
        ],

        'google_client_secret' => [
            'onde' => 'A assinatura do pedido de consentimento: o par do ID acima, criado no mesmo lugar e na mesma hora.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => 'Copie no momento da criação', 'texto' => 'O Cloud Console mostra o segredo ao criar a credencial e permite consultá-lo depois na ficha da própria credencial.'],
                ['icone' => 'fa-lock', 'titulo' => 'Salve sabendo que ele cifrado', 'texto' => 'Gravado aqui, ele entra cifrado no banco e nunca mais aparece nesta tela.'],
                ['icone' => 'fa-rotate', 'titulo' => 'Vazou? Gere outro', 'texto' => 'Apague a credencial antiga no Cloud Console, crie outra e cole o valor novo aqui. Trocar a senha do anunciante não muda nada: isto é segredo da aplicação.'],
            ],
            'links' => [
                ['rotulo' => 'Credenciais do Google Cloud', 'url' => 'https://console.cloud.google.com/apis/credentials'],
            ],
        ],

        'google_redirect_uri' => [
            'onde' => 'A porta pela qual o Google devolve a autorização depois do consentimento. Em branco, o painel usa o próprio endereço dele.',
            'passos' => [
                ['icone' => 'fa-crosshairs', 'titulo' => 'Use o endereço que o painel indica', 'texto' => 'Ele é o endereço deste painel terminado em `/auth/google-ads/callback`. É o mesmo valor que precisa estar cadastrado nas origens do Cloud Console.'],
                ['icone' => 'fa-globe', 'titulo' => 'Bata letra, barra e protocolo', 'texto' => 'O Google compara string por string: `http` em vez de `https`, ou uma barra a mais no fim, responde `redirect_uri_mismatch`.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Deixe vazio se o painel já está no ar', 'texto' => 'Para uma instalação única, o valor próprio é o suficiente e não precisa ser digitado aqui.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir o endereço de volta', 'rota' => 'google-ads.callback'],
            ],
            'nota' => 'Este endereço só faz sentido depois do consentimento; abri-lo antes de conectar conta responde um erro do Google, e isso não é defeito do painel.',
        ],

        'google_ads_login_customer_id' => [
            'onde' => 'O número da conta gerente (MCC), que permite uma única credencial enxergar as contas anexadas a ela.',
            'passos' => [
                ['icone' => 'fa-hashtag', 'titulo' => 'Copie o ID do anunciante', 'texto' => 'No Google Ads, o número aparece ao lado do nome da conta, com dez dígitos.'],
                ['icone' => 'fa-scissors', 'titulo' => 'Tire o hífen', 'texto' => 'A API quer `1234567890`, não `123-456-7890`.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Conta única pode ficar em branco', 'texto' => 'Sem MCC, cada conta conectada é consultada por si só, e este campo não serve para nada.'],
            ],
            'links' => [
                ['rotulo' => 'Estrutura de contas do Google Ads', 'url' => 'https://support.google.com/google-ads/answer/6139186'],
            ],
        ],

        'google_ads_cache_minutos' => [
            'onde' => 'Quanto tempo a janela já sincronizada serve à tela antes de o painel voltar a chamar o Google.',
            'passos' => [
                ['icone' => 'fa-gauge', 'titulo' => 'Meia hora é o ponto de partida', 'texto' => 'Suficiente para quem abre a tela de hora em hora, curto o bastante para o gasto do dia aparecer antes do fim do dia.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Quer número vivo? Use o botão de sincronizar', 'texto' => 'A tela força a leitura nova quando alguém pede — não é preciso encurtar o cache para conseguir isso.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Cache muito curto custa chamadas', 'texto' => 'O Google conta chamadas por projeto. Recarregar a tela a cada minuto com cache de um minuto gasta cota à toa.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a tela do Google Ads', 'rota' => 'admin.google-ads.index'],
            ],
        ],

        'google_ads_dias' => [
            'onde' => 'A janela de dias que a tela abre escolhendo, quando ninguém selecionou período nenhum.',
            'passos' => [
                ['icone' => 'fa-calendar-days', 'titulo' => 'Escolha o período da sua rotina', 'texto' => 'Quem revisa campanha toda semana vive dos últimos 7 dias; quem fecha o mês escolhe 30.'],
                ['icone' => 'fa-list', 'titulo' => 'As outras janelas continuam na tela', 'texto' => '7, 30 e 90 dias seguem disponíveis para quem consulta — este campo só decide o que aparece primeiro.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a tela do Google Ads', 'rota' => 'admin.google-ads.index'],
            ],
        ],

        /* ---------------------------------------------------------------- Chaves: avaliações do Google */

        'avaliacoes' => [
            'onde' => 'Lê as avaliações da sua ficha do Google para a tela de Depoimentos. Três valores: a conta, a ficha e o token que autoriza a leitura.',
            'passos' => [
                ['icone' => 'fa-store', 'titulo' => 'Confirme que você administra a ficha', 'texto' => 'Gerenciador de negócios, na conta que tem acesso à ficha da empresa. Sem esse acesso, nenhuma credencial ajuda.'],
                ['icone' => 'fa-plug', 'titulo' => 'Ative a API de desempenho do perfil', 'texto' => 'No Cloud Console, ative "Business Profile Performance API" no mesmo projeto usado para o Google Ads ou num projeto próprio.'],
                ['icone' => 'fa-key', 'titulo' => 'Gere o token', 'texto' => 'O caminho mais curto é o OAuth Playground: escolha o escopo `https://www.googleapis.com/auth/business.profile`, autorize com o login da ficha e copie o `ya29.` que voltar.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'Pegue o número da conta e o da ficha', 'texto' => 'Os dois aparecem no endereço do Gerenciador: `accounts/<número>/locations/<número>`. Copie o número puro, ou o trecho inteiro como a API escreve.'],
                ['icone' => 'fa-star', 'titulo' => 'Importe', 'texto' => 'Conteúdo do site → Depoimentos → importar do Google. O que volta entra como depoimento, e a nota mínima decide o que vira vitrine.'],
            ],
            'links' => [
                ['rotulo' => 'Google Business Profile', 'url' => 'https://business.google.com/manage/'],
                ['rotulo' => 'Ativar a API de desempenho do perfil', 'url' => 'https://console.cloud.google.com/apis/library/businessprofileperformance.googleapis.com'],
                ['rotulo' => 'OAuth Playground (gerar o token)', 'url' => 'https://developers.google.com/oauthplayground'],
                ['rotulo' => 'Documentação da API', 'url' => 'https://developers.google.com/business-profile/performance'],
            ],
            'nota' => 'Token de OAuth expira. Quando a importação passar a responder `unauthorized`, o conserto é gerar outro token — não reinstalar nada.',
        ],

        'google_places_place_id' => [
            'onde' => 'O Place ID da sua empresa no Google Maps. É um código único que começa com `ChIJ` (ex: `ChIJN1t_tDeuEmsRUsoyG83frY4`). Identifica a sua ficha no Google Maps e nunca expira.',
            'passos' => [
                ['icone' => 'fa-magnifying-glass', 'titulo' => 'Abra o Place ID Finder da Google', 'texto' => 'Acesse developers.google.com/maps/documentation/places/web-service/place-id.'],
                ['icone' => 'fa-store', 'titulo' => 'Pesquise por Golden Rastreamento', 'texto' => 'Digite o nome da empresa ou o endereço em Três Pontas e selecione o local no mapa.'],
                ['icone' => 'fa-copy', 'titulo' => 'Copie o Place ID', 'texto' => 'Copie o código que aparece em "Place ID" (começa com `ChIJ`). Cole neste campo.'],
            ],
            'links' => [
                ['rotulo' => 'Buscador de Place ID da Google', 'url' => 'https://developers.google.com/maps/documentation/places/web-service/place-id'],
            ],
        ],

        'google_places_api_key' => [
            'onde' => 'A chave de API do Google Cloud com a "Places API" habilitada. Ela permite a sincronização automática e periódica de avaliações sem expirar.',
            'passos' => [
                ['icone' => 'fa-cloud', 'titulo' => 'Acesse o Google Cloud Console', 'texto' => 'Abra console.cloud.google.com no projeto da empresa.'],
                ['icone' => 'fa-plug', 'titulo' => 'Ative a Places API', 'texto' => 'No menu lateral, vá em "APIs e serviços" → "Biblioteca" → pesquise "Places API" e clique em "Ativar".'],
                ['icone' => 'fa-key', 'titulo' => 'Gere a Chave de API', 'texto' => 'Em "APIs e serviços" → "Credenciais" → clique em "+ Criar credenciais" → "Chave de API".'],
                ['icone' => 'fa-check', 'titulo' => 'Cole e salve', 'texto' => 'Cole a chave aqui. Ela nunca expira e o agendador atualizará as avaliações automaticamente todos os dias.'],
            ],
            'links' => [
                ['rotulo' => 'Google Cloud Console', 'url' => 'https://console.cloud.google.com/'],
                ['rotulo' => 'Documentação da Places API', 'url' => 'https://developers.google.com/maps/documentation/places/web-service/overview'],
            ],
        ],

        'google_business_account' => [
            'onde' => 'O número da conta do Google Business Profile que administra a sua ficha.',
            'passos' => [
                ['icone' => 'fa-store', 'titulo' => 'Abra o gerenciador do perfil', 'texto' => 'Gerenciador de negócios → Contas. O nome da conta mostra o número entre parênteses.'],
                ['icone' => 'fa-paste', 'titulo' => 'Copie o número, ou o endereço da API', 'texto' => 'Aceita o número puro (`1234567890`) ou a forma completa `accounts/1234567890`.'],
                ['icone' => 'fa-star', 'titulo' => 'Conira com uma importação', 'texto' => 'Conteúdo do site → Depoimentos → importar. Sem conta informada, a tela explica que a importação depende desta chave.'],
            ],
            'links' => [
                ['rotulo' => 'Contas do Google Business Profile', 'url' => 'https://business.google.com/manage/'],
            ],
        ],

        'google_business_location' => [
            'onde' => 'O número da ficha (o local) cujas avaliações serão lidas — uma conta pode ter mais de uma ficha.',
            'passos' => [
                ['icone' => 'fa-map-pin', 'titulo' => 'Abra a ficha', 'texto' => 'Gerenciador do perfil → a ficha da empresa. O número da ficha aparece no endereço do navegador.'],
                ['icone' => 'fa-paste', 'titulo' => 'Copie o número ou a forma da API', 'texto' => 'Aceita `1234567890` ou `locations/1234567890`.'],
                ['icone' => 'fa-circle-check', 'titulo' => 'Uma ficha por instalação', 'texto' => 'Quem tem filiais escolhe aqui qual delas vira depoimento no site; as outras precisam do próprio site.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir o gerenciador do perfil', 'url' => 'https://business.google.com/manage/'],
            ],
        ],

        'google_business_token' => [
            'onde' => 'O token de acesso (`ya29.`) que prova à API que quem pede a leitura administra a ficha.',
            'passos' => [
                ['icone' => 'fa-plug', 'titulo' => 'Ative a API no projeto', 'texto' => 'Cloud Console → Biblioteca de APIs → "Business Profile Performance API" → Ativar.'],
                ['icone' => 'fa-right-to-bracket', 'titulo' => 'Autorize no OAuth Playground', 'texto' => 'Use o próprio PlayGround com a credencial do seu projeto, escolha o escopo `business.profile` e autorize com o login da ficha.'],
                ['icone' => 'fa-paste', 'titulo' => 'Cole o access token', 'texto' => 'O valor que começa com `ya29.`. Ele expira em algumas horas; a importação que falhar por token é convite para gerar outro.'],
            ],
            'links' => [
                ['rotulo' => 'OAuth Playground', 'url' => 'https://developers.google.com/oauthplayground'],
                ['rotulo' => 'Escopos da API do perfil', 'url' => 'https://developers.google.com/business-profile/performance/scope-overview'],
            ],
            'nota' => 'Este token é uma chave de acesso curta: se ele vazar, revogue a sessão no próprio Google e gere outro. Nada aqui muda a senha de quem administra a ficha.',
        ],

        'google_business_limite' => [
            'onde' => 'Quantas avaliações a importação puxa em cada rodada.',
            'passos' => [
                ['icone' => 'fa-paragraph', 'titulo' => '50 lê a ficha inteira de uma vez', 'texto' => 'É o teto que a API entrega por página, e o padrão quando o campo fica em branco.'],
                ['icone' => 'fa-eye', 'titulo' => 'Número pequeno serve para espiar', 'texto' => 'Importar 10 primeiro, ver como o texto chega no carrossel e então rodar a leitura completa.'],
                ['icone' => 'fa-rotate', 'titulo' => 'A repetição não duplica', 'texto' => 'Quem já foi importado é atualizado no lugar, em vez de virar um segundo depoimento.'],
            ],
            'links' => [
                ['rotulo' => 'Ver os depoimentos importados', 'rota' => 'admin.conteudo.depoimentos.index'],
            ],
        ],

        'google_business_nota_minima' => [
            'onde' => 'A nota a partir da qual uma avaliação do Google vira depoimento no site.',
            'passos' => [
                ['icone' => 'fa-filter', 'titulo' => 'Quatro é o piso da vitrine', 'texto' => 'Nota baixa é chamado, não propaganda: ela merece resposta da equipe, não o carrossel.'],
                ['icone' => 'fa-inbox', 'titulo' => 'O que fica de fora não se perde', 'texto' => 'A avaliação continua no Google, onde quem pesquisa vê. O filtro é só sobre o que a casa repete no próprio site.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Ajuste quando a régua apertar demais', 'texto' => 'Ficha com poucas avaliações de 4 pode deixar o carrossel vazio: nesse caso baixá-lo para 3 é o ajuste, não importar tudo.'],
            ],
            'links' => [
                ['rotulo' => 'Rever a lista de depoimentos', 'rota' => 'admin.conteudo.depoimentos.index'],
            ],
        ],

        /* ---------------------------------------------------------------- Chaves: e-mail */

        'email' => [
            'onde' => 'O servidor de saída de todo aviso do painel e do portal: link de senha, chamado respondido, recibo de venda e segunda via da fatura.',
            'passos' => [
                ['icone' => 'fa-box-open', 'titulo' => 'Abra uma caixa só para disparar', 'texto' => 'avisos@ ou disparos@ na sua domínio, com senha própria. Caixa de pessoa não serve: muda de dono, e o site fica sem e-mail.'],
                ['icone' => 'fa-server', 'titulo' => 'Pegue os dados do SMTP', 'texto' => 'Na hospedagem, cada caixa tem a sua saída: servidor (`mail.seudominio`), porta (465 com SSL, 587 com STARTTLS) e usuário com o endereço inteiro.'],
                ['icone' => 'fa-key', 'titulo' => 'Preencha os campos desta tela', 'texto' => 'Transporte SMTP, servidor, porta, criptografia, usuário, senha, remetente e nome. O que ficar em branco continua obedecendo ao `.env` deste servidor.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Mande a prova', 'texto' => 'O card "Testar o disparo agora" nesta mesma aba envia uma mensagem de verdade para a caixa que você digitar, e diz em português o que aconteceu.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Configuração de clientes de e-mail (Gmail)', 'url' => 'https://support.google.com/mail/answer/7126229'],
            ],
            'nota' => 'Faltar credencial não tira o recurso do sistema: o código do disparo continua inteiro, pronto para funcionar assim que a senha for preenchida.',
        ],

        'mail_mailer' => [
            'onde' => 'Diz por onde a mensagem sai: servidor de correio, o programa da máquina ou só o arquivo de log.',
            'passos' => [
                ['icone' => 'fa-envelope', 'titulo' => 'SMTP é a escolha de produção', 'texto' => 'Servidor de correio com usuário e senha: é o que chega, o que se autentica e o que não cai no spam por saída anônima.'],
                ['icone' => 'fa-file-lines', 'titulo' => '"Só gravar em log" desliga de verdade', 'texto' => 'A mensagem é escrita em `storage/logs/email.log` e ninguém recebe nada. Útil para ensaiar sem incomodar cliente.'],
                ['icone' => 'fa-terminal', 'titulo' => 'sendmail é para servidor com MTA local', 'texto' => 'Só escolha se a máquina tiver sendmail instalado — em hospedagem compartilhada, o SMTP do domínio é o caminho.'],
            ],
            'links' => [
                ['rotulo' => 'Contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
            ],
        ],

        'mail_host' => [
            'onde' => 'O servidor que aceita a saída: `mail.seudominio.com.br` na maioria das hospedagens.',
            'passos' => [
                ['icone' => 'fa-book-open', 'titulo' => 'Leia a ficha de conexão da caixa', 'texto' => 'Na hospedagem, ao criar a conta de e-mail aparece "Configuração manual": o servidor de saída (SMTP) vem ali, com as portas.'],
                ['icone' => 'fa-globe', 'titulo' => 'Sem `https://`, sem `ssl://`', 'texto' => 'O campo quer o endereço puro. Em alguns painéis de hospedagem ele vem escrito com prefixo — o prefixo não vai para cá.'],
                ['icone' => 'fa-circle-check', 'titulo' => 'Confira na prova de disparo', 'texto' => 'Endereço errado responde "conexão recusada" ou tempo esgotado, e a tela diz qual dos dois.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as configurações de conexão da hospedagem', 'url' => 'https://{host}:2083/webmail'],
            ],
        ],

        'mail_port' => [
            'onde' => 'A porta de saída, que anda de mãos dadas com a criptografia escolhida abaixo.',
            'passos' => [
                ['icone' => 'fa-plug', 'titulo' => '587 pede STARTTLS', 'texto' => 'A conexão começa limpa e sobe para TLS. É a escolha mais comum, e a que as hospedagens liberam primeiro.'],
                ['icone' => 'fa-lock', 'titulo' => '465 pede SSL desde o abraço', 'texto' => 'A conexão já nasce criptografada. Funciona onde a 587 estiver bloqueada.'],
                ['icone' => 'fa-ban', 'titulo' => '25 quase nunca é opção', 'texto' => 'Porta de trânsito entre servidores, costuma estar fechada para saída de hospedagem compartilhada.'],
                ['icone' => 'fa-box-open', 'titulo' => '587 bloqueada? Troque o par', 'texto' => '465 com SSL é o par alternativo, e a hospedagem pode também oferecer uma porta de substituição na ficha de conexão.'],
            ],
            'links' => [
                ['rotulo' => 'Configuração manual de e-mail (cPanel)', 'url' => 'https://{host}:2083/email/delivery-encryption'],
            ],
        ],

        'mail_scheme' => [
            'onde' => 'Como a conversa com o servidor é criptografada. As duas opções são os nomes que o transporte do Laravel entende.',
            'passos' => [
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Combine com a porta', 'texto' => 'STARTTLS com 587, SSL desde o abraço com 465. Par trocado é o erro de SMTP mais comum.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, decide a porta', 'texto' => 'Sem valor aqui nem no `.env`, o transporte deduz pela porta informada.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Confira na prova de disparo', 'texto' => 'A tela de teste mostra o transporte, o servidor, a porta e a criptografia antes de enviar — e o erro, se vier.'],
            ],
            'links' => [
                ['rotulo' => 'Configuração de criptografia (cPanel)', 'url' => 'https://{host}:2083/email/delivery-encryption'],
            ],
        ],

        'mail_username' => [
            'onde' => 'O login da caixa que envia — quase sempre o próprio endereço de e-mail, por extenso.',
            'passos' => [
                ['icone' => 'fa-at', 'titulo' => 'Digite o endereço completo', 'texto' => 'disparos@seudominio.com.br, e não só `disparos`. A hospedagem entende os dois em alguns casos, mas o SMTP padrão pede o endereço inteiro.'],
                ['icone' => 'fa-user-shield', 'titulo' => 'Use a caixa de disparo, não a sua', 'texto' => 'O usuário precisa ter permissão de saída nesta servidor; a caixa de uma pessoa pode não ter.'],
                ['icone' => 'fa-box-open', 'titulo' => 'Criou agora? Espere alguns minutos', 'texto' => 'A caixa leva um instante para valer no SMTP depois de criada no painel da hospedagem.'],
            ],
            'links' => [
                ['rotulo' => 'Criar a caixa de disparo', 'url' => 'https://{host}:2083/email'],
            ],
        ],

        'mail_password' => [
            'onde' => 'A senha da caixa que envia — o único segredo deste formulário, cifrado no banco e escondido da tela depois de salvo.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => 'Senha de aplicativo, quando houver', 'texto' => 'No Gmail e no Microsoft, a senha normal não vale para SMTP: gere uma senha de aplicativo na conta.'],
                ['icone' => 'fa-building-columns', 'titulo' => 'Na hospedagem, é a senha da caixa', 'texto' => 'A mesma que você daria ao webmail ou ao programa de e-mail, definida ao criar a conta no painel da hospedagem.'],
                ['icone' => 'fa-paste', 'titulo' => 'Cole e salve uma vez', 'texto' => 'Depois de gravado, o valor não aparece mais aqui para ninguém. Para trocar, digite a nova por cima.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Prove com a mensagem de teste', 'texto' => 'Senha errada responde autenticação recusada, e a tela diz isso com todas as letras.'],
            ],
            'links' => [
                ['rotulo' => 'Senhas de aplicativo do Google', 'url' => 'https://myaccount.google.com/apppasswords'],
                ['rotulo' => 'Contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
            ],
            'nota' => 'Caixa de máquina, nunca o e-mail pessoal de alguém. Vazou? Troque a senha na hospedagem e cole a nova aqui — o registro antigo continua cifrado e inútil.',
        ],

        'mail_from_address' => [
            'onde' => 'O endereço que o destinatário vê como remetente, e que precisa ser aceito pelo servidor de saída.',
            'passos' => [
                ['icone' => 'fa-envelope', 'titulo' => 'Use endereço do seu domínio', 'texto' => 'avisos@seudominio.com.br. Endereço de outro domínio é recusado pelo SMTP ou cai no spam.'],
                ['icone' => 'fa-fingerprint', 'titulo' => 'Combine com a autenticação', 'texto' => 'O ideal é que este endereço seja a própria caixa usada no usuário acima, ou uma caixa do mesmo domínio.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Reputação é outro campo', 'texto' => 'SPF, DKIM e DMARC ficam no DNS do domínio, na hospedagem. Sem eles, a mensagem pode sair e ainda assim ir para o spam.'],
            ],
            'links' => [
                ['rotulo' => 'Registrar SPF e DKIM da caixa', 'url' => 'https://{host}:2083/email/delivery-encryption'],
            ],
        ],

        'mail_from_name' => [
            'onde' => 'O nome que o celular mostra na lista de mensagens, antes de qualquer assunto: "Golden Rastreamento", e não "avisos".',
            'passos' => [
                ['icone' => 'fa-font', 'titulo' => 'Escreva a marca como ela é dita', 'texto' => 'Sem sigla interna, sem o nome do servidor e sem "sistema". É a linha que a pessoa reconhece ou descarta.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, vale o nome do site', 'texto' => 'Este campo existe para quando o nome de quem envia precisa ser diferente do nome do site.'],
                ['icone' => 'fa-reply', 'titulo' => 'Não coloque um e-mail aqui', 'texto' => 'Nome é texto simples; o endereço de responder fica no campo de remetente, acima.'],
            ],
            'links' => [
                ['rotulo' => 'Autenticação SPF/DKIM no cPanel', 'url' => 'https://{host}:2083/email/delivery-encryption'],
            ],
            'nota' => 'O que decide se a mensagem cai no spam é o domínio autenticado (SPF, DKIM, DMARC) no DNS da hospedagem, não o nome escrito aqui.',
        ],

        /* ---------------------------------------------------------------- Chaves: segurança do acesso */

        'seguranca' => [
            'onde' => 'Os números da porta de entrada do painel: quantas senhas erradas até a conta travar, quem é obrigado a ter o aplicativo de autenticação e quanto tempo uma janela aberta sobrevive sem ninguém mexendo.',
            'passos' => [
                ['icone' => 'fa-sliders', 'titulo' => 'Decida a regra da casa aqui', 'texto' => 'Nada desta aba é código: cada campo grava no banco e vale no minuto seguinte, sem reiniciar nada. Em branco, cada um volta a obedecer ao `.env` deste servidor.'],
                ['icone' => 'fa-user-lock', 'titulo' => 'O efeito se vê em Segurança do acesso', 'texto' => 'Sistema → Segurança do acesso mostra o contador de tentativas da janela, as janelas abertas na sua conta e o estado da segunda chave de cada pessoa — é por ali que se confere se o número escolhido está fazendo o que se queria.'],
                ['icone' => 'fa-file-shield', 'titulo' => 'Toda decisão fica registrada', 'texto' => 'Entrada, saída, conta travada, janela encerrada e segunda chave ligada ou desligada deixam linha na Auditoria, com autor, endereço de rede e hora do fuso da casa.'],
            ],
            'links' => [
                ['rotulo' => 'Ver o movimento da porta', 'rota' => 'admin.seguranca.index'],
                ['rotulo' => 'Ler o guia de uso do painel', 'rota' => 'admin.guia.index'],
            ],
            'nota' => 'Travar a conta não é punição: é o que derruba o script que roda lista de senhas vazadas sem atrapalhar quem errou o teclado duas vezes.',
        ],

        'seguranca_login_tentativas' => [
            'onde' => 'Quantas senhas erradas uma mesma conta pode receber antes de a porta fechar sozinha.',
            'passos' => [
                ['icone' => 'fa-keyboard', 'titulo' => 'Contadas por e-mail e por endereço de rede juntos', 'texto' => 'Um escritório inteiro atrás do mesmo roteador não trava a conta de um colega, e quem troca de rede não zera o próprio contador.'],
                ['icone' => 'fa-scale-balanced', 'titulo' => 'Cinco é a medida da casa', 'texto' => 'Derruba o script que experimenta dezenas de senhas por minuto e ainda deixa quem errou o teclado duas vezes tentar de novo sem ligar para ninguém.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, vale o arquivo de configuração', 'texto' => 'Sem valor aqui, o número do `.env` deste servidor continua mandando — a porta nunca fica sem regra por campo vazio.'],
                ['icone' => 'fa-eye', 'titulo' => 'Confira na tela de Segurança', 'texto' => 'O cartão de números mostra "trava em N tentativas" e quantas batidas cabem na janela de agora.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as tentativas de agora', 'rota' => 'admin.seguranca.index'],
            ],
        ],

        'seguranca_login_janela' => [
            'onde' => 'O tamanho da janela de tempo em que as tentativas erradas são somadas — fora dela, a conta recomeça do zero.',
            'passos' => [
                ['icone' => 'fa-hourglass-half', 'titulo' => 'Janela curta castiga o script', 'texto' => 'Quinze minutos é o bastante para segurar quem tenta dezenas de senhas, e curto o bastante para quem errou por engano poder tentar de novo no café seguinte.'],
                ['icone' => 'fa-arrows-left-right', 'titulo' => 'Ela manda no tamanho do bloqueio', 'texto' => 'O tempo de espera abaixo costuma ser do mesmo tamanho ou maior que a janela: fechar a porta por menos tempo do que ela leva para encher não segura ninguém.'],
                ['icone' => 'fa-check-to-slot', 'titulo' => 'O contador envelhece sozinho', 'texto' => 'Ninguém limpa nada à mão: a cada batida o sistema descarta o que ficou para trás na janela.'],
            ],
            'links' => [
                ['rotulo' => 'Ver tentativas na tela de Segurança', 'rota' => 'admin.seguranca.index'],
            ],
        ],

        'seguranca_login_bloqueio' => [
            'onde' => 'Quanto tempo a conta fica fechada depois de estourar a janela de tentativas.',
            'passos' => [
                ['icone' => 'fa-clock', 'titulo' => 'A porta reabre sozinha', 'texto' => 'Vencido o prazo, a conta volta a aceitar senha sem intervenção de ninguém — o script já desistiu e quem se enganou nem percebeu que esteve fora.'],
                ['icone' => 'fa-unlock-keyhole', 'titulo' => 'Adiantar a reabertura é decisão de administrador', 'texto' => 'Sistema → Segurança do acesso tem o cartão "Destravar uma conta": ele apaga as batidas daquela conta agora, e só quem administra usa.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Não zerar o número para "ver se funciona"', 'texto' => 'Bloqueio de um minuto é convite para o script tentar de novo; ele foi desenhado para incomodar quem bate, não quem entra.'],
            ],
            'links' => [
                ['rotulo' => 'Destravar uma conta', 'rota' => 'admin.seguranca.index'],
            ],
        ],

        'seguranca_dois_fatores' => [
            'onde' => 'Quem é obrigado a ter o aplicativo de autenticação além da senha: ninguém, só quem administra, ou toda a equipe do painel.',
            'passos' => [
                ['icone' => 'fa-shield-halved', 'titulo' => 'A escolha vale para a próxima entrada', 'texto' => 'Quem ainda não tem a segunda chave é levado à ativação no primeiro acesso, e não barrado na porta — a política educa, não tranca.'],
                ['icone' => 'fa-qrcode', 'titulo' => 'Acender leva um minuto', 'texto' => 'Sistema → Segurança do acesso → "Acender a segunda chave": a própria tela desenha o código QR, o aplicativo do celular devolve seis números e a chave só é gravada depois que um número real bate. A pessoa ainda recebe oito códigos de recuperação.'],
                ['icone' => 'fa-phone', 'titulo' => 'Perdeu o celular?', 'texto' => 'Um código de recuperação abre a porta uma vez e se apaga. Um administrador pode desligar a chave de qualquer conta na tela de Segurança — o colega ao lado não pode.'],
                ['icone' => 'fa-store', 'titulo' => 'O cliente do portal não passa por aqui', 'texto' => 'A política é da casa, não da vitrine: quem usa o portal do cliente não é alcançado por este campo.'],
            ],
            'links' => [
                ['rotulo' => 'Acender a minha segunda chave', 'rota' => 'dois-fatores.configurar'],
                ['rotulo' => 'Ver a equipe e as chaves', 'rota' => 'admin.seguranca.index'],
            ],
            'nota' => 'A senha sozinha é a única chave quando este campo está em "ninguém". Vazada em alguém da sua lista de distribuição, ela abre o painel em qualquer navegador.',
        ],

        'seguranca_sessao_minutos' => [
            'onde' => 'Quanto tempo o painel fica aberto sem ninguém mexer, antes de pedir a senha de novo.',
            'passos' => [
                ['icone' => 'fa-display', 'titulo' => 'É por janela, não por navegador', 'texto' => 'A contagem é renovada a cada clique dentro do painel; uma aba esquecida aberta num computador do estoque às oito da manhã não é um acesso em uso às oito da noite.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Trocar de aparelho não reinicia o relógio', 'texto' => 'Cada janela aberta na conta tem o seu carimbo e o seu último sinal — a tela de Segurança mostra um por aparelho.'],
                ['icone' => 'fa-ruler-combined', 'titulo' => 'Meça pelo trabalho real', 'texto' => 'Duas horas serve a quem usa o painel o dia inteiro; vinte minutos serve a quem só entra para conferir um chamado.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, vale o arquivo de configuração', 'texto' => 'Sem valor aqui, o `.env` deste servidor decide — e a cookie de sessão continua vivendo pelo tempo do painel.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as janelas abertas', 'rota' => 'admin.seguranca.index'],
            ],
        ],

        'seguranca_sessoes_simultaneas' => [
            'onde' => 'Quantas janelas do painel uma mesma pessoa pode ter abertas ao mesmo tempo — celular, notebook e o computador da loja.',
            'passos' => [
                ['icone' => 'fa-window-maximize', 'titulo' => 'As mais antigas caem fora do número', 'texto' => 'Ao estourar o limite, a janela que ficou de fora perde o painel no pedido seguinte; é o que faz "encerrar o notebook do escritório" ser um clique, e não uma espera.'],
                ['icone' => 'fa-circle-half-stroke', 'titulo' => 'Zero deixa todas viverem', 'texto' => 'Use zero só quando outro sistema divide a mesma conta com a pessoa — sem limite, o controle é manual, na tela de Segurança.'],
                ['icone' => 'fa-user-large-slash', 'titulo' => 'Uma janela que não é sua', 'texto' => 'Feche ali mesmo: o botão derruba aquele aparelho na batida seguinte, e o acesso continua funcionando no navegador que você usa agora.'],
            ],
            'links' => [
                ['rotulo' => 'Ver e encerrar janelas', 'rota' => 'admin.seguranca.index'],
            ],
        ],

        /* ---------------------------------------------------------------- Chaves: assistente */

        'assistente-chaves' => [
            'onde' => 'A chave de cada provedor de IA que atende pelo chat. Uma por provedor é suficiente — o modelo escolhido em cada capacidade vem do provedor correspondente.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => 'Gere a chave na casa do provedor', 'texto' => 'Google AI Studio para o Gemini, platform.openai.com para a OpenAI. A chave nasce com créditos de teste na maioria dos planos.'],
                ['icone' => 'fa-lock', 'titulo' => 'Cole aqui, nunca no código', 'texto' => 'Gravada nesta tela, ela é cifrada no banco e some da view. No `.env` ela seria plano de fundo de quem ainda não preencheu nada.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Escolha o modelo de cada capacidade', 'texto' => 'No grupo "Assistente — modelo por capacidade" você diz qual modelo responde, qual transcreve áudio e qual aprende.'],
                ['icone' => 'fa-comment-dots', 'titulo' => 'Prove no site', 'texto' => 'Abra o chat do site e faça uma pergunta que esteja na base. A resposta sai com a fonte marcada para a equipe conferir.'],
            ],
            'links' => [
                ['rotulo' => 'Google AI Studio — chave do Gemini', 'url' => 'https://aistudio.google.com/apikey'],
                ['rotulo' => 'OpenAI — chaves de API', 'url' => 'https://platform.openai.com/api-keys'],
            ],
            'nota' => 'Sem nenhuma chave, o chat do site continua funcionando do jeito seguro: ele responde com a base que tem e chama gente quando não sabe — não inventa preço.',
        ],

        'assistente_gemini_key' => [
            'onde' => 'A chave do Google Gemini, usada quando a capacidade correspondente aponta para um modelo do Google.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra o Google AI Studio', 'texto' => 'Entre com a conta Google da empresa, a mesma que administra o Analytics e as fichas.'],
                ['icone' => 'fa-key', 'titulo' => 'Crie a chave', 'texto' => 'Obter chave de API → criar chave num projeto. Copie o valor que começa com `AIza`.'],
                ['icone' => 'fa-lock', 'titulo' => 'Cobrança é decisão sua', 'texto' => 'O plano gratuito tem limites por minuto; para produção com tráfego pago, associe um faturamento no mesmo projeto e acompanhe o uso.'],
                ['icone' => 'fa-comment-dots', 'titulo' => 'Confira no chat', 'texto' => 'Faça uma pergunta ao assistente do site e, se a resposta falhar, a ficha de log da API diz o provedor e o erro por extenso.'],
            ],
            'links' => [
                ['rotulo' => 'Criar chave do Gemini', 'url' => 'https://aistudio.google.com/apikey'],
                ['rotulo' => 'Documentação da API', 'url' => 'https://ai.google.dev/gemini-api/docs'],
            ],
        ],

        'assistente_openai_key' => [
            'onde' => 'A chave da OpenAI, usada pelas capacidades apontadas para um modelo dela.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra a plataforma', 'texto' => 'platform.openai.com, com o login da empresa e o método de pagamento associado se o volume for de produção.'],
                ['icone' => 'fa-key', 'titulo' => 'Crie a chave', 'texto' => 'Chaves de API → Criar chave nova, com um nome que diga que é do painel de atendimento. O valor `sk-` aparece uma vez só.'],
                ['icone' => 'fa-paste', 'titulo' => 'Cole aqui', 'texto' => 'É cifrado no banco ao salvar e não volta mais para esta tela.'],
                ['icone' => 'fa-chart-line', 'titulo' => 'Vigie o gasto', 'texto' => 'A ficha de log de chamadas guarda duração, tokens de entrada e tokens de saída de cada resposta do chat.'],
            ],
            'links' => [
                ['rotulo' => 'Criar chave da OpenAI', 'url' => 'https://platform.openai.com/api-keys'],
                ['rotulo' => 'Documentação da API', 'url' => 'https://platform.openai.com/docs'],
            ],
        ],

        'assistente-modelos' => [
            'onde' => 'Qual modelo atende cada capacidade: o texto do chat, a transcrição do áudio recebido e a leitura que alimenta a base de conhecimento.',
            'passos' => [
                ['icone' => 'fa-comment', 'titulo' => 'Comece com o modelo de resposta', 'texto' => 'É o que fala com o visitante. Escolha o mais rápido que você tem, porque a resposta chega na frente do cliente.'],
                ['icone' => 'fa-microphone', 'titulo' => 'Transcrição é capacidade à parte', 'texto' => 'Só entra em ação quando o visitante manda áudio. Pode ser um modelo menor que o do chat.'],
                ['icone' => 'fa-brain', 'titulo' => 'Aprendizado usa o modelo de leitura', 'texto' => 'É o que resume as páginas permitidas em itens de conhecimento, na rodada agendada.'],
                ['icone' => 'fa-circle-check', 'titulo' => 'Teste cada uma', 'texto' => 'Pergunte no site, mande um áudio e rode o aprendizado agora, na tela do assistente. As três têm log próprio.'],
            ],
            'links' => [
                ['rotulo' => 'Chaves e modelos no Google AI Studio', 'url' => 'https://aistudio.google.com/apikey'],
                ['rotulo' => 'Modelos e chaves na OpenAI', 'url' => 'https://platform.openai.com/api-keys'],
                ['rotulo' => 'Abrir ficha do assistente', 'rota' => 'admin.assistente.persona'],
            ],
            'nota' => 'Uma capacidade sem modelo cai no padrão da casa. Nenhum seletor desta tela é obrigatório para o chat funcionar.',
        ],

        'assistente_rodada_conversa' => [
            'onde' => 'O modelo que escreve as respostas do chat, capacidade por capacidade.',
            'passos' => [
                ['icone' => 'fa-bolt', 'titulo' => 'Prefira o modelo rápido', 'texto' => 'A resposta chega na frente do cliente, e um modelo leve responde em um terço do tempo com qualidade suficiente para atendimento.'],
                ['icone' => 'fa-coins', 'titulo' => 'Troque quando o assunto exigir', 'texto' => 'Cobrança por token cai na mesma proporção em que sobe o tamanho do modelo. Para chat, o pequeno é o padrão.'],
                ['icone' => 'fa-comment-dots', 'titulo' => 'Teste no site', 'texto' => 'Faça a pergunta que um cliente faria e leia a resposta antes de fechar a tela.'],
            ],
            'links' => [
                ['rotulo' => 'Modelos do Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/models'],
                ['rotulo' => 'Modelos da OpenAI', 'url' => 'https://platform.openai.com/docs/models'],
            ],
        ],

        'assistente_rodada_visao' => [
            'onde' => 'O modelo que lê imagem mandada no chat: foto do equipamento, print da tela, nota fiscal.',
            'passos' => [
                ['icone' => 'fa-image', 'titulo' => 'Precisa de um modelo com visão', 'texto' => 'Os modelos marcados como "visão" aceitam imagem junto com a pergunta; um modelo só de texto não consegue.'],
                ['icone' => 'fa-toggle-on', 'titulo' => 'Só funciona com "O cliente pode mandar imagem" ligado', 'texto' => 'O interruptor da conversa está no grupo "Assistente — comportamento do chat".'],
                ['icone' => 'fa-camera', 'titulo' => 'Teste com uma foto real', 'texto' => 'Mande a foto de um rastreador instalado pelo chat e veja se a resposta descreve o que está na imagem.'],
            ],
            'links' => [
                ['rotulo' => 'Leitura de imagem no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/image-understanding'],
            ],
        ],

        'assistente_rodada_documento' => [
            'onde' => 'O modelo que lê documento anexado no chat — contrato, nota fiscal, PDF de manual.',
            'passos' => [
                ['icone' => 'fa-file-pdf', 'titulo' => 'Documento é página, e página tem limite', 'texto' => 'Arquivo longo é cortado pelo limite de caracteres da leitura; o resto não entra na resposta.'],
                ['icone' => 'fa-toggle-on', 'titulo' => 'Sem modelo aqui, o envio de documento fica desligado', 'texto' => 'A tela avisa em vez de responder vazio — é por isso que o seletor existe.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Combine com o tamanho máximo aceito', 'texto' => 'O teto em megabytes do arquivo recebido está nos campos de mídia, abaixo.'],
            ],
            'links' => [
                ['rotulo' => 'Documentos e PDF no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/document-processing'],
            ],
        ],

        'assistente_rodada_transcricao' => [
            'onde' => 'O modelo que transforma o áudio mandado pelo cliente em texto, antes de virar pergunta.',
            'passos' => [
                ['icone' => 'fa-microphone', 'titulo' => 'Modelo de transcrição é mais barato que o de conversa', 'texto' => 'Usar o modelo grande para escutar áudio é pagar caro por um trabalho de ouvido.'],
                ['icone' => 'fa-language', 'titulo' => 'Confira o português', 'texto' => 'Mande um áudio de dez segundos em português e leia a transcrição registrada na conversa.'],
                ['icone' => 'fa-toggle-on', 'titulo' => 'Depende do recebimento de áudio estar ligado', 'texto' => 'O interruptor "O cliente pode mandar áudio" está no grupo de comportamento do chat.'],
            ],
            'links' => [
                ['rotulo' => 'Áudio no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/audio'],
                ['rotulo' => 'Transcrição na OpenAI', 'url' => 'https://platform.openai.com/docs/guides/speech-to-text'],
            ],
        ],

        'assistente_rodada_voz' => [
            'onde' => 'O modelo que fala a resposta escrita, quando a resposta em áudio está ligada.',
            'passos' => [
                ['icone' => 'fa-volume-high', 'titulo' => 'Cobrança é por caractere falado', 'texto' => 'Resposta de parágrafo em áudio custa mais que a mesma em texto; ligue sabendo disso.'],
                ['icone' => 'fa-ear-listen', 'titulo' => 'Escolha uma voz curta e clara', 'texto' => 'Voz de atendimento não é locução: a que soa como gente falando ao telefone é a certa.'],
                ['icone' => 'fa-toggle-on', 'titulo' => 'Só entra em ação com a saída de áudio ligada', 'texto' => 'O interruptor "O assistente pode responder em áudio" está no grupo de comportamento do chat.'],
            ],
            'links' => [
                ['rotulo' => 'Síntese de voz na OpenAI', 'url' => 'https://platform.openai.com/docs/guides/text-to-speech'],
            ],
        ],

        'assistente_voz_padrao' => [
            'onde' => 'A voz de quem não escolheu voz própria — a persona pode ter a dela, na tela do assistente.',
            'passos' => [
                ['icone' => 'fa-user-astronaut', 'titulo' => 'Ouça antes de decidir', 'texto' => 'As vozes são listas em ordem alfabética no seletor; a que agrada no teste escrito pode cansar em três mensagens.'],
                ['icone' => 'fa-id-card', 'titulo' => 'Deixe a persona decidir', 'texto' => 'Na ficha do assistente, em Atendimento → Assistente virtual, dá para fixar outra voz só para ela.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, vale a da casa', 'texto' => 'Nada aqui é obrigatório: sem escolha, a resposta falada sai na voz padrão da plataforma.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a ficha do assistente', 'rota' => 'admin.assistente.persona'],
            ],
        ],

        'assistente-chat' => [
            'onde' => 'O que a conversa aceita e até onde ela vai antes de chamar gente: o pedido do nome, a régua de conferência de segurança, o tamanho da resposta e a confiança da busca.',
            'passos' => [
                ['icone' => 'fa-id-badge', 'titulo' => 'Peça o nome e o e-mail primeiro', 'texto' => 'Ligado, o atendimento personalizado só começa depois que o visitante informa o nome e o e-mail — e no fim, a conversa pede avaliação do atendimento.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Monte a régua de conferência', 'texto' => 'Escolha os dados que a ficha precisa confirmar antes de qualquer envio, quantos acertos liberam o envio e quantos erros são aceitos. É por aqui, e não em código.'],
                ['icone' => 'fa-gauge-high', 'titulo' => 'Ajuste o piso de confiança', 'texto' => 'Busca sem fonte acima do piso chama gente em vez de chutar. Suba o piso se a Ana responder demais sem saber.'],
                ['icone' => 'fa-ruler-horizontal', 'titulo' => 'Dê o tamanho da resposta', 'texto' => 'O teto de caracteres e o histórico levado por chamada controlam o custo e a paciência do leitor.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir ficha do assistente', 'rota' => 'admin.assistente.persona'],
                ['rotulo' => 'Ver conversas no painel', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_pedir_nome' => [
            'onde' => 'Se a máquina se apresenta e pede o nome antes de conversar com quem não fez login.',
            'passos' => [
                ['icone' => 'fa-id-badge', 'titulo' => 'Ligado, o atendimento começa com quem é a pessoa', 'texto' => 'A Ana se apresenta, pede o nome e trata pelo nome a conversa inteira.'],
                ['icone' => 'fa-comment', 'titulo' => 'Desligado, todo mundo é "você"', 'texto' => 'Ela responde desde a primeira pergunta, sem tratamento pessoal.'],
                ['icone' => 'fa-star', 'titulo' => 'O nome é o que abre o atendimento personalizado', 'texto' => 'Com "exigir identificação" ligado, nome e e-mail vêm antes de qualquer consulta à conta.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as conversas registradas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_nome_perguntas' => [
            'onde' => 'Quantas vezes o nome é pedido na mesma conversa antes de a máquina desistir de perguntar.',
            'passos' => [
                ['icone' => 'fa-shuffle', 'titulo' => 'Insistir demais faz a pessoa ir embora', 'texto' => 'Quem não quer dizer o nome tem o direito de seguir sendo atendido no assunto geral.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Esgotado o número, o atendimento continua', 'texto' => 'A máquina para de perguntar e responde normalmente — a conversa não é cortada.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas registradas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_pedir_email' => [
            'onde' => 'Se a máquina pede o e-mail do cadastro — o que localiza a conta da pessoa no financeiro.',
            'passos' => [
                ['icone' => 'fa-magnifying-glass', 'titulo' => 'É pelo e-mail que a conta aparece', 'texto' => 'Sem ele, nada de segunda via, fatura ou serviço: a consulta ao WHMCS começa pelo endereço.'],
                ['icone' => 'fa-headset', 'titulo' => 'Desligado, o que é de conta vai para a equipe', 'texto' => 'A máquina atende o assunto geral e escala o resto, em vez de ficar pedindo dado.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas registradas', 'rota' => 'admin.assistente.conversas.index'],
                ['rotulo' => 'Clientes no WHMCS', 'url' => '{whmcs}/admin/clients.php'],
            ],
        ],

        'assistente_email_perguntas' => [
            'onde' => 'Quantas vezes o e-mail é pedido na mesma conversa.',
            'passos' => [
                ['icone' => 'fa-shuffle', 'titulo' => 'Passou daqui, segue sem e-mail', 'texto' => 'O pedido personalizado é escalado para gente em vez de virar formulário infinito.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Endereço recusado é outra história', 'texto' => 'Um e-mail inválido digitado uma vez já consome uma das tentativas — é por isso que o número existe.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas registradas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_exigir_identificacao' => [
            'onde' => 'A porta do atendimento personalizado: sem nome e e-mail informados, a máquina não mexe na conta de ninguém.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligado, ação de conta só depois de se identificar', 'texto' => 'Segunda via e qualquer consulta à ficha só abrem quando a pessoa informou nome e e-mail; antes disso, o pedido fica na fila e retoma sozinho.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'A conferência de dados é outra chave', 'texto' => 'Confirmar documento, nascimento ou placa antes de enviar algo não depende deste interruptor e continua valendo igual.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligado serve a quem testa', 'texto' => 'A máquina responde e age desde a primeira frase — útil para conferir o atendimento, não para deixar no ar.'],
            ],
            'links' => [
                ['rotulo' => 'Testar no chat do site', 'rota' => 'site.home'],
                ['rotulo' => 'Ver conversas registradas', 'rota' => 'admin.assistente.conversas.index'],
            ],
            'nota' => 'Desligar não enfraquece a verificação de segurança: ela é outra chave, e continua conferindo os dados da ficha antes de qualquer envio.',
        ],

        'assistente_pedir_avaliacao' => [
            'onde' => 'Se, no fim do atendimento, a conversa pede a nota do atendimento da Ana.',
            'passos' => [
                ['icone' => 'fa-star', 'titulo' => 'Uma vez por conversa', 'texto' => 'A pesquisa aparece no encerramento do atendimento, nunca no meio de uma resposta.'],
                ['icone' => 'fa-user-tie', 'titulo' => 'Humano no fio da conversa cancela o pedido', 'texto' => 'Se a equipe assumiu o atendimento, a nota seria sobre a pessoa, não sobre a máquina.'],
                ['icone' => 'fa-chart-simple', 'titulo' => 'Onde a nota aparece', 'texto' => 'Na conversa registrada e no resumo de atendimento — é por aqui que se sabe se a Ana está atendendo bem.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as conversas e as notas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_avaliacao_minimo_falas' => [
            'onde' => 'O piso de conversa de verdade antes de incomodar alguém com a pesquisa de nota.',
            'passos' => [
                ['icone' => 'fa-comment-slash', 'titulo' => 'Pesquisa no "oi" não é pesquisa', 'texto' => 'Quem trocou duas frases e foi embora não tem o que avaliar.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Aumente se a nota chega no meio do assunto', 'texto' => 'O pedido só acontece depois que a máquina respondeu o suficiente para a pessoa ter opinião.'],
            ],
            'links' => [
                ['rotulo' => 'Ver notas nas conversas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_verificacao_campos' => [
            'onde' => 'Os dados que a ficha precisa confirmar antes de a máquina enviar qualquer coisa.',
            'passos' => [
                ['icone' => 'fa-list-ol', 'titulo' => 'Escreva separando por vírgula', 'texto' => 'Os nomes aceitos são `documento` (CPF ou CNPJ), `nascimento`, `placas`, `telefone` e `cep` — na ordem em que devem ser perguntados.'],
                ['icone' => 'fa-ban', 'titulo' => 'Nome fora da lista é descartado', 'texto' => 'Um campo inventado aqui não trava a conversa: simplesmente não entra na conferência.'],
                ['icone' => 'fa-user-check', 'titulo' => 'Campo que a ficha não tem nunca é perguntado', 'texto' => 'Quem não tem placa cadastrada não é obrigado a decorar nada: a régua se ajusta ao que existe na conta.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as colunas de conferência na ficha do cliente', 'rota' => 'admin.clientes.index'],
            ],
        ],

        'assistente_verificacao_minimo' => [
            'onde' => 'Quantos acertos liberam o envio.',
            'passos' => [
                ['icone' => 'fa-balance-scale', 'titulo' => 'É o piso, não o pedido', 'texto' => 'Se a ficha só tem dois dos campos da lista, conferir dois é tudo o que existe.'],
                ['icone' => 'fa-lock', 'titulo' => 'Um a menos entrega dado para quem não é dono', 'texto' => 'Um a mais custa fricção: a pessoa desiste no meio da conferência.'],
            ],
            'links' => [
                ['rotulo' => 'Ver fichas de clientes', 'rota' => 'admin.clientes.index'],
            ],
        ],

        'assistente_verificacao_tentativas' => [
            'onde' => 'Quantas respostas erradas são aceitas na mesma conferência.',
            'passos' => [
                ['icone' => 'fa-headset', 'titulo' => 'Passou daqui, a equipe assume', 'texto' => 'A conversa é escalada e nada é enviado — quem errou duas vezes pode ser alguém que não é o dono da conta.'],
                ['icone' => 'fa-comment-dots', 'titulo' => 'A máquina não diz qual dado errou', 'texto' => 'Ela só repete a pergunta, de propósito: revelar o erro entrega informação a quem está tentando adivinhar.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas escaladas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_verificacao_minutos' => [
            'onde' => 'Quanto tempo uma conferência parada continua valendo.',
            'passos' => [
                ['icone' => 'fa-hourglass-end', 'titulo' => 'Uma hora parada não é a pessoa no computador', 'texto' => 'Vencido o prazo, os acertos são esquecidos e a conferência recomeça do zero.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Encurte em dispositivo compartilhado', 'texto' => 'Tablet de escritório ou computador da frota: a janela curta evita que outra pessoa continue a conversa começada.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas do chat', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_completar_nascimento' => [
            'onde' => 'Se o chat pode completar a data de nascimento de quem não tem nenhuma na ficha.',
            'passos' => [
                ['icone' => 'fa-user-check', 'titulo' => 'Só depois da prova de identidade', 'texto' => 'A pergunta só aparece na segunda via da fatura, quando a pessoa já acertou os dados da ficha — nunca antes, porque provar identidade com o dado que se está coletando seria entregar a conta.'],
                ['icone' => 'fa-pen-to-square', 'titulo' => 'Nunca por cima do que já existe', 'texto' => 'Ficha com data preenchida não é perguntada nem substituída: o cadastro da casa não se reescreve com texto de chat.'],
                ['icone' => 'fa-receipt', 'titulo' => 'A pergunta não segura a fatura', 'texto' => 'Se a pessoa recusar ou escrever algo que não é data, a segunda via sai do mesmo jeito e a data continua em branco.'],
            ],
            'links' => [
                ['rotulo' => 'Ver as fichas dos clientes', 'rota' => 'admin.clientes.index'],
            ],
        ],

        'assistente_audio_entrada' => [
            'onde' => 'Se o cliente pode gravar um áudio no chat — ele é transcrito antes de virar pergunta.',
            'passos' => [
                ['icone' => 'fa-microphone', 'titulo' => 'Depende do modelo de transcrição', 'texto' => 'Sem capacidade de transcrição escolhida no grupo de modelos, o botão de microfone não tem o que fazer.'],
                ['icone' => 'fa-file-audio', 'titulo' => 'A gravação respeita os limites de tamanho', 'texto' => 'Duração máxima e tamanho máximo estão nos campos de mídia, abaixo.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Vale a pena no celular', 'texto' => 'Quem está no carro fala em vez de digitar — é o atalho mais usado por quem liga sobre o veículo.'],
            ],
            'links' => [
                ['rotulo' => 'Áudio no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/audio'],
                ['rotulo' => 'Testar no chat do site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_imagem' => [
            'onde' => 'Se o cliente pode mandar imagem no chat: foto do equipamento, print da tela, nota fiscal.',
            'passos' => [
                ['icone' => 'fa-eye', 'titulo' => 'Depende do modelo de visão', 'texto' => 'A imagem é lida pelo modelo escolhido no grupo de modelos; sem ele, o envio fica desligado.'],
                ['icone' => 'fa-camera', 'titulo' => 'Bom para o que é difícil descrever', 'texto' => 'Luz acesa no rastreador, etiqueta do aparelho, tela com erro — tudo o que a pessoa não sabe escrever.'],
            ],
            'links' => [
                ['rotulo' => 'Visão computacional no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/image-understanding'],
                ['rotulo' => 'Testar no chat do site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_documento' => [
            'onde' => 'Se o cliente pode mandar documento no chat — contrato, nota fiscal, manual em PDF.',
            'passos' => [
                ['icone' => 'fa-file-pdf', 'titulo' => 'Só liga com um modelo de leitura de documento', 'texto' => 'Escolha a capacidade no grupo de modelos; sem isso, a tela avisa em vez de responder vazio.'],
                ['icone' => 'fa-scissors', 'titulo' => 'Documento longo é cortado', 'texto' => 'O texto aproveitado é limitado em caracteres, e o modelo só responde com o que entrou.'],
            ],
            'links' => [
                ['rotulo' => 'Documentos e PDF no Gemini', 'url' => 'https://ai.google.dev/gemini-api/docs/document-processing'],
                ['rotulo' => 'Testar no chat do site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_audio_saida' => [
            'onde' => 'Se a resposta escrita também sai falada, na voz escolhida.',
            'passos' => [
                ['icone' => 'fa-coins', 'titulo' => 'Ligado custa por caractere', 'texto' => 'A resposta em texto continua vindo junto e não muda de preço; o áudio é o extra.'],
                ['icone' => 'fa-volume-high', 'titulo' => 'Depende do modelo de voz', 'texto' => 'Sem capacidade de voz escolhida, nada é falado, mesmo com o interruptor ligado.'],
                ['icone' => 'fa-ear-listen', 'titulo' => 'Deixe a pessoa escolher ouvir', 'texto' => 'O play aparece na mensagem: quem está em reunião não é obrigado a escutar.'],
            ],
            'links' => [
                ['rotulo' => 'Síntese de voz na OpenAI', 'url' => 'https://platform.openai.com/docs/guides/text-to-speech'],
                ['rotulo' => 'Escolher voz na ficha do assistente', 'rota' => 'admin.assistente.persona'],
            ],
        ],

        'assistente_so_com_fonte' => [
            'onde' => 'A regra de ouro do atendimento: só responder o que tem lastro no conhecimento aprendido.',
            'passos' => [
                ['icone' => 'fa-book', 'titulo' => 'Ligado, sem fonte a máquina se cala', 'texto' => 'Ela chama a equipe em vez de inventar preço, prazo ou cobertura.'],
                ['icone' => 'fa-flask', 'titulo' => 'Desligado serve para experimentar', 'texto' => 'A resposta sai do que o modelo sabe sobre o mundo, e pode estar errada para a sua empresa.'],
                ['icone' => 'fa-database', 'titulo' => 'Encher a base é o caminho', 'texto' => 'Atendimento → Assistente virtual → conhecimento: quanto mais item da casa cadastrado, menos o interruptor precisa ser desligado.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        'assistente_audio_segundos' => [
            'onde' => 'A duração máxima de um áudio recebido no chat.',
            'passos' => [
                ['icone' => 'fa-stopwatch', 'titulo' => 'Sessenta segundos chega para uma pergunta', 'texto' => 'Áudio mais longo é ditado, não pergunta — e ditado é o que estoura a transcrição.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Passou do limite, a tela avisa', 'texto' => 'A mensagem volta com o motivo em português, em vez de transcrever um pedaço e responder torto.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas do assistente', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_audio_mb' => [
            'onde' => 'O tamanho máximo, em megabytes, de um áudio recebido.',
            'passos' => [
                ['icone' => 'fa-weight-hanging', 'titulo' => 'A moldura é de segurança, não de qualidade', 'texto' => 'Um arquivo enorme no chat não é conversa: é tráfego que custa e pode travar a fila.'],
                ['icone' => 'fa-mobile', 'titulo' => 'Lembre da rede do cliente', 'texto' => 'Quem manda áudio de dentro do carro está em rede de celular; arquivo pesado demora e falha.'],
            ],
            'links' => [
                ['rotulo' => 'Testar envio de áudio no site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_imagem_mb' => [
            'onde' => 'O tamanho máximo, em megabytes, de uma imagem recebida no chat.',
            'passos' => [
                ['icone' => 'fa-camera-retro', 'titulo' => 'Foto de celular hoje passa dos 5 MB', 'texto' => 'Um teto apertado demais recusa a foto que o cliente mais manda; meio caminho é o que cabe em rede de celular.'],
                ['icone' => 'fa-compress', 'titulo' => 'A leitura não precisa de resolução de impressão', 'texto' => 'O modelo de visão trabalha com muito menos pixels do que a câmera entrega.'],
            ],
            'links' => [
                ['rotulo' => 'Testar envio de imagem no site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_documento_mb' => [
            'onde' => 'O tamanho máximo, em megabytes, de um documento recebido no chat.',
            'passos' => [
                ['icone' => 'fa-file-lines', 'titulo' => 'Contrato e nota fiscal cabem em poucos megabytes', 'texto' => 'PDF escaneado em alta resolução é o que estoura: um limite baixo ensina o cliente a comprimir antes de mandar.'],
                ['icone' => 'fa-scissors', 'titulo' => 'Combine com o texto aproveitado', 'texto' => 'Documento grande com pouco texto lido responde pior que documento pequeno bem lido.'],
            ],
            'links' => [
                ['rotulo' => 'Testar anexo no site', 'rota' => 'site.home'],
            ],
        ],

        'assistente_documento_caracteres' => [
            'onde' => 'Quanto de um documento de texto (.txt, .csv e .docx) é lido e levado ao modelo em cada resposta.',
            'passos' => [
                ['icone' => 'fa-file-lines', 'titulo' => 'Texto é lido pelo sistema, não pela IA', 'texto' => 'O conteúdo do arquivo é extraído aqui e entra na chamada como texto — por isso a resposta cita o que está escrito em vez de inventar. PDF continua indo como anexo.'],
                ['icone' => 'fa-scissors', 'titulo' => 'O que passa do teto é omitido', 'texto' => 'A leitura para no limite e o modelo recebe um aviso de que o restante ficou de fora; ele deve dizer isso em vez de completar.'],
                ['icone' => 'fa-coins', 'titulo' => 'Documento longo custa por chamada', 'texto' => 'Todo texto levado é cobrado como entrada. Um contrato de poucas páginas cabe folgado no padrão da casa.'],
            ],
            'links' => [
                ['rotulo' => 'Ver conversas com anexos', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_resposta_audio_mb' => [
            'onde' => 'O teto, em megabytes, do áudio que a máquina devolve como resposta.',
            'passos' => [
                ['icone' => 'fa-file-audio', 'titulo' => 'É o freio do texto longo falado', 'texto' => 'Resposta grande demais vira arquivo que não abre no celular — e o cliente nem sabe que veio.'],
                ['icone' => 'fa-align-left', 'titulo' => 'O texto continua vindo inteiro', 'texto' => 'Se o áudio estourar o teto, a resposta escrita chega do mesmo jeito.'],
            ],
            'links' => [
                ['rotulo' => 'Ver ficha do assistente', 'rota' => 'admin.assistente.persona'],
            ],
        ],

        'assistente_resposta_max' => [
            'onde' => 'O tamanho máximo da resposta, em caracteres.',
            'passos' => [
                ['icone' => 'fa-ruler', 'titulo' => 'Atendimento bom cabe em duas telas de celular', 'texto' => 'Passe disso, a pessoa para de ler e pergunta de novo.'],
                ['icone' => 'fa-coins', 'titulo' => 'Custo acompanha o tamanho', 'texto' => 'Cada caractere de saída é cobrado; responder longo a toda pergunta multiplica a conta sem multiplicar a solução.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Em branco, vale o padrão da casa', 'texto' => 'Este campo é ajuste fino, não configuração obrigatória.'],
            ],
            'links' => [
                ['rotulo' => 'Ver respostas nas conversas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_historico' => [
            'onde' => 'Quantas falas da conversa são levadas ao modelo em cada chamada.',
            'passos' => [
                ['icone' => 'fa-clock-rotate-left', 'titulo' => 'É memória de curto prazo, não arquivo', 'texto' => 'A conversa inteira continua gravada no painel; aqui se decide quanto dela vai junto na próxima pergunta.'],
                ['icone' => 'fa-bullseye', 'titulo' => 'Pouco faz a máquina esquecer', 'texto' => 'Quem responde "como eu disse antes?" levou histórico de menos.'],
                ['icone' => 'fa-coins', 'titulo' => 'Muito custa por chamada', 'texto' => 'Todo histórico levado é cobrado como entrada, mesmo o que não foi usado.'],
            ],
            'links' => [
                ['rotulo' => 'Ver histórico das conversas', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_minimo_fonte' => [
            'onde' => 'O piso de confiança da busca de conhecimento: abaixo dele, a resposta não tem lastro e a equipe é chamada.',
            'passos' => [
                ['icone' => 'fa-gauge-high', 'titulo' => 'Comece no meio e ajuste pelo que vê', 'texto' => 'Piso alto é a Ana calada demais; piso baixo é ela respondendo coisa parecida.'],
                ['icone' => 'fa-comments', 'titulo' => 'Leia as conversas antes de mexer', 'texto' => 'As escalas para equipe dizem se o piso está alto; as respostas fora do assunto dizem se está baixo.'],
                ['icone' => 'fa-arrow-up', 'titulo' => 'Suba quando preço ou prazo aparecerem errados', 'texto' => 'Erro de número é o sintoma de uma busca aceita por pouca semelhança.'],
            ],
            'links' => [
                ['rotulo' => 'Ler as conversas do chat', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'assistente_itens_fonte' => [
            'onde' => 'Quantos itens de conhecimento são levados ao modelo para responder cada pergunta.',
            'passos' => [
                ['icone' => 'fa-layer-group', 'titulo' => 'Poucos itens deixam a resposta apertada', 'texto' => 'Um assunto que mora em três cadastros precisa dos três na frente do modelo.'],
                ['icone' => 'fa-coins', 'titulo' => 'Muitos itens custam por chamada', 'texto' => 'Cada item levado é texto de entrada cobrado, e parte dele não será usado.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Ajuste pelo tipo de pergunta', 'texto' => 'Catálogo de produto pede mais itens que "qual o horário de atendimento".'],
            ],
            'links' => [
                ['rotulo' => 'Abrir base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        'assistente-aprendizado' => [
            'onde' => 'A leitura que ensina a máquina a partir de endereços que você autoriza, na cadência que você escolhe.',
            'passos' => [
                ['icone' => 'fa-list-check', 'titulo' => 'Escreva os domínios permitidos', 'texto' => 'Um por linha ou separados por vírgula, sem `https://`: seu domínio, o da sua empresa-mãe e mais nada. Fora dessa lista, a leitura nem é tentada.'],
                ['icone' => 'fa-clock', 'titulo' => 'Dê a cadência', 'texto' => 'A cada quantos dias reler, quantas páginas por rodada e quantos caracteres aproveitar de cada página. Comece conservador.'],
                ['icone' => 'fa-graduation-cap', 'titulo' => 'Rode a primeira leitura agora', 'texto' => 'Atendimento → Assistente virtual → conhecimento: "Aprender da internet" roda na hora, sem esperar o agendador.'],
                ['icone' => 'fa-magnifying-glass', 'titulo' => 'Confira o que entrou', 'texto' => 'Cada item traz procedência, endereço de origem e data de leitura. O que não tem fonte não é respondido.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
                ['rotulo' => 'Ver site que será lido', 'rota' => 'site.home'],
            ],
            'nota' => 'O agendador da hospedagem precisa estar vivo para a releitura automática acontecer; sem cron, nada é lido sozinho.',
        ],

        'assistente_leitura' => [
            'onde' => 'O interruptor geral da leitura da internet: com ele desligado, nenhuma página é buscada, mesmo com domínios autorizados na lista.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ligue depois de escrever os domínios', 'texto' => 'Lista vazia com a leitura ligada não lê nada — a ordem certa é autorizar primeiro.'],
                ['icone' => 'fa-graduation-cap', 'titulo' => 'Rode a primeira leitura à mão', 'texto' => 'Atendimento → Assistente virtual → conhecimento → "Aprender da internet", para ver o que entra antes de confiar no agendador.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligar não apaga o que já foi aprendido', 'texto' => 'Os itens continuam no ar e seguem sendo usados como fonte; só a releitura para.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir a base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        'assistente_dominios' => [
            'onde' => 'A lista de endereços que a máquina tem permissão de ler. Fora dela, nada é tentado.',
            'passos' => [
                ['icone' => 'fa-globe', 'titulo' => 'Escreva sem `https://` e sem barra no fim', 'texto' => 'seudominio.com.br, um por linha ou separados por vírgula.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Só o que você é dono ou confia', 'texto' => 'O domínio do seu site, o da empresa-mãe do rastreador e o da plataforma que você revende. Rede social e fórum não entram.'],
                ['icone' => 'fa-list-check', 'titulo' => 'Cada leitura fica registrada', 'texto' => 'O item de conhecimento guarda o endereço de origem e a data da leitura, para a equipe conferir antes de alguém perguntar de onde veio.'],
            ],
            'links' => [
                ['rotulo' => 'Ver o site que será lido', 'rota' => 'site.home'],
            ],
            'nota' => 'O conteúdo lido entra como conhecimento da casa: um site errado no ar vira resposta errada no chat. É a razão de a lista ser curta por padrão.',
        ],

        'assistente_leitura_dias' => [
            'onde' => 'De quantos em quantos dias as páginas autorizadas são relidas.',
            'passos' => [
                ['icone' => 'fa-calendar-week', 'titulo' => 'Uma semana é o começo razoável', 'texto' => 'Preço e campanha mudam mais rápido que manual de instalação; para o site da casa, sete dias chega.'],
                ['icone' => 'fa-coins', 'titulo' => 'Dias curtos multiplicam chamadas ao modelo', 'texto' => 'Cada releitura resume o texto com um modelo, e isso é cobrado por caractere.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Mudou algo hoje? Role agora', 'texto' => 'Publicou preço novo: rode a leitura na tela do assistente em vez de encurtar o prazo para todo dia.'],
            ],
            'links' => [
                ['rotulo' => 'Rodar a leitura agora', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        'assistente_leitura_paginas' => [
            'onde' => 'Quantas páginas a rodada de leitura percorre por vez.',
            'passos' => [
                ['icone' => 'fa-sitemap', 'titulo' => 'O endereço descobre os links internos', 'texto' => 'A rodada anda pelo site a partir do que está autorizado, e para no número de páginas definido aqui.'],
                ['icone' => 'fa-hourglass-half', 'titulo' => 'Site grande se lê em várias rodadas', 'texto' => 'O que coube na rodada de hoje é lido; o resto espera a próxima, sem perder o que já entrou.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Rodada grande pesa no servidor', 'texto' => 'Cem páginas por vez é leitura longa e custo de modelo alto; comece com o que cabe numa tarde.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        'assistente_leitura_caracteres' => [
            'onde' => 'Quantos caracteres de cada página são aproveitados para virar conhecimento.',
            'passos' => [
                ['icone' => 'fa-align-left', 'titulo' => 'É o corte do texto lido', 'texto' => 'Passou deste ponto, a página é resumida só com o que coube — e o que ficou de fora não é respondido.'],
                ['icone' => 'fa-compress', 'titulo' => 'Página de catálogo rende mais que página de blog', 'texto' => 'O que importa costuma estar no começo; aumentar o teto serve a manual longo, não a página de vendas.'],
                ['icone' => 'fa-coins', 'titulo' => 'O teto controla o custo', 'texto' => 'Cada caractere lido entra no resumo feito pelo modelo, e a cobrança é proporcional a isso.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir base de conhecimento', 'rota' => 'admin.assistente.conhecimento.index'],
            ],
        ],

        /* ---------------------------------------------------------------- Modo de Manutenção (503) */

        'manutencao_status' => [
            'onde' => 'Painel de comando imediato que mostra se o site está aberto ao público ou em contingência HTTP 503 e permite ligar ou desligar com um clique.',
            'passos' => [
                ['icone' => 'fa-eye', 'titulo' => 'Confira a prévia antes de fechar ao público', 'texto' => 'Abra o botão Prévia da Tela 503 no topo para conferir títulos, contagem regressiva e botões de plantão antes de ativar.'],
                ['icone' => 'fa-shield-halved', 'titulo' => 'Garanta o acesso da equipe', 'texto' => 'Inclua os IPs do escritório ou gere/copie o link com a chave de bypass na aba Bypass & Liberação por IP.'],
                ['icone' => 'fa-power-off', 'titulo' => 'Acione ou encerre a manutenção', 'texto' => 'Use o botão de acionamento deste cartão para alternar o estado ao vivo; o painel `/admin` permanece acessível para administradores autenticados.'],
            ],
            'links' => [
                ['rotulo' => 'Ver site público', 'rota' => 'site.home'],
                ['rotulo' => 'Consultar auditoria do painel', 'rota' => 'admin.activities.index'],
            ],
            'nota' => 'Alterar campos nas abas e clicar em Salvar preserva o status operacional atual sem desligar a manutenção por engano.',
        ],

        'manutencao_conteudo' => [
            'onde' => 'Bloco de textos principais exibidos dentro do cartão central da tela de manutenção 503 para informar os clientes com clareza.',
            'passos' => [
                ['icone' => 'fa-heading', 'titulo' => 'Defina um título direto ao ponto', 'texto' => 'Informe logo na primeira linha que é uma melhoria programada para transmitir segurança aos motoristas e gestores de frota.'],
                ['icone' => 'fa-star', 'titulo' => 'Destaque a continuidade no subtítulo dourado', 'texto' => 'Reforce que os rastreadores veiculares continuam transmitindo telemetria normalmente mesmo com o site em atualização.'],
                ['icone' => 'fa-align-left', 'titulo' => 'Detalhe orientações na mensagem', 'texto' => 'Explique brevemente o motivo da janela de manutenção e convide quem tiver urgência a usar os canais de plantão logo abaixo.'],
            ],
            'links' => [
                ['rotulo' => 'Personalizar textos padrão das páginas de erro', 'rota' => 'admin.erros.index'],
            ],
        ],

        'manutencao_cronometro' => [
            'onde' => 'Controla quem é atingido pelo bloqueio 503 e configura o relógio regressivo que mostra quanto tempo falta para a reabertura.',
            'passos' => [
                ['icone' => 'fa-globe', 'titulo' => 'Escolha o escopo do bloqueio', 'texto' => 'Selecione Bloqueio Total para fechar tudo ou Apenas Site Público se os clientes puderem continuar usando o portal em `/portal`.'],
                ['icone' => 'fa-clock', 'titulo' => 'Informe a data e hora previstas para término', 'texto' => 'Preencha manualmente ou toque nos botões rápidos (+30 min, +1 h, +2 h, +4 h) para ajustar a janela com um toque.'],
                ['icone' => 'fa-stopwatch', 'titulo' => 'Ative ou oculte o mostrador digital', 'texto' => 'Com o interruptor ligado e a previsão no futuro, a tela 503 exibe dias, horas, minutos e segundos atualizados ao vivo.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir fuso horário da instalação', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_modo' => [
            'onde' => 'Define a abrangência da barreira 503 quando o modo de manutenção está ligado.',
            'passos' => [
                ['icone' => 'fa-lock', 'titulo' => 'Bloqueio Total', 'texto' => 'Exibe a tela 503 tanto no site institucional e loja quanto no portal do cliente (`/portal`), útil em migrações de banco de dados.'],
                ['icone' => 'fa-users-gear', 'titulo' => 'Apenas Site Público', 'texto' => 'Mantém `/portal` acessível aos clientes já cadastrados enquanto fecha apenas as páginas comerciais da vitrine e loja.'],
                ['icone' => 'fa-user-shield', 'titulo' => 'Painel administrativo nunca trava', 'texto' => 'As rotas `/admin` e `/login` permanecem sempre liberadas para que a equipe possa operar e desativar a manutenção.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir auditoria de acessos', 'rota' => 'admin.activities.index'],
            ],
        ],

        'manutencao_previsao_fim' => [
            'onde' => 'Data e horário estimados para conclusão dos trabalhos técnicos, usados pelo cronômetro regressivo.',
            'passos' => [
                ['icone' => 'fa-calendar-check', 'titulo' => 'Use o horário local configurado no painel', 'texto' => 'O valor respeita o fuso horário escolhido em Configurações → Contato e endereço.'],
                ['icone' => 'fa-bolt', 'titulo' => 'Aproveite os atalhos de duração', 'texto' => 'Os botões abaixo do campo preenchem automaticamente +30 min, +1h, +2h ou +4h a partir de agora, sem conta de cabeça.'],
                ['icone' => 'fa-hourglass-end', 'titulo' => 'O que ocorre quando o tempo zera', 'texto' => 'A tela 503 continua visível até você desligar manualmente a manutenção no topo, mas o cronômetro é recolhido automaticamente.'],
            ],
            'links' => [
                ['rotulo' => 'Ajustar fuso horário oficial', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_titulo' => [
            'onde' => 'Cabeçalho principal exibido em fonte grande no centro da página de manutenção 503.',
            'passos' => [
                ['icone' => 'fa-pen', 'titulo' => 'Prefira frases curtas e objetivas', 'texto' => 'Recomenda-se até 60 caracteres para manter o equilíbrio visual na tela cheia em computadores e celulares.'],
                ['icone' => 'fa-check-double', 'titulo' => 'Campo obrigatório', 'texto' => 'Caso fique sem texto personalizado, a tela recua automaticamente para o padrão cadastrado nas páginas de erro.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciar padrões de erro HTTP', 'rota' => 'admin.erros.index'],
            ],
        ],

        'manutencao_subtitulo' => [
            'onde' => 'Linha dourada em destaque logo abaixo do título na tela de manutenção.',
            'passos' => [
                ['icone' => 'fa-award', 'titulo' => 'Reforce a confiança operacional', 'texto' => 'Ideal para lembrar que o rastreamento por satélite e aplicativo segue ativo durante a atualização do portal web.'],
                ['icone' => 'fa-eraser', 'titulo' => 'Campo opcional', 'texto' => 'Se não preenchido, o texto fallback da página HTTP 503 entra no lugar.'],
            ],
            'links' => [
                ['rotulo' => 'Ver lista de páginas de erro', 'rota' => 'admin.erros.index'],
            ],
        ],

        'manutencao_mensagem' => [
            'onde' => 'Parágrafo explicativo que orienta o visitante sobre o motivo da pausa e os próximos passos.',
            'passos' => [
                ['icone' => 'fa-comment-dots', 'titulo' => 'Comunique com transparência', 'texto' => 'Explique brevemente o que está sendo aprimorado (segurança, velocidade ou novos recursos) em até 2000 caracteres.'],
                ['icone' => 'fa-life-ring', 'titulo' => 'Sinalize alternativas de contato', 'texto' => 'Combine este texto com o WhatsApp e o plantão ativos na aba Plantão & Redes Sociais para zerar a ansiedade do cliente.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir páginas de erro do sistema', 'rota' => 'admin.erros.index'],
            ],
        ],

        'manutencao_exibir_cronometro' => [
            'onde' => 'Interruptor que mostra ou oculta o relógio regressivo na tela 503.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Ative apenas quando houver prazo estimado', 'texto' => 'Exige que o campo Previsão de Término contenha data e horário no futuro.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Desligue em janelas sem prazo fechado', 'texto' => 'Em manutenções emergenciais sem previsão exata, desligar evita mostrar expectativa imprecisa aos clientes.'],
            ],
            'links' => [
                ['rotulo' => 'Ver configurações gerais do sistema', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_animacao' => [
            'onde' => 'Controla o cenário animado em Canvas atrás do cartão 503, com veículos circulando exclusivamente nas vias asfaltadas e satélite emitindo telemetria.',
            'passos' => [
                ['icone' => 'fa-car-side', 'titulo' => 'Decida sobre a malha viária animada', 'texto' => 'Ativa ou pausa os veículos com faróis e lanternas circulando na paleta escura e dourada da Golden.'],
                ['icone' => 'fa-satellite', 'titulo' => 'Combine com o satélite orbital', 'texto' => 'Ativa ondas de rádio e feixes conectando o satélite aos veículos da pista sem sobrecarregar celulares simples.'],
                ['icone' => 'fa-gauge-high', 'titulo' => 'Ajuste o ritmo do movimento', 'texto' => 'Escolha Suave para leitura tranquila, Padrão para 60 FPS equilibrados ou Dinâmica para telemetria ágil.'],
            ],
            'links' => [
                ['rotulo' => 'Configurar preloader e visual do painel', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_resumo_visual' => [
            'onde' => 'Checklist instantâneo dos 6 módulos visuais e de atendimento que estão ligados na tela 503 atual.',
            'passos' => [
                ['icone' => 'fa-list-check', 'titulo' => 'Audite antes de acionar ao vivo', 'texto' => 'Cada selo verde indica um recurso ativo após o último salvamento; selos neutros indicam módulos ocultos.'],
                ['icone' => 'fa-eye', 'titulo' => 'Valide na prévia real', 'texto' => 'Após salvar alterações nas abas, abra a demonstração em nova aba para confirmar o resultado final em tela cheia.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciar tela 503 nas páginas de erro', 'rota' => 'admin.erros.index'],
            ],
        ],

        'manutencao_exibir_animacao' => [
            'onde' => 'Liga ou desliga o fundo animado de tráfego viário na tela 503.',
            'passos' => [
                ['icone' => 'fa-road', 'titulo' => 'Veículos restritos às pistas', 'texto' => 'Os carros circulam apenas dentro das faixas asfaltadas das ruas traçadas no fundo, nunca invadindo calçadas ou texto.'],
                ['icone' => 'fa-mobile-screen', 'titulo' => 'Desligue para modo estático leve', 'texto' => 'Mantém apenas os gradientes escuros da marca quando preferir consumo mínimo de GPU no navegador do visitante.'],
            ],
            'links' => [
                ['rotulo' => 'Ver ajustes visuais em Configurações', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_exibir_satelite' => [
            'onde' => 'Controla o satélite em órbita e os feixes luminosos de telemetria sobre o mapa viário.',
            'passos' => [
                ['icone' => 'fa-satellite-dish', 'titulo' => 'Reforça a identidade de rastreamento', 'texto' => 'Exibe pulsos orbitais dourados e verdes simbolizando que a cobertura satelital continua operando 24h.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Funciona em conjunto com o Canvas', 'texto' => 'Para o satélite aparecer, certifique-se de que o fundo animado com carros também esteja ativado ou prefira manter ambos ligados.'],
            ],
            'links' => [
                ['rotulo' => 'Ver site público da Golden', 'rota' => 'site.home'],
            ],
        ],

        'manutencao_velocidade_animacao' => [
            'onde' => 'Define a velocidade de deslocamento dos veículos e a cadência dos feixes no fundo animado.',
            'passos' => [
                ['icone' => 'fa-feather', 'titulo' => 'Suave para discrição máxima', 'texto' => 'Deslocamento lento para não desviar atenção do comunicado central e do cronômetro.'],
                ['icone' => 'fa-gauge', 'titulo' => 'Padrão ou Dinâmica para ritmo vivo', 'texto' => 'Ideal para demonstrar monitoramento ativo constante enquanto o sistema atualiza.'],
            ],
            'links' => [
                ['rotulo' => 'Ajustar estilo de animações em Configurações', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_atendimento' => [
            'onde' => 'Agrupa o botão flutuante de WhatsApp de emergência e os contatos diretos de plantão exibidos durante a manutenção.',
            'passos' => [
                ['icone' => 'fa-comments', 'titulo' => 'Ative o botão de WhatsApp dedicado', 'texto' => 'Informe um número com DDI + DDD ou deixe vazio para herdar automaticamente o WhatsApp oficial cadastrado no sistema.'],
                ['icone' => 'fa-phone-volume', 'titulo' => 'Publique telefone e e-mail do plantão', 'texto' => 'Quando o interruptor de plantão está ligado, clientes veem o número 24h e o e-mail de suporte logo abaixo do comunicado.'],
                ['icone' => 'fa-paper-plane', 'titulo' => 'Teste o link do WhatsApp após salvar', 'texto' => 'Abra a prévia da tela 503 e confirme se a mensagem pré-formatada abre corretamente na conversa de suporte.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir WhatsApp oficial da empresa', 'rota' => 'admin.settings.index'],
                ['rotulo' => 'Plataforma WhatsApp Business', 'url' => 'https://business.whatsapp.com/'],
            ],
        ],

        'manutencao_exibir_whatsapp' => [
            'onde' => 'Exibe ou oculta o botão verde de WhatsApp na tela de manutenção 503.',
            'passos' => [
                ['icone' => 'fa-comments', 'titulo' => 'Canal prioritário em contingências', 'texto' => 'Evita que clientes com urgência veicular (roubo, furto ou bloqueio) fiquem sem atendimento enquanto o portal atualiza.'],
                ['icone' => 'fa-hashtag', 'titulo' => 'Verifique o número vinculado', 'texto' => 'Garanta que o campo Número de WhatsApp ou o contato padrão do site esteja preenchido sem símbolos extras.'],
            ],
            'links' => [
                ['rotulo' => 'Ver configurações de contato e WhatsApp', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_exibir_plantao' => [
            'onde' => 'Mostra os atalhos clicáveis de telefone 24h e e-mail técnico na base do cartão de manutenção.',
            'passos' => [
                ['icone' => 'fa-headset', 'titulo' => 'Ofereça alternativas além do WhatsApp', 'texto' => 'Útil para solicitações corporativas que dependem de ligação telefônica direta ou registro formal por e-mail.'],
                ['icone' => 'fa-envelope-open-text', 'titulo' => 'Fallback automático dos dados da loja', 'texto' => 'Se os campos desta tela ficarem vazios, o sistema exibe o telefone e e-mail oficiais da aba Contato e endereço.'],
            ],
            'links' => [
                ['rotulo' => 'Editar contato oficial em Configurações', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_whatsapp_numero' => [
            'onde' => 'Linha exclusiva para atender chamadas urgentes durante uma manutenção; opcional quando já existe WhatsApp oficial.',
            'passos' => [
                ['icone' => 'fa-hashtag', 'titulo' => 'Digite apenas dígitos com DDI + DDD', 'texto' => 'Para Brasil, inicie por `55` seguido do DDD e dos 9 dígitos (exemplo: `5519997101483`), sem traços, parênteses ou `+`.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Em branco, usa a linha oficial', 'texto' => 'Deixar este campo limpo reaproveita automaticamente o WhatsApp principal salvo em Configurações → Contato e endereço.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir WhatsApp oficial', 'rota' => 'admin.settings.index'],
                ['rotulo' => 'Documentação de links wa.me', 'url' => 'https://faq.whatsapp.com/5913398998672934'],
            ],
        ],

        'manutencao_whatsapp_mensagem' => [
            'onde' => 'Frase automática já digitada no campo de texto quando o visitante toca no botão do WhatsApp da tela 503.',
            'passos' => [
                ['icone' => 'fa-tag', 'titulo' => 'Identifique a origem no texto', 'texto' => 'Escreva uma abertura clara citando a manutenção para que o atendente identifique de imediato quem veio pela tela 503.'],
                ['icone' => 'fa-ruler-horizontal', 'titulo' => 'Mantenha até 255 caracteres', 'texto' => 'Mensagens curtas abrem instantaneamente no WhatsApp Web e no aplicativo sem truncar na URL.'],
            ],
            'links' => [
                ['rotulo' => 'Configurar mensagens dos atendentes do site', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_telefone' => [
            'onde' => 'Número exibido no bloco de plantão 24h da tela de manutenção.',
            'passos' => [
                ['icone' => 'fa-phone', 'titulo' => 'Formate para leitura fácil', 'texto' => 'Use o padrão `(DD) 9XXXX-XXXX` ou `0800` para o visitante copiar ou ligar pelo celular em um toque.'],
                ['icone' => 'fa-inbox', 'titulo' => 'Fallback automático', 'texto' => 'Se deixado em branco, busca o telefone configurado em Configurações → Contato e endereço.'],
            ],
            'links' => [
                ['rotulo' => 'Ver telefones oficiais', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_email' => [
            'onde' => 'Caixa de suporte exibida nos canais de plantão da tela 503.',
            'passos' => [
                ['icone' => 'fa-at', 'titulo' => 'Aponte para caixa monitorada no plantão', 'texto' => 'Prefira `suporte@` ou `plantao@` em vez de conta pessoal para garantir atendimento contínuo pela equipe.'],
                ['icone' => 'fa-envelope-circle-check', 'titulo' => 'Validação de formato', 'texto' => 'Informe um endereço de e-mail completo e válido; em branco, herda o e-mail comercial cadastrado em Configurações.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciar contas de e-mail da hospedagem', 'url' => 'https://{host}:2083/email'],
                ['rotulo' => 'Ver e-mail comercial em Configurações', 'rota' => 'admin.settings.index'],
            ],
        ],

        'manutencao_redes' => [
            'onde' => 'Links das redes sociais oficiais da Golden exibidos discretamente no rodapé da página 503.',
            'passos' => [
                ['icone' => 'fa-share-nodes', 'titulo' => 'Centralize avisos externos', 'texto' => 'Em manutenções mais longas, os visitantes costumam verificar comunicados oficiais no Instagram ou YouTube.'],
                ['icone' => 'fa-link', 'titulo' => 'Cole URLs completas com https://', 'texto' => 'Somente redes com link preenchido são desenhadas como ícone clicável na tela 503.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir Instagram', 'url' => 'https://www.instagram.com/'],
                ['rotulo' => 'Abrir Facebook', 'url' => 'https://www.facebook.com/'],
                ['rotulo' => 'Abrir YouTube', 'url' => 'https://www.youtube.com/'],
                ['rotulo' => 'Abrir LinkedIn', 'url' => 'https://www.linkedin.com/'],
            ],
        ],

        'manutencao_exibir_redes' => [
            'onde' => 'Ativa ou oculta toda a faixa de ícones das redes sociais no rodapé da tela 503.',
            'passos' => [
                ['icone' => 'fa-toggle-on', 'titulo' => 'Mantenha visível para comunicação institucional', 'texto' => 'Permite que o visitante acompanhe novidades da empresa sem perder o vínculo com a marca.'],
                ['icone' => 'fa-toggle-off', 'titulo' => 'Oculte durante manutenções breves', 'texto' => 'Deixa o cartão 503 mais enxuto quando o foco total precisa estar apenas no suporte emergencial.'],
            ],
            'links' => [
                ['rotulo' => 'Ver site público', 'rota' => 'site.home'],
            ],
        ],

        'manutencao_instagram' => [
            'onde' => 'Endereço completo do perfil oficial da Golden Rastreamento no Instagram.',
            'passos' => [
                ['icone' => 'fa-link', 'titulo' => 'Copie a URL completa do navegador', 'texto' => 'Exemplo: `https://instagram.com/goldenrastreamento`. Deixar em branco oculta apenas o ícone do Instagram.'],
            ],
            'links' => [
                ['rotulo' => 'Acessar Instagram', 'url' => 'https://www.instagram.com/'],
            ],
        ],

        'manutencao_facebook' => [
            'onde' => 'Endereço completo da página oficial da empresa no Facebook.',
            'passos' => [
                ['icone' => 'fa-link', 'titulo' => 'Informe o link começando por https://', 'texto' => 'Exemplo: `https://facebook.com/goldenrastreamento`. Em branco, não exibe o botão do Facebook.'],
            ],
            'links' => [
                ['rotulo' => 'Acessar Facebook', 'url' => 'https://www.facebook.com/'],
            ],
        ],

        'manutencao_youtube' => [
            'onde' => 'Endereço do canal oficial da Golden Rastreamento no YouTube.',
            'passos' => [
                ['icone' => 'fa-play', 'titulo' => 'Use o identificador oficial do canal', 'texto' => 'Exemplo: `https://youtube.com/@goldenrastreamento`. Vazio remove o ícone do YouTube da tela 503.'],
            ],
            'links' => [
                ['rotulo' => 'Acessar YouTube Studio', 'url' => 'https://studio.youtube.com/'],
            ],
        ],

        'manutencao_linkedin' => [
            'onde' => 'Endereço da página corporativa da Golden Rastreamento no LinkedIn.',
            'passos' => [
                ['icone' => 'fa-briefcase', 'titulo' => 'Preencha se usar canal corporativo', 'texto' => 'Exemplo: `https://linkedin.com/company/goldenrastreamento`. Em branco por padrão para não exibir ícone sem página ativa.'],
            ],
            'links' => [
                ['rotulo' => 'Acessar LinkedIn', 'url' => 'https://www.linkedin.com/'],
            ],
        ],

        'manutencao_ips' => [
            'onde' => 'Lista de endereços IP que atravessam o modo de manutenção automaticamente e navegam no site sem precisar de senha ou link secreto.',
            'passos' => [
                ['icone' => 'fa-network-wired', 'titulo' => 'Adicione seu próprio IP com um clique', 'texto' => 'Clique no botão Incluir meu IP atual para liberar a sua conexão na hora antes de ligar a manutenção.'],
                ['icone' => 'fa-list-ul', 'titulo' => 'Um endereço IP por linha', 'texto' => 'Você também pode listar o IP fixo do escritório, da central de monitoramento ou dos desenvolvedores, um por linha.'],
                ['icone' => 'fa-triangle-exclamation', 'titulo' => 'Atenção a conexões móveis dinâmicas', 'texto' => 'Em 4G/5G ou internet residencial o IP muda com frequência; nesses casos, prefira enviar o link de Bypass por chave secreta.'],
            ],
            'links' => [
                ['rotulo' => 'Descobrir meu endereço IP público', 'url' => 'https://whatismyipaddress.com/'],
                ['rotulo' => 'Ver Whitelist de segurança WAF', 'rota' => 'admin.seguranca-militar.index'],
            ],
        ],

        'manutencao_ips_permitidos' => [
            'onde' => 'Lista de endereços IPv4 ou IPv6 autorizados a ignorar completamente a barreira HTTP 503.',
            'passos' => [
                ['icone' => 'fa-keyboard', 'titulo' => 'Separe por quebra de linha ou vírgula', 'texto' => 'Cada IP listado é comparado em tempo real com `$request->ip()` assim que alguém acessa qualquer página pública.'],
                ['icone' => 'fa-user-plus', 'titulo' => 'Adicione seu IP detectado pelo botão rápido', 'texto' => 'Use o atalho logo abaixo do campo para evitar erro de digitação ao copiar seu endereço atual.'],
            ],
            'links' => [
                ['rotulo' => 'Conferir IP na ferramenta externa', 'url' => 'https://whatismyipaddress.com/'],
            ],
        ],

        'manutencao_bypass' => [
            'onde' => 'Chave secreta que libera o site inteiro para diretoria, equipe de QA ou terceiros através de uma URL especial que grava um cookie de 7 dias no navegador.',
            'passos' => [
                ['icone' => 'fa-copy', 'titulo' => 'Copie o Link com Chave Secreta', 'texto' => 'Use o botão Copiar ao lado do endereço `/?golden_bypass=...` para compartilhar com quem precisa homologar o site fechado.'],
                ['icone' => 'fa-cookie-bite', 'titulo' => 'Navegação contínua por 7 dias', 'texto' => 'Ao abrir o link uma única vez, o navegador recebe o cookie `golden_maintenance_bypass` e navega normalmente por todas as páginas.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Revogue e gere nova chave quando terminar', 'texto' => 'O botão Gerar Nova Chave Secreta invalida na hora qualquer link ou cookie antigo que tenha sido compartilhado.'],
            ],
            'links' => [
                ['rotulo' => 'Documentação do Google sobre HTTP 503', 'url' => 'https://developers.google.com/search/docs/crawling-indexing/http-network-errors'],
                ['rotulo' => 'Consultar auditoria de geração de chaves', 'rota' => 'admin.activities.index'],
            ],
            'nota' => 'Enquanto o bloqueio está ativo para visitantes, todas as páginas públicas retornam cabeçalho `HTTP 503 Service Unavailable` com `Retry-After: 300`, protegendo a indexação SEO no Google e Bing.',
        ],

        'manutencao_token_bypass' => [
            'onde' => 'Texto da chave secreta anexada ao parâmetro `?golden_bypass=` do endereço liberado.',
            'passos' => [
                ['icone' => 'fa-key', 'titulo' => 'Prefira chaves longas e aleatórias', 'texto' => 'Por padrão o sistema sorteia 32 caracteres aleatórios; só edite manualmente se precisar combinar uma senha específica com a equipe.'],
                ['icone' => 'fa-arrows-rotate', 'titulo' => 'Salvar atualiza o link na hora', 'texto' => 'Ao salvar este campo ou clicar em Gerar Nova Chave Secreta, os cookies que usavam o código anterior perdem validade imediatamente.'],
            ],
            'links' => [
                ['rotulo' => 'Boas práticas HTTP 503 (Google Search Central)', 'url' => 'https://developers.google.com/search/docs/crawling-indexing/http-network-errors'],
            ],
        ],

        'dashboard_grafico_leads' => [
            'onde' => 'Curva diária dos leads capturados nos últimos 30 dias pelos formulários do site, loja, simulador e campanhas rastreadas.',
            'passos' => [
                ['icone' => 'fa-chart-area', 'titulo' => 'Compare picos com suas campanhas', 'texto' => 'Os dias de maior volume indicam quando anúncios ou páginas de destino geraram mais contatos comerciais.'],
                ['icone' => 'fa-moon', 'titulo' => 'Adaptado ao tema claro e escuro', 'texto' => 'O gráfico acompanha automaticamente as cores da paleta Golden ao alternar o tema do painel no topo.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir lista completa de leads', 'rota' => 'admin.leads.index'],
                ['rotulo' => 'Gerenciar campanhas', 'rota' => 'admin.campanhas.index'],
            ],
        ],

        'dashboard_para_fazer' => [
            'onde' => 'Fila imediata das pendências operacionais que exigem retorno da equipe comercial, suporte ou expedição.',
            'passos' => [
                ['icone' => 'fa-user-plus', 'titulo' => 'Atenda leads novos rapidamente', 'texto' => 'Contatos em estado novo ainda não receberam o primeiro retorno do time de vendas.'],
                ['icone' => 'fa-headset', 'titulo' => 'Acompanhe chamados e conversas aguardando', 'texto' => 'Chamados abertos no portal e atendimentos do assistente transferidos para a equipe ficam destacados para resposta ágil.'],
                ['icone' => 'fa-box-open', 'titulo' => 'Despache pedidos e reponha estoque baixo', 'texto' => 'Pedidos em aberto aguardam confirmação ou postagem na loja, enquanto alertas de estoque indicam produtos no limite mínimo.'],
            ],
            'links' => [
                ['rotulo' => 'Ver leads', 'rota' => 'admin.leads.index'],
                ['rotulo' => 'Ver pedidos da loja', 'rota' => 'admin.pedidos.index'],
                ['rotulo' => 'Ver chamados de suporte', 'rota' => 'admin.chamados.index'],
                ['rotulo' => 'Ver conversas aguardando equipe', 'rota' => 'admin.assistente.conversas.index'],
            ],
        ],

        'dashboard_funil' => [
            'onde' => 'Distribuição do volume total de leads entre cada etapa comercial até a venda fechada ou perda.',
            'passos' => [
                ['icone' => 'fa-filter', 'titulo' => 'Identifique gargalos entre etapas', 'texto' => 'Se houver muitos leads parados em contatado ou proposta, priorize retornos para acelerar o fechamento.'],
                ['icone' => 'fa-check-double', 'titulo' => 'Atualize o andamento em cada ficha', 'texto' => 'Alterar a etapa na ficha do lead recalcula a taxa de conversão e a receita fechada no painel.'],
            ],
            'links' => [
                ['rotulo' => 'Filtrar leads por etapa', 'rota' => 'admin.leads.index'],
            ],
        ],

        'dashboard_leads_recentes' => [
            'onde' => 'Os oito últimos contatos registrados no sistema, mostrando nome, cidade, origem ou campanha, etapa atual e data.',
            'passos' => [
                ['icone' => 'fa-arrow-up-right-from-square', 'titulo' => 'Abra o contato direto da linha', 'texto' => 'Selecione o nome do lead para abrir a ficha completa, registrar anotações e converter em cliente.'],
                ['icone' => 'fa-bullhorn', 'titulo' => 'Confira a campanha de origem', 'texto' => 'A coluna de campanha mostra qual ação de marketing trouxe o interessado, ou marca direto quando veio sem código de rastreio.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir todos os leads', 'rota' => 'admin.leads.index'],
            ],
        ],

        'dashboard_pedidos_recentes' => [
            'onde' => 'Resumo dos últimos pedidos da loja virtual e despachos, com valor total, situação atual e vínculo com o rastreamento de entregas.',
            'passos' => [
                ['icone' => 'fa-truck-fast', 'titulo' => 'Acompanhe despachos e códigos de rastreio', 'texto' => 'Quando o pedido já possui código cadastrado, acesse o pedido no painel ou confira a linha do tempo na página pública de rastreamento.'],
                ['icone' => 'fa-receipt', 'titulo' => 'Atualize status e paradas de entrega', 'texto' => 'Clique no número do pedido para lançar pagamento, nota fiscal, código de envio e movimentações manuais ou automáticas.'],
            ],
            'links' => [
                ['rotulo' => 'Gerenciar todos os pedidos', 'rota' => 'admin.pedidos.index'],
                ['rotulo' => 'Abrir página pública de rastreamento', 'rota' => 'site.rastreio.index'],
            ],
        ],

        'dashboard_campanhas' => [
            'onde' => 'As oito campanhas mais produtivas em volume de leads acumulados nos últimos contatos.',
            'passos' => [
                ['icone' => 'fa-bullseye', 'titulo' => 'Compare campanhas ativas e pausadas', 'texto' => 'Cada barra mostra quantos leads chegaram por aquele código, facilitando o ajuste de orçamento nos canais mais eficientes.'],
                ['icone' => 'fa-sliders', 'titulo' => 'Gerencie links e status', 'texto' => 'Acesse o módulo de campanhas para ativar, pausar ou criar novos links de rastreamento por veículo ou canal de mídia.'],
            ],
            'links' => [
                ['rotulo' => 'Abrir campanhas', 'rota' => 'admin.campanhas.index'],
            ],
        ],

        'dashboard_modulos' => [
            'onde' => 'Visão consolidada dos módulos integrados do sistema — relacionamento, catálogo da loja, pedidos em trânsito, atendimento e conteúdo publicado.',
            'passos' => [
                ['icone' => 'fa-cubes', 'titulo' => 'Monitore a operação em um só lugar', 'texto' => 'Cada cartão resume os números ativos do módulo e abre a listagem correspondente ao ser selecionado.'],
                ['icone' => 'fa-gears', 'titulo' => 'Ajuste integrações e loja quando necessário', 'texto' => 'Administradores podem ligar ou desligar a loja, manutenção ou escudo militar nas respectivas telas de controle.'],
            ],
            'links' => [
                ['rotulo' => 'Ver clientes', 'rota' => 'admin.clientes.index'],
                ['rotulo' => 'Ver chamados abertos', 'rota' => 'admin.chamados.index'],
                ['rotulo' => 'Ver pedidos da loja', 'rota' => 'admin.pedidos.index'],
            ],
        ],
    ],
];
