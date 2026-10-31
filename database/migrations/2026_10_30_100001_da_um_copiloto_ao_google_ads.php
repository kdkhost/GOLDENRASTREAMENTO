<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O copiloto de tráfego: a ficha de quem planeja, os planos que ele escreveu e os relatórios
 * que ele analisou.
 *
 * A ficha é uma linha só, como a da Ana: nome, cargo, tom e as instruções da casa. É dela que
 * sai a ordem enviada ao provedor de IA — trocar o texto no painel muda o plano na chamada
 * seguinte, sem deploy.
 *
 * O plano guarda o briefing *e* o resultado, os dois em texto (o cast `array` do modelo cuida
 * do JSON): o briefing é a prova do que foi pedido, e o plano normalizado é o que a aplicação
 * escreve no Google. `validacao_json` leva os problemas e avisos da conferência local, para a
 * tela explicar o que o modelo errou sem refazer a conta.
 *
 * O relatório é a foto de um período com a análise do copiloto: os números ficam congelados no
 * momento em que a pessoa pediu, e é o que permite comparar a leitura de ontem com a de hoje
 * mesmo depois que a cache da tela já mudou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_copiloto', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('cargo', 120);
            $table->string('tom', 160)->nullable();
            $table->text('instrucoes')->nullable();
            $table->timestamps();
        });

        Schema::create('google_ads_planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_ads_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nome', 190);
            // rascunho, aprovado, aplicado, falhou, descartado — os rótulos vivem no modelo.
            $table->string('status', 20)->default('rascunho');
            $table->string('objetivo', 30)->default('leads');
            $table->unsignedInteger('orcamento_diario_centavos')->default(0);
            $table->text('briefing_json')->nullable();
            $table->text('plano_json')->nullable();
            $table->text('validacao_json')->nullable();
            $table->text('aplicacao_json')->nullable();
            $table->string('modelo', 120)->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('aplicado_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['google_ads_account_id', 'aplicado_em']);
            $table->index('created_at');
        });

        Schema::create('google_ads_relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('google_ads_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('dias');
            $table->text('resumo_json')->nullable();
            $table->text('campanhas_json')->nullable();
            $table->text('analise_json')->nullable();
            $table->string('modelo', 120)->nullable();
            $table->timestamp('gerado_em');
            $table->timestamps();

            $table->index(['google_ads_account_id', 'dias', 'gerado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_relatorios');
        Schema::dropIfExists('google_ads_planos');
        Schema::dropIfExists('google_ads_copiloto');
    }
};
