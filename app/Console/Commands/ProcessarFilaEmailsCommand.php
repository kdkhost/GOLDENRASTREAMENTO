<?php

namespace App\Console\Commands;

use App\Services\Mail\FilaDeEmailService;
use Illuminate\Console\Command;

/**
 * Drena a fila cadenciada anti-spam respeitando o teto por minuto, hora e intervalo entre envios.
 */
class ProcessarFilaEmailsCommand extends Command
{
    protected $signature = 'golden:emails:processar-fila {--limite=20 : Máximo de e-mails por execução} {--ignorar-janela : Ignora janelas de cadência por minuto/hora}';

    protected $description = 'Processa os e-mails e faturas PDF agendados na fila anti-spam.';

    public function handle(FilaDeEmailService $fila): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $respeitarJanela = ! (bool) $this->option('ignorar-janela');

        $resultado = $fila->processarLote($limite, $respeitarJanela);

        if ($resultado['motivo'] !== null) {
            $this->warn($resultado['motivo']);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Fila processada: %d verificados, %d enviados, %d falhas.',
            $resultado['processados'],
            $resultado['enviados'],
            $resultado['falhas']
        ));

        return self::SUCCESS;
    }
}
