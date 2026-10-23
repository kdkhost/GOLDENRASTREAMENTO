<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Console\Command;

class PodarRastreio extends Command
{
    protected $signature = 'rastreio:podar
                            {--dias= : Quantos dias guardar (o padrão é golden.rastreio.dias_retencao)}';

    protected $description = 'Apagar as visitas e os cliques mais antigos que o período de retenção';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?: config('golden.rastreio.dias_retencao'));

        if ($dias < 1) {
            $this->error('O período precisa ser de pelo menos um dia.');

            return self::FAILURE;
        }

        $corte = now()->subDays($dias);
        $visitantes = Visit::where('aberta_em', '<', $corte)->count();

        // Os eventos vão antes, na mão: o cascade da chave estrangeira só vale no MySQL,
        // e o SQLite do teste deixa as linhas órfãs passarem.
        Visit::where('aberta_em', '<', $corte)->chunkById(500, function ($linhas): void {
            $ids = $linhas->pluck('id')->all();
            VisitEvent::whereIn('visit_id', $ids)->delete();
            Visit::destroy($ids);
        });

        $this->info("{$visitantes} visita(s) anteriores a {$corte->format('d/m/Y')} removidas (retenção de {$dias} dias).");
        Activity::log('rastreio_podado', "Registro de tráfego podado: {$visitantes} visita(s) antes de {$corte->toDateString()}.");

        return self::SUCCESS;
    }
}
