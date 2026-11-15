<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 80)->unique();
            $table->string('nome', 140);
            $table->string('categoria', 50)->default('geral')->index();
            $table->string('descricao', 255)->nullable();
            $table->string('assunto', 220);
            $table->string('pre_cabecalho', 220)->nullable();
            $table->longText('corpo_html');
            $table->longText('corpo_texto')->nullable();
            $table->json('variaveis')->nullable();
            $table->boolean('usar_layout')->default(true);
            $table->boolean('ativo')->default(true);
            $table->string('rota_preview', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('email_fila', function (Blueprint $table): void {
            $table->id();
            $table->string('destinatario_email', 190);
            $table->string('destinatario_nome', 160)->nullable();
            $table->string('template_chave', 80)->nullable()->index();
            $table->string('assunto', 220);
            $table->longText('corpo_html');
            $table->longText('corpo_texto')->nullable();
            $table->json('anexos')->nullable();
            $table->json('metadados')->nullable();
            $table->string('status', 30)->default('pendente')->index();
            $table->unsignedTinyInteger('prioridade')->default(5);
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedTinyInteger('max_tentativas')->default(4);
            $table->timestamp('agendado_para')->nullable()->index();
            $table->timestamp('enviado_em')->nullable();
            $table->text('ultimo_erro')->nullable();
            $table->string('lote_id', 60)->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'agendado_para', 'prioridade'], 'email_fila_despacho_idx');
        });

        if (Schema::hasTable('cron_tarefas')) {
            $comando = 'golden:emails:processar-fila --limite=20';
            $existe = DB::table('cron_tarefas')->where('comando', $comando)->exists();

            if (! $existe) {
                $agora = now();
                DB::table('cron_tarefas')->insert([
                    'nome' => 'Fila anti-spam de e-mails',
                    'descricao' => 'Processa os e-mails e faturas em PDF respeitando intervalo mínimo e limites por minuto e hora.',
                    'comando' => $comando,
                    'agenda' => 'everyMinute',
                    'hora' => null,
                    'dia_semana' => null,
                    'expressao' => '* * * * *',
                    'ativa' => true,
                    'sem_sobreposicao' => true,
                    'sistema' => true,
                    'status' => 'nunca',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_tarefas')) {
            DB::table('cron_tarefas')
                ->where('comando', 'golden:emails:processar-fila --limite=20')
                ->delete();
        }

        Schema::dropIfExists('email_fila');
        Schema::dropIfExists('email_templates');
    }
};
