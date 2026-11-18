<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('cobrar_primeira_mensalidade')->default(true)->after('mensalidade_cents');
            $table->boolean('permite_escolha_chip')->default(false)->after('chip_operadora');
            $table->json('operadoras_chip')->nullable()->after('permite_escolha_chip');
            $table->string('chip_padrao', 32)->nullable()->after('operadoras_chip');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('chip_operadora', 64)->nullable()->after('mensalidade_cents');
            $table->boolean('cobrar_mensalidade_hoje')->default(true)->after('chip_operadora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['chip_operadora', 'cobrar_mensalidade_hoje']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cobrar_primeira_mensalidade', 'permite_escolha_chip', 'operadoras_chip', 'chip_padrao']);
        });
    }
};
