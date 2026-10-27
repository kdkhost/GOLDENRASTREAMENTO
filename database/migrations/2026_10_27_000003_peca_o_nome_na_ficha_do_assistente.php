<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pergunta pelo nome, escrita por quem administra o atendimento.
 *
 * A regra de abrir a conversa pedindo o nome é do código (a máquina não atende sem ela), mas a
 * frase é da casa: é o mesmo desenho dos textos da bolha, que a ficha já entrega prontos para o
 * painel editar. Em branco, vale o padrão de `config('assistente.persona.pergunta_nome')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->text('pergunta_nome')->nullable()->after('saudacao');
        });
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn('pergunta_nome');
        });
    }
};
