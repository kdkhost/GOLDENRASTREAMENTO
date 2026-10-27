<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O que a conversa estava pedindo enquanto a casa terminava de identificar a pessoa.
     *
     * Sem estas colunas, quem pede a segunda via e é corretamente questionado do e-mail teria que
     * repetir o pedido depois de responder — e a conversa inteira passaria por uma máquina que não
     * leu a frase anterior. É um código e a hora do pedido, nada mais: a fala dita já está no
     * histórico, e o mesmo relógio da conferência (`assistente.verificacao.minutos`) decide até
     * quando um pedido velho vale — retomar uma segunda via de uma hora atrás, dita noutro assunto,
     * seria a máquina agindo por conta própria.
     */
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('acao_pendente', 60)->nullable()->after('acao_em');
            $table->timestamp('acao_pendente_em')->nullable()->after('acao_pendente');
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropColumn(['acao_pendente', 'acao_pendente_em']);
        });
    }
};
