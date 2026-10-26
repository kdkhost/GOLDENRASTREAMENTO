<?php

/*
|--------------------------------------------------------------------------
| Central de crons
|--------------------------------------------------------------------------
| A hospedagem tem uma linha só de crontab, e ela não muda:
| `* * * * * artisan schedule:run`. Tudo o que roda depois disso vive na tabela
| `cron_tarefas` e se edita em Sistema → Crons. Este arquivo é a régua dessa
| agenda: as presets que a tela oferece, o teto do histórico e a lista curta de
| comandos que ninguém tem o que fazer rodando por aqui.
|
| Nada de agendamento fica escrito em código a partir de agora — exatamente como
| pede a regra da casa: o painel manda, o banco guarda, o ambiente é plano de
| fundo quando o banco está vazio.
*/

return [

    /*
    | As frequências que a tela oferece, no lugar de deixar a pessoa escrever
    | uma expressão cron de cabeça. `expressao` é um modelo: {h} é a hora, {m} o
    | minuto e {dow} o dia da semana, todos resolvidos por
    | CronTarefa::resolverExpressao(). Guardar o modelo e não o texto pronto é o
    | que permite mudar uma preset daqui a dois anos sem reescrever as linhas do
    | banco.
    |
    | `tolerancia` é o tempo máximo, em segundos, que uma tarefa dessa preset
    | pode ficar sem registrar execução antes de virar aviso no sino. Ela é o
    | dobro da janela, com folga: um worker que demora não é uma agenda morta, e
    | alarme falso é o jeito certo de fazer a equipe parar de ler alarme.
    */
    'agendas' => [
        'everyMinute' => [
            'rotulo' => 'A cada minuto',
            'expressao' => '* * * * *',
            'hora' => null,
            'dia' => false,
            'tolerancia' => 600,
        ],
        'everyFiveMinutes' => [
            'rotulo' => 'A cada cinco minutos',
            'expressao' => '*/5 * * * *',
            'hora' => null,
            'dia' => false,
            'tolerancia' => 1800,
        ],
        'everyTenMinutes' => [
            'rotulo' => 'A cada dez minutos',
            'expressao' => '*/10 * * * *',
            'hora' => null,
            'dia' => false,
            'tolerancia' => 3600,
        ],
        'everyFifteenMinutes' => [
            'rotulo' => 'A cada quinze minutos',
            'expressao' => '*/15 * * * *',
            'hora' => null,
            'dia' => false,
            'tolerancia' => 5400,
        ],
        'everyThirtyMinutes' => [
            'rotulo' => 'A cada meia hora',
            'expressao' => '*/30 * * * *',
            'hora' => null,
            'dia' => false,
            'tolerancia' => 7200,
        ],
        // 'hora' => 'minuto': da preset horária só interessa o minuto, porque a
        // hora se repete sozinha a cada volta do relógio.
        'hourly' => [
            'rotulo' => 'Uma vez por hora',
            'expressao' => '{m} * * * *',
            'hora' => 'minuto',
            'dia' => false,
            'tolerancia' => 10800,
        ],
        'daily' => [
            'rotulo' => 'Todos os dias',
            'expressao' => '{m} {h} * * *',
            'hora' => 'ambas',
            'dia' => false,
            'tolerancia' => 172800,
        ],
        'weekly' => [
            'rotulo' => 'Toda semana',
            'expressao' => '{m} {h} * * {dow}',
            'hora' => 'ambas',
            'dia' => true,
            'tolerancia' => 691200,
        ],
        'monthly' => [
            'rotulo' => 'Todo mês, no dia 1',
            'expressao' => '{m} {h} 1 * *',
            'hora' => 'ambas',
            'dia' => false,
            'tolerancia' => 2678400,
        ],
    ],

    // O dia da semana no padrão do cron: 0 é domingo. A mesma numeração do banco,
    // do select da tela e do `weeklyOn` que o agendador já usava no código.
    'dias_semana' => [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ],

    // A hora que a tarefa assume quando a preset pede hora e a pessoa não preencheu.
    // Madrugada, de propósito: tarefa agendada sem hora é tarefa de manutenção.
    'hora_padrao' => '03:00',

    // Quantas execuções a ficha mostra e quantas a poda de histórico preserva. O
    // histórico cresce a cada batimento — um cron de minuto faz 1.440 linhas por dia
    // por tarefa — e sem teto a tabela vira o problema que o cron existia para resolver.
    'historico_por_tarefa' => 500,
    'historico_na_ficha' => 20,

    // A saída é cortada antes de entrar no banco. Um `queue:work` falando por cinquenta
    // segundos cospe log de cada job; guardar tudo é inchar a tabela e apagar o que
    // importa, que é o começo e o fim.
    'saida_maxima' => 6000,

    // O caderno de tarefas que a tela oferece no lugar de deixar a pessoa digitar a
    // linha de comando. É a lista do que já existe, não uma permissão: a validação
    // confere contra os comandos registrados no `artisan`, e um comando novo do sistema
    // entra aqui para aparecer escolhido no select.
    'comandos_sugeridos' => [
        'queue:work --stop-when-empty --max-time=50' => 'Drenar a fila de envios (e-mails, avisos e jobs)',
        'google-ads:sync --agendamento' => 'Sincronizar as métricas das contas de Google Ads conectadas',
        'rastreio:podar' => 'Apagar visitas e eventos mais velhos que a retenção',
        'notificacoes:apodrecer' => 'Aposentar as notificações lidas e vencidas',
        'depoimentos:google' => 'Ler as avaliações do Google Business e publicar os depoimentos novos',
        'cache:clear' => 'Esquecer o cache do painel inteiro',
        'optimize:clear' => 'Refazer caches de configuração, rotas e visualizações',
    ],

    // O que a tela recusa, escrito uma vez e com o motivo na mensagem. `tinker` executa
    // PHP arbitrário; `migrate` e as variants de rollback mexem no esquema de uma conta
    // que já estava autenticada com o comando; `storage:link` e afins não têm nada que
    // rodar em loop. É o único ponto onde a agenda do painel sabe o que é perigoso — os
    // testes e a validação do model consultam esta lista em vez de repeti-la.
    'comandos_recusados' => [
        'tinker' => 'O tinker executa qualquer PHP pela agenda, e isso não é tarefa de cron.',
        'migrate' => 'Mudar o esquema do banco por cron é a receita de um deploy quebrado no meio da madrugada.',
        'migrate:rollback' => 'Reverter migration automático não existe: quem reverte é uma pessoa, de caso pensado.',
        'migrate:fresh' => 'Isto apaga todas as tabelas. Não roda, e não roda sozinho.',
        'migrate:refresh' => 'Reconstruir tabelas por agenda joga os dados fora a cada batimento.',
        'db:wipe' => 'Isto esvazia o banco inteiro. Não roda, e não roda sozinho.',
        'queue:flush' => 'Jogar a fila fora apaga os avisos que ainda não saíram.',
        'storage:link' => 'Um link de uma vez só: não é tarefa para repetir.',
        'key:generate' => 'Isto troca a chave que cifra os tokens gravados no banco.',
        'app:define-senha' => 'Ele pede a senha na tela, e no cron não existe tela para responder — a tarefa travaria ali.',
    ],

    // Os comandos que são a própria fila. "Rodar agora" recusa estes: o worker é o
    // mecanismo que executa os jobs, e chamá-lo dentro da requisição travaria a tela do
    // usuário por cinquenta segundos para no fim fazer o que o cron faz sozinho.
    'comandos_da_fila' => [
        'queue:work',
        'queue:listen',
        'queue:restart',
    ],

    // O batimento-cardíaco da agenda: a última vez que `schedule:run` passou pelo
    // agendador. A tela mostra "vivo há 40 segundos" ou "sem batida há 2 horas", que é a
    // diferença entre uma tarefa devagar e a crontab da hospedagem arrancada. Fica em
    // cache, não no banco: é informação de minuto, e banco é para o que se consulta depois.
    'batida' => [
        'chave_no_cache' => 'golden.cron_batida',
        'tolerancia_segundos' => 120,
    ],

    // A próxima vez que a agenda bate, em segundos. É a conta do botão "a tarefa tal vai
    // rodar daqui a 40 segundos" da tela — a pergunta que todo mundo faz depois de mexer
    // numa agenda e não quer esperar cinco minutos para conferir.
    'batida_proxima_segundos' => 60,
];
