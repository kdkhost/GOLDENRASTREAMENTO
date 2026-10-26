<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conta de Google Ads que o painel lê. Cada usuário do painel conecta a própria conta
 * Google: o token de atualização (`refresh_token`) é o que permite ao sistema chamar a API
 * depois que a janela do navegador já fechou, e por isso ele nunca sai da tela em claro —
 * o cast `encrypted` do modelo cifra no banco. O `customer_id` é o número da conta que se
 * consulta (10 dígitos, sem hífen): quem tem um MCC troca de conta no painel sem precisar
 * reconectar, porque o token vale para toda a árvore de acesso.
 *
 * A segunda tabela é a caixa-preta das chamadas: quando a API responde um erro, a linha
 * guarda o GAQL executado e o texto do erro — sem isso a discussão com o Google fica sem
 * prova, e o operador não tem como saber se foi cota, token ou consulta malformada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('customer_id', 20);
            // Cifrado pelo cast `encrypted`: a coluna é texto porque o criptograma muda de
            // tamanho a cada gravação (o IV vai junto), e um varchar de 191 não comportaria
            // um token de renovação mais o envelope.
            $table->text('refresh_token');
            $table->string('descriptive_name', 190)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('status', 20)->default('ativo');
            $table->string('motivo_desconexao', 190)->nullable();
            $table->timestamp('sincronizado_em')->nullable();
            $table->timestamps();

            // Reconectar a mesma conta atualiza a linha em vez de abrir duplicata.
            $table->unique(['user_id', 'customer_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('google_ads_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('google_ads_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint', 120);
            $table->text('request_json')->nullable();
            $table->text('response_json')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['google_ads_account_id', 'created_at']);
            $table->index(['created_at', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_api_logs');
        Schema::dropIfExists('google_ads_accounts');
    }
};
