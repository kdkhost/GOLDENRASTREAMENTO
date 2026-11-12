<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conta de gerente (MCC) organiza a árvore, mas não tem campanha nenhuma: o Google recusa
 * pedido de métrica feito nela com `REQUESTED_METRICS_FOR_MANAGER` e manda consultar cada conta
 * de anúncio separadamente.
 *
 * O painel já sabia disso na hora de conectar — `detalhesDaConta()` lê `customer.manager` e
 * devolve `gerente` — e deitava a resposta fora, porque a fila não tinha onde guardá-la. Sem a
 * marca, o agendamento chamava o gerente a cada rodada, queimava cota com uma falha certa e
 * enchia `google_ads_api_logs`; e o seletor de conta da tela oferecia o gerente como se ele
 * tivesse número, deixando quem escolhia ele diante de um "Carregando…" que nunca terminava.
 *
 * Nenhuma linha existente é reescrita aqui. Quem conecta recebe o valor do Google na hora, e uma
 * conexão antiga aprende com a primeira recusa: o job marca a conta e para de chamá-la.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_ads_accounts', function (Blueprint $table) {
            $table->boolean('gerente')->default(false)->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('google_ads_accounts', function (Blueprint $table) {
            $table->dropColumn('gerente');
        });
    }
};
