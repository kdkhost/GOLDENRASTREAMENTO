<?php

/*
|--------------------------------------------------------------------------
| O que a Golden ensina ao assistente de fábrica
|--------------------------------------------------------------------------
|
| A base que o comando `assistente:ensinar` (e o botão "Ensinar o básico" da tela
| de conhecimento) grava em `conhecimento_itens`. É texto de procedência interna:
| nada aqui é inventado — cada linha sai do que o site já diz, do catálogo real e
| dos endereços que o painel controla.
|
| Os marcadores entre dois-pontos são preenchidos na hora de semear, sempre com o
| valor vigente do painel (`:whatsapp`, `:email`, `:servidor_1`…). Assim o texto
| não envelhece quando o número, o endereço ou o domínio mudam: o comando é
| rodado de novo e a base acompanha.
|
| Regra de ouro: preço, prazo, cobertura e especificação só entram quando existe
| fonte. O que depende de proposta fica dito como proposta, nunca como tabela.
*/

return [

    'itens' => [

        [
            'referencia' => 'golden:quem-somos',
            'titulo' => 'Quem é a Golden Rastreamento',
            'tags' => 'empresa golden quem somos cidade três pontas atendimento história',
            'corpo' => 'A Golden Rastreamento é uma empresa de Três Pontas, Minas Gerais, especializada em rastreamento e monitoramento veicular. Trabalha com rastreadores J16 4G/2G, chips M2M, acessórios de instalação e planos de monitoramento com plataforma web e aplicativo. Atende motoristas de aplicativo, veículos particulares, frotas de empresa, locadoras e operações rurais. O atendimento humano fica no WhatsApp :whatsapp e no e-mail :email. Telefones: :telefones.',
        ],

        [
            'referencia' => 'golden:o-que-a-ana-resolve',
            'titulo' => 'O que a assistente Ana resolve',
            'tags' => 'ajuda assuntos atendimento vendas serviços produtos dúvidas o que você faz',
            'corpo' => 'A Ana ajuda a entender e contratar os serviços da Golden. Ela explica como funciona cada rastreador, qual equipamento combina com cada uso, como é a instalação, como funciona o bloqueio remoto, a cobertura do chip M2M, os planos e a mensalidade. Também mostra como acessar a plataforma de rastreamento (Servidor 1, Servidor 2 e aplicativo) e o portal do cliente, onde ficam pedidos, chamados, contratos, serviços e faturas. Assuntos financeiros ela orienta pelo portal e pelo canal de cobrança. O que ela não faz: fechar proposta sozinha, prometer desconto, alterar conta ou pedir documento, senha ou dados de pagamento pelo chat.',
        ],

        [
            'referencia' => 'golden:rastreador-j16',
            'titulo' => 'Como funciona o rastreador J16 4G/2G',
            'tags' => 'rastreador j16 equipamento 4g 2g localização tempo real cerca virtual alerta aplicativo plataforma bloqueio instalação',
            'corpo' => 'O rastreador J16 4G/2G é o equipamento principal da Golden, homologado pela Anatel. Ele envia a posição do veículo pela rede de celular 4G/2G para a plataforma, onde o cliente acompanha em tempo real pelo navegador ou pelo aplicativo. Com ele dá para ver o trajeto, criar cercas virtuais e receber alertas. O bloqueio remoto é um recurso à parte: só funciona quando o rastreador é instalado com o chicote e o relé adequados. O J16 atende a maioria dos veículos leves, motos e linha pesada; a compatibilidade com o seu veículo é confirmada no atendimento. A instalação é feita por um profissional de confiança do cliente, contratado e pago diretamente a ele.',
        ],

        [
            'referencia' => 'golden:rastreador-moto',
            'titulo' => 'Rastreador para moto J16',
            'tags' => 'moto motocicleta rastreador j16 vibração resistente equipamento',
            'corpo' => 'Para motocicletas a Golden tem a versão do rastreador J16 para moto: compacta e resistente à vibração e às intempéries, com a mesma rede 4G/2G e o mesmo acompanhamento pela plataforma. A instalação também é feita por um profissional de confiança do cliente, e a compatibilidade com o modelo da moto é confirmada no atendimento.',
        ],

        [
            'referencia' => 'golden:instalacao',
            'titulo' => 'Como funciona a instalação do rastreador',
            'tags' => 'instalação instalar instalam aparelho equipamento chicote relé profissional confiança contratar quem instala',
            'corpo' => 'A Golden não faz a instalação: ela é contratada e paga pelo cliente diretamente ao profissional de confiança dele. O kit chicote de instalação acompanha o equipamento, e o relé é o que habilita o bloqueio remoto. Vale instalar com quem trabalha com elétrica automotiva e conferir, na hora, se o rastreador está enviando posição para a plataforma. Se o instalador tiver dúvida técnica, a equipe da Golden orienta pelo WhatsApp :whatsapp.',
        ],

        [
            'referencia' => 'golden:bloqueio-remoto',
            'titulo' => 'Bloqueio remoto: bloquear o veículo pelo celular',
            'tags' => 'bloqueio bloquear remoto celular aplicativo relé chicote roubo furto roubado emergência parar veículo',
            'corpo' => 'O bloqueio remoto é um recurso compatível com o rastreador quando ele é instalado com o chicote e o relé adequados. Sem esse conjunto, o equipamento rastreia e monitora, mas não bloqueia. O bloqueio é acionado pela plataforma, e quem precisa de bloqueio imediato em caso de roubo ou furto deve falar com o canal de emergência da Golden no WhatsApp :whatsapp para a equipe orientar o procedimento.',
        ],

        [
            'referencia' => 'golden:cobertura',
            'titulo' => 'Cobertura do chip M2M',
            'tags' => 'cobertura chip m2m sinal 4g 2g vivo claro tim algar interior cidade região',
            'corpo' => 'O chip M2M usa a cobertura das operadoras nacionais com rede 4G/2G, conforme a região de uso. A Golden trabalha com chips Vivo, Claro, TIM e Algar; a Algar costuma atender bem o interior de Minas Gerais e região. A cobertura exata no endereço onde o veículo circula é confirmada no atendimento, porque varia de cidade para cidade e até dentro do mesmo município.',
        ],

        [
            'referencia' => 'golden:mensalidade-e-planos',
            'titulo' => 'Mensalidade, conectividade e planos',
            'tags' => 'mensalidade plano preço valor quanto custa recorrência conectividade m2m plano frota por veículo',
            'corpo' => 'O rastreamento tem cobrança recorrente: a conectividade M2M do chip e o acesso à plataforma. Existe o Plano Frota, cobrado por veículo e por mês, com plataforma web, aplicativo, cercas virtuais e relatórios. Os valores vigentes são informados na proposta e aparecem na loja do site quando o produto está publicado. Quem já é cliente vê as faturas e a situação de cada serviço no portal do cliente. A Ana não fecha desconto nem altera valor: isso é com o time comercial.',
        ],

        [
            'referencia' => 'golden:acessar-a-plataforma',
            'titulo' => 'Como acessar a plataforma de rastreamento',
            'tags' => 'acessar plataforma login entrar servidor 1 servidor 2 aplicativo app senha rastreamento rastrear rastreio localizar veículo carro moto caminhão',
            'corpo' => 'A plataforma de rastreamento abre pelo navegador e pelo aplicativo, com o login entregue na ativação do serviço. A Golden tem dois servidores, porque são duas plataformas diferentes: Servidor 1 em :servidor_1 e Servidor 2 em :servidor_2. O cliente entra no servidor onde a instalação dele foi cadastrada — quem não lembra qual é, fala com a equipe pelo WhatsApp :whatsapp e recebe o endereço certo. No site, o botão "Rastreamento" no topo abre as duas portas. A senha da plataforma é a mesma do servidor em que o veículo está, e a troca é feita dentro dela ou com a equipe.',
        ],

        [
            'referencia' => 'golden:portal-do-cliente',
            'titulo' => 'Portal do cliente: o que tem dentro',
            'tags' => 'portal cliente área login entrar cadastro senha trocar senha esqueci senha pedidos chamados contratos dados serviços faturas financeiro',
            'corpo' => 'O portal do cliente fica em :portal. Com e-mail e senha, o cliente acompanha tudo o que é dele: pedidos da loja, chamados de atendimento, contratos, serviços e produtos contratados, faturas e dados cadastrais. No portal dá para trocar a senha e corrigir telefone, endereço, cidade e CEP. Quem ainda não tem conta cria no próprio portal, e quem esqueceu a senha recupera pelo link "esqueci minha senha", que chega no e-mail de acesso. CPF/CNPJ e o e-mail de login são alterados pela equipe, porque são a chave que liga pedidos, contratos e faturas à conta.',
        ],

        [
            'referencia' => 'golden:faturas-e-financeiro',
            'titulo' => 'Faturas, pagamento e financeiro',
            'tags' => 'fatura faturas faturamento financeiro boleto pix pagar pagamento vencimento segunda via atraso cobrança mensalidade onde vejo minhas faturas',
            'corpo' => 'As faturas dos serviços aparecem no portal do cliente (:portal), na área de faturas, com vencimento, valor e situação de cada uma. A fatura aberta leva ao financeiro para pagamento e para a segunda via. O sistema financeiro antigo continua no ar em :financeiro para quem já usava esse endereço. Se uma fatura não aparecer, ou se o pagamento não baixar, a equipe de cobrança resolve pelo WhatsApp :whatsapp. A Ana não vê fatura pelo chat nem recebe pagamento: ela orienta o caminho.',
        ],

        [
            'referencia' => 'golden:como-comprar',
            'titulo' => 'Como comprar o rastreador e o plano',
            'tags' => 'comprar compra loja produto rastreador plano orçamento proposta vendedor comercial contratar',
            'corpo' => 'Dá para comprar pelo site, na loja, quando a vitrine está publicada, ou falar direto com o time comercial pelo WhatsApp :whatsapp. O caminho é simples: entender o uso do veículo, escolher o equipamento e o plano, receber a proposta com os valores vigentes e combinar a instalação com o profissional de confiança do cliente. Para frota, locadora ou operação rural, o comercial monta a proposta por quantidade de veículos. O e-mail comercial é :email.',
        ],

        [
            'referencia' => 'golden:rastreador-sem-sinal',
            'titulo' => 'Rastreador sem sinal, parado ou sem atualizar',
            'tags' => 'rastreador parou funcionar sem sinal offline não atualiza posição problema defeito suporte técnico aplicativo plataforma',
            'corpo' => 'Quando o rastreador para de atualizar a posição, o caminho é conferir a instalação e a alimentação do aparelho, ver se o chip M2M está ativo e se o veículo está em área com sinal 4G/2G. Se continuar sem atualizar, o suporte técnico da Golden verifica a conexão do equipamento pelo WhatsApp :whatsapp. O time consegue ver se o aparelho está enviando dados e orienta o próximo passo; a Ana não acessa a instalação do cliente pelo chat.',
        ],

        [
            'referencia' => 'golden:roubo-ou-furto',
            'titulo' => 'Roubo ou furto: veículo roubado, o que fazer',
            'tags' => 'roubo furto roubado furtado veículo carro moto sumido desapareceu emergência polícia boletim bloqueio rastrear localizar',
            'corpo' => 'Em caso de roubo, furto ou veículo sumido, o primeiro passo é registrar o boletim de ocorrência e avisar a polícia. Em seguida, fale com o canal de emergência da Golden pelo WhatsApp :whatsapp: a equipe consulta o rastreamento, passa a localização para as autoridades e orienta o bloqueio quando a instalação tem o chicote e o relé adequados. Não tente recuperar o veículo por conta própria. Se o veículo estiver em movimento, a plataforma mostra a posição em tempo real para quem tem o acesso.',
        ],

        [
            'referencia' => 'golden:suporte-e-emergencia',
            'titulo' => 'Suporte, emergência e atendimento',
            'tags' => 'suporte emergência atendimento contato whatsapp telefone horário roubo furto roubado problema defeito falar pessoa humano atendente equipe',
            'corpo' => 'O atendimento da Golden é multicanal e sai do mesmo WhatsApp :whatsapp: emergência e central, comercial e novos planos, suporte técnico e aplicativo, e financeiro. No site, o botão flutuante abre a caixa com todos os canais e a mensagem já pronta. Em caso de roubo, furto ou veículo sumido, procure o canal de emergência e a polícia; a equipe orienta o rastreamento e o bloqueio quando a instalação permite. O e-mail de contato é :email.',
        ],

        [
            'referencia' => 'golden:garantia-e-contrato',
            'titulo' => 'Garantia, contrato e cancelamento',
            'tags' => 'garantia contrato cancelamento adesão prazo defeito troca documento',
            'corpo' => 'As condições de garantia do equipamento, o prazo e as regras de cancelamento estão no contrato de adesão e na proposta assinada com o cliente. O contrato fica disponível no portal do cliente, na área de contratos, e a equipe confirma cada condição pelo WhatsApp :whatsapp. A Ana não promete prazo de garantia nem autoriza troca, cancelamento ou desconto por conta própria: ela explica o caminho e chama a equipe.',
        ],

        [
            'referencia' => 'golden:planos-e-valores',
            'titulo' => 'Quanto custa: equipamentos, chip e mensalidade',
            'tags' => 'preço preços valor valores quanto custa custa mensalidade plano planos frota rastreador carro moto chip m2m kit chicote relé botão pânico orçamento',
            'corpo' => 'Valores publicados hoje na loja da Golden: rastreador veicular J16 4G/2G R$ 299,00; rastreador para moto J16 R$ 279,00; kit chicote de instalação R$ 35,00; relé de bloqueio R$ 59,00; botão de pânico R$ 49,00; chip M2M (Vivo, Claro, TIM ou Algar) R$ 39,00, com mensalidade de R$ 29,90; Plano Frota R$ 59,90 por veículo ao mês, com plataforma web, aplicativo, cercas virtuais e relatórios. A instalação é contratada e paga à parte, direto ao profissional de confiança do cliente. A proposta e a fatura confirmam o valor vigente; a Ana não fecha desconto nem altera preço.',
        ],

        [
            'referencia' => 'golden:conjunto-para-funcionar',
            'titulo' => 'O que é preciso para o rastreamento funcionar',
            'tags' => 'conjunto preciso preciso comprar equipamento rastreador chip m2m plano instalação chicote relé como funciona o serviço',
            'corpo' => 'Para o rastreamento funcionar, o conjunto é: rastreador, chip M2M, plano de monitoramento e instalação. O rastreador é o aparelho que fica no veículo; o chip M2M é a conexão dele com a operadora; o plano mantém a plataforma e o acompanhamento; o kit chicote liga o equipamento ao veículo, e o relé é o que habilita o bloqueio remoto. Na loja os itens aparecem separados, e a proposta indica o conjunto certo para o seu caso.',
        ],

        [
            'referencia' => 'golden:app-e-plataforma-recursos',
            'titulo' => 'O que a plataforma e o aplicativo mostram',
            'tags' => 'aplicativo app plataforma recursos funções funciona localização tempo real histórico trajetos paradas quilometragem velocidade relatórios cerca virtual alertas manutenção',
            'corpo' => 'Pela plataforma web e pelo aplicativo o cliente vê a localização do veículo em tempo real, o histórico de trajetos, as paradas, a quilometragem e a velocidade. Também configura cercas virtuais, recebe alertas e consulta relatórios. O bloqueio remoto aparece quando a instalação tem o chicote e o relé adequados. Há ainda a gestão de manutenções por data ou quilometragem. O login é entregue na ativação, e o cliente entra no Servidor 1 ou no Servidor 2 conforme onde o veículo foi cadastrado.',
        ],

        [
            'referencia' => 'golden:cercas-virtuais-e-alertas',
            'titulo' => 'Cercas virtuais e alertas',
            'tags' => 'cerca virtual cercas virtuais alerta alertas entrada saída área mapa velocidade parada aviso',
            'corpo' => 'Cerca virtual é uma área desenhada no mapa — casa, empresa, escola, obra ou uma região inteira. Quando o veículo entra ou sai dela, a plataforma e o aplicativo avisam. Também é possível acompanhar excesso de velocidade e paradas. Quem configura é o próprio cliente, dentro da plataforma, com o login do serviço.',
        ],

        [
            'referencia' => 'golden:frota-locadora-e-rural',
            'titulo' => 'Frota, locadora e operação rural',
            'tags' => 'frota frotas empresa locadora locadoras rural fazenda agrícola muitos veículos gestão quantidade',
            'corpo' => 'A Golden atende veículos particulares, motoristas de aplicativo, frotas de empresa, locadoras e operações rurais. Para frota, o Plano Frota é cobrado por veículo e por mês (R$ 59,90 na loja), e a plataforma permite separar os veículos, ver relatórios e acompanhar o uso de cada um. Para quantidade maior, o time comercial monta a proposta. A compatibilidade de cada veículo com o J16 é confirmada no atendimento.',
        ],

        [
            'referencia' => 'golden:formas-de-pagamento',
            'titulo' => 'Formas de pagamento da loja',
            'tags' => 'pagamento pagar pago pix cartão cartao boleto bancário boleto comprar loja checkout cobrança mensalidade',
            'corpo' => 'Na loja, o checkout oferece Pix — a chave e o QR Code são enviados pelo WhatsApp depois de confirmar o pedido —, cartão e boleto bancário. Nada é cobrado no momento do pedido: a Golden confirma o pagamento e a entrega antes de emitir a cobrança. A mensalidade é recorrente e aparece no portal do cliente. A Ana não recebe pagamento nem pede número de cartão, senha ou código pelo chat.',
        ],

        [
            'referencia' => 'golden:endereco-e-canais',
            'titulo' => 'Endereço, telefones e canais de atendimento',
            'tags' => 'endereço endereco onde fica loja telefone telefones whatsapp email contato central 24h canais atendimento',
            'corpo' => 'A Golden fica na R. Pref. Olinto Réis Campos, 104, Centro, Três Pontas - MG, CEP 37185-016. Telefones: :telefones. WhatsApp: :whatsapp. E-mail: :email. A caixa de atendimento do site separa os assuntos: Emergência & Central 24h (roubo, furto, sinistro e bloqueio), Comercial & Novos Planos, Suporte Técnico & Aplicativo e Faturamento & Mensalidade.',
        ],

        [
            'referencia' => 'golden:depois-da-compra',
            'titulo' => 'O que acontece depois da compra',
            'tags' => 'depois da compra entrega instalador ativação ativacao login primeiro acesso testar veículo no mapa próximos passos',
            'corpo' => 'Depois da compra, o kit é entregue e a instalação é feita pelo profissional de confiança do cliente. A Golden ativa o chip M2M e o acesso, e o login do rastreamento chega ao cliente. O primeiro teste é abrir a plataforma ou o aplicativo e conferir o veículo no mapa; depois é só cadastrar cercas e alertas. Se o veículo não aparecer, o suporte técnico confere a conexão do equipamento.',
        ],

        [
            'referencia' => 'golden:privacidade-e-dados',
            'titulo' => 'Privacidade e proteção de dados',
            'tags' => 'privacidade lgpd dados pessoais segurança senha cpf cartão localização política',
            'corpo' => 'A Golden trata os dados pessoais conforme a LGPD e a Política de Privacidade publicada no site. A localização do veículo é usada para prestar o serviço de rastreamento. A Ana não pede CPF, senha, código de acesso nem dados de cartão pelo chat; a conferência de cadastro é feita em etapa própria do sistema. Quem quiser falar sobre os próprios dados pode procurar a equipe pelo WhatsApp :whatsapp.',
        ],

        [
            'referencia' => 'golden:segunda-via-e-pagamento',
            'titulo' => 'Segunda via e pagamento que não baixou',
            'tags' => 'segunda via fatura boleto pagamento não baixou comprovante atraso vencimento financeiro',
            'corpo' => 'A segunda via da fatura fica no portal do cliente (:portal), na área de faturas, com vencimento, valor e situação. Se o pagamento não baixar ou a fatura não aparecer, fale com o Faturamento & Mensalidade pelo WhatsApp :whatsapp e envie o comprovante. A Ana não altera status de pagamento nem emite segunda via pelo chat.',
        ],

        [
            'referencia' => 'golden:compatibilidade-de-veiculos',
            'titulo' => 'Em quais veículos o rastreador funciona',
            'tags' => 'compatibilidade compatível veículo carro moto caminhão linha pesada máquina rural funciona qualquer veículo',
            'corpo' => 'O J16 4G/2G é o equipamento principal e atende a maioria dos veículos leves e da linha pesada; para moto existe a versão específica. A Golden também atende operações rurais. Como cada veículo tem particularidades de instalação, a compatibilidade é confirmada no atendimento antes da compra.',
        ],

        [
            'referencia' => 'golden:manutencao-do-rastreador',
            'titulo' => 'Cuidados e manutenção do rastreador',
            'tags' => 'manutenção manutencao cuidado limpeza defeito quebrou parou alimentação fusível revisão preventiva',
            'corpo' => 'O rastreador não exige manutenção do cliente no dia a dia: basta manter o chip ativo e a mensalidade em dia. A plataforma permite cadastrar a manutenção preventiva do veículo por data ou quilometragem. Se o aparelho parar de atualizar, confira a alimentação e o sinal e fale com o suporte técnico. Não é preciso mexer no equipamento por conta própria.',
        ],

        [
            'referencia' => 'golden:perguntas-frequentes',
            'titulo' => 'Perguntas frequentes sobre o serviço',
            'tags' => 'perguntas frequentes dúvidas rápidas qualquer veículo instalação mensalidade bloqueio cobertura celular aplicativo resumo',
            'corpo' => 'Perguntas frequentes: o rastreador funciona em qualquer veículo? O J16 4G/2G atende a maioria dos veículos leves, motos e linha pesada, e a compatibilidade é confirmada no atendimento. A instalação é feita pela Golden? Não: é contratada e paga pelo cliente diretamente ao profissional de confiança dele. Preciso pagar mensalidade? Sim: a conectividade M2M e o acesso à plataforma são recorrentes. Como funciona o bloqueio remoto? Só quando a instalação tem o chicote e o relé adequados. Qual a cobertura do chip? A das operadoras nacionais em 4G/2G, conforme a região. Consigo acessar pelo celular? Sim, pela plataforma web e pelo aplicativo.',
        ],

    ],

];
