<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As tabelas do assistente.
 *
 * `assistente` é a ficha da persona — nome, rosto, tom, regras — e existe uma linha só: é um
 * atendente, não uma lista. O singular no nome da tabela é deliberado e o modelo declara
 * `$table` para acompanhar; a tela de painel edita essa ficha, nunca cadastra.
 *
 * `conhecimento_itens` é o que a máquina sabe. Cada linha tem procedência (`origem`, `url`,
 * `referencia`) e data de leitura, porque resposta sem fonte é a mesma coisa que chute — e é
 * essa lista que permite ao sistema dizer "não sei" em vez de inventar preço.
 *
 * `conversas` e `conversas_mensagens` são o atendimento anônimo do site e do funil: enquanto o
 * cliente logado conversa pelo chamado (e a fila continua sendo `ticket_messages`), quem ainda
 * não tem conta fala aqui. A conversa guarda as pontes (`visit_id`, `lead_id`, `customer_id`,
 * `ticket_id`) em vez de duplicar cadastro: ela nasce anônima, vira lead e pode virar chamado
 * sem perder o histórico.
 *
 * `assistente_api_logs` é a caixa-preta das chamadas de IA, no mesmo desenho de
 * `google_ads_api_logs`: o que foi perguntado, o que voltou, quanto demorou e o erro por
 * escrito. Mídia nunca entra em claro aqui — o log guarda a marcação do arquivo, não o
 * base64, porque um PDF caberia no banco e estouraria o texto da coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistente', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('cargo', 160)->nullable();
            $table->text('saudacao')->nullable();
            $table->string('tom', 20)->default('amigavel');
            $table->text('regras')->nullable();
            $table->string('avatar', 190)->nullable();
            $table->string('avatar_alt', 120)->nullable();
            // `provedor:voz` da lista de config('assistente.vozes'); vazio é a voz padrão da casa.
            $table->string('voz', 40)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('no_site')->default(true);
            $table->boolean('no_funil')->default(true);
            $table->boolean('no_chamado')->default(false);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('conhecimento_itens', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 180);
            $table->text('corpo');
            $table->string('tags', 190)->nullable();
            $table->string('origem', 20)->default('painel');
            $table->string('url', 500)->nullable();
            // Chave da fonte no mundo: o endereço lido ou o id da página do site. Com `origem`,
            // é o que impede o robô de reaprender a mesma página a cada leitura.
            $table->string('referencia', 190)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('lido_em')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Em MySQL, índice único aceita várias linhas nulas: o item escrito à mão não tem
            // procedência, e cada um desses precisa poder existir ao mesmo tempo.
            $table->unique(['origem', 'referencia']);
            $table->index(['ativo', 'origem']);
        });

        Schema::create('conversas', function (Blueprint $table) {
            $table->id();
            $table->string('canal', 20);
            $table->string('status', 20)->default('aberta');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nome', 120)->nullable();
            $table->string('contato', 160)->nullable();
            $table->string('pagina', 500)->nullable();
            // Por que a máquina chamou gente: 'sem_fonte', 'midia_nao_lida', 'pediu_humano'.
            $table->string('escala_motivo', 40)->nullable();
            $table->timestamp('respondido_por_equipe_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            // A caixa de entrada ordena por aqui; contar mensagens a cada listagem seria o
            // mesmo trabalho que a listagem faz em vão.
            $table->timestamp('ultima_fala_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'ultima_fala_em']);
            $table->index(['canal', 'status']);
            $table->index('ticket_id');
        });

        Schema::create('conversas_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained()->cascadeOnDelete();
            // cliente | assistente | equipe | sistema. O autor é uma palavra porque a fala pode
            // vir de três tabelas diferentes (visitante anônimo, ficha do cliente, painel) e um
            // único id não diria de qual.
            $table->string('autor', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 20)->default('texto');
            $table->text('corpo')->nullable();
            $table->string('midia', 190)->nullable();
            $table->string('midia_nome', 190)->nullable();
            $table->string('midia_mime', 120)->nullable();
            $table->unsignedInteger('midia_bytes')->nullable();
            $table->unsignedSmallInteger('duracao_segundos')->nullable();
            $table->text('transcricao')->nullable();
            $table->string('resposta_audio', 190)->nullable();
            // A lista de itens de conhecimento usada na resposta, com título e procedência: é o
            // que a equipe lê para conferir de onde a máquina tirou aquilo.
            $table->text('fontes_json')->nullable();
            $table->string('provedor', 30)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_saida')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'id']);
        });

        Schema::create('assistente_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('capacidade', 20);
            $table->string('provedor', 30);
            $table->string('modelo', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversa_id')->nullable()->constrained()->nullOnDelete();
            $table->text('request_json')->nullable();
            $table->text('response_json')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_saida')->nullable();
            $table->unsignedInteger('audio_bytes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['created_at', 'capacidade']);
            $table->index(['conversa_id', 'created_at']);
        });

        // A fala do assistente dentro do chamado precisa de autor próprio. Sem isto, `daEquipe()`
        // (user_id preenchido) ou mentiria — a máquina não é a equipe — ou a mensagem sairia
        // assinada como "Cliente", que é o troço mais errado possível.
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->foreignId('assistant_id')->nullable()->after('user_id')->constrained('assistente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_id');
        });

        Schema::dropIfExists('assistente_api_logs');
        Schema::dropIfExists('conversas_mensagens');
        Schema::dropIfExists('conversas');
        Schema::dropIfExists('conhecimento_itens');
        Schema::dropIfExists('assistente');
    }
};
