<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A central de crons: a agenda do painel vira tabela.
 *
 * A hospedagem tem UMA linha de crontab registrada, e ela não se mexe:
 * `* * * * *  artisan schedule:run`. O que roda depois disso é decidido aqui dentro, nesta
 * tabela, e editado pela tela `/admin/crons`. Trocar a frequência de uma tarefa deixa de ser
 * SSH e edição de crontab — passa a ser um cadastro no painel, com histórico e com botão de
 * "rodar agora" para quem quer conferir sem esperar o próximo minuto.
 *
 * `sistema` marca as quatro tarefas que o sistema traz prontas (a fila, o Google Ads, a poda de
 * tráfego e a poda de notificações). Elas podem ser pausadas e ter a agenda mudada, mas não
 * apagadas: apagar o worker da fila é o jeito certo de perder todo e-mail do portal em silêncio,
 * e a tela de exclusão recusa essa linha por causa disso.
 *
 * `expressao` não é autoridade — é a expressão cron resolvida a partir de `agenda` + `hora` +
 * `dia_semana`, guardada para a listagem ler sem calcular e para o `schedule:list` do painel
 * conferir com o que o agendador registrou de fato. Quem muda `agenda` muda as três.
 *
 * A segunda tabela é o histórico. Sem ela, "o cron rodou?" é uma pergunta sem resposta: ficam
 * aqui a saída e o erro de cada execução, para a ficha da tarefa mostrar os últimos batimentos e
 * para a notificação de falha apontar em que minuto o mundo parou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_tarefas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->text('descricao')->nullable();
            // O comando artisan, com as flags. É texto porque `queue:work --stop-when-empty
            // --max-time=50` é um comando com argumentos e nada aqui precisa ser parseado pelo
            // banco — quem lê é o agendador, e ele valida contra os comandos registrados.
            $table->string('comando', 500);
            // A chave da preset em config/cron.php (everyMinute, daily, weekly...). Guarda a
            // preset, não a expressão, porque a tela precisa reabrir o select na opção certa.
            $table->string('agenda', 20);
            // 'HH:MM' para as presets que têm hora; dia da semana para a semanal. Null nas
            // presets que não usam nada disso (a cada minuto, a cada cinco).
            $table->string('hora', 5)->nullable();
            $table->unsignedTinyInteger('dia_semana')->nullable();
            // Resolvida a partir das três acima. É espelho, não fonte.
            $table->string('expressao', 60);
            $table->boolean('ativa')->default(true);
            // Sem dois workers sobre a mesma tarefa: é isto que impede a poda de apagar metade
            // da tabela porque a execução anterior ainda não acabou.
            $table->boolean('sem_sobreposicao')->default(true);
            $table->boolean('sistema')->default(false);
            // O último batimento, denormalizado de propósito: é o que a listagem lê, e lê sem
            // dar join no histórico. A fonte da verdade continua sendo cron_execucoes.
            $table->timestamp('rodou_em')->nullable();
            // nunca | sucesso | falha | executando (CronTarefa::STATUS). Literal aqui porque
            // migration não consulta modelo: a migration tem que rodar sozinha, mesmo depois de
            // a constante mudar de nome daqui a dois anos.
            $table->string('status', 12)->default('nunca');
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->text('ultima_saida')->nullable();
            $table->timestamps();

            $table->index(['ativa', 'agenda']);
            $table->unique('nome');
        });

        Schema::create('cron_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cron_tarefa_id')->constrained()->cascadeOnDelete();
            // A janela, não um timestamp só: duração é o que diz se a tarefa inchou, e o
            // agendador fecha `fim` quando o processo devolve — inclusive quando falha.
            $table->timestamp('inicio');
            $table->timestamp('fim')->nullable();
            $table->string('status', 12);
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->text('saida')->nullable();
            $table->text('erro')->nullable();
            // agendamento | painel: quem apertou o botão. Muda o aviso — falha de cron vira
            // notificação para a equipe, falha de "rodar agora" só aparece na tela de quem clicou.
            $table->string('acionada_por', 20)->default('agendamento');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['cron_tarefa_id', 'inicio']);
            $table->index(['status', 'inicio']);
        });

        DB::table('cron_tarefas')->insert($this->tarefasDeSistema());
    }

    /**
     * As quatro tarefas que o sistema já fazia no código e passa a fazer daqui.
     *
     * São exatamente as três linhas que estavam fixas em `bootstrap/app.php` mais a poda de
     * notificações, que é nova. Migrar sem semear deixaria a fila parada: sem worker, todo
     * aviso do portal entra na tabela `jobs` e espera para sempre, que é o pior tipo de
     * silêncio — nada quebra, nada reclama, ninguém recebe e-mail.
     */
    private function tarefasDeSistema(): array
    {
        $agora = now()->toDateTimeString();

        return collect([
            [
                'nome' => 'Drenar a fila de envios',
                'descricao' => 'Leva os avisos que entraram em fila para a caixa de entrada de quem os espera. '
                    .'O cron da hospedagem bate a cada minuto e este worker tem 50 segundos de vida: '
                    .'limpa a fila e devolve a janela antes do próximo batimento.',
                'comando' => 'queue:work --stop-when-empty --max-time=50',
                'agenda' => 'everyMinute',
                'expressao' => '* * * * *',
                'sem_sobreposicao' => true,
            ],
            [
                'nome' => 'Sincronizar o Google Ads',
                'descricao' => 'Enfileira a leitura das métricas de cada conta conectada. O painel só lê do cache, '
                    .'que tem meia hora de vida; quinze minutos mantêm a janela sempre preenchida sem dobrar as '
                    .'chamadas à API. Sem credencial, é aviso em vez de falha.',
                'comando' => 'google-ads:sync --agendamento',
                'agenda' => 'everyFifteenMinutes',
                'expressao' => '*/15 * * * *',
                'sem_sobreposicao' => true,
            ],
            [
                'nome' => 'Podar o registro de tráfego',
                'descricao' => 'Apaga visitas e eventos mais velhos que a retenção configurada na tela de '
                    .'configurações. Roda de madrugada, fora do expediente, para não competir com o acesso real.',
                'comando' => 'rastreio:podar',
                'agenda' => 'weekly',
                'hora' => '03:30',
                'dia_semana' => 2,
                'expressao' => '30 3 * * 2',
                'sem_sobreposicao' => true,
            ],
            [
                'nome' => 'Podar as notificações lidas',
                'descricao' => 'Tira da central o que a equipe já leu e o que venceu sem ser lido. É isto que '
                    .'mantém o sino pequeno: notificação velha não desaparece sozinha.',
                'comando' => 'notificacoes:apodrecer',
                'agenda' => 'hourly',
                'hora' => '00:15',
                'expressao' => '15 * * * *',
                'sem_sobreposicao' => true,
            ],
        ])->map(fn (array $tarefa): array => array_merge([
            'ativa' => true,
            'sistema' => true,
            'status' => 'nunca',
            // Todas as linhas com as mesmas colunas, preenchidas com null quando a preset não
            // usa: um insert só, e o MySQL não aceita VALUES de larguras diferentes.
            'hora' => null,
            'dia_semana' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], $tarefa))->all();
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_execucoes');
        Schema::dropIfExists('cron_tarefas');
    }
};
