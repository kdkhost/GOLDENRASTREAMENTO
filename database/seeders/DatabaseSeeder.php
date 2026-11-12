<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        // Módulos integrados do sistema: Páginas de erro, ERP (catálogo), CMS, Funil e Campanhas
        $this->call([
            ErrorPageSeeder::class,
            CatalogSeeder::class,
            CmsSeeder::class,
            FunnelSeeder::class,
            CampanhaMultiOperadoraSeeder::class,
        ]);
    }
}
