<?php

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Ajusta a confirmação de nome padrão para manter compatibilidade e amplia as tags de busca das funções da Ana.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $ficha = Assistente::first();

        if ($ficha !== null && ($ficha->confirmacao_nome === null || $ficha->confirmacao_nome === 'Muito prazer, :nome! Como posso te ajudar hoje?')) {
            $ficha->forceFill([
                'confirmacao_nome' => (string) config('assistente.persona.confirmacao_nome'),
            ])->save();
        }

        $item = ConhecimentoItem::where('referencia', 'golden:o-que-a-ana-resolve')->first();

        if ($item !== null) {
            $novasTags = 'ajuda assuntos atendimento vendas serviços produtos dúvidas o que você faz sabe fazer funções capacidades quem é você como pode me ajudar consultora ana aninha';

            if ($item->tags !== $novasTags) {
                $item->forceFill(['tags' => $novasTags])->save();
            }
        }

        Cache::forget('golden.assistente_bolha');
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        // Mantém os ajustes aplicados para preservar edições feitas no painel administrativo.
    }
};
