<?php

use App\Services\Assistente\Semeador;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Aplica a personalidade humanizada e cordial da Ana e sincroniza sua base de conhecimento e catálogo.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $semeador = app(Semeador::class);
        $semeador->personalidade();
        $semeador->conhecimento(false);
        $semeador->catalogo();

        Cache::forget('golden.assistente_bolha');
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        // Não desfaz para preservar edições feitas pelo administrador no painel.
    }
};
