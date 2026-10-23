<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@golden.com.br'],
            [
                'name' => 'Administrador Golden',
                'password' => Hash::make('golden123'),
                'role' => 'admin',
                'status' => 'ativo',
                'telefone' => '(19) 99710-1483',
            ]
        );

        User::firstOrCreate(
            ['email' => 'vendedor@golden.com.br'],
            [
                'name' => 'Ana Vendedora',
                'password' => Hash::make('golden123'),
                'role' => 'vendedor',
                'status' => 'ativo',
            ]
        );

        Setting::set('site_nome', 'Golden Rastreamento');
        Setting::set('contato_whatsapp', env('GOLDEN_WHATSAPP', '5519997101483'));
        Setting::set('contato_telefone', '(19) 99710-1483 · (19) 97139-5129');
        Setting::set('contato_email', env('GOLDEN_EMAIL', 'comercial@goldenrastreamento.com.br'));
        Setting::set('empresa_endereco', 'R. Pref. Olinto Réis Campos, 104 - Centro');
        Setting::set('empresa_cidade', 'Três Pontas - MG');
        Setting::set('empresa_cep', '37185-016');
        Setting::set('tracking_enabled', env('GOLDEN_TRACKING_ENABLED', '0'));
        Setting::set('loja_ativa', '1');
        Setting::set('loja_livre', '1');

        $google = Campaign::firstOrCreate(
            ['name' => 'Google Ads — Frota', 'source' => 'google', 'medium' => 'cpc'],
            ['channel' => 'pago', 'content' => 'pesquisa-gen']
        );
        $meta = Campaign::firstOrCreate(
            ['name' => 'Meta Ads — Motoristas', 'source' => 'facebook', 'medium' => 'paid-social'],
            ['channel' => 'pago', 'content' => 'carrossel-j16']
        );
        $organico = Campaign::firstOrCreate(
            ['name' => 'Orgânico / Indicação', 'source' => null, 'medium' => 'direto'],
            ['channel' => 'organico']
        );

        if (Lead::count() === 0) {
            $campanhas = [$google, $meta, $organico];
            $perfis = ['motorista', 'empresa', 'locadora', 'rural', 'outro'];
            $kits = ['Kit 5 veículos', 'Kit 10 veículos', 'Mais de 10 (sob consulta)', 'Unidade avulsa'];
            $statuses = ['novo', 'novo', 'contatado', 'contatado', 'proposta', 'vendido', 'perdido'];

            foreach (range(1, 42) as $i) {
                $camp = $campanhas[array_rand($campanhas)];
                $status = $statuses[array_rand($statuses)];
                $criado = now()->subDays(rand(0, 45))->subHours(rand(0, 23));

                $lead = Lead::create([
                    'name' => $this->nome(),
                    'phone' => '(3' . rand(1, 9) . ') 9' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'profile' => $perfis[array_rand($perfis)],
                    'fleet_size' => [1, 5, 10, '10+'][array_rand([1, 5, 10, '10+'])],
                    'kit' => $kits[array_rand($kits)],
                    'campaign_id' => $camp->id,
                    'utm_source' => $camp->source,
                    'utm_medium' => $camp->medium,
                    'utm_campaign' => $camp->name,
                    'gclid' => $camp->source === 'google' ? Str::random(12) : null,
                    'fbclid' => $camp->source === 'facebook' ? Str::random(12) : null,
                    'visitor_id' => Str::random(24),
                    'status' => $status,
                    'consent_given' => true,
                    'value' => $status === 'vendido' ? rand(5, 40) * 100000 : null,
                    'won_at' => $status === 'vendido' ? $criado->copy()->addDays(rand(1, 6)) : null,
                    'lost_at' => $status === 'perdido' ? $criado->copy()->addDays(rand(1, 6)) : null,
                    'owner_id' => $admin->id,
                    'created_at' => $criado,
                    'updated_at' => $criado,
                ]);

                LeadStatusHistory::create([
                    'lead_id' => $lead->id,
                    'from_status' => null,
                    'to_status' => 'novo',
                    'note' => 'Lead recebido pelo site.',
                    'created_at' => $criado,
                ]);

                if ($status !== 'novo') {
                    LeadStatusHistory::create([
                        'lead_id' => $lead->id,
                        'from_status' => 'novo',
                        'to_status' => $status,
                        'note' => 'Atualização de funil.',
                        'user_id' => $admin->id,
                        'created_at' => $criado->copy()->addDays(1),
                    ]);
                }
            }
        }

        // Módulos integrados: ERP (catálogo), CMS, CRM e pedidos de demonstração
        $this->call([
            CatalogSeeder::class,
            CmsSeeder::class,
            CrmSeeder::class,
            OrdersSeeder::class,
        ]);
    }

    private function nome(): string
    {
        $nomes = ['Carlos Mendes', 'Mariana Silva', 'João Pedro Alves', 'Fernanda Rocha', 'Rafael Souza', 'Juliana Costa', 'Bruno Ferreira', 'Patrícia Gomes', 'Diego Martins', 'Camila Barbosa', 'Lucas Ramos', 'Beatriz Lima', 'Eduardo Nunes', 'Larissa Melo', 'Gustavo Pinto'];
        return $nomes[array_rand($nomes)];
    }
}
