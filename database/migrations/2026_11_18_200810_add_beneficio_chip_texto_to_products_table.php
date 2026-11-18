<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona o texto configurável do benefício do chip exibido na loja e no pedido.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'beneficio_chip_texto')) {
                $table->string('beneficio_chip_texto', 255)->nullable()->after('chip_operadora');
            }
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'beneficio_chip_texto')) {
                $table->dropColumn('beneficio_chip_texto');
            }
        });
    }
};
