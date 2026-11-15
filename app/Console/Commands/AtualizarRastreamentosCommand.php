<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\RastreioEncomendaService;
use App\Support\Notifica;
use Illuminate\Console\Command;

class AtualizarRastreamentosCommand extends Command
{
    protected $signature = 'golden:rastreamentos:atualizar';

    protected $description = 'Consulta e atualiza o rastreamento em tempo real dos pedidos despachados, alertando o administrador a cada etapa.';

    public function handle(RastreioEncomendaService $rastreio): int
    {
        $pedidos = Order::where('status', 'enviado')
            ->whereNotNull('codigo_rastreio')
            ->where('codigo_rastreio', '!=', '')
            ->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido em trânsito com rastreamento pendente.');

            return self::SUCCESS;
        }

        $this->info("Consultando rastreamento de {$pedidos->count()} pedido(s)...");
        $atualizados = 0;

        foreach ($pedidos as $pedido) {
            $codigo = $pedido->codigo_rastreio;
            $dados = $rastreio->rastrear($codigo, true);

            $ultimo = $dados['eventos'][0] ?? null;
            if (! $ultimo) {
                continue;
            }

            $titulo = $ultimo['status'] ?? ($ultimo['titulo'] ?? 'Atualizado');
            $descricao = $ultimo['detalhe'] ?? ($ultimo['descricao'] ?? '');
            $dataHora = trim(($ultimo['data'] ?? '').' '.($ultimo['hora'] ?? '')) ?: now()->format('d/m/Y H:i');
            $chave = "rastreio:{$pedido->id}:".md5($titulo.$dataHora);

            $disparou = Notifica::disparar(
                'entrega_atualizada',
                "Passo a passo de entrega · Pedido {$pedido->numero} ({$titulo})",
                "Objeto {$codigo}: {$descricao} em ".($ultimo['local'] ?? 'Trânsito').'.',
                (string) $pedido->id,
                [
                    'pedido_numero' => $pedido->numero,
                    'codigo_rastreio' => $codigo,
                    'etapa' => $titulo,
                    'descricao' => $descricao,
                    'local' => $ultimo['local'] ?? 'Trânsito',
                    'data_hora' => $dataHora,
                ],
                $chave
            );

            if ($disparou > 0) {
                $atualizados++;
                $this->line("Pedido {$pedido->numero} ({$codigo}): {$ultimo['titulo']}");
            }

            // Se o objeto foi entregue, atualiza automaticamente o status do pedido para entregue
            $statusTexto = mb_strtolower((string) ($dados['status'] ?? ($ultimo['titulo'] ?? '')));
            if (str_contains($statusTexto, 'entregue')) {
                $pedido->registrarStatus('entregue', 'Entrega confirmada no destino pelos Correios / Transportadora.', null);
                $this->info("Pedido {$pedido->numero} marcado como ENTREGUE.");
            }
        }

        $this->info("Concluído. {$atualizados} atualização(ões) de entrega registrada(s).");

        return self::SUCCESS;
    }
}
