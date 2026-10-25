<?php

namespace Database\Seeders;

use App\Models\FunnelBlock;
use App\Models\FunnelPage;
use Illuminate\Database\Seeder;

/**
 * Uma campanha pronta para o operador revisar e anunciar: os nove blocos do funil,
 * na ordem de leitura, com o texto do produto real da Golden.
 *
 * Preço e mensalidade ficam em branco de propósito — a régua da empresa é "valores
 * na proposta comercial" (config('golden')['planos']), e os pixels também são vazios
 * porque a chave de cada conta é do anunciante. O painel preenche os dois.
 */
class FunnelSeeder extends Seeder
{
    public function run(): void
    {
        $pagina = FunnelPage::firstOrCreate(
            ['slug' => 'frota-j16'],
            [
                'nome' => 'Campanha de frota — J16 4G/2G',
                'status' => 'publicada',
                'visibilidade' => 'noindex',
                'pele' => 'ouro',
                'titulo' => 'Rastrear e bloquear a frota pelo celular — kit com chips M2M inclusos',
                'meta_description' => 'Rastreador J16 4G/2G com bloqueio remoto, cercas virtuais e histórico de rota. Kits de 5, 10 ou mais veículos, com chips M2M. Peça a proposta da sua frota.',
                'meta_keywords' => 'rastreador de veículo, rastreamento de frota, bloqueio remoto, j16 4g, chip m2m, três pontas',
                'og_imagem' => 'img/home/segmento-frota.jpg',
                'og_imagem_alt' => 'Frota de veículos acompanhada em uma tela de monitoramento',
                'nota_media' => 5,
                'num_avaliacoes' => 3,
                'nota_legal' => 'Página de campanha. Equipamento, mensalidade de conectividade e instalação são detalhados na proposta comercial; a instalação é contratada pelo cliente.',
            ]
        );

        if ($pagina->blocks()->count() > 0) {
            return;
        }

        $kits = collect(config('golden.kits'))->map(fn (array $k): string => implode('|', [
            $k['eyebrow'].' — '.$k['titulo'],
            '',
            '',
            implode(';', $k['itens']),
            $k['rotulo'] ?? '',
        ]))->implode("\n");

        $duvidas = collect(config('golden.faq'))->take(6)
            ->map(fn (array $f): string => $f['q'].'|'.$f['a'])->implode("\n");

        $blocos = [
            [
                'tipo' => 'hero',
                'titulo' => 'Sua frota no mapa, em uma tela só',
                'texto' => 'Rastreador J16 4G/2G com bloqueio remoto, cerca virtual e histórico de rota. Kit de 5, 10 ou mais veículos, com os chips M2M já inclusos.',
                'imagem' => 'img/home/hero-monitoramento.jpg',
                'imagem_alt' => 'Veículos de uma frota acompanhados em tempo real na plataforma Golden',
                'itens' => "fa-shield-halved|Bloqueio remoto pela plataforma\nfa-mobile-screen|App no celular e plataforma web\nfa-tower-cell|Chips M2M inclusos no kit\nfa-location-crosshairs|Cercas virtuais e alertas de rota",
                'cta_texto' => 'Quero a proposta da minha frota',
                'cta_destino' => '#formulario',
                'cta2_texto' => 'Falar com a equipe no WhatsApp',
                'cta2_destino' => wa_url('Olá, Golden! Vim pela página da campanha de frota e quero a proposta do kit de rastreadores.'),
            ],
            [
                'tipo' => 'beneficios',
                'titulo' => 'O que você passa a controlar',
                'texto' => 'As quatro leituras que a operação precisa todos os dias.',
                'itens' => "fa-map-location-dot|Posição em tempo real|Cada veículo no mapa, com a última leitura e o trajeto do dia.\nfa-lock|Bloqueio na hora|Bloqueie e desbloqueie pelo app quando a rota sair do combinado.\nfa-draw-polygon|Cerca virtual|Alerta quando o veículo entra ou sai de um raio desenhado por você.\nfa-file-lines|Relatórios por período|Kilometragem, paradas e serviços para conferir a conta do mês.",
            ],
            [
                'tipo' => 'oferta',
                'titulo' => 'Kits para frota de qualquer tamanho',
                'texto' => 'Equipamento e mensalidade de conectividade são informados separadamente na proposta — sem surpresa na fatura.',
                'itens' => $kits,
                'cta_texto' => 'Pedir a proposta deste kit',
                'cta_destino' => '#formulario',
            ],
            [
                'tipo' => 'video',
                'titulo' => 'Veja a plataforma funcionando',
                'texto' => 'Dois minutos pelo app Golden: mapa, bloqueio, alertas e relatórios.',
                'cta_destino' => 'https://www.youtube.com/watch?v=OhtdphIxdf0',
            ],
            [
                'tipo' => 'prova',
                'titulo' => 'Quem já usa conta',
                'itens' => "Douglas|Cliente|Ótima empresa de segurança! Atendimento top e rapidez na configuração.|5\nJonatha|Cliente|Testando o chip e a plataforma até agora está tudo certo. Destaco a atenção dada pelo suporte.|5\nMarcelle|Três Pontas - MG|Agora tenho no meu carro, e posso bloquear em caso de roubo, tenho enfim uma proteção extra no meu veículo.|5",
            ],
            [
                'tipo' => 'urgencia',
                'titulo' => 'Janela desta campanha',
                'texto' => 'Esta página fica no ar com a condição dela até a data abaixo. Depois, o link sai do ar e o cadastro entra na agenda seguinte.',
                'limite_em' => now()->addDays(14)->setTime(18, 0),
                'cta_texto' => 'Enviar meu cadastro agora',
                'cta_destino' => '#formulario',
            ],
            [
                'tipo' => 'formulario',
                'titulo' => 'Receba a proposta para a sua frota',
                'texto' => 'Sem compromisso. Os dados servem só para montar a proposta do kit.',
                'cta_texto' => 'Quero a proposta',
            ],
            [
                'tipo' => 'faq',
                'titulo' => 'Antes de pedir a proposta',
                'itens' => $duvidas,
            ],
            [
                'tipo' => 'conteudo',
                'titulo' => 'Como o kit chega até você',
                'corpo' => '<p>Você escolhe a quantidade de veículos, recebe os rastreadores com os chips M2M já programados e instala com o profissional de sua confiança. A partir da ativação, toda a frota aparece na plataforma web e no app.</p><ul><li>Equipamento enviado para todo o Brasil</li><li>Ativação e suporte acompanhados pela equipe de Três Pontas</li><li>Compatibilidade confirmada antes do envio</li></ul>',
                'imagem' => 'img/home/plataforma-monitor.jpg',
                'imagem_alt' => 'Tela da plataforma Golden com o mapa dos veículos monitorados',
                'cta_texto' => 'Tirar uma dúvida no WhatsApp',
                'cta_destino' => wa_url('Olá, Golden! Tenho uma dúvida antes de pedir a proposta do kit de rastreadores.'),
            ],
        ];

        foreach ($blocos as $i => $bloco) {
            FunnelBlock::create($bloco + [
                'funnel_page_id' => $pagina->id,
                'ordem' => $i + 1,
                'ativa' => true,
            ]);
        }
    }
}
