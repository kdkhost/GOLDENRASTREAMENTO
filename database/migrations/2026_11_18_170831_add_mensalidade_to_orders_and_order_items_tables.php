<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('mensalidade_total_cents')->default(0)->after('subtotal_cents');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('mensalidade_cents')->default(0)->after('preco_unit_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('mensalidade_cents');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('mensalidade_total_cents');
        });
    }
};
