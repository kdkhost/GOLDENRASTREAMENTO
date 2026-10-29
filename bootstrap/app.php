<?php

use App\Http\Middleware\AcessoSupervisionado;
use App\Http\Middleware\AtestarSessaoDoPainel;
use App\Http\Middleware\AutenticarCliente;
use App\Http\Middleware\CabecalhosDeSeguranca;
use App\Http\Middleware\ControleAcesso;
use App\Http\Middleware\GarantirDoisFatores;
use App\Http\Middleware\IdentificadorVisita;
use App\Http\Middleware\LojaAtiva;
use App\Http\Middleware\RegistrarRastreio;
use App\Http\Middleware\ResponderEscritaAjax;
use App\Support\Agendador;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // A agenda mora na tabela `cron_tarefas`, e a hospedagem continua com a linha única de
        // sempre: `* * * * * artisan schedule:run`. Registrar a partir do banco é o que faz o
        // painel ser o dono do relógio sem que ninguém precise editar crontab — o que roda, quando
        // roda e se pode sobrepor vem da tela Sistema → Crons, com histórico e botão de rodar agora.
        //
        // As três tarefas que estavam fixas aqui viraram linhas da migration (a fila, o Google Ads
        // e a poda de tráfego), e a poda de notificação é a quarta. Uma agenda que só existe em
        // código é uma agenda que ninguém ajusta sem deploy — e é também uma agenda sem história.
        Agendador::registrar($schedule);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // A senha da conta passou a ter efeito imediato: trocou a senha (ou alguém trocou por ela na
        // tela de Usuários), toda janela aberta daquela conta se fecha no próximo pedido. É o
        // mecanismo da própria framework, e ele também amarra a cookie "lembrar de mim" à senha —
        // roubar a cookie de uma conta cuja senha mudou não rouba mais nada.
        $middleware->authenticateSessions();

        // Global, e não só `web`: a resposta sai daqui já carimbada para qualquer rota da casa,
        // incluindo os pontos de entrada anônimos (login, portal, captura de lead), que são justamente
        // onde um navegador é induzido a confiar numa página que não é nossa.
        $middleware->append(CabecalhosDeSeguranca::class);

        $middleware->web(append: [
            IdentificadorVisita::class,
            RegistrarRastreio::class,
            // Depois da sessão iniciada, antes de qualquer controller: é aqui que uma janela
            // encerrada pelo painel deixa de mandar no sistema.
            AtestarSessaoDoPainel::class,
            // Por último, para ver a resposta já pronta de qualquer controller: é aqui que o
            // redirect de uma gravação vira JSON para quem pediu em segundo plano.
            ResponderEscritaAjax::class,
        ]);

        $middleware->alias([
            'role' => ControleAcesso::class,
            'cliente' => AutenticarCliente::class,
            'supervisao' => AcessoSupervisionado::class,
            'loja' => LojaAtiva::class,
            'dois_fatores' => GarantirDoisFatores::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
