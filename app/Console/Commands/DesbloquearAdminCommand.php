<?php

namespace App\Console\Commands;

use App\Models\SecurityIpRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DesbloquearAdminCommand extends Command
{
    protected $signature = 'seguranca:desbloquear {ip?}';

    protected $description = 'Remove IP da blacklist e adiciona a whitelist';

    public function handle(): int
    {
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
