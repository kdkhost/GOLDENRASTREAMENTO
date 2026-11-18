<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migração para adicionar a flag de preço sob consulta sem zerar valores.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sob_consulta')) {
                $table->boolean('sob_consulta')->default(false)->after('mensalidade_cents');
            }
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sob_consulta')) {
                $table->dropColumn('sob_consulta');
            }
        });
    }
};
