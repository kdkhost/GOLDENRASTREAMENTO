<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O número da ficha no WHMCS, guardado na própria ficha do CRM.
 *
 * Sem esta coluna, toda visita ao financeiro custaria uma chamada `GetClientsDetails` só para
 * descobrir quem é a pessoa. Preenchida uma vez — pelo e-mail de acesso —, as telas de serviços
 * e faturas falam direto com o id certo. O índice é único para o mesmo cliente não ser ligado a
 * duas fichas; nulo é permitido porque a integração pode nunca ter sido configurada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('whmcs_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['whmcs_id']);
            $table->dropColumn('whmcs_id');
        });
    }
};
