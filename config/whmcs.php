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
