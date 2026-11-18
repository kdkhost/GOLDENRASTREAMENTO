<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Product::whereIn('category_id', [1, 2, 4])
            ->orWhere('mensalidade_cents', '>', 0)
            ->orWhere('nome', 'like', '%Rastreador%')
            ->orWhere('nome', 'like', '%Plano%')
            ->orWhere('nome', 'like', '%Chip%')
            ->update([
                'permite_escolha_chip' => 1,
                'operadoras_chip' => ['algar', 'vivo', 'claro', 'tim'],
                'chip_padrao' => 'algar',
            ]);

        Cache::flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não reverte para manter integridade dos dados cadastrados
    }
};
