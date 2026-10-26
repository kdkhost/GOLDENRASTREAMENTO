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
        $produtos = Product::query()->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();

        if ($produtos->isEmpty()) {
            return false;
        }

        $linhas = $produtos->map(function (Product $produto): string {
            $preco = $produto->preco() > 0
                ? 'Preço na loja: '.brl($produto->preco()).'.'
                : 'Preço sob consulta na proposta.';

            $mensal = $produto->mensalidade_cents
                ? ' Mensalidade: '.brl($produto->mensalidade_cents).' por mês.'
                : '';

            return '• '.$produto->nome.': '.trim((string) $produto->resumo).' '.$preco.$mensal
                .(filled($produto->descricao) ? ' '.trim(preg_replace('/\s+/', ' ', $produto->descricao)) : '');
        })->implode("\n");

        ConhecimentoItem::updateOrCreate(
            ['origem' => 'painel', 'referencia' => 'golden:catalogo'],
            [
                'titulo' => 'Catálogo de produtos e serviços da Golden',
                'corpo' => "Produtos e serviços publicados hoje pela Golden, com o valor da loja. "
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
            'tom' => (string) ($padrao['tom'] ?? 'amigavel'),
            'regras' => (string) ($padrao['regras'] ?? ''),
        ];

        if (blank($ficha->nome)) {
            $dados['nome'] = (string) ($padrao['nome'] ?? 'Ana');
        }

        $mudou = false;

        foreach ($dados as $campo => $valor) {
            if ((string) $ficha->{$campo} !== $valor) {
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
