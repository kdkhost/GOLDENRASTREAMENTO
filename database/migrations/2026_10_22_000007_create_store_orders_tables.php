<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 24)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // vendedor responsável
            $table->string('nome', 160)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 32)->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade', 96)->nullable();
            $table->string('uf', 4)->nullable();
            $table->string('cep', 12)->nullable();
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('frete_cents')->default(0);
            $table->unsignedInteger('desconto_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('pagamento', 16)->default('cartao'); // cartao | boleto | pix | nao
            $table->string('status', 16)->default('pendente')->index(); // pendente | pago | em_andamento | enviado | entregue | cancelado
            $table->text('notas')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->timestamp('pago_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('nome');                       // snapshot do nome no momento da venda
            $table->unsignedInteger('quantidade')->default(1);
            $table->unsignedInteger('preco_unit_cents')->default(0);
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
