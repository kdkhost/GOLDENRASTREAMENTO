<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;

/**
 * Carrinho da loja, guardado na sessão do visitante. As telas (badge do topo, página do
 * carrinho, checkout) resolvem os itens contra o catálogo em cache — adicionar, trocar
 * quantidade e ver o total não custam nenhuma consulta ao banco. Só no momento de gravar
 * o pedido os preços e o estoque são relidos do banco, dentro da transação.
 */
class Carrinho
{
    public const CHAVE = 'golden_carrinho';

    public const CHAVE_CUPOM = 'golden_carrinho_cupom';

    public const CHAVE_OPERADORAS = 'golden_carrinho_operadoras';

    public const CHAVE_PACOTES_ALERTAS = 'golden_carrinho_pacotes_alertas';

    /** @var array<int, int> product_id => quantidade */
    private array $itens;

    public function __construct(array $itens = [])
    {
        $this->itens = $itens;
    }

    public static function daSessao(): self
    {
        return new self((array) session(self::CHAVE, []));
    }

    public function adicionar(int $produtoId, int $quantidade = 1): void
    {
        $atual = (int) ($this->itens[$produtoId] ?? 0);
        $this->gravar($produtoId, $atual + max(1, $quantidade));
    }

    public function definir(int $produtoId, int $quantidade): void
    {
        $this->ajustar($produtoId, $quantidade);
    }

    public function quantidadeDoProduto(int $produtoId): int
    {
        return (int) ($this->itens[$produtoId] ?? 0);
    }

    public function temProduto(int $produtoId): bool
    {
        return $this->quantidadeDoProduto($produtoId) > 0;
    }

    public function totalProdutosDiferentes(): int
    {
        return count($this->itens);
    }

    public function ajustar(int $produtoId, int $quantidade): void
    {
        $quantidade <= 0 ? $this->remover($produtoId) : $this->gravar($produtoId, $quantidade);
    }

    public function remover(int $produtoId): void
    {
        unset($this->itens[$produtoId]);
        $this->salvar();

        $operadoras = (array) session(self::CHAVE_OPERADORAS, []);
        unset($operadoras[$produtoId]);
        session([self::CHAVE_OPERADORAS => $operadoras]);

        $pacotes = (array) session(self::CHAVE_PACOTES_ALERTAS, []);
        unset($pacotes[$produtoId]);
        session([self::CHAVE_PACOTES_ALERTAS => $pacotes]);
    }

    public function limpar(): void
    {
        $this->itens = [];
        $this->salvar();
        $this->removerCupom();
        session()->forget(self::CHAVE_OPERADORAS);
        session()->forget(self::CHAVE_PACOTES_ALERTAS);
    }

    public function definirOperadora(int $produtoId, string $operadora): void
    {
        $operadoras = (array) session(self::CHAVE_OPERADORAS, []);
        $operadoras[$produtoId] = $operadora;
        session([self::CHAVE_OPERADORAS => $operadoras]);
    }

    public function operadoraDoProduto(int $produtoId, string $padrao = 'algar'): string
    {
        $operadoras = (array) session(self::CHAVE_OPERADORAS, []);

        return $operadoras[$produtoId] ?? $padrao;
    }

    /**
     * @param  array<int, string>  $pacotesIds
     */
    public function definirPacotesAlertas(int $produtoId, array $pacotesIds): void
    {
        $pacotes = (array) session(self::CHAVE_PACOTES_ALERTAS, []);
        $limpos = array_values(array_unique(array_filter(array_map(
            fn ($id): string => trim((string) $id),
            $pacotesIds
        ))));

        if ($limpos === []) {
            unset($pacotes[$produtoId]);
        } else {
            $pacotes[$produtoId] = $limpos;
        }

        session([self::CHAVE_PACOTES_ALERTAS => $pacotes]);
    }

    /**
     * @return array<int, string>
     */
    public function pacotesAlertasDoProduto(int $produtoId): array
    {
        $pacotes = (array) session(self::CHAVE_PACOTES_ALERTAS, []);

        return array_values(array_filter(array_map(
            fn ($id): string => trim((string) $id),
            (array) ($pacotes[$produtoId] ?? [])
        )));
    }

    /**
     * Aplica um código de cupom na sessão após validar vigência e valor mínimo.
     *
     * @return array{ok: bool, erro?: string, cupom?: Coupon}
     */
    public function aplicarCupom(string $codigo): array
    {
        $cupom = Coupon::buscarPorCodigo($codigo);

        if ($cupom === null) {
            return ['ok' => false, 'erro' => 'Cupom não encontrado. Confira o código digitado.'];
        }

        $validacao = $cupom->validarParaCompra($this->subtotalHojeCents());

        if (! $validacao['ok']) {
            return $validacao;
        }

        session([self::CHAVE_CUPOM => $cupom->codigo]);

        return ['ok' => true, 'cupom' => $cupom];
    }

    public function removerCupom(): void
    {
        session()->forget(self::CHAVE_CUPOM);
    }

    public function cupomCodigo(): ?string
    {
        $codigo = session(self::CHAVE_CUPOM);

        return is_string($codigo) && $codigo !== '' ? Coupon::normalizarCodigo($codigo) : null;
    }

    public function cupomValido(?int $subtotalCents = null): ?Coupon
    {
        $codigo = $this->cupomCodigo();

        if ($codigo === null) {
            return null;
        }

        $cupom = Coupon::buscarPorCodigo($codigo);
        $subtotal = $subtotalCents ?? $this->subtotalHojeCents();

        if ($cupom === null || ! $cupom->validarParaCompra($subtotal)['ok']) {
            $this->removerCupom();

            return null;
        }

        return $cupom;
    }

    /**
     * @return array{cupom: ?Coupon, codigo: ?string, desconto_cents: int, frete_gratis: bool}
     */
    public function resumoDesconto(?int $subtotalCents = null): array
    {
        $subtotal = $subtotalCents ?? $this->subtotalHojeCents();
        $cupom = $this->cupomValido($subtotal);

        if ($cupom === null) {
            return [
                'cupom' => null,
                'codigo' => null,
                'desconto_cents' => 0,
                'frete_gratis' => false,
            ];
        }

        $calc = $cupom->calcularDesconto($subtotal);

        return [
            'cupom' => $cupom,
            'codigo' => $cupom->codigo,
            'desconto_cents' => $calc['desconto_cents'],
            'frete_gratis' => $calc['frete_gratis'],
        ];
    }

    /** Quantidade pedida, já limitada ao estoque publicado no catálogo. */
    private function gravar(int $produtoId, int $quantidade): void
    {
        $produto = Product::doCatalogo($produtoId);

        if ($produto === null || ! empty($produto['sob_consulta'])) {
            $this->remover($produtoId);

            return;
        }

        $teto = $produto['controle_estoque'] ? max(0, min(999, $produto['estoque'])) : 999;

        if ($teto === 0) {
            $this->remover($produtoId);

            return;
        }

        $this->itens[$produtoId] = min($teto, max(1, $quantidade));
        $this->salvar();
    }

    private function salvar(): void
    {
        session([self::CHAVE => $this->itens]);
    }

    public function vazio(): bool
    {
        return $this->itens === [];
    }

    public function totalItens(): int
    {
        return array_sum($this->itens);
    }

    /**
     * Itens do carrinho com os dados do catálogo e o subtotal de cada linha.
     * Linhas de produto que saiu da vitrine são descartadas (e somem da sessão).
     *
     * @return array<int, array<string, mixed>>
     */
    public function linhas(): array
    {
        $catalogo = collect(Product::catalogoParaSite())->keyBy('id');
        $linhas = [];

        foreach ($this->itens as $id => $quantidade) {
            $produto = $catalogo->get((int) $id);

            if ($produto === null || ! empty($produto['sob_consulta'])) {
                unset($this->itens[$id]);

                continue;
            }

            if ($produto['controle_estoque']) {
                $quantidade = min((int) $quantidade, max(1, (int) $produto['estoque']));
                $this->itens[$id] = $quantidade;
            }

            $operadoraPadrao = $produto['chip_padrao'] ?? 'algar';
            $operadoraEscolhida = $this->operadoraDoProduto((int) $id, $operadoraPadrao);
            $dadosOperadora = $produto['operadoras_detalhes'][$operadoraEscolhida] ?? ($produto['operadoras_detalhes'][$operadoraPadrao] ?? (Product::OPERADORAS_CHIP[$operadoraEscolhida] ?? null));
            $cobrarMensalidadeHoje = (bool) ($produto['cobrar_primeira_mensalidade'] ?? false);
            $mensalidadeChip = (int) ($dadosOperadora['mensalidade_cents'] ?? ($produto['mensalidade'] ?? 0));

            $pacotesDisponiveis = (array) ($produto['pacotes_alertas'] ?? []);
            $pacotesIdsMarcados = $this->pacotesAlertasDoProduto((int) $id);
            $pacotesSelecionados = [];
            $pacotesAlertasUnitCents = 0;

            foreach ($pacotesDisponiveis as $pacote) {
                $pacoteId = (string) ($pacote['id'] ?? '');
                if ($pacoteId !== '' && in_array($pacoteId, $pacotesIdsMarcados, true)) {
                    $pacotesSelecionados[] = $pacote;
                    $pacotesAlertasUnitCents += (int) ($pacote['valor_cents'] ?? 0);
                }
            }

            $mensalidadeItem = $mensalidadeChip + $pacotesAlertasUnitCents;

            $linhas[] = [
                ...$produto,
                'quantidade' => $quantidade,
                'subtotal' => $produto['preco'] * $quantidade,
                'mensalidade_chip' => $mensalidadeChip,
                'pacotes_alertas_disponiveis' => $pacotesDisponiveis,
                'pacotes_alertas_selecionados' => $pacotesSelecionados,
                'pacotes_alertas_ids' => array_column($pacotesSelecionados, 'id'),
                'pacotes_alertas_unit_cents' => $pacotesAlertasUnitCents,
                'mensalidade' => $mensalidadeItem,
                'cobrar_mensalidade_hoje' => $cobrarMensalidadeHoje,
                'operadora_codigo' => $operadoraEscolhida,
                'operadora_nome' => $dadosOperadora['nome'] ?? ($produto['chip_operadora'] ?: 'Chip Multioperadora'),
                'operadora_rotulo' => $dadosOperadora['rotulo'] ?? ($produto['chip_operadora'] ?: 'Chip Multioperadora'),
                'operadora_tipo' => $dadosOperadora['tipo'] ?? 'multioperadora',
                'operadora_icone' => $dadosOperadora['icone'] ?? 'fa-sim-card',
                'operadora_destaque' => $dadosOperadora['destaque'] ?? null,
                'chip_incluso' => true,
                'beneficio_chip' => ! empty($produto['beneficio_chip_texto']) ? $produto['beneficio_chip_texto'] : 'Chip M2M incluso no benefício do plano',
            ];
        }

        $this->salvar();

        return $linhas;
    }

    public function subtotalCents(): int
    {
        return array_sum(array_column($this->linhas(), 'subtotal'));
    }

    public function mensalidadeTotalCents(): int
    {
        return array_sum(array_map(fn (array $l): int => ((int) ($l['mensalidade'] ?? 0)) * ((int) ($l['quantidade'] ?? 1)), $this->linhas()));
    }

    public function mensalidadeCobradaHojeCents(): int
    {
        return array_sum(array_map(fn (array $l): int => (! empty($l['cobrar_mensalidade_hoje']) ? ((int) ($l['mensalidade'] ?? 0)) * ((int) ($l['quantidade'] ?? 1)) : 0), $this->linhas()));
    }

    public function subtotalHojeCents(): int
    {
        return $this->subtotalCents() + $this->mensalidadeCobradaHojeCents();
    }

    /**
     * Regra de frete do painel: frete grátis global, frete grátis acima de um valor ou
     * frete fixo. Retirada na loja nunca paga frete.
     */
    public static function fretePara(int $subtotalCents, string $entrega = 'entrega'): int
    {
        if ($entrega === 'retira' || Setting::get('loja_livre', '0') === '1') {
            return 0;
        }

        $limiteGratis = (int) Setting::get('loja_frete_gratis_acima_cents', 0);

        if ($limiteGratis > 0 && $subtotalCents >= $limiteGratis) {
            return 0;
        }

        return max(0, (int) Setting::get('loja_frete_cents', 0));
    }

    /** Quantos ainda faltam para o frete grátis, em centavos (0 quando não há meta). */
    public static function faltaParaFreteGratis(int $subtotalCents): int
    {
        $meta = (int) Setting::get('loja_frete_gratis_acima_cents', 0);
        $liberado = $meta > 0 && $subtotalCents >= $meta;

        return ($meta > 0 && ! $liberado && Setting::get('loja_livre', '0') !== '1') ? $meta - $subtotalCents : 0;
    }
}
