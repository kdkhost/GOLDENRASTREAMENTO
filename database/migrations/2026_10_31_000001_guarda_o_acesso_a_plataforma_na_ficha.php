<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O acesso do cliente à plataforma de rastreamento, que só o financeiro sabia.
 *
 * No WHMCS, cada ficha guarda em campos personalizados o servidor em que o cliente está ("servidor
 * 3") e o login que ele usa lá. Nada disso existia no CRM: quem abria a ficha para atender uma
 * dúvida de acesso precisava abrir o financeiro em outra aba. As duas colunas são texto simples
 * porque são exatamente o que o financeiro escreve — não há catálogo de servidores nem senha aqui,
 * e inventar os dois seria criar uma segunda verdade sobre a mesma conta.
 *
 * Preenchidas, as duas aparecem na ficha; em branco, continuam sendo um traço. O valor entra pelo
 * botão "Puxar dados do financeiro" ou pela rotina em massa, e só quando a ficha não tem nada
 * escrito naquele campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('plataforma', 60)->nullable()->after('frota_tamanho');
            $table->string('usuario_plataforma', 190)->nullable()->after('plataforma');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['plataforma', 'usuario_plataforma']);
        });
    }
};
