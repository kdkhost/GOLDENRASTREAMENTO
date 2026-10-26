<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os textos da bolha que o painel passa a escrever.
 *
 * Nenhuma coluna nasce preenchida: vazio é o padrão de `config('assistente.bolha')`, e é assim
 * que a instalação que nunca abriu a tela continua com o mesmo texto de sempre. A ficha é uma
 * linha só, então acrescentar coluna é acrescentar campo — não há risco de repetir a persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->string('dica', 80)->nullable();
            $table->string('status', 60)->nullable();
            $table->string('placeholder', 120)->nullable();
            $table->string('humano', 120)->nullable();
            $table->string('icone', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn(['dica', 'status', 'placeholder', 'humano', 'icone']);
        });
    }
};
