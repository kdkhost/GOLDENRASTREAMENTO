<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Implanta todas as rotinas reais de cron e remove dados residuais de demonstração.
     */
    public function up(): void
    {
        // 1. Limpeza cirúrgica de dados de demonstração (Orders 1-6 e Customers demo 1-5)
        DB::transaction(function (): void {
            $demoCustomerIds = [1, 2, 3, 4, 5];
            $demoOrderIds = [1, 2, 3, 4, 5, 6];

            // Pedidos de demonstração e tabelas filhas
            DB::table('order_histories')->whereIn('order_id', $demoOrderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $demoOrderIds)->delete();
            DB::table('orders')->whereIn('id', $demoOrderIds)->delete();

            // Clientes de demonstração e tabelas filhas (preserva estritamente clientes reais)
            DB::table('contracts')->whereIn('customer_id', $demoCustomerIds)->delete();
            DB::table('ticket_messages')->whereIn('customer_id', $demoCustomerIds)->delete();
            DB::table('tickets')->whereIn('customer_id', $demoCustomerIds)->delete();
            DB::table('interactions')->whereIn('customer_id', $demoCustomerIds)->delete();
            DB::table('customers')->whereIn('id', $demoCustomerIds)->delete();
        });

        // 2. Registro e sincronização das 11 rotinas reais de sistema
        $agora = now()->toDateTimeString();

        $tarefas = [
            [
                'nome' => 'Drenar a fila de envios',
                'descricao' => 'Leva os avisos que entraram em fila para a caixa de entrada de quem os espera. O cron da hospedagem bate a cada minuto e este worker tem 50 segundos de vida: limpa a fila e devolve a janela antes do próximo batimento.',
                'comando' => 'queue:work --stop-when-empty --max-time=50',
                'agenda' => 'everyMinute',
                'hora' => null,
                'dia_semana' => null,
                'expressao' => '* * * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Sincronizar o Google Ads',
                'descricao' => 'Enfileira a sincronização das métricas das contas conectadas de Google Ads a cada 15 minutos.',
                'comando' => 'google-ads:sync --agendamento',
                'agenda' => 'everyFifteenMinutes',
                'hora' => null,
                'dia_semana' => null,
                'expressao' => '*/15 * * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Sincronizar Clientes com Financeiro (WHMCS)',
                'descricao' => 'Sincroniza dados e fichas de clientes com o financeiro WHMCS todas as manhãs às 06:00.',
                'comando' => 'clientes:puxar-do-financeiro',
                'agenda' => 'daily',
                'hora' => '06:00',
                'dia_semana' => null,
                'expressao' => '00 06 * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Importar Avaliações do Google',
                'descricao' => 'Lê avaliações e depoimentos atualizados do Google Business todas as manhãs às 05:00.',
                'comando' => 'depoimentos:google',
                'agenda' => 'daily',
                'hora' => '05:00',
                'dia_semana' => null,
                'expressao' => '00 05 * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Limpar Tokens de Recuperação Expirados',
                'descricao' => 'Remove tokens expirados de recuperação de senha da tabela password_reset_tokens.',
                'comando' => 'auth:clear-resets',
                'agenda' => 'daily',
                'hora' => '04:00',
                'dia_semana' => null,
                'expressao' => '00 04 * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Podar Jobs Falhos da Fila',
                'descricao' => 'Remove falhas da fila de jobs mais antigas que 7 dias (168 horas) semanalmente aos domingos.',
                'comando' => 'queue:prune-failed --hours=168',
                'agenda' => 'weekly',
                'hora' => '04:00',
                'dia_semana' => 0,
                'expressao' => '00 04 * * 0',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Podar Lotes Concluídos da Fila',
                'descricao' => 'Remove lotes de jobs finalizados há mais de 48 horas da tabela job_batches diariamente às 04:30.',
                'comando' => 'queue:prune-batches --hours=48',
                'agenda' => 'daily',
                'hora' => '04:30',
                'dia_semana' => null,
                'expressao' => '30 04 * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Podar o registro de tráfego',
                'descricao' => 'Apaga visitas e eventos mais velhos que a retenção configurada para manter o banco enxuto.',
                'comando' => 'rastreio:podar',
                'agenda' => 'weekly',
                'hora' => '03:30',
                'dia_semana' => 2,
                'expressao' => '30 03 * * 2',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Podar as notificações lidas',
                'descricao' => 'Tira da central o que a equipe já leu e o que venceu sem ser lido a cada hora no minuto 15.',
                'comando' => 'notificacoes:apodrecer',
                'agenda' => 'hourly',
                'hora' => '00:15',
                'dia_semana' => null,
                'expressao' => '15 * * * *',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Podar Logs Antigos de Defesa Militar',
                'descricao' => 'Remove logs e eventos perimetrais de defesa militar anteriores a 30 dias semanalmente.',
                'comando' => 'seguranca:podar-logs',
                'agenda' => 'weekly',
                'hora' => '03:00',
                'dia_semana' => 0,
                'expressao' => '00 03 * * 0',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
            [
                'nome' => 'Atualizar Base de Conhecimento da Ana (IA)',
                'descricao' => 'Atualiza produtos, regras e perguntas frequentes do catálogo para a assistente virtual aos domingos.',
                'comando' => 'assistente:ensinar --atualizar',
                'agenda' => 'weekly',
                'hora' => '02:00',
                'dia_semana' => 0,
                'expressao' => '00 02 * * 0',
                'sem_sobreposicao' => true,
                'sistema' => true,
            ],
        ];

        foreach ($tarefas as $dados) {
            $existente = DB::table('cron_tarefas')->where('comando', $dados['comando'])->first();

            if ($existente) {
                DB::table('cron_tarefas')->where('id', $existente->id)->update([
                    'nome' => $dados['nome'],
                    'descricao' => $dados['descricao'],
                    'agenda' => $dados['agenda'],
                    'hora' => $dados['hora'],
                    'dia_semana' => $dados['dia_semana'],
                    'expressao' => $dados['expressao'],
                    'sem_sobreposicao' => $dados['sem_sobreposicao'],
                    'sistema' => true,
                    'ativa' => true,
                    'updated_at' => $agora,
                ]);
            } else {
                DB::table('cron_tarefas')->insert([
                    'nome' => $dados['nome'],
                    'descricao' => $dados['descricao'],
                    'comando' => $dados['comando'],
                    'agenda' => $dados['agenda'],
                    'hora' => $dados['hora'],
                    'dia_semana' => $dados['dia_semana'],
                    'expressao' => $dados['expressao'],
                    'sem_sobreposicao' => $dados['sem_sobreposicao'],
                    'sistema' => true,
                    'ativa' => true,
                    'status' => 'nunca',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Reversão não remove dados reais
    }
};
