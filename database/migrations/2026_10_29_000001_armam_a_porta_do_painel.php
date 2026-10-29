<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A porta de entrada ganha fechadura dupla e histórico.
     *
     * As três colunas em `users` são o dois fatores: o segredo TOTP e os códigos de recuperação
     * cifrados pelo `encrypted` do model (nunca em texto — um vazamento do banco não pode entregar
     * a segunda chave da casa), e o instante em que a pessoa provou ter o aplicativo, que é o que
     * separa "comecei a configurar" de "estou protegido".
     *
     * `sessoes_do_painel` existe porque o driver de sessão é arquivo e a hospedagem não dá acesso
     * ao conteúdo das sessões alheias. Sem esta tabela, "encerrar o notebook do escritório" não tem
     * o que encerrar; com ela, o painel sabe quantos aparelhos estão abertos em nome de cada
     * pessoa, de onde, e pode derrubar um por um.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('avatar');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::create('sessoes_do_painel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sessao', 100);
            $table->string('carimbo', 40)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agente', 160)->nullable();
            $table->timestamp('ultimo_visto_em')->nullable();
            $table->timestamps();

            $table->index('sessao');
            $table->index(['user_id', 'carimbo']);
            $table->index(['user_id', 'ultimo_visto_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessoes_do_painel');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
