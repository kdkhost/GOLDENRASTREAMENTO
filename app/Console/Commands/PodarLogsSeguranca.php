<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Services\Security\MilitaryShield;
use Illuminate\Console\Command;

class PodarLogsSeguranca extends Command
{
    protected $signature = 'seguranca:podar-logs
                            {--dias=30 : Quantos dias de logs preservar}';

    protected $description = 'Apagar logs de segurança e tentativas interceptadas anteriores ao período de retenção';

    public function handle(MilitaryShield $shield): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $removidos = $shield->limparLogsAntigos($dias);

        $this->info("{$removidos} log(s) de segurança anteriores a {$dias} dias removidos.");
        Activity::log('seguranca_podada', "Logs de segurança podados: {$removidos} registro(s) com mais de {$dias} dias.");

        return self::SUCCESS;
    }
}
