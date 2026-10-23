<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('documento', 24)->nullable();  // CPF ou CNPJ
            $table->string('tipo', 16)->default('fisica'); // fisica | juridica
            $table->string('telefone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade', 96)->nullable();
            $table->string('uf', 4)->nullable();
            $table->string('cep', 12)->nullable();
            $table->string('segmento')->nullable();       // motorista | empresa | locadora | rural | outro
            $table->unsignedInteger('frota_tamanho')->nullable();
            $table->string('status', 16)->default('prospecto')->index(); // prospecto | ativo | inativo
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete(); // origem
            $table->unsignedInteger('valor_anual_cents')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('tipo', 16)->default('nota');  // ligacao | whatsapp | email | reuniao | nota
            $table->string('assunto')->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('concluida')->default(false);
            $table->timestamp('proxima_acao_em')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('customers');
    }
};
