<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\Video;
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

        // Galeria de vídeos tutoriais: os tutoriais que já estão no ar no canal da
        // Golden, agrupados pelas categorias de config('golden.video_categorias'). O
        // título não tem numeração de passo — a ordem de leitura é o campo 'ordem'.
        if (Video::count() === 0) {
            $videos = [
                ['OhtdphIxdf0', 'aplicativo', 'Conheça o app Golden'],
                ['WlYkoiqhZzI', 'aplicativo', 'Veja o histórico completo do seu veículo'],
                ['JO3SKuPrkO0', 'aplicativo', 'Como habilitar ou desabilitar os alertas'],
                ['O4RUKHo1K5A', 'aplicativo', 'Informações do veículo: tudo o que você precisa ver'],
                ['Lm_9Xmx5-Pw', 'aplicativo', 'Como usar o Google Street View e o Google Maps no app'],
                ['asxS978JC94', 'aplicativo', 'Como compartilhar o acesso do seu rastreador'],
                ['dysoTH36NWQ', 'aplicativo', 'Suporte Golden: onde encontrar no app'],
                ['ZejNzrvtI8A', 'aplicativo', 'Como alterar sua senha'],
                ['XR7xaBQu60k', 'plataforma', 'Bloqueio e desbloqueio de um carro pela plataforma'],
                ['8SWRF1gijT4', 'plataforma', 'Como pegar o histórico de um veículo'],
                ['kvXDDEtE_Q8', 'plataforma', 'Como criar alertas personalizados e cercas virtuais'],
                ['h_ETZlJNhNU', 'plataforma', 'Como fazer relatórios básicos'],
                ['eoYFZdG0KVI', 'plataforma', 'Como fazer relatórios avançados'],
                ['v0iu584YpmQ', 'plataforma', 'Despesas, serviços e manutenção'],
                ['_Myu84LVlNA', 'plataforma', 'Como alterar dados do veículo'],
                ['zhSFHX6RIK0', 'frotas', 'Cercas virtuais'],
                ['QZBn-bajvec', 'frotas', 'Como criar limite de velocidade usando cercas virtuais'],
                ['siGg6aXh6rQ', 'frotas', 'Como criar despesas para seu veículo na plataforma'],
                ['H7c7pS7gfKU', 'frotas', 'Como realizar a manutenção do seu veículo?'],
                ['r35J4su4o9w', 'bloqueio', 'Como usar o bloqueio e a âncora no app'],
                ['dE5DGijpdR4', 'bloqueio', 'Bloqueio, desbloqueio e relatório de entrega de SMS'],
                ['R8mdYgE4XdA', 'financeiro', 'Como se cadastrar no financeiro'],
                ['hYGCvyJkwLo', 'financeiro', 'Como gerar e pagar a fatura'],
                ['EZ8Hual-0iI', 'financeiro', 'Como pegar a fatura direto pelo aplicativo'],
            ];

            foreach ($videos as $i => [$codigo, $categoria, $titulo]) {
                Video::create([
                    'codigo' => $codigo,
                    'categoria' => $categoria,
                    'titulo' => $titulo,
                    'ordem' => $i + 1,
                    'ativa' => true,
                ]);
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
