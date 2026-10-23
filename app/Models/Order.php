<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS = [
        'pendente' => 'Pendente',
        'pago' => 'Pago',
        'em_andamento' => 'Em andamento',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];

    public const PAGAMENTOS = [
        'cartao' => 'Cartão',
        'boleto' => 'Boleto bancário',
        'pix' => 'Pix',
        'nao' => 'Não informado',
    ];

    protected $fillable = [
        'numero', 'customer_id', 'lead_id', 'user_id', 'nome', 'email', 'telefone',
        'endereco', 'cidade', 'uf', 'cep', 'subtotal_cents', 'frete_cents', 'desconto_cents',
        'total_cents', 'pagamento', 'status', 'notas', 'ip_hash', 'visitor_id', 'pago_em', 'enviado_em',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'frete_cents' => 'integer',
        'desconto_cents' => 'integer',
        'total_cents' => 'integer',
        'pago_em' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recalcular(): void
    {
        $subtotal = $this->items()->sum('subtotal_cents');
        $this->subtotal_cents = (int) $subtotal;
        $this->total_cents = max(0, $subtotal + (int) $this->frete_cents - (int) $this->desconto_cents);
        $this->save();
    }

    public static function proximoNumero(): string
    {
        $ano = now()->format('y');
        $seq = (int) static::where('numero', 'like', "GR{$ano}-%")->count() + 1;
        return sprintf('GR%s-%05d', $ano, $seq);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function pagamentoLabel(): string
    {
        return self::PAGAMENTOS[$this->pagamento] ?? $this->pagamento;
    }
}
