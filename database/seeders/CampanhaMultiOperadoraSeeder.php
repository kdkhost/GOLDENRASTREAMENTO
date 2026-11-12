<?php

namespace Database\Seeders;

use App\Models\FunnelPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Campanha Google Ads — Rastreador J6 Multi-Operadora
 *
 * Página de alta conversão para tráfego pago (Google Ads) no endereço /c/google-ads-multi-operadora.
 * Apresenta o Rastreador Veicular J6 4G com chip M2M multi-operadora (Vivo, Claro, TIM, Algar),
 * imagens coerentes em alta resolução, prova social, vídeo, formulário de captura completo e FAQ.
 */
class CampanhaMultiOperadoraSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $pagina = FunnelPage::updateOrCreate(
                ['slug' => 'google-ads-multi-operadora'],
                [
                    'nome' => 'Campanha Google Ads — Rastreador J6 Multi-Operadora',
                    'status' => 'publicada',
                    'visibilidade' => 'noindex',
                    'pele' => 'ouro',
                    'titulo' => 'Rastreador Veicular J6 4G com Chip Multi-Operadora e Bloqueio pelo Celular | Golden Rastreamento',
                    'meta_description' => 'Proteja seu carro, moto ou frota com o Rastreador J6 4G de alta precisão e chip multi-operadora (Vivo, Claro, TIM e Algar). Bloqueio remoto pelo celular, rastreamento 24h e suporte rápido.',
                    'meta_keywords' => 'rastreador j6, rastreador j6 4g, rastreador j16, rastreamento veicular, rastreador com chip multioperadora, rastreador com bloqueio, golden rastreamento',
                    'og_imagem' => 'img/home/hero-monitoramento.jpg',
                    'og_imagem_alt' => 'Rastreador J6 4G com monitoramento veicular em tempo real',
                    'preco_cents' => null,
                    'nota_media' => 5.0,
                    'num_avaliacoes' => 28,
                    'nota_legal' => 'Página oficial de campanha Golden Rastreamento. Equipamento homologado pela Anatel. Condições de conectividade multi-operadora e instalação informadas na proposta comercial.',
                ]
            );

            // Substitui ou recria os blocos garantindo integridade e dados sempre atualizados
            $pagina->blocks()->delete();

            $blocos = [
                // 1. HERO - Abertura com promessa de valor forte para o visitante de anúncio
                [
                    'tipo' => 'hero',
                    'titulo' => 'Rastreador J6 4G com Chip Multi-Operadora e Bloqueio pelo Celular',
                    'texto' => 'Proteja seu carro, moto ou frota contra roubo e furto. Localização precisa em tempo real na palma da sua mão com cobertura nacional multi-operadora (Vivo, Claro, TIM e Algar) sem áreas de sombra.',
                    'imagem' => 'img/home/hero-monitoramento.jpg',
                    'imagem_alt' => 'Central de rastreamento veicular e mapa em tempo real Golden Rastreamento',
                    'itens' => "fa-shield-halved|Bloqueio antifurto imediato via aplicativo\nfa-tower-cell|Chip M2M multi-operadora (Vivo, Claro, TIM, Algar)\nfa-mobile-screen|App exclusivo iOS/Android e Plataforma Web\nfa-microchip|Rastreador J6 4G homologado pela Anatel",
                    'cta_texto' => 'Solicitar Proposta Agora',
                    'cta_destino' => '#formulario',
                    'cta2_texto' => 'Falar no WhatsApp',
                    'cta2_destino' => wa_url('Olá! Vim pela campanha do Rastreador J6 Multi-Operadora e quero solicitar uma proposta.'),
                ],

                // 2. BENEFÍCIOS - Os 4 pilares essenciais da solução
                [
                    'tipo' => 'beneficios',
                    'titulo' => 'Por que o Rastreador J6 Multi-Operadora é a sua melhor escolha?',
                    'texto' => 'Tecnologia de ponta pensada para quem não pode ficar sem sinal e exige resposta imediata em qualquer situação.',
                    'itens' => "fa-map-location-dot|Localização em Tempo Real 24/7|Acompanhe seu veículo no mapa com alta precisão e atualização contínua pelo app no celular ou no computador.\nfa-lock|Bloqueio Antifurto Imediato|Em caso de emergência, envie o comando de bloqueio do motor diretamente pelo aplicativo para imobilizar o veículo com segurança.\nfa-tower-cell|Multi-Operadora Sem Ponto Cego|Conexão inteligente com as maiores operadoras do país (Vivo, Claro, TIM e Algar), buscando sempre a torre com melhor sinal.\nfa-draw-polygon|Cercas Virtuais e Alertas Instantâneos|Crie perímetros de segurança e receba alertas imediatos no celular se o veículo sair da rota, ligar a ignição ou sofrer violação.",
                ],

                // 3. CONTEÚDO 1 - Destaque do Rastreador J6 com foto do equipamento
                [
                    'tipo' => 'conteudo',
                    'titulo' => 'Rastreador J6 4G: Alta Tecnologia, Precisão e Máxima Discrição',
                    'imagem' => 'img/home/rastreador-j6.jpg',
                    'imagem_alt' => 'Rastreador veicular J6 4G homologado com chicote de instalação profissional',
                    'corpo' => '<p>O <strong>Rastreador J6 4G</strong> é o dispositivo definitivo em rastreamento e telemetria veicular. Compacto, robusto e de fácil instalação, ele é ideal para carros de passeio, motocicletas, frotas comerciais, caminhonetes e caminhões.</p><ul><li><strong>Comunicação 4G com fallback 2G automático:</strong> máxima velocidade na transmissão de dados e compatibilidade em qualquer região do Brasil.</li><li><strong>Bateria interna de backup:</strong> continua transmitindo a localização e emite alertas mesmo se a bateria principal do veículo for desligada ou violada.</li><li><strong>Bloqueio remoto com relé de corte:</strong> comando de imobilização segura da partida direto pelo app.</li><li><strong>Consumo inteligente de energia:</strong> tecnologia de gerenciamento que não sobrecarrega nem descarrega a bateria do seu veículo.</li><li><strong>Homologado Anatel:</strong> segurança e confiabilidade de um equipamento certificado nos padrões nacionais.</li></ul><p><small>*Equipamento compacto que permite instalação oculta e discreta, preservando a originalidade e o acabamento do seu veículo.</small></p>',
                    'cta_texto' => 'Consultar o Rastreador J6 para Meu Veículo',
                    'cta_destino' => '#formulario',
                ],

                // 4. CONTEÚDO 2 - Cobertura Multi-Operadora Inteligente
                [
                    'tipo' => 'conteudo',
                    'titulo' => 'Conectividade Multi-Operadora: Nunca Mais Fique Sem Sinal',
                    'imagem' => 'img/home/estrada-por-do-sol.jpg',
                    'imagem_alt' => 'Veículo trafegando em rodovia conectado com chip multi-operadora',
                    'corpo' => '<p>Rastreadores comuns com chip de uma única operadora deixam você no escuro assim que você entra em uma área de sombra ou viaja por rodovias estaduais e federais.</p><p>A Golden resolveu esse problema com o <strong>Chip M2M Multi-Operadora Inteligente</strong>. O sistema opera com as maiores redes do país — <strong>Vivo, Claro, TIM e Algar Telecom</strong> — selecionando a torre de transmissão com sinal mais forte na sua região de deslocamento.</p><ul><li><strong>Cobertura em todo o Brasil:</strong> monitoramento confiável tanto em grandes centros urbanos quanto no interior e estradas.</li><li><strong>Transmissão ininterrupta:</strong> dados de telemetria, ignição e alertas enviados em tempo real para a nuvem.</li><li><strong>Sem surpresas na fatura:</strong> plano de conectividade com valor fixo e previsível acordado na sua proposta.</li></ul><p>Seja para o seu carro pessoal no dia a dia ou para sua frota que roda milhares de quilômetros, viaje com a tranquilidade de estar sempre conectado.</p>',
                    'cta_texto' => 'Quero Cobertura Multi-Operadora',
                    'cta_destino' => '#formulario',
                ],

                // 5. CONTEÚDO 3 - Plataforma Web & Aplicativo Mobile
                [
                    'tipo' => 'conteudo',
                    'titulo' => 'Seu Veículo na Palma da Mão pelo Celular ou Computador',
                    'imagem' => 'img/home/plataforma-monitor.jpg',
                    'imagem_alt' => 'Plataforma Golden de rastreamento veicular em monitor de computador e app mobile',
                    'corpo' => '<p>Acompanhe tudo em tempo real através do aplicativo intuitivo para <strong>Android e iPhone</strong> ou pela nossa moderna <strong>plataforma web no computador</strong>.</p><ul><li><strong>Histórico completo de 90 dias:</strong> veja onde o veículo esteve, velocidades atingidas, trajetos percorridos e pontos de parada com horário exato.</li><li><strong>Alertas na tela do celular:</strong> aviso imediato de chave ligada, excesso de velocidade, saída de cerca virtual ou bateria desconectada.</li><li><strong>Controle de frotas e motoristas:</strong> gerencie 1, 10 ou mais de 50 veículos em um único mapa unificado, com relatórios gerenciais e quilometragem.</li><li><strong>Suporte técnico humanizado:</strong> equipe pronta para auxiliar na ativação, configurações e dúvidas de uso.</li></ul>',
                    'cta_texto' => 'Ver Proposta com App Incluso',
                    'cta_destino' => '#formulario',
                ],

                // 6. OFERTA - Planos e Kits Transparentes
                [
                    'tipo' => 'oferta',
                    'titulo' => 'Planos e Kits para Carro, Moto e Frota',
                    'texto' => 'Equipamento de última geração com chip incluso e acesso total à plataforma. Preço transparente e condições sob medida na proposta.',
                    'itens' => "Proteção Individual — 1 Veículo|||1 Rastreador J6 4G Homologado;Chip M2M Multi-Operadora incluso;App para Android e iPhone + Plataforma Web;Bloqueio Remoto Antifurto pelo Celular;Histórico de Rotas por 90 dias;Alertas de Ignição e Cerca Virtual|Mais Escolhido\nProteção Familiar — 2 a 4 Veículos|||Rastreadores J6 4G para todos os veículos;Chips Multi-Operadora configurados;Todos os veículos no mesmo mapa do App;Controle familiar com alertas em tempo real;Bloqueio individual de cada veículo;Condição especial para múltiplos veículos|Família Protegida\nSolução Corporativa — Frotas e Empresas|||Kits a partir de 5 veículos;Gestão completa em painel gerencial unificado;Relatórios de percurso, paradas e velocidades;Controle de motoristas e rotas de entrega;Condições comerciais exclusivas para CNPJ;Atendimento e suporte dedicado|Alta Produtividade",
                    'cta_texto' => 'Pedir Proposta Deste Kit',
                    'cta_destino' => '#formulario',
                ],

                // 7. VÍDEO - Demonstração real do sistema
                [
                    'tipo' => 'video',
                    'titulo' => 'Veja o Sistema Golden Funcionando na Prática',
                    'texto' => 'Confira em menos de 2 minutos como é fácil rastrear, bloquear e acompanhar seu veículo pelo nosso aplicativo.',
                    'cta_texto' => 'Assistir Demonstração',
                    'cta_destino' => 'https://www.youtube.com/watch?v=OhtdphIxdf0',
                ],

                // 8. PROVA SOCIAL - Depoimentos de clientes
                [
                    'tipo' => 'prova',
                    'titulo' => 'Quem usa a Golden recomenda',
                    'texto' => 'Mais segurança, tranquilidade no dia a dia e atendimento de verdade.',
                    'itens' => "Douglas Mendes|Cliente Particular — Carro|Ótima empresa de segurança! O rastreador J6 funciona muito bem, o aplicativo é rápido e a equipe de suporte configurou tudo com muita agilidade.|5|\nJonatha Silveira|Motorista de Aplicativo|O chip multi-operadora faz toda a diferença rodando na cidade e na estrada. O app nunca perde o sinal e o suporte sempre me atende com muita atenção.|5|\nMarcelle Guimarães|Três Pontas - MG|Instalei no meu carro e agora tenho a tranquilidade de acompanhar onde ele está e poder bloquear pelo celular em caso de emergência. Recomendo demais!|5|\nCarlos Eduardo Prado|Gestor de Frota — Distribuidora|Colocamos os rastreadores na nossa frota e reduziu drasticamente desvios de rota e excessos de velocidade. O controle pelo computador é impecável.|5|",
                ],

                // 9. URGÊNCIA - Gatilho promocional para Google Ads
                [
                    'tipo' => 'urgencia',
                    'titulo' => 'Condição Especial para Visitantes Desta Campanha',
                    'texto' => 'Garanta seu Rastreador J6 4G com chip multi-operadora e atendimento prioritário da nossa equipe comercial. Condição promocional por tempo limitado.',
                    'limite_em' => now()->addDays(7)->setTime(23, 59),
                    'cta_texto' => 'Garantir Minha Condição Especial Agora',
                    'cta_destino' => '#formulario',
                ],

                // 10. FORMULÁRIO - Captura completa com campos de lead
                [
                    'tipo' => 'formulario',
                    'titulo' => 'Receba sua Proposta do Rastreador J6 Multi-Operadora',
                    'texto' => 'Sem compromisso. Resposta rápida de nossos especialistas via WhatsApp ou ligação em horário comercial.',
                    'cta_texto' => 'Solicitar Minha Proposta',
                ],

                // 11. FAQ - Resposta a todas as objeções de compra
                [
                    'tipo' => 'faq',
                    'titulo' => 'Perguntas Frequentes sobre o Rastreador J6 e Multi-Operadora',
                    'texto' => 'Tire todas as suas dúvidas antes de solicitar a sua proposta.',
                    'itens' => "O que é o Rastreador J6 e em quais veículos ele funciona?|O Rastreador J6 é um equipamento veicular moderno com conectividade 4G/2G, antenas internas de alta sensibilidade e bateria interna de backup. É compatível com carros de passeio, motocicletas, caminhonetes, vans, caminhões e frotas (tensão de 9V a 90V).\nO que significa chip multi-operadora e qual a vantagem?|Ao contrário de chips normais vinculados a uma só operadora, o chip M2M multi-operadora da Golden tem acesso às principais redes do Brasil (Vivo, Claro, TIM e Algar). Ele seleciona a rede com melhor sinal no local onde o veículo se encontra, evitando pontos cegos e áreas de sombra em cidades e estradas.\nComo funciona o bloqueio remoto pelo celular?|O bloqueio é realizado diretamente pelo app da Golden. Com a instalação adequada do relé de corte por um profissional qualificado, você consegue desligar a ignição do veículo em caso de emergência, roubo ou furto com apenas um clique.\nO rastreador J6 descarrega a bateria do meu veículo ou moto?|Não. O Rastreador J6 conta com gerenciamento inteligente de energia com modo sleep (hibernação) quando a ignição está desligada, consumindo uma quantidade mínima de energia que não afeta a bateria do veículo.\nE se cortarem ou desconectarem a bateria do veículo?|O Rastreador J6 possui bateria interna de backup recarregável. Se houver corte de cabos ou remoção da bateria do carro, o aparelho continua ativo, transmitindo as coordenadas e disparando um alerta imediato de violação no seu aplicativo.\nA instalação é difícil? Quem faz a instalação?|A instalação do J6 é simples e rápida para qualquer profissional eletricista automotivo de sua preferência. O aparelho acompanha chicote com fusíveis e manual, e nosso suporte técnico fica à disposição para orientar na ativação.\nPosso acompanhar mais de um veículo pelo mesmo aplicativo?|Sim! Você pode ter 1, 2, 5 ou dezenas de veículos na mesma conta e acompanhar todos no mesmo mapa em tempo real pelo celular ou computador.\nQual é o preço e como funciona a mensalidade?|O valor do equipamento e a mensalidade de conectividade multi-operadora e plataforma são extremamente acessíveis, custando uma fração do valor de um seguro tradicional. A equipe da Golden monta a proposta sob medida para sua necessidade.\nRastreamento substitui o seguro automotivo?|O rastreamento veicular é uma solução focada em prevenção ativa, monitoramento 24h e recuperação rápida do patrimônio. Embora não seja uma apólice de seguro de indenização, oferece segurança em tempo real com excelente custo-benefício.",
                ],
            ];

            foreach ($blocos as $ordem => $bloco) {
                $pagina->blocks()->create($bloco + ['ordem' => $ordem + 1, 'ativa' => true]);
            }

            Cache::forget('golden.paginas_funil');
        });
    }
}
