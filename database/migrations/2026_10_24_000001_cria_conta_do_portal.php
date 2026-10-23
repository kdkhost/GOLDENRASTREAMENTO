<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conta do portal do cliente.
 *
 * O cadastro do CRM (customers) ganha três colunas de acesso e nada mais: quem entra no
 * /portal é a mesma ficha que o vendedor já cuida no painel, então pedidos, interações e
 * histórico não precisam de segunda fonte. `email_portal` (e não `email`) é o login, porque
 * o CRM aceita e-mails repetidos entre fichas — o índice único só pode nascer numa coluna
 * que o próprio portal preenche, uma conta por endereço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email_portal', 190)->nullable()->unique()->after('email');
            $table->string('password')->nullable()->after('email_portal');
            $table->string('remember_token', 100)->nullable()->after('password');
            $table->timestamp('ultimo_acesso_em')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email_portal']);
            $table->dropColumn(['email_portal', 'password', 'remember_token', 'ultimo_acesso_em']);
        });
    }
};
