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

        // Todo aviso do portal (chamado aberto, resposta da equipe, recibo, link de senha)
        // entra em fila, e a hospedagem só tem o cron de schedule:run — sem isto o e-mail
        // espera para sempre na tabela jobs. O --max-time deixa a janela livre antes do
        // próximo minuto e o withoutOverlapping impede dois workers sobre a mesma fila.
        $schedule->command('queue:work --stop-when-empty --max-time=50')
            ->everyMinute()->withoutOverlapping();

        // A métrica do Google Ads tem meia hora de vida no cache e o painel só lê de lá.
        // Quinze minutos mantém a janela sempre preenchida para quem abre a tela, sem
        // dobrar as chamadas: o comando só enfileira, quem fala com o Google é a fila.
        // `--agendamento` porque conta ainda não conectada não é erro de cron — é a instalação
        // esperando o dono preencher a chave, e o log precisa continuar legível.
        $schedule->command('google-ads:sync --agendamento')->everyFifteenMinutes()->withoutOverlapping();
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
