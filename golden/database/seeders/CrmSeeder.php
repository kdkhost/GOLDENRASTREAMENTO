<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        // Converte leads já vendidos em clientes do CRM (idempotente)
        $vendidos = Lead::where('status', 'vendido')->whereNull('customer_id')->get();
        foreach ($vendidos as $lead) {
            $cliente = Customer::deLead($lead);
            Interaction::create([
                'customer_id' => $cliente->id,
                'tipo' => 'whatsapp',
                'assunto' => 'Boas-vindas',
                'descricao' => 'Contato inicial de ativação do rastreamento.',
                'concluida' => true,
            ]);
        }

        // Prospecto adicional com pipeline, para demonstrar o CRM
        if (Customer::where('status', 'prospecto')->count() === 0) {
            $p = Customer::create([
                'nome' => 'TransLog Frotas Ltda',
                'tipo' => 'juridica',
                'documento' => '12.345.678/0001-90',
                'telefone' => '(35) 3521-0000',
                'email' => 'gestao@translogexemplo.com.br',
                'cidade' => 'Três Pontas',
                'uf' => 'MG',
                'segmento' => 'empresa',
                'frota_tamanho' => 18,
                'status' => 'prospecto',
            ]);
            Interaction::create([
                'customer_id' => $p->id,
                'tipo' => 'ligacao',
                'assunto' => 'Primeiro contato',
                'descricao' => 'Quer cotação para 18 veículos.',
                'concluida' => true,
            ]);
            Interaction::create([
                'customer_id' => $p->id,
                'tipo' => 'email',
                'assunto' => 'Proposta enviada',
                'descricao' => 'Kit 20 rastreadores + planos.',
                'concluida' => false,
                'proxima_acao_em' => now()->addDays(2),
            ]);
        }
    }
}
