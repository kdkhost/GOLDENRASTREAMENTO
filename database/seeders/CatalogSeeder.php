<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            ['nome' => 'Rastreadores', 'slug' => 'rastreadores', 'descricao' => 'Equipamentos J16 4G/2G', 'ordem' => 1],
            ['nome' => 'Chips M2M', 'slug' => 'chips-m2m', 'descricao' => 'Planos de conectividade', 'ordem' => 2],
            ['nome' => 'Acessórios', 'slug' => 'acessorios', 'descricao' => 'Botão de pânico, relés e chicotes', 'ordem' => 3],
            ['nome' => 'Planos de monitoramento', 'slug' => 'planos', 'descricao' => 'Mensalidades da plataforma', 'ordem' => 4],
        ];
        $ids = [];
        foreach ($cats as $c) {
            $cat = Category::firstOrCreate(['slug' => $c['slug']], $c);
            $ids[$c['slug']] = $cat->id;
        }

        // Preços de referência (edite no painel; a Golden não publica valores fixos no site).
        $produtos = [
            ['nome' => 'Rastreador veicular J16 4G/2G', 'slug' => 'rastreador-j16-4g', 'sku' => 'J16-4G', 'cat' => 'rastreadores', 'preco' => 29900, 'mensal' => 0, 'estoque' => 40, 'destaque' => true,
             'resumo' => 'Rastreamento e bloqueio remoto em veículos leves, com chip M2M.',
             'descricao' => "Rastreador J16 4G/2G homologado Anatel.\n\n• Localização em tempo real\n• Bloqueio remoto (com chicote/relé)\n• Cercas virtuais e alertas\n• Compatível com app e plataforma web"],
            ['nome' => 'Rastreador para moto J16', 'slug' => 'rastreador-moto-j16', 'sku' => 'J16-MOTO', 'cat' => 'rastreadores', 'preco' => 27900, 'estoque' => 25,
             'resumo' => 'Versão compacta e resistente à vibração para motocicletas.',
             'descricao' => "Rastreador J16 para motos, à prova de vibração e intempéries, com rede 4G/2G."],
            ['nome' => 'Chip M2M — Vivo', 'slug' => 'chip-m2m-vivo', 'sku' => 'M2M-VIVO', 'cat' => 'chips-m2m', 'preco' => 3900, 'mensal' => 2990, 'estoque' => 60,
             'resumo' => 'SIM M2M para dados de rastreamento na rede Vivo.',
             'descricao' => "Chip M2M para rastreadores na rede Vivo. Cobertura 4G/2G nacional."],
            ['nome' => 'Chip M2M — Claro', 'slug' => 'chip-m2m-claro', 'sku' => 'M2M-CLARO', 'cat' => 'chips-m2m', 'preco' => 3900, 'mensal' => 2990, 'estoque' => 60,
             'resumo' => 'SIM M2M na rede Claro.', 'descricao' => "Chip M2M para rastreadores na rede Claro. Cobertura 4G/2G nacional."],
            ['nome' => 'Chip M2M — Algar', 'slug' => 'chip-m2m-algar', 'sku' => 'M2M-ALGAR', 'cat' => 'chips-m2m', 'preco' => 3900, 'mensal' => 2990, 'estoque' => 40,
             'resumo' => 'SIM M2M na rede Algar, ótimo no interior.', 'descricao' => "Chip M2M na rede Algar. Boa cobertura em Minas Gerais e região."],
            ['nome' => 'Chip M2M — TIM', 'slug' => 'chip-m2m-tim', 'sku' => 'M2M-TIM', 'cat' => 'chips-m2m', 'preco' => 3900, 'mensal' => 2990, 'estoque' => 40,
             'resumo' => 'SIM M2M na rede TIM.', 'descricao' => "Chip M2M para rastreadores na rede TIM. Cobertura 4G/2G nacional."],
            ['nome' => 'Botão de pânico', 'slug' => 'botao-de-panico', 'sku' => 'ACE-PANICO', 'cat' => 'acessorios', 'preco' => 4900, 'estoque' => 30,
             'resumo' => 'Acionamento de emergência ligado ao rastreador.', 'descricao' => "Botão de pânico sem fio integrado ao rastreador para alertas imediatos."],
            ['nome' => 'Relé de bloqueio', 'slug' => 'rele-de-bloqueio', 'sku' => 'ACE-RELE', 'cat' => 'acessorios', 'preco' => 5900, 'estoque' => 35,
             'resumo' => 'Habilita o bloqueio remoto do motor.', 'descricao' => "Relé para bloqueio remoto da partida. Instalar com profissional de confiança."],
            ['nome' => 'Kit chicote de instalação', 'slug' => 'kit-chicote', 'sku' => 'ACE-CHICOTE', 'cat' => 'acessorios', 'preco' => 3500, 'estoque' => 50,
             'resumo' => 'Chicote completo para instalação do J16.', 'descricao' => "Chicote de instalação com fusíveis para o rastreador J16."],
            ['nome' => 'Plano Frota (por veículo)', 'slug' => 'plano-frota', 'sku' => 'PLANO-FROTA', 'cat' => 'planos', 'preco' => 0, 'mensal' => 5990, 'estoque' => 999, 'controle' => false, 'destaque' => true,
             'resumo' => 'Monitoramento 24h por 365 dias, por veículo.', 'descricao' => "Plano de monitoramento com plataforma web, app, cercas virtuais e relatórios. Cobrado por veículo/mês."],
        ];

        foreach ($produtos as $p) {
            $produto = Product::firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'category_id' => $ids[$p['cat']] ?? null,
                    'nome' => $p['nome'],
                    'sku' => $p['sku'],
                    'resumo' => $p['resumo'],
                    'descricao' => $p['descricao'],
                    'preco_cents' => $p['preco'],
                    'mensalidade_cents' => $p['mensal'] ?? null,
                    'estoque' => $p['estoque'],
                    'estoque_minimo' => 5,
                    'destaque' => $p['destaque'] ?? false,
                    'ativo' => true,
                    'controle_estoque' => $p['controle'] ?? true,
                ]
            );

            if ($produto->wasRecentlyCreated && $produto->controle_estoque && $produto->estoque > 0) {
                StockMovement::registrar($produto, 'entrada', $produto->estoque, 'Estoque inicial (carga de demonstração)');
            }
        }

        if (Supplier::count() === 0) {
            Supplier::insert([
                ['nome' => 'Vivo (Telefônica)', 'contato' => 'Cobertura M2M', 'created_at' => now(), 'updated_at' => now()],
                ['nome' => 'Claro', 'contato' => 'Cobertura M2M', 'created_at' => now(), 'updated_at' => now()],
                ['nome' => 'Algar Telecom', 'contato' => 'Cobertura M2M', 'created_at' => now(), 'updated_at' => now()],
                ['nome' => 'TIM', 'contato' => 'Cobertura M2M', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
