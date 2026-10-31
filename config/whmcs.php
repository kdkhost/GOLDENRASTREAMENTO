<?php

/*
|--------------------------------------------------------------------------
| WHMCS — o financeiro e os serviços de cada cliente
|--------------------------------------------------------------------------
|
| A Golden usa o WHMCS como sistema financeiro e de assinaturas. Pela API dele, o portal do
| cliente passa a mostrar os serviços contratados e as faturas, e a espelhar a troca de senha e
| a correção dos dados cadastrais. Sem as três credenciais abaixo, a integração fica desligada
| e o portal avisa — o código não quebra e nenhuma tela some por falta de chave.
|
| A regra da casa vale aqui como em toda integração: o que está gravado no painel manda, e o
| `.env` só conduz enquanto ninguém preencheu Sistema → Configurações → Chaves de integração.
| O segredo é cifrado no banco e nunca volta para a tela.
|
| `url` é a raiz do WHMCS (`https://financeiro.seudominio.com.br`): a classe completa
| `/includes/api.php`. Se a instalação renomeou o arquivo da API, informe o endereço inteiro
| terminando em `.php`, que ele é usado como está.
|
| `veiculacao` é o outro lado da moeda das credenciais: com a porta aberta, o que sai dela para
| o cliente ver? Cada interruptor abaixo é uma decisão de vitrine, não de código — desligado, a
| tela do portal some do menu, a consulta deixa de ser feita e o espelho deixa de acontecer. Em
| branco na tela, o valor daqui conduz; gravado em Sistema → Configurações → Financeiro (WHMCS),
| o banco passa na frente.
*/

return [

    'url' => env('WHMCS_URL', ''),
    'identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'secret' => env('WHMCS_API_SECRET', ''),

    // Sem CPU sobrando na hospedagem, esperar o financeiro não pode segurar a página do
    // cliente por um minuto. Passado o prazo, a tela mostra o aviso de indisponível.
    'timeout' => (int) env('WHMCS_TIMEOUT', 12),

    // Quantas faturas e serviços a API devolve por consulta. A tela mostra o que veio.
    'limite' => (int) env('WHMCS_LIMITE', 50),

    /*
    | Os campos personalizados da ficha do financeiro, pelo número que a API devolve.
    |
    | O WHMCS não tem ação de API que liste o *nome* de um campo personalizado: `GetClientsDetails`
    | entrega `{id, value}` e nada mais. Quem sabe que o campo 2 é o CPF é a instalação, não a
    | documentação — por isso o mapa mora aqui, com os números da Golden, e pode ser corrigido na
    | tela de Configurações quando outro financeiro usar números diferentes.
    |
    | `plataforma` é o servidor de rastreamento ("servidor 3") e `usuario` é o login que o cliente
    | usa nele — os dois vêm do cadastro do WHMCS e são o que a ficha mostra como acesso.
    */
    'campos' => [
        'documento' => (int) env('WHMCS_CAMPO_DOCUMENTO', 2),
        'nascimento' => (int) env('WHMCS_CAMPO_NASCIMENTO', 10),
        'plataforma' => (int) env('WHMCS_CAMPO_PLATAFORMA', 17),
        'usuario' => (int) env('WHMCS_CAMPO_USUARIO', 18),
    ],

    /*
    | O que a conexão veicula no portal. `servicos` e `faturas` abrem ou fecham as duas telas do
    | cliente; `servicos_encerrados` e `faturas_pagas` decidem se o passado continua na vitrine;
    | `link_externo` controla se o cliente pode saltar para o financeiro a partir do portal;
    | `espelhar_dados` e `espelhar_senha` são as duas escritas que o portal faz lá; `grupos`,
    | quando preenchido, mostra só aqueles grupos de serviços — útil quando o mesmo WHMCS vende
    | mais do que a Golden quer que o cliente veja por aqui.
    |
    | `supervisao` não é vitrine do cliente, é a janela da equipe: ligado, o administrador abre a
    | conta de um cliente no portal, pelas mesmas telas e com os mesmos filtros que o cliente vê,
    | em modo somente leitura. `supervisao_minutos` é o prazo dessa janela — vencido, a sessão cai
    | e ninguém fica dentro da conta de outra pessoa por descuido.
    |
    | `segunda_via` é o financeiro saindo pelo chat: ligado, o assistente do site e da página de
    | funil pode enviar as faturas em aberto ao e-mail cadastrado da pessoa, depois de conferir os
    | dados de segurança da ficha (`config/assistente.php` → `verificacao`). Desligado, o assunto
    | volta a ser só o portal e o atendimento humano — nenhuma chamada é feita ao WHMCS pelo chat.
    */
    'veiculacao' => [
        'servicos' => (bool) env('WHMCS_VEICULA_SERVICOS', true),
        'faturas' => (bool) env('WHMCS_VEICULA_FATURAS', true),
        'servicos_encerrados' => (bool) env('WHMCS_VEICULA_SERVICOS_ENCERRADOS', false),
        'faturas_pagas' => (bool) env('WHMCS_VEICULA_FATURAS_PAGAS', true),
        'link_externo' => (bool) env('WHMCS_VEICULA_LINK_EXTERNO', true),
        'espelhar_dados' => (bool) env('WHMCS_VEICULA_ESPELHAR_DADOS', true),
        'espelhar_senha' => (bool) env('WHMCS_VEICULA_ESPELHAR_SENHA', true),
        'grupos' => env('WHMCS_VEICULA_GRUPOS', ''),
        'supervisao' => (bool) env('WHMCS_VEICULA_SUPERVISAO', true),
        'supervisao_minutos' => (int) env('WHMCS_VEICULA_SUPERVISAO_MINUTOS', 45),
        'segunda_via' => (bool) env('WHMCS_VEICULA_SEGUNDA_VIA', true),
    ],

    /*
    | Os estados que a API devolve, traduzidos uma vez só. A view pede o rótulo e o tom a
    | estes mapas — nenhum Blade escreve "Unpaid" nem escolhe cor por conta própria.
    */
    'faturas_status' => [
        'Paid' => ['rotulo' => 'Paga', 'tom' => 'green'],
        'Unpaid' => ['rotulo' => 'Em aberto', 'tom' => 'amber'],
        'Payment Pending' => ['rotulo' => 'Aguardando pagamento', 'tom' => 'gold'],
        'Cancelled' => ['rotulo' => 'Cancelada', 'tom' => 'neutral'],
        'Refunded' => ['rotulo' => 'Reembolsada', 'tom' => 'blue'],
        'Collections' => ['rotulo' => 'Em cobrança', 'tom' => 'red'],
    ],

    'servicos_status' => [
        'Active' => ['rotulo' => 'Ativo', 'tom' => 'green'],
        'Pending' => ['rotulo' => 'Pendente', 'tom' => 'amber'],
        'Suspended' => ['rotulo' => 'Suspenso', 'tom' => 'red'],
        'Terminated' => ['rotulo' => 'Encerrado', 'tom' => 'neutral'],
        'Cancelled' => ['rotulo' => 'Cancelado', 'tom' => 'neutral'],
        'Fraud' => ['rotulo' => 'Em análise', 'tom' => 'red'],
    ],

    // Domínios: o WHMCS usa os mesmos estados de serviço e mais os do registro em si.
    'dominios_status' => [
        'Active' => ['rotulo' => 'Ativo', 'tom' => 'green'],
        'Pending' => ['rotulo' => 'Pendente', 'tom' => 'amber'],
        'Suspended' => ['rotulo' => 'Suspenso', 'tom' => 'red'],
        'Cancelled' => ['rotulo' => 'Cancelado', 'tom' => 'neutral'],
        'Terminated' => ['rotulo' => 'Encerrado', 'tom' => 'neutral'],
        'Fraud' => ['rotulo' => 'Em análise', 'tom' => 'red'],
        'Grace' => ['rotulo' => 'Em período de graça', 'tom' => 'amber'],
        'Redemption' => ['rotulo' => 'Em redenção', 'tom' => 'red'],
        'Expired' => ['rotulo' => 'Expirado', 'tom' => 'red'],
        'Transferred Away' => ['rotulo' => 'Transferido', 'tom' => 'neutral'],
        'Pending Delete' => ['rotulo' => 'Aguardando exclusão', 'tom' => 'red'],
    ],

    // Como o domínio chegou à conta: registrado lá ou transferido de outro lugar.
    'dominios_tipos' => [
        'Register' => 'Registrado aqui',
        'Transfer' => 'Transferido',
        'Add FLD' => 'Domínio raiz novo',
    ],

    // O que cada linha de um pedido é. "Hosting" é o nome do WHMCS para o produto, qualquer que
    // seja ele — a Golden vende rastreador, não hospedagem de site.
    'itens_pedido' => [
        'Hosting' => 'Serviço',
        'Product' => 'Serviço',
        'Domain' => 'Domínio',
        'Addon' => 'Adicional',
        'Upgrade' => 'Melhoria',
    ],

    // As caixas de e-mail que um contato da conta pode receber. O WHMCS guarda o nome da permissão
    // numa lista separada por vírgulas; o que não estiver aqui aparece como veio.
    'permissoes_contato' => [
        'profile' => 'Dados da conta',
        'contacts' => 'Contatos',
        'products' => 'Produtos',
        'services' => 'Serviços',
        'domains' => 'Domínios',
        'invoices' => 'Faturas',
        'quotes' => 'Orçamentos',
        'tickets' => 'Chamados',
        'addfunds' => 'Adicionar fundos',
        'affiliates' => 'Afiliado',
        'massordering' => 'Pedidos em massa',
        'cancel' => 'Cancelar serviços',
        'login' => 'Acesso (subconta)',
        'order' => 'Fazer pedidos',
        'loginAsClient' => 'Entrar como o cliente',
    ],

    // Pedidos do financeiro. "Active" é o pedido concluído — o serviço nasceu dele.
    'pedidos_status' => [
        'Pending' => ['rotulo' => 'Pendente', 'tom' => 'amber'],
        'Active' => ['rotulo' => 'Concluído', 'tom' => 'green'],
        'Fraud' => ['rotulo' => 'Em análise', 'tom' => 'red'],
        'Cancelled' => ['rotulo' => 'Cancelado', 'tom' => 'neutral'],
        'Deleted' => ['rotulo' => 'Excluído', 'tom' => 'neutral'],
    ],

    // Os chamados do financeiro são um histórico separado do chamado do portal: estado e
    // prioridade vêm em inglês, e as instalações podem ter estados personalizados em português.
    'chamados_status' => [
        'Open' => ['rotulo' => 'Aberto', 'tom' => 'gold'],
        'Answered' => ['rotulo' => 'Respondido', 'tom' => 'blue'],
        'Customer-Reply' => ['rotulo' => 'Resposta do cliente', 'tom' => 'amber'],
        'In Progress' => ['rotulo' => 'Em atendimento', 'tom' => 'gold'],
        'On Hold' => ['rotulo' => 'Em espera', 'tom' => 'amber'],
        'Closed' => ['rotulo' => 'Fechado', 'tom' => 'neutral'],
        'Solved' => ['rotulo' => 'Resolvido', 'tom' => 'green'],
        'Fechado' => ['rotulo' => 'Fechado', 'tom' => 'neutral'],
        'Fechada' => ['rotulo' => 'Fechada', 'tom' => 'neutral'],
        'Fechadas' => ['rotulo' => 'Fechado', 'tom' => 'neutral'],
    ],

    'chamados_prioridade' => [
        'Low' => ['rotulo' => 'Baixa', 'tom' => 'neutral'],
        'Medium' => ['rotulo' => 'Média', 'tom' => 'blue'],
        'High' => ['rotulo' => 'Alta', 'tom' => 'amber'],
        'Urgent' => ['rotulo' => 'Urgente', 'tom' => 'red'],
        'Baixa' => ['rotulo' => 'Baixa', 'tom' => 'neutral'],
        'Média' => ['rotulo' => 'Média', 'tom' => 'blue'],
        'Alta' => ['rotulo' => 'Alta', 'tom' => 'amber'],
        'Urgente' => ['rotulo' => 'Urgente', 'tom' => 'red'],
    ],

    /*
    | Os meios de pagamento que o financeiro devolve como identificador técnico. O pedido já traz
    | o nome de exibição (`paymentmethodname`), mas a transação e o cadastro trazem só o código — e
    | código não é texto para a tela. O que não estiver aqui aparece como veio, entre parênteses,
    | porque inventar um nome para um meio desconhecido seria pior do que mostrar o código.
    */
    'formas_pagamento' => [
        'golden_inter_boleto' => 'Boleto (Banco Inter)',
        'bancointer_pix' => 'Pix (Banco Inter)',
        'banktransfer' => 'Pix (Bradesco)',
        'paghiper' => 'Boleto (PagHiper)',
        'iugucartao' => 'Cartão de crédito',
        'paypal' => 'PayPal',
        'stripe' => 'Cartão (Stripe)',
        'mercadopago' => 'Mercado Pago',
        'pagseguro' => 'PagSeguro',
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'creditcard' => 'Cartão de crédito',
    ],

    // Os orçamentos do financeiro. Esta instalação devolve a situação no campo `stage`, e ela
    // chega vazia quando o registro é antigo — a tela lê `status` ou `stage`, nesta ordem.
    'orcamentos_status' => [
        'Open' => ['rotulo' => 'Em aberto', 'tom' => 'gold'],
        'Pending' => ['rotulo' => 'Pendente', 'tom' => 'amber'],
        'Delivered' => ['rotulo' => 'Enviado', 'tom' => 'blue'],
        'Accepted' => ['rotulo' => 'Aceito', 'tom' => 'green'],
        'Declined' => ['rotulo' => 'Recusado', 'tom' => 'neutral'],
        'Deleted' => ['rotulo' => 'Excluído', 'tom' => 'neutral'],
        'On' => ['rotulo' => 'Em aberto', 'tom' => 'gold'],
    ],

    // Os departamentos de chamado têm o nome que a instalação der, e o WHMCS nasce com alguns em
    // inglês. O que não estiver aqui aparece como foi escrito lá.
    'departamentos' => [
        'General Enquiries' => 'Assuntos gerais',
        'General' => 'Geral',
        'Technical Support' => 'Suporte técnico',
        'Billing Questions' => 'Cobrança',
        'Pre-Sales Questions' => 'Dúvidas antes da compra',
        'Site Problems' => 'Problemas no site',
        'Financeiro' => 'Financeiro',
    ],

    // As caixas de e-mail da conta. O cadastro devolve as chaves técnicas; a tela mostra o nome
    // daqui. Uma preferência que a instalação criou e não está aqui aparece como veio.
    'preferencias_email' => [
        'general' => 'Geral',
        'product' => 'Serviços',
        'service' => 'Serviços',
        'domain' => 'Domínios',
        'invoice' => 'Faturas',
        'quote' => 'Orçamentos',
        'ticket' => 'Chamados',
        'affiliation' => 'Afiliado',
        'affiliate' => 'Afiliado',
        'cancel' => 'Cancelamentos',
        'fraud' => 'Análise de fraude',
        'renewals' => 'Renovações',
        'clienteditor' => 'Edição de cadastro',
        'promotions' => 'Promoções',
    ],

    // A situação da conta no financeiro — não é a do CRM nem a do serviço: é o cliente inteiro
    // habilitado, inativo ou encerrado lá.
    'cliente_status' => [
        'Active' => ['rotulo' => 'Ativo', 'tom' => 'green'],
        'Inactive' => ['rotulo' => 'Inativo', 'tom' => 'neutral'],
        'Closed' => ['rotulo' => 'Encerrado', 'tom' => 'neutral'],
        'Fraud' => ['rotulo' => 'Em análise', 'tom' => 'red'],
    ],

    // O idioma da conta no WHMCS, que também chega em inglês.
    'idiomas' => [
        'portuguese-br' => 'Português (Brasil)',
        'portuguese-pt' => 'Português (Portugal)',
        'english' => 'Inglês',
        'spanish' => 'Espanhol',
    ],

    // O nome do país que o WHMCS guarda em inglês. A lista cobre o que a instalação tem; país que
    // não estiver aqui aparece como o financeiro escreveu — nunca vira linha em branco.
    'paises' => [
        'Brazil' => 'Brasil',
        'Portugal' => 'Portugal',
        'Argentina' => 'Argentina',
        'Paraguay' => 'Paraguai',
        'Uruguay' => 'Uruguai',
        'Chile' => 'Chile',
        'Bolivia' => 'Bolívia',
        'Peru' => 'Peru',
        'Ecuador' => 'Equador',
        'Colombia' => 'Colômbia',
        'Venezuela' => 'Venezuela',
        'Mexico' => 'México',
        'United States' => 'Estados Unidos',
        'United Kingdom' => 'Reino Unido',
        'Canada' => 'Canadá',
        'Germany' => 'Alemanha',
        'Spain' => 'Espanha',
        'France' => 'França',
        'Italy' => 'Itália',
        'Ireland' => 'Irlanda',
        'Netherlands' => 'Holanda',
        'Sweden' => 'Suécia',
        'Norway' => 'Noruega',
        'China' => 'China',
        'Japan' => 'Japão',
    ],

    // A periodicidade de cobrança que o WHMCS devolve em inglês, na língua da tela.
    'ciclos' => [
        'Monthly' => 'por mês',
        'Quarterly' => 'por trimestre',
        'Semi-Annually' => 'por semestre',
        'Annually' => 'por ano',
        'Biennially' => 'a cada 2 anos',
        'Triennially' => 'a cada 3 anos',
        'One Time' => 'pagamento único',
        'Free Account' => 'sem mensalidade',
    ],

];
