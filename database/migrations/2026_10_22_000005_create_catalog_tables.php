<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj', 24)->nullable();
            $table->string('contato')->nullable();
            $table->string('telefone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('descricao')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('sku', 64)->nullable();
            $table->string('resumo')->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedInteger('preco_cents')->default(0);
            $table->unsignedInteger('preco_promocional_cents')->nullable();
            $table->unsignedInteger('mensalidade_cents')->nullable(); // planos de monitoramento
            $table->unsignedInteger('estoque')->default(0);
            $table->unsignedInteger('estoque_minimo')->default(0);
            $table->string('imagem')->nullable();
            $table->boolean('destaque')->default(false);
            $table->boolean('ativo')->default(true);
            $table->boolean('controle_estoque')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['ativo', 'destaque']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('tipo', 16); // entrada | saida | ajuste
            $table->integer('quantidade');
            $table->integer('saldo_after')->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
    }
};
