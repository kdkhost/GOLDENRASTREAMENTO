<?php

namespace App\Console\Commands;

use App\Models\SecurityIpRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DesbloquearAdminCommand extends Command
{
    protected $signature = 'seguranca:desbloquear {ip?} {--listar} {--eventos}';

    protected $description = 'Remove IP da blacklist e adiciona a whitelist';

    public function handle(): int
    {
        if ($this->option('eventos')) {
            $this->table(
                ['ID', 'IP', 'Evento', 'Nível', 'URL', 'Data/Hora'],
                \App\Models\SecurityEvent::latest('created_at')->limit(15)->get()->map(fn ($e) => [
                    $e->id,
                    $e->ip,
                    $e->evento,
                    $e->nivel,
                    substr($e->url, 0, 40),
                    $e->created_at?->format('d/m/Y H:i:s'),
                ])
            );

            return Command::SUCCESS;
        }

        if ($this->option('listar')) {
            $this->table(
                ['ID', 'IP', 'Tipo', 'Motivo', 'Nível', 'Expira Em', 'Bloqueios'],
                SecurityIpRule::all()->map(fn ($r) => [
                    $r->id,
                    $r->ip,
                    $r->tipo,
                    $r->motivo,
                    $r->nivel_ameaca,
                    $r->expira_em ? $r->expira_em->format('d/m/Y H:i') : 'Permanente',
                    $r->tentativas_bloqueadas,
                ])
            );

            return Command::SUCCESS;
        }
        $ip = $this->argument('ip') ?: '177.192.4.34';

        SecurityIpRule::where('ip', $ip)->delete();

        SecurityIpRule::create([
            'ip' => $ip,
            'tipo' => 'whitelist',
            'motivo' => 'IP Administrador Liberado',
            'nivel_ameaca' => 'baixo',
        ]);

        Cache::forget("shield_strikes_{$ip}");
        Cache::flush();

        $this->info("IP {$ip} desbloqueado e adicionado à Whitelist com sucesso!");

        return Command::SUCCESS;
    }
}

