<?php

/*
|--------------------------------------------------------------------------
| Segurança do acesso
|--------------------------------------------------------------------------
| Os números que decidem o quanto a porta de entrada aguenta. Nada aqui é escrito
| em código de controller: o login, a sessão e o dois fatores lêem desta folha, e
| cada linha tem o correspondente na tela Configurações → Chaves de integração →
| Segurança do acesso. O painel grava no banco e manda por cima do que está aqui;
| este arquivo é o plano de fundo que segura a instalação antes do primeiro
| `migrate` e depois de um campo apagado na tela.
|
| A razão de cada número é a mesma que fez a barra existir: numa hospedagem
| compartilhada, quem bate na porta de um painel de CRM não é gente — é script
| rodando lista de senhas vazadas, e ele experimenta dezenas por minuto. Cinco
| tentativas por quarto de hora derrubam esse script sem derrubar ninguém que
| errou o teclado duas vezes.
*/

return [

    // Tentativas de senha até a conta travar, e o tamanho da janela em que elas são
    // contadas. A contagem é por e-mail **e** por IP, então um escritório inteiro
    // atrás do mesmo IP não trava a conta de um colega, e um atacante trocando de
    // rede não zera o próprio contador.
    'tentativas' => (int) env('SEGURANCA_LOGIN_TENTATIVAS', 5),
    'janela_minutos' => (int) env('SEGURANCA_LOGIN_JANELA', 15),

    // Quanto a conta fica fechada depois de estourar a janela. Trinta minutos é o
    // suficiente para um script desistir e curto o bastante para quem digitou a
    // senha errada cinco vezes poder tentar de novo sem depender de suporte.
    'bloqueio_minutos' => (int) env('SEGURANCA_LOGIN_BLOQUEIO', 30),

    // Quem precisa de dois fatores além da senha: 'ninguem' deixa a porta como
    // estava, 'administradores' alcança só quem pode mexer na configuração, e
    // 'equipe' alcança todo perfil de painel. O cliente do portal nunca é alcançado
    // daqui — a política é da casa, não da vitrine.
    'dois_fatores' => env('SEGURANCA_DOIS_FATORES', 'ninguem'),

    // Minutos de inatividade antes de o painel pedir a senha de novo. O cookie de
    // sessão vive tanto quanto isto; a diferença é que aqui a contagem é renovada a
    // cada clique, e uma aba esquecida aberta num computador emprestado não é uma
    // sessão em uso.
    'sessao_minutos' => (int) env('SEGURANCA_SESSAO_MINUTOS', 120),

    // Quantas sessões de painel uma mesma pessoa pode ter abertas ao mesmo tempo.
    // As mais antigas caem fora deste limite — é o que faz "encerrar o notebook do
    // escritório" ser um clique em vez de uma espera. Zero deixa todas viverem.
    'sessoes_simultaneas' => (int) env('SEGURANCA_SESSOES_SIMULTANEAS', 5),

    // Janela de tolerância do TOTP, em períodos de 30 segundos. Três para cada lado
    // é o que salva o celular do atraso de relógio sem virar uma porta aberta: mais
    // que isso começa a aceitar códigos de dez minutos atrás.
    'totp_janela' => (int) env('SEGURANCA_TOTP_JANELA', 3),

    // Janela de validade do desafio de dois fatores. Passado este tempo, a senha
    // precisa ser digitada de novo — sem isto, o segundo fator vira uma formalidade
    // que se pula com uma aba velha aberta.
    'desafio_minutos' => (int) env('SEGURANCA_DESAFIO_MINUTOS', 10),
];
