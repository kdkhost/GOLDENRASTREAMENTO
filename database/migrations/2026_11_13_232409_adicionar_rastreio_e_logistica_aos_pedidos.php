<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'codigo_rastreio')) {
                    $table->string('codigo_rastreio', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('orders', 'transportadora')) {
                    $table->string('transportadora', 32)->default('correios');
                }
                if (! Schema::hasColumn('orders', 'rastreio_notificado_em')) {
                    $table->timestamp('rastreio_notificado_em')->nullable();
                }
                if (! Schema::hasColumn('orders', 'rastreio_historico')) {
                    $table->text('rastreio_historico')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = ['codigo_rastreio', 'transportadora', 'rastreio_notificado_em', 'rastreio_historico'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
