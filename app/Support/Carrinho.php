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

    public function ajustar(int $produtoId, int $quantidade): void
    {
        $quantidade <= 0 ? $this->remover($produtoId) : $this->gravar($produtoId, $quantidade);
    }

    public function remover(int $produtoId): void
    {
        unset($this->itens[$produtoId]);
        $this->salvar();
    }

    public function limpar(): void
    {
        $this->itens = [];
        $this->salvar();
        $this->removerCupom();
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

        $validacao = $cupom->validarParaCompra($this->subtotalCents());

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
        $subtotal = $subtotalCents ?? $this->subtotalCents();

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
        $subtotal = $subtotalCents ?? $this->subtotalCents();
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

        if ($produto === null) {
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

            if ($produto === null) {
                unset($this->itens[$id]);

                continue;
            }

            if ($produto['controle_estoque']) {
                $quantidade = min((int) $quantidade, max(1, (int) $produto['estoque']));
                $this->itens[$id] = $quantidade;
            }

            $linhas[] = $produto + [
                'quantidade' => $quantidade,
                'subtotal' => $produto['preco'] * $quantidade,
            ];
        }

        $this->salvar();

        return $linhas;
    }

    public function subtotalCents(): int
    {
        return array_sum(array_column($this->linhas(), 'subtotal'));
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
