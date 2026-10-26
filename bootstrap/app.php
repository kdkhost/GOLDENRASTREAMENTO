<?php

use App\Http\Middleware\AutenticarCliente;
use App\Http\Middleware\ControleAcesso;
use App\Http\Middleware\IdentificadorVisita;
use App\Http\Middleware\LojaAtiva;
use App\Http\Middleware\RegistrarRastreio;
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
        $middleware->web(append: [
            IdentificadorVisita::class,
            RegistrarRastreio::class,
        ]);

        $middleware->alias([
            'role' => ControleAcesso::class,
            'cliente' => AutenticarCliente::class,
            'loja' => LojaAtiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
