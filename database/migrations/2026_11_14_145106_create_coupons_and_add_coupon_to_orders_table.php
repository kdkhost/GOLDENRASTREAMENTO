<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('descricao', 190)->nullable();
            $table->string('tipo', 20)->default('percentual'); // percentual, fixo, frete_gratis
            $table->unsignedInteger('valor')->default(0); // % (1..100) ou centavos para fixo
            $table->unsignedInteger('valor_minimo_cents')->default(0);
            $table->unsignedInteger('desconto_maximo_cents')->nullable();
            $table->unsignedInteger('limite_usos')->nullable();
            $table->unsignedInteger('limite_por_cliente')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->timestamp('valido_de')->nullable();
            $table->timestamp('valido_ate')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ativo', 'valido_de', 'valido_ate'], 'coupons_vigencia_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('desconto_cents')->constrained('coupons')->nullOnDelete();
            $table->string('cupom_codigo', 40)->nullable()->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('cupom_codigo');
        });

        Schema::dropIfExists('coupons');
    }
};
