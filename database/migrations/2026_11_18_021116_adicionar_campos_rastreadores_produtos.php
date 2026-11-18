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
            $table->string('tipo_cobranca', 32)->default('mensal')->after('mensalidade_cents');
            $table->string('chip_operadora', 64)->nullable()->after('tipo_cobranca');
            $table->string('garantia', 64)->nullable()->after('chip_operadora');
            $table->string('anatel_homologado', 64)->nullable()->after('garantia');
            $table->json('recursos')->nullable()->after('anatel_homologado');
            $table->unsignedInteger('peso_gramas')->nullable()->after('recursos');
            $table->unsignedSmallInteger('comprimento_cm')->nullable()->after('peso_gramas');
            $table->unsignedSmallInteger('largura_cm')->nullable()->after('comprimento_cm');
            $table->unsignedSmallInteger('altura_cm')->nullable()->after('largura_cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_cobranca',
                'chip_operadora',
                'garantia',
                'anatel_homologado',
                'recursos',
                'peso_gramas',
                'comprimento_cm',
                'largura_cm',
                'altura_cm',
            ]);
        });
    }
};
