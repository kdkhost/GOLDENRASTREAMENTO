<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        // Depoimentos reais coletados do site oficial
        if (Testimonial::count() === 0) {
            $depoimentos = [
                ['nome' => 'Douglas', 'papel' => 'Cliente', 'texto' => 'Ótima empresa de segurança! Atendimento top e rapidez na configuração.', 'nota' => 5, 'ordem' => 1],
                ['nome' => 'Jonatha', 'papel' => 'Cliente', 'texto' => 'Testando o chip e a plataforma até agora está tudo certo. Destaco a atenção dada pelo suporte.', 'nota' => 5, 'ordem' => 2],
                ['nome' => 'Marcelle', 'papel' => 'Cliente', 'cidade' => 'Três Pontas - MG', 'texto' => 'Agora tenho no meu carro, e posso bloquear em caso de roubo, tenho enfim uma proteção extra no meu veículo.', 'nota' => 5, 'ordem' => 3],
            ];
            foreach ($depoimentos as $d) {
                Testimonial::create($d + ['aprovado' => true]);
            }
        }

        // FAQs a partir do config (fallback) para permitir edição no painel
        if (Faq::count() === 0) {
            foreach (config('golden.faq', []) as $i => $f) {
                Faq::create(['pergunta' => $f['q'], 'resposta' => $f['a'], 'ordem' => $i, 'ativa' => true]);
            }
        }

        // Páginas institucionais do CMS
        if (Page::count() === 0) {
            Page::create([
                'titulo' => 'Sobre a Golden Rastreamento',
                'slug' => 'sobre',
                'eyebrow' => 'Quem somos',
                'intro' => 'Rastreamento e monitoramento veicular, sua segurança nossa prioridade.',
                'corpo' => '<p>A Golden Rastreamento oferece rastreamento terrestre e via satélite para veículos leves, motos e linha pesada. Sua frota protegida 24h por 365 dias.</p><h2>O que fazemos</h2><ul><li>Localização em tempo real pelo aplicativo e plataforma web</li><li>Bloqueio remoto com apenas um clique, quando instalado com o chicote/relé adequado</li><li>Cercas virtuais, alertas e relatórios completos</li><li>Chips M2M dos parceiros Vivo, Claro, Algar e TIM</li></ul><p>Estamos na R. Pref. Olinto Réis Campos, 104 - Centro, Três Pontas - MG. Pagamento por cartão ou boleto.</p>',
                'exibir_menu' => true,
                'ordem_menu' => 5,
                'publicada' => true,
            ]);

            Page::create([
                'titulo' => 'Segurança e LGPD',
                'slug' => 'seguranca',
                'eyebrow' => 'Confiança',
                'intro' => 'Como tratamos os dados e o equipamento dos seus veículos.',
                'corpo' => '<p>Utilizamos pseudonimização de IP, consentimento explícito e controle de cookies conforme a LGPD. O rastreador J16 é homologado pela Anatel e opera em rede 4G/2G.</p><p>A Golden não presta vigilância humana, pronta resposta nem seguro.</p>',
                'exibir_menu' => false,
                'ordem_menu' => 9,
                'publicada' => true,
            ]);
        }
    }
}
