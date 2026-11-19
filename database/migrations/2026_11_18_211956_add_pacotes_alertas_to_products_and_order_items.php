<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona suporte a pacotes adicionais de alertas recorrentes configuráveis por produto.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'pacotes_alertas')) {
                $table->json('pacotes_alertas')->nullable()->after('recursos');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'pacotes_alertas')) {
                $table->json('pacotes_alertas')->nullable()->after('chip_operadora');
            }
            if (! Schema::hasColumn('order_items', 'pacotes_alertas_cents')) {
                $table->unsignedInteger('pacotes_alertas_cents')->default(0)->after('pacotes_alertas');
            }
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'pacotes_alertas')) {
                $table->dropColumn('pacotes_alertas');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'pacotes_alertas_cents')) {
                $table->dropColumn('pacotes_alertas_cents');
            }
            if (Schema::hasColumn('order_items', 'pacotes_alertas')) {
                $table->dropColumn('pacotes_alertas');
            }
        });
    }
};
