<?php

namespace App\Services\GoogleAds;

use App\Models\GoogleAdsAccount;
use App\Models\GoogleAdsCopiloto;
use App\Services\Assistente\Chamada;
use App\Services\Assistente\Excecoes\ChamadaFalhou;
use App\Services\Assistente\Provedores\Fabrica;
use App\Services\Assistente\Resposta;
use App\Services\GoogleAds\Excecoes\CopilotoIndisponivel;
use Illuminate\Support\Str;

/**
 * O copiloto de tráfego: a IA que escreve o plano da campanha e lê o relatório da conta.
 *
 * Duas entradas e duas saídas, e o formato é a única coisa que este serviço garante:
 *
 * · `planejar()` recebe o briefing em português e devolve o plano já conferido por
 *   `PlanoDeCampanha` — a IA propõe, as travas do Google decidem o que passa.
 * · `analisar()` recebe o resumo e as campanhas que a cache já tem e devolve diagnóstico,
 *   destaques e recomendações, também em JSON e também presos a limites.
 *
 * O modelo não escreve nada no Google: esta classe só conversa com a IA. Quem toca na conta é
 * `GoogleAdsWriter`, e só depois de um administrador aprovar o plano. A separação é o freio —
 * um JSON malformado vira erro de tela, nunca gasto.
 *
 * A resposta é pedida em JSON e relida com uma segunda tentativa de reparo: modelo que devolve
 * cerca de código ou frase antes do objeto é comum, e desistir na primeira seria devolver erro
 * ao operador por um detalhe que a própria IA corrige quando avisada. Se nem a segunda vier
 * legível, a exceção diz isso em português e nada é gravado.
 */
final class Copiloto
{
    /** O plano é longo (grupos + palavras + anúncio); 6 mil tokens cabem com folga. */
    private const MAX_TOKENS_DO_PLANO = 6000;

    /** A análise é curta de propósito: diagnóstico e até oito recomendações. */
    private const MAX_TOKENS_DA_ANALISE = 3000;

    /**
     * O plano de campanha a partir do briefing.
     *
     * @return array{plano: array<string, mixed>, problemas: array<int, string>, avisos: array<int, string>, forca: array{nota:int, rotulo:string, chave:string}, modelo: string}
     */
    public function planejar(array $briefing): array
    {
        $resposta = $this->perguntar(
            $this->pedidoDoPlano($briefing),
            $this->ordemDoPlano(),
            self::MAX_TOKENS_DO_PLANO,
        );

        $dados = $this->jsonDe($resposta->texto);

        if ($dados === null) {
            $resposta = $this->perguntar(
                $this->pedidoDeReparo($resposta->texto, 'plano'),
                $this->ordemDoPlano(),
                self::MAX_TOKENS_DO_PLANO,
            );
            $dados = $this->jsonDe($resposta->texto);
        }

        if ($dados === null) {
            throw CopilotoIndisponivel::respostaInvalida('plano');
        }

        return PlanoDeCampanha::conferir($dados, $briefing) + ['modelo' => $this->identificacao($resposta)];
    }

    /**
     * A leitura do período: o que os números dizem e o que fazer com eles.
     *
     * @param  array<string, mixed>  $resumo  o resumo que a cache já entregou
     * @param  array<int, array<string, mixed>>  $campanhas  as campanhas da mesma janela
     * @return array{diagnostico: string, destaques: array<int, string>, recomendacoes: array<int, array{tipo:string, titulo:string, detalhe:string, impacto:string}>, modelo: string}
     */
    public function analisar(array $resumo, array $campanhas, int $dias): array
    {
        $resposta = $this->perguntar(
            $this->pedidoDaAnalise($resumo, $campanhas, $dias),
            $this->ordemDaAnalise(),
            self::MAX_TOKENS_DA_ANALISE,
        );

        $dados = $this->jsonDe($resposta->texto);

        if ($dados === null) {
            $resposta = $this->perguntar(
                $this->pedidoDeReparo($resposta->texto, 'análise'),
                $this->ordemDaAnalise(),
                self::MAX_TOKENS_DA_ANALISE,
            );
            $dados = $this->jsonDe($resposta->texto);
        }

        if ($dados === null) {
            throw CopilotoIndisponivel::respostaInvalida('análise');
        }

        return $this->analiseNormalizada($dados) + ['modelo' => $this->identificacao($resposta)];
    }

    /**
     * Análise de IA Max e Otimização Avançada de uma campanha viva.
     * Avalia ativos visuais, sitelinks, frases, termos negativos anti-desperdício, RSA e score de prontidão.
     *
     * @param  array<string, mixed>  $campanha  A estrutura da campanha devolvida pelo editor
     * @param  array<string, mixed>|null  $metricas  Métricas opcionais de desempenho
     * @return array{
     *     score: int,
     *     nivel: string,
     *     copiloto: string,
     *     diagnostico: string,
     *     pilares: array<string, array{titulo: string, status: string, nota: int, max: int, detalhe: string}>,
     *     negativas_sugeridas: array<int, string>,
     *     recomendacoes: array<int, array{tipo: string, titulo: string, detalhe: string, impacto: string, acao: ?string, dados?: array<int, string>}>
     * }
     */
    public function otimizarCampanha(GoogleAdsAccount $conta, array $campanha, ?array $metricas = null): array
    {
        $recursos = (array) ($campanha['recursos'] ?? []);
        $imagens = (array) ($recursos['imagens'] ?? []);
        $sitelinks = (array) ($recursos['sitelinks'] ?? []);
        $frases = (array) ($recursos['frases'] ?? []);
        $negativas = (array) ($campanha['negativas'] ?? []);
        $grupos = (array) ($campanha['grupos'] ?? []);

        $score = 0;
        $pilares = [];

        // 1. Recursos Visuais & Logotipo (20 pts)
        $temLogoOuImagem = count($imagens) > 0;
        $scoreImagens = $temLogoOuImagem ? 20 : 0;
        $score += $scoreImagens;
        $pilares['imagens'] = [
            'titulo' => 'Recursos Visuais & Logotipo',
            'status' => $temLogoOuImagem ? 'ok' : 'alerta',
            'nota' => $scoreImagens,
            'max' => 20,
            'detalhe' => $temLogoOuImagem
                ? count($imagens).' imagem(ns)/logotipo(s) vinculados à campanha.'
                : 'Nenhuma imagem ou logotipo vinculado. O Google Ads dá maior destaque para anúncios com logotipo.',
        ];

        // 2. Sitelinks (20 pts)
        $totalSitelinks = count($sitelinks);
        $scoreSitelinks = $totalSitelinks >= 4 ? 20 : ($totalSitelinks >= 2 ? 12 : ($totalSitelinks > 0 ? 6 : 0));
        $score += $scoreSitelinks;
        $pilares['sitelinks'] = [
            'titulo' => 'Sitelinks do Anúncio',
            'status' => $totalSitelinks >= 4 ? 'ok' : ($totalSitelinks >= 2 ? 'atencao' : 'alerta'),
            'nota' => $scoreSitelinks,
            'max' => 20,
            'detalhe' => $totalSitelinks >= 4
                ? "{$totalSitelinks} sitelinks ativos (quantidade ideal atingida)."
                : "Apenas {$totalSitelinks} sitelink(s). O Google recomenda pelo menos 4 sitelinks para preencher o bloco do anúncio.",
        ];

        // 3. Frases de Destaque (15 pts)
        $totalFrases = count($frases);
        $scoreFrases = $totalFrases >= 4 ? 15 : ($totalFrases >= 2 ? 8 : ($totalFrases > 0 ? 4 : 0));
        $score += $scoreFrases;
        $pilares['frases'] = [
            'titulo' => 'Frases de Destaque (Callouts)',
            'status' => $totalFrases >= 4 ? 'ok' : ($totalFrases >= 2 ? 'atencao' : 'alerta'),
            'nota' => $scoreFrases,
            'max' => 15,
            'detalhe' => $totalFrases >= 4
                ? "{$totalFrases} frases de destaque cadastradas."
                : "Apenas {$totalFrases} frase(s). Frases de destaque aumentam o CTR ao destacar benefícios sem gastar caracteres de título.",
        ];

        // 4. Termos Negativos Anti-Desperdício (20 pts)
        $termosBaseNegativas = [
            'gratis', 'gratuito', 'download', 'apk', 'manual', 'mercado livre', 'olx', 'shopee',
            'como fazer', 'hackear', 'esquema eletrico', 'pinout', 'trabalhe conosco', 'vagas',
        ];
        $textosNegativasAtuais = array_map(static fn ($n) => mb_strtolower(is_array($n) ? (string) ($n['texto'] ?? '') : (string) $n), $negativas);
        $negativasSugeridas = [];
        foreach ($termosBaseNegativas as $t) {
            if (! in_array($t, $textosNegativasAtuais, true)) {
                $negativasSugeridas[] = $t;
            }
        }
        $totalNegativas = count($negativas);
        $scoreNegativas = $totalNegativas >= 5 ? 20 : ($totalNegativas >= 2 ? 10 : 0);
        $score += $scoreNegativas;
        $pilares['negativas'] = [
            'titulo' => 'Termos Negativos Anti-Desperdício',
            'status' => $totalNegativas >= 5 ? 'ok' : 'alerta',
            'nota' => $scoreNegativas,
            'max' => 20,
            'detalhe' => $totalNegativas >= 5
                ? "{$totalNegativas} palavras negativas cadastradas bloqueando cliques não qualificados."
                : "Apenas {$totalNegativas} palavra(s) negativa(s). Você pode estar pagando por cliques sem intenção de compra.",
        ];

        // 5. Anúncios Responsivos de Pesquisa (25 pts)
        $temAnuncioBom = false;
        $totalAnuncios = 0;
        foreach ($grupos as $g) {
            $anunciosGrupo = (array) ($g['anuncios'] ?? []);
            foreach ($anunciosGrupo as $anuncio) {
                $totalAnuncios++;
                $titulos = (array) ($anuncio['titulos'] ?? []);
                $descricoes = (array) ($anuncio['descricoes'] ?? []);
                if (count($titulos) >= 5 && count($descricoes) >= 2) {
                    $temAnuncioBom = true;
                }
            }
        }
        $scoreAnuncio = $temAnuncioBom ? 25 : ($totalAnuncios > 0 ? 12 : 0);
        $score += $scoreAnuncio;
        $pilares['anuncios'] = [
            'titulo' => 'Qualidade dos Anúncios Responsivos (RSA)',
            'status' => $temAnuncioBom ? 'ok' : ($totalAnuncios > 0 ? 'atencao' : 'alerta'),
            'nota' => $scoreAnuncio,
            'max' => 25,
            'detalhe' => $temAnuncioBom
                ? 'Anúncio responsivo com variedade de títulos e descrições para a IA do Google combinar.'
                : 'Adicione mais variações de títulos (mínimo 5) e descrições (mínimo 2) para permitir combinações otimizadas.',
        ];

        // Recomendações
        $recomendacoes = [];
        if (! empty($negativasSugeridas)) {
            $recomendacoes[] = [
                'tipo' => 'negativas',
                'titulo' => 'Adicionar termos negativos essenciais',
                'detalhe' => 'Bloqueie buscas como "grátis", "download", "mercado livre", "manual" para evitar gasto inútil de orçamento.',
                'impacto' => 'Alto — Reduz custo por clique e corta desperdício',
                'acao' => 'adicionar_negativas',
                'dados' => array_slice($negativasSugeridas, 0, 8),
            ];
        }
        if ($totalSitelinks < 4) {
            $recomendacoes[] = [
                'tipo' => 'sitelinks',
                'titulo' => 'Completar os 4 sitelinks da Golden',
                'detalhe' => 'Vincule os sitelinks padrão: Planos e Preços, Rastreador J16 4G, Gestão de Frotas e WhatsApp.',
                'impacto' => 'Médio — Aumenta a taxa de cliques (CTR) em até 20%',
                'acao' => 'vincular_sitelinks',
            ];
        }
        if (! $temLogoOuImagem) {
            $recomendacoes[] = [
                'tipo' => 'imagem',
                'titulo' => 'Vincular logotipo da empresa e fotos dos produtos',
                'detalhe' => 'Anúncios de busca com logotipo da Golden Rastreamento geram 15% mais confiança e cliques qualificados.',
                'impacto' => 'Alto — Melhora a nota de relevância do Google',
                'acao' => 'vincular_imagens',
            ];
        }

        $nivel = match (true) {
            $score >= 85 => 'Excelente',
            $score >= 60 => 'Bom',
            default => 'Atenção Necessária',
        };

        $ficha = GoogleAdsCopiloto::garanta();
        $nomeCopiloto = $ficha->nome !== '' ? $ficha->nome : 'Max';

        $diagnostico = "A campanha {$campanha['nome']} está com Score IA Max de {$score}/100 ({$nivel}). ";
        if ($score >= 85) {
            $diagnostico .= 'A estrutura de anúncios e recursos está sólida para os algoritmos de lances inteligentes e IA do Google Ads.';
        } else {
            $diagnostico .= 'Aplicando as recomendações de termos negativos e recursos visuais abaixo, sua campanha ganhará mais relevância e reduzirá o custo por clique.';
        }

        return [
            'score' => $score,
            'nivel' => $nivel,
            'copiloto' => $nomeCopiloto,
            'diagnostico' => $diagnostico,
            'pilares' => $pilares,
            'negativas_sugeridas' => $negativasSugeridas,
            'recomendacoes' => $recomendacoes,
        ];
    }

    /**
     * Uma pergunta à IA pronta do painel.
     *
     * A checagem de `Fabrica::pronta` acontece antes da chamada para a tela poder dizer o que
     * falta (chave, modelo, provedor) sem depender de um HTTP de erro. Erro de provedor vira
     * `CopilotoIndisponivel` com a frase da casa; o detalhe já ficou no log da chamada.
     */
    private function perguntar(string $pergunta, array $instrucoes, int $maxTokens): Resposta
    {
        if (! Fabrica::pronta('conversa')) {
            throw CopilotoIndisponivel::semModelo();
        }

        try {
            return Fabrica::conversante('conversa')->responder(new Chamada(
                pergunta: $pergunta,
                instrucoes: $instrucoes,
                userId: auth()->id(),
                capacidade: 'conversa',
                maxTokens: $maxTokens,
            ));
        } catch (ChamadaFalhou $falha) {
            throw CopilotoIndisponivel::chamadaFalhou($falha);
        }
    }

    /** A ficha do copiloto e as travas da casa, na ordem em que o modelo as recebe. */
    private function ordemDoPlano(): array
    {
        $ficha = GoogleAdsCopiloto::garanta();
        $nome = $ficha->nome !== '' ? $ficha->nome : 'Max';
        $cargo = $ficha->cargo !== '' ? $ficha->cargo : 'copiloto de tráfego';

        return array_values(array_filter([
            "Você é {$nome}, {$cargo} da Golden Rastreamento.",
            $ficha->tom !== '' ? 'Tom de voz: '.$ficha->tom.'.' : null,
            (string) $ficha->instrucoes,
            'Responda sempre em português do Brasil.',
            'Devolva **apenas** um objeto JSON válido: sem texto antes ou depois, sem comentários e sem cerca de código.',
            'Formato exato do plano:'
                .' {"nome":"...","objetivo":"pesquisa|vendas|leads|whatsapp|ligacoes",'
                .'"estrategia_lance":"MANUAL_CPC",'
                .'"cpc_maximo_centavos":200,"orcamento_diario":40.00,'
                .'"url":"https://...","regioes":["..."],"idiomas":["Português"],'
                .'"grupos":[{"nome":"...","tema":"...",'
                .'"palavras_chave":[{"texto":"...","correspondencia":"frase"}],"negativas":["..."]}],'
                .'"anuncios":{"titulos":["..."],"descricoes":["..."],"caminhos":["..."]},'
                .'"sitelinks":[{"texto":"...","url":"https://...","descricao1":"...","descricao2":"..."}],'
                .'"justificativa":"..."}',
            'Regras do plano:'
                .' use de 3 a '.$this->limite('grupos_max', 8).' grupos;'
                .' até '.$this->limite('palavras_por_grupo_max', 25).' palavras-chave por grupo;'
                .' até '.$this->limite('negativas_max', 60).' negativas;'
                .' de 8 a '.(int) config('google_ads.rsa.titulos_max', 15).' títulos de até '.(int) config('google_ads.rsa.caracteres_titulo', 30).' caracteres;'
                .' de 3 a '.(int) config('google_ads.rsa.descricoes_max', 4).' descrições de até '.(int) config('google_ads.rsa.caracteres_descricao', 90).' caracteres;'
                .' até 2 caminhos de até '.(int) config('google_ads.rsa.caracteres_rota', 15).' caracteres, sem espaço;'
                .' de 2 a 4 sitelinks (texto até 25 caracteres, url válida, descrições 1 e 2 até 35 caracteres).',
            'O anúncio precisa ser específico do serviço e do público; nada de texto genérico que serviria para qualquer empresa.',
            'Nunca invente preço, prazo, endereço, percentual ou condição que o briefing não informe. '
                .'Se faltar um dado, escreva o anúncio sem ele em vez de preencher com suposição.',
            'Não prometa resultado garantido, posição no Google nem prazo de entrega não informado.',
            'Justifique em poucas frases por que essa estrutura atende ao briefing.',
        ]));
    }

    /** O que a análise pode dizer e como, nas mesmas travas de tamanho. */
    private function ordemDaAnalise(): array
    {
        $ficha = GoogleAdsCopiloto::garanta();
        $recomendacoes = (int) config('google_ads.copiloto.recomendacoes_max', 8);

        return array_values(array_filter([
            'Você é '.($ficha->nome !== '' ? $ficha->nome : 'Max').', copiloto de tráfego da Golden Rastreamento.',
            $ficha->tom !== '' ? 'Tom de voz: '.$ficha->tom.'.' : null,
            'Analise a métrica do Google Ads que segue e responda em português do Brasil.',
            'Devolva **apenas** um objeto JSON válido: sem texto antes ou depois, sem comentários e sem cerca de código.',
            'Formato exato da análise:'
                .' {"diagnostico":"...","destaques":["..."],'
                .'"recomendacoes":[{"tipo":"orcamento|palavra_chave|anuncio|segmentacao|lance|medicao",'
                .'"titulo":"...","detalhe":"...","impacto":"alto|medio|baixo"}]}',
            'Regras da análise:'
                .' diagnóstico de até 1.200 caracteres, baseado só nos números recebidos;'
                .' de 1 a 5 destaques, cada um com até 160 caracteres;'
                .' de 1 a '.$recomendacoes.' recomendações, cada uma com título de até 120 caracteres e detalhe de até 600.',
            'Cada recomendação precisa dizer o que fazer, onde e como medir o efeito — nada de conselho vago como "melhorar o anúncio".',
            'Não invente números: se a métrica não permite concluir algo, diga o que falta medir (conversões, por exemplo).',
            'Não prometa resultado; descreva o que a mudança tende a melhorar e o que observar.',
        ]));
    }

    /** O briefing como a IA o lê: rótulo em português e o valor que a pessoa digitou. */
    private function pedidoDoPlano(array $briefing): string
    {
        $objetivos = (array) config('google_ads.copiloto.objetivos');
        $objetivo = (string) ($briefing['objetivo'] ?? 'leads');

        $diretrizObjetivo = match ($objetivo) {
            'pesquisa' => 'Diretriz de pesquisa: foque em termos de busca de alta relevância, dúvidas frequentes sobre rastreamento veicular, comparativos de tecnologia e autoridade da marca Golden Rastreamento.',
            'vendas' => 'Diretriz de vendas diretas: foque em conversão e fechamento comercial com planos a partir de R$ 49,90/mês, kit rastreador J16 4G com chip M2M, bloqueio remoto, instalação rápida e chamadas fortes para ação (Compre Já, Instale Hoje).',
            'leads' => 'Diretriz de leads: foque em frotas e empresas corporativas, controle de rotas e combustível, telemetria e solicitação de orçamento.',
            'whatsapp' => 'Diretriz de WhatsApp: foque em atendimento imediato e personalizado pelo WhatsApp dos consultores comerciais.',
            'ligacoes' => 'Diretriz de ligações: foque em contato telefônico imediato com a central comercial.',
            default => 'Diretriz: equilibre atração de tráfego qualificado e conversão de novos clientes.',
        };

        return implode("\n", array_filter([
            'Briefing da campanha de pesquisa:',
            $this->linha('Nome sugerido', $briefing['nome'] ?? ''),
            $this->linha('Objetivo do anúncio', $objetivos[$objetivo] ?? $objetivo),
            $this->linha('Foco estratégico', $diretrizObjetivo),
            $this->linha('Orçamento diário (R$)', $briefing['orcamento'] ?? ''),
            $this->linha('Página de destino', $briefing['url'] ?? ''),
            $this->linha('Regiões', $briefing['regioes'] ?? ''),
            $this->linha('Público', $briefing['publico'] ?? ''),
            $this->linha('O que oferecer', $briefing['oferta'] ?? ''),
            $this->linha('Observações', $briefing['observacoes'] ?? ''),
        ]));
    }

    /**
     * A métrica como a IA a lê.
     *
     * Só entra o resumo e as campanhas que couberem no teto da config: a conta inteira custaria
     * tokens sem melhorar o diagnóstico, e a ordem já vem por custo — as que mais pesaram vêm
     * primeiro.
     */
    private function pedidoDaAnalise(array $resumo, array $campanhas, int $dias): string
    {
        $linhas = [
            'Métrica dos últimos '.$dias.' dias, lida da conta do Google Ads.',
            '',
            'Resumo do período:',
            '- Impressões: '.$this->numero($resumo['impressoes'] ?? 0),
            '- Cliques: '.$this->numero($resumo['cliques'] ?? 0),
            '- CTR: '.$this->decimal($resumo['ctr'] ?? 0).'%',
            '- CPC médio: '.brl((int) ($resumo['cpc_medio_centavos'] ?? 0)),
            '- Custo: '.brl((int) ($resumo['custo_centavos'] ?? 0)),
            '- Conversões: '.$this->numero($resumo['conversoes'] ?? 0),
            '- Valor das conversões: '.brl((int) ($resumo['valor_conversoes_centavos'] ?? 0)),
            '- ROAS: '.(isset($resumo['roas']) && $resumo['roas'] !== null ? $this->decimal($resumo['roas']).'×' : 'sem conversão registrada'),
            '',
            'Campanhas:',
        ];

        $teto = max(1, (int) config('google_ads.copiloto.campanhas_na_analise', 12));

        foreach (array_slice(array_values($campanhas), 0, $teto) as $campanha) {
            $linhas[] = '- '.($campanha['nome'] ?? 'Sem nome')
                .' (id '.($campanha['id'] ?? '—').', '.($campanha['status_rotulo'] ?? $campanha['status'] ?? '—').'): '
                .$this->numero($campanha['impressoes'] ?? 0).' impressões, '
                .$this->numero($campanha['cliques'] ?? 0).' cliques, '
                .'CTR '.$this->decimal($campanha['ctr'] ?? 0).'%, '
                .'CPC '.brl((int) ($campanha['cpc_medio_centavos'] ?? 0)).', '
                .'custo '.brl((int) ($campanha['custo_centavos'] ?? 0)).', '
                .$this->numero($campanha['conversoes'] ?? 0).' conversões, '
                .'ROAS '.(($campanha['roas'] ?? null) === null ? '—' : $this->decimal($campanha['roas']).'×').'.';
        }

        if ($campanhas === []) {
            $linhas[] = '- Nenhuma campanha com métrica nesta janela.';
        }

        return implode("\n", $linhas);
    }

    /** A segunda pergunta: a resposta anterior não virou JSON, então ela volta para ser corrigida. */
    private function pedidoDeReparo(string $saida, string $tipo): string
    {
        return "A resposta anterior não foi um JSON válido para o {$tipo}.\n\n"
            .'Devolva agora **somente** o objeto JSON, seguindo exatamente o formato pedido — '
            ."sem texto em volta e sem cerca de código.\n\n"
            ."Resposta anterior:\n".mb_substr($saida, 0, 4000);
    }

    /** O primeiro `{` até o último `}` — o que sobrevive a cerca de código e a frase de introdução. */
    private function jsonDe(string $texto): ?array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $texto) ?? $texto;

        $inicio = strpos($texto, '{');
        $fim = strrpos($texto, '}');

        if ($inicio === false || $fim === false || $fim <= $inicio) {
            return null;
        }

        $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);

        return is_array($dados) ? $dados : null;
    }

    /**
     * A análise dentro dos limites da tela — o que passa do teto é cortado, não recusado.
     *
     * Um modelo que devolve nove recomendações quando a config pede oito não errou o trabalho;
     * errou a contagem. Recusar a análise inteira por isso seria perder o diagnóstico junto.
     *
     * @return array{diagnostico: string, destaques: array<int, string>, recomendacoes: array<int, array{tipo:string, titulo:string, detalhe:string, impacto:string}>}
     */
    private function analiseNormalizada(array $dados): array
    {
        $recomendacoes = [];
        $limite = max(1, (int) config('google_ads.copiloto.recomendacoes_max', 8));
        $impactos = array_keys((array) config('google_ads.copiloto.impactos'));

        foreach ((array) ($dados['recomendacoes'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $titulo = $this->texto($item['titulo'] ?? '', 120);
            $detalhe = $this->texto($item['detalhe'] ?? '', 800);

            if ($titulo === '' || $detalhe === '') {
                continue;
            }

            $impacto = mb_strtolower($this->texto($item['impacto'] ?? 'medio', 10));

            $recomendacoes[] = [
                'tipo' => Str::slug($this->texto($item['tipo'] ?? 'geral', 40), '_') ?: 'geral',
                'titulo' => $titulo,
                'detalhe' => $detalhe,
                'impacto' => in_array($impacto, $impactos, true) ? $impacto : 'medio',
            ];

            if (count($recomendacoes) >= $limite) {
                break;
            }
        }

        $destaques = [];

        foreach ((array) ($dados['destaques'] ?? []) as $destaque) {
            $destaque = $this->texto($destaque, 240);

            if ($destaque !== '' && count($destaques) < 5) {
                $destaques[] = $destaque;
            }
        }

        return [
            'diagnostico' => $this->texto($dados['diagnostico'] ?? '', 2000),
            'destaques' => $destaques,
            'recomendacoes' => $recomendacoes,
        ];
    }

    /** `provedor/modelo`, como o painel grava e a ficha do plano mostra. */
    private function identificacao(Resposta $resposta): string
    {
        return trim(($resposta->provedor ?? '').'/'.($resposta->modelo ?? ''), '/');
    }

    private function linha(string $rotulo, mixed $valor): ?string
    {
        if (is_array($valor)) {
            $valor = implode(', ', array_filter($valor, 'is_scalar'));
        }

        $texto = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '') : '';

        return $texto === '' ? null : "- {$rotulo}: {$texto}";
    }

    private function limite(string $chave, int $padrao): int
    {
        return (int) config('google_ads.copiloto.'.$chave, $padrao);
    }

    private function numero(mixed $valor): string
    {
        return number_format((float) $valor, 0, ',', '.');
    }

    private function decimal(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    private function texto(mixed $valor, int $limite): string
    {
        if (! is_scalar($valor)) {
            return '';
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? ''), 0, $limite);
    }
}
