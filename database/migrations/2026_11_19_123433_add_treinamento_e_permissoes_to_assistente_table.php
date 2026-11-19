<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona colunas estruturadas de objetivo, restrições, treinamento e permissões de acesso da Ana.
     */
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            if (! Schema::hasColumn('assistente', 'objetivo_atendimento')) {
                $table->text('objetivo_atendimento')->nullable()->after('regras');
            }
            if (! Schema::hasColumn('assistente', 'restricoes')) {
                $table->text('restricoes')->nullable()->after('objetivo_atendimento');
            }
            if (! Schema::hasColumn('assistente', 'instrucoes_extras')) {
                $table->text('instrucoes_extras')->nullable()->after('restricoes');
            }
            if (! Schema::hasColumn('assistente', 'acessos_sistema')) {
                $table->json('acessos_sistema')->nullable()->after('instrucoes_extras');
            }
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            foreach (['acessos_sistema', 'instrucoes_extras', 'restricoes', 'objetivo_atendimento'] as $col) {
                if (Schema::hasColumn('assistente', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
