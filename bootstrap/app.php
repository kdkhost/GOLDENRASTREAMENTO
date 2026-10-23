<?php

use App\Http\Middleware\AutenticarCliente;
use App\Http\Middleware\ControleAcesso;
use App\Http\Middleware\IdentificadorVisita;
use App\Http\Middleware\LojaAtiva;
use App\Http\Middleware\RegistrarRastreio;
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
        // O registro de tráfego cresce com cada visita; sem a poda semanal a tabela
        // visits/visit_events incha para sempre. A retenção é o dias_retencao do config.
        $schedule->command('rastreio:podar')->weeklyOn(2, '03:30')->withoutOverlapping();
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
