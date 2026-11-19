<?php

use App\Models\Assistente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Atualiza as regras de atendimento da Ana para priorizar cordialidade, reciprocidade e postura humana madura.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $ficha = Assistente::first();

        if ($ficha !== null) {
            $regras = (string) config('assistente.persona.regras', '');

            if ($regras !== '') {
                $ficha->forceFill(['regras' => $regras])->save();
            }
        }

        Cache::forget('golden.assistente_bolha');
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        // Mantém as regras atualizadas para preservar ajustes realizados pelo administrador no painel.
    }
};
