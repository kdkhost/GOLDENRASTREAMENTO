<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O MySQL de produção truncava status com mais de 16 caracteres:
 * 'aguardando_pagamento' tem 20, então o pedido quebrava no meio do fluxo (e o
 * seed morria em 'Data too long for column'). O SQLite nunca reclamou, porque
 * não olha comprimento de string — por isso o bug só apareceu no servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('pendente')->change();
        });

        Schema::table('order_histories', function (Blueprint $table) {
            $table->string('status', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 16)->default('pendente')->change();
        });

        Schema::table('order_histories', function (Blueprint $table) {
            $table->string('status', 16)->change();
        });
    }
};
