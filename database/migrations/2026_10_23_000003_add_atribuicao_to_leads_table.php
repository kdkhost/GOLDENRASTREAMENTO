<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();

            // Último toque: o que o vendedor vê na lista de leads.
            $table->string('canal', 24)->nullable();
            $table->string('rede', 40)->nullable();
            $table->boolean('pago')->default(false);

            // Primeiro toque: como a pessoa conheceu a Golden. Quando os dois diferem,
            // a rede social apresentou e o anúncio fechou — é isso que o painel mostra.
            $table->string('canal_primeiro', 24)->nullable();
            $table->string('rede_primeiro', 40)->nullable();
            $table->dateTime('primeiro_toque_em')->nullable();
            // 'toques' é a contagem de visitas do mesmo visitante (90 dias de cookie). Um
            // tinyInteger para em 255 e o MySQL em modo estrito devolve 'Out of range' no
            // cadastro do lead — o SQLite nunca reclamaria.
            $table->unsignedSmallInteger('toques')->default(0);

            $table->string('dispositivo', 16)->nullable();
            $table->string('navegador', 24)->nullable();

            $table->index(['canal', 'created_at']);
            $table->index(['rede', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
            $table->dropColumn([
                'canal', 'rede', 'pago', 'canal_primeiro', 'rede_primeiro',
                'primeiro_toque_em', 'toques', 'dispositivo', 'navegador',
            ]);
        });
    }
};
