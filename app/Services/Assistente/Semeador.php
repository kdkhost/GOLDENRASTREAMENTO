<?php

namespace App\Services\Assistente;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Product;
use App\Models\Setting;

/**
 * O treinamento de fábrica do assistente.
 *
 * Duas coisas moram aqui: a persona que a Golden escolheu (nome, tom, regras) e a base de
 * conhecimento que ela lê antes de responder. O texto fixo está em `config/conhecimento.php`
 * porque é conteúdo, não código; o catálogo é montado na hora a partir dos produtos ativos,
 * porque preço publicado no banco não pode envelhecer na base do robô.
 *
 * O comando `assistente:ensinar` e o botão "Ensinar o básico" da tela de conhecimento chamam
 * este serviço — a mesma régua nos dois caminhos. Por padrão ele **não sobrescreve** o que a
 * equipe já editou: só acrescenta o que falta e atualiza o catálogo (que é derivado). A opção
 * `--atualizar` regrava os textos fixos; a persona só muda com `--personalidade`, para uma
 * segunda rodada nunca apagar o que o painel escreveu por cima.
 */
class Semeador
{
    /** Quantos itens entraram, quantos foram atualizados e quantos já estavam lá. */
    public function conhecimento(bool $atualizar = false): array
    {
        $contagem = ['criados' => 0, 'atualizados' => 0, 'intactos' => 0];
        $valores = $this->valoresDoPainel();

        foreach ((array) config('conhecimento.itens', []) as $item) {
            $dados = [
                'titulo' => $this->preencher($item['titulo'], $valores),
                'corpo' => $this->preencher($item['corpo'], $valores),
                'tags' => $item['tags'] ?? null,
                'ativo' => true,
            ];

            $existente = ConhecimentoItem::where('origem', 'painel')
                ->where('referencia', $item['referencia'])
                ->first();

            if ($existente === null) {
                ConhecimentoItem::create($dados + ['origem' => 'painel', 'referencia' => $item['referencia']]);
                $contagem['criados']++;

                continue;
            }

            if ($atualizar) {
                $existente->update($dados);
                $contagem['atualizados']++;

                continue;
            }

            $contagem['intactos']++;
        }

        return $contagem;
    }

    /**
     * O catálogo é sempre regravado: se o preço ou o produto mudou no painel, a resposta do
     * robô tem de mudar junto. Sem produto ativo, o item não é criado — melhor não ter fonte
     * do que ter uma lista vazia dizendo que a loja não vende nada.
     */
    public function catalogo(): bool
    {
        $produtos = Product::query()
            ->with('category:id,nome')
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        if ($produtos->isEmpty()) {
            return false;
        }

        $linhas = $produtos->map(function (Product $produto): string {
            $categoria = $produto->category?->nome ? ' ['.$produto->category->nome.']' : '';
            $estoque = $produto->disponivel() ? 'Disponível para pedido.' : 'Temporariamente sem estoque (consultar reposição).';

            if ($produto->sob_consulta) {
                $preco = 'Produto, plano, chip e todas as condições comerciais sob consulta — oriente o cliente a entrar em contato com a equipe Golden para receber uma proposta.';
                $detalhesExtras = '';
            } else {
                $preco = $produto->preco() > 0
                    ? 'Valor do item na loja: '.brl($produto->preco()).'.'
                    : 'Adesão do equipamento grátis.';

                $partes = [];
                if ($produto->permiteEscolhaChip()) {
                    $opsTexto = collect($produto->operadorasConfiguradas())
                        ->map(fn (array $op): string => $op['nome'].' ('.brl((int) ($op['mensalidade_cents'] ?? 0)).'/mês)')
                        ->implode(', ');
                    if ($opsTexto !== '') {
                        $cobranca1a = $produto->cobrar_primeira_mensalidade
                            ? '1ª mensalidade somada no ato da compra'
                            : 'mensalidade cobrada apenas no próximo mês (recorrente)';
                        $partes[] = 'Chip M2M incluso (sem taxa de ativação). Opções de operadora: '.$opsTexto.'. Regra: '.$cobranca1a.'.';
                    }
                } elseif ($produto->mensalidade_cents) {
                    $partes[] = 'Mensalidade: '.brl($produto->mensalidade_cents).'/mês.';
                }

                $recursos = collect($produto->recursosEstruturados(true))
                    ->map(function (array $r): string {
                        if (($r['tipo_valor'] ?? '') === 'mensal' && ($r['valor_cents'] ?? 0) > 0) {
                            return $r['titulo'].' ('.brl($r['valor_cents']).'/mês)';
                        }
                        if (($r['tipo_valor'] ?? '') === 'unico' && ($r['valor_cents'] ?? 0) > 0) {
                            return $r['titulo'].' ('.brl($r['valor_cents']).')';
                        }

                        return $r['titulo'];
                    })
                    ->implode(', ');
                if ($recursos !== '') {
                    $partes[] = 'Recursos inclusos: '.$recursos.'.';
                }

                $pacotes = collect($produto->pacotesAlertasAtivos())
                    ->map(fn (array $p): string => $p['nome'].' (+'.brl($p['valor_cents']).'/mês)')
                    ->implode(', ');
                if ($pacotes !== '') {
                    $partes[] = 'Pacotes opcionais de alertas no carrinho: '.$pacotes.'.';
                }

                $detalhesExtras = $partes !== [] ? ' '.implode(' ', $partes) : '';
            }

            return '• '.$produto->nome.$categoria.': '.trim((string) $produto->resumo).' '.$preco.' '.$estoque.$detalhesExtras
                .(filled($produto->descricao) ? ' '.trim(preg_replace('/\s+/', ' ', strip_tags((string) $produto->descricao))) : '');
        })->implode("\n");

        ConhecimentoItem::updateOrCreate(
            ['origem' => 'painel', 'referencia' => 'golden:catalogo'],
            [
                'titulo' => 'Catálogo de produtos e serviços da Golden',
                'corpo' => 'Produtos e serviços publicados hoje pela Golden, com o valor da loja. '
                    ."Valores e disponibilidade podem mudar: a proposta e a fatura confirmam o valor vigente.\n\n".$linhas,
                'tags' => 'catálogo catálogo produtos serviços preço valores valor quanto custa loja comprar rastreador chip acessório plano',
                'ativo' => true,
            ]
        );

        return true;
    }

    /**
     * Aplica a persona de `config('assistente.persona')` na ficha. Devolve true quando alguma
     * coisa mudou. Nome vazio na ficha volta ao padrão; nome já escolhido pela equipe fica.
     */
    public function personalidade(): bool
    {
        $ficha = Assistente::garanta();
        $padrao = (array) config('assistente.persona');

        $dados = [
            'cargo' => (string) ($padrao['cargo'] ?? ''),
            'saudacao' => (string) ($padrao['saudacao'] ?? ''),
            'pergunta_nome' => (string) ($padrao['pergunta_nome'] ?? ''),
            'confirmacao_nome' => (string) ($padrao['confirmacao_nome'] ?? ''),
            'pergunta_email' => (string) ($padrao['pergunta_email'] ?? ''),
            'confirmacao_email' => (string) ($padrao['confirmacao_email'] ?? ''),
            'confirmacao_cadastro' => (string) ($padrao['confirmacao_cadastro'] ?? ''),
            'pedido_avaliacao' => (string) ($padrao['pedido_avaliacao'] ?? ''),
            'agradecimento_avaliacao' => (string) ($padrao['agradecimento_avaliacao'] ?? ''),
            'despedida' => (string) ($padrao['despedida'] ?? ''),
            'tom' => (string) ($padrao['tom'] ?? 'humanizado'),
            'regras' => (string) ($padrao['regras'] ?? ''),
            'objetivo_atendimento' => (string) ($padrao['objetivo_atendimento'] ?? ''),
            'restricoes' => (string) ($padrao['restricoes'] ?? ''),
            'instrucoes_extras' => (string) ($padrao['instrucoes_extras'] ?? ''),
            'acessos_sistema' => Assistente::acessosPadrao(),
            'ativo' => true,
            'no_site' => true,
            'no_funil' => true,
            'no_chamado' => true,
        ];

        if (blank($ficha->nome)) {
            $dados['nome'] = (string) ($padrao['nome'] ?? 'Ana');
        }

        $mudou = false;

        foreach ($dados as $campo => $valor) {
            if ($ficha->{$campo} != $valor) {
                $ficha->{$campo} = $valor;
                $mudou = true;
            }
        }

        if ($mudou) {
            $ficha->save();
        }

        return $mudou;
    }

    /** O que o painel controla hoje — o texto fixo nunca repete número ou endereço na mão. */
    private function valoresDoPainel(): array
    {
        $telefones = Setting::get('contato_telefone', implode(' · ', (array) config('golden.contato.telefones')));

        return [
            ':whatsapp' => wa_url(),
            ':email' => (string) Setting::get('contato_email', config('golden.email')),
            ':telefones' => (string) ($telefones ?: 'no site, na página de contato'),
            ':cidade' => (string) config('golden.marca.cidade'),
            ':servidor_1' => (string) Setting::get('acesso_servidor_1_url', config('golden.acessos.servidor_1')),
            ':servidor_2' => (string) Setting::get('acesso_servidor_2_url', config('golden.acessos.servidor_2')),
            ':financeiro' => (string) Setting::get('acesso_financeiro_url', config('golden.acessos.financeiro')),
            ':portal' => url('/portal'),
        ];
    }

    /** Troca os marcadores numa passada só: um valor que contenha outro marcador não é relido. */
    private function preencher(string $texto, array $valores): string
    {
        return strtr($texto, $valores);
    }
}
