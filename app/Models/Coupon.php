<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Coupon extends Model
{
    public const TIPOS = [
        'percentual' => 'Percentual (%)',
        'fixo' => 'Valor fixo (R$)',
        'frete_gratis' => 'Frete grátis',
    ];

    protected $fillable = [
        'codigo',
        'descricao',
        'tipo',
        'valor',
        'valor_minimo_cents',
        'desconto_maximo_cents',
        'limite_usos',
        'limite_por_cliente',
        'usos',
        'valido_de',
        'valido_ate',
        'ativo',
    ];

    protected $casts = [
        'valor' => 'integer',
        'valor_minimo_cents' => 'integer',
        'desconto_maximo_cents' => 'integer',
        'limite_usos' => 'integer',
        'limite_por_cliente' => 'integer',
        'usos' => 'integer',
        'valido_de' => 'datetime',
        'valido_ate' => 'datetime',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $cupom) {
            $cupom->codigo = self::normalizarCodigo((string) $cupom->codigo);
        });
    }

    public static function normalizarCodigo(string $codigo): string
    {
        return Str::upper(trim($codigo));
    }

    public static function buscarPorCodigo(string $codigo): ?self
    {
        $limpo = self::normalizarCodigo($codigo);

        if ($limpo === '') {
            return null;
        }

        return static::where('codigo', $limpo)->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function estaExpirado(): bool
    {
        return $this->valido_ate !== null && $this->valido_ate->isPast();
    }

    public function estaEsgotado(): bool
    {
        return $this->limite_usos !== null && $this->usos >= $this->limite_usos;
    }

    public function estaVigente(): bool
    {
        if (! $this->ativo || $this->estaExpirado() || $this->estaEsgotado()) {
            return false;
        }

        if ($this->valido_de !== null && $this->valido_de->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Valida se o cupom pode ser aplicado ao subtotal e cliente informados.
     *
     * @return array{ok: bool, erro?: string}
     */
    public function validarParaCompra(int $subtotalCents, ?string $email = null, ?string $documento = null): array
    {
        if (! $this->ativo) {
            return ['ok' => false, 'erro' => 'Este cupom está desativado no momento.'];
        }

        if ($this->valido_de !== null && $this->valido_de->isFuture()) {
            return ['ok' => false, 'erro' => 'Este cupom ainda não entrou em vigência.'];
        }

        if ($this->estaExpirado()) {
            return ['ok' => false, 'erro' => 'Este cupom expirou em '.$this->valido_ate->format('d/m/Y H:i').'.'];
        }

        if ($this->estaEsgotado()) {
            return ['ok' => false, 'erro' => 'Este cupom atingiu o limite máximo de utilizações.'];
        }

        if ($this->valor_minimo_cents > 0 && $subtotalCents < $this->valor_minimo_cents) {
            return [
                'ok' => false,
                'erro' => 'Este cupom exige pedido mínimo de '.brl($this->valor_minimo_cents).'.',
            ];
        }

        if ($this->limite_por_cliente !== null && ($email || $documento)) {
            $usosCliente = Order::query()
                ->where('coupon_id', $this->id)
                ->where('status', '!=', 'cancelado')
                ->where(function ($q) use ($email, $documento) {
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($documento) {
                        $q->orWhere('documento', $documento);
                    }
                })
                ->count();

            if ($usosCliente >= $this->limite_por_cliente) {
                return ['ok' => false, 'erro' => 'Você já atingiu o limite de uso deste cupom.'];
            }
        }

        return ['ok' => true];
    }

    /**
     * Calcula o desconto em centavos e se o cupom zera o frete.
     *
     * @return array{desconto_cents: int, frete_gratis: bool}
     */
    public function calcularDesconto(int $subtotalCents): array
    {
        if ($this->tipo === 'frete_gratis') {
            return ['desconto_cents' => 0, 'frete_gratis' => true];
        }

        if ($subtotalCents <= 0) {
            return ['desconto_cents' => 0, 'frete_gratis' => false];
        }

        if ($this->tipo === 'percentual') {
            $pct = min(100, max(0, $this->valor));
            $desconto = (int) round(($subtotalCents * $pct) / 100);

            if ($this->desconto_maximo_cents !== null && $this->desconto_maximo_cents > 0) {
                $desconto = min($desconto, $this->desconto_maximo_cents);
            }

            return [
                'desconto_cents' => min($subtotalCents, max(0, $desconto)),
                'frete_gratis' => false,
            ];
        }

        return [
            'desconto_cents' => min($subtotalCents, max(0, $this->valor)),
            'frete_gratis' => false,
        ];
    }

    public function rotuloDesconto(): string
    {
        return match ($this->tipo) {
            'percentual' => $this->valor.'% OFF',
            'fixo' => brl($this->valor).' OFF',
            'frete_gratis' => 'Frete grátis',
            default => 'Desconto',
        };
    }
}
