<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chamados e contratos do portal do cliente.
 *
 * Os dois pertencem à ficha do CRM (customers), não ao usuário da sessão: o mesmo chamado
 * continua visível para a equipe no painel e para o cliente no /portal, e o contrato é o
 * documento que a equipe anexa — o cliente só lê. Comprimentos de coluna explícitos porque
 * o MySQL da hospedagem reclama do que o SQLite aceita em silêncio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();               // CH26-00001
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('assunto', 180);
            $table->string('canal', 16)->default('portal');       // portal | telefone | whatsapp | painel
            $table->string('status', 24)->default('aberto')->index();
            $table->string('prioridade', 16)->default('normal');  // baixa | normal | alta | urgente
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('corpo');
            $table->timestamps();

            $table->index('ticket_id');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('titulo', 180);
            $table->string('tipo', 20)->default('adesao');        // adesao | aditivo | renovacao
            $table->string('status', 20)->default('assinado')->index();
            $table->string('arquivo', 255)->nullable();           // caminho no disco público
            $table->date('celebrado_em')->nullable();
            $table->date('vigencia_ate')->nullable();
            $table->unsignedInteger('valor_mensal_cents')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
