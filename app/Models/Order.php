<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUS = [
        'pendente' => 'Pendente',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pago' => 'Pago',
        'separando' => 'Em separação',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];

    /** Ordem dos status na linha do tempo do cliente (cancelado fica à parte). */
    public const JORNADA = ['pendente', 'aguardando_pagamento', 'pago', 'separando', 'enviado', 'entregue'];

    /** Pedidos que ainda exigem ação da equipe (KPIs do dashboard e da sidebar). */
    public const ABERTOS = ['pendente', 'aguardando_pagamento', 'pago', 'separando'];

    public const PAGAMENTOS = [
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'boleto' => 'Boleto bancário',
        'retirada' => 'Pagamento na retirada',
        'nao' => 'Não informado',
    ];

    public const ENTREGAS = [
        'entrega' => 'Entrega nos Correios/transportadora',
        'retira' => 'Retirada na loja',
    ];

    protected $fillable = [
        'numero', 'customer_id', 'lead_id', 'user_id', 'nome', 'email', 'telefone',
        'tipo_pessoa', 'documento', 'entrega', 'endereco', 'bairro', 'complemento',
        'cidade', 'uf', 'cep', 'subtotal_cents', 'frete_cents', 'desconto_cents',
        'total_cents', 'pagamento', 'status', 'notas', 'consent_given', 'ip_hash',
        'visitor_id', 'pago_em', 'enviado_em',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'frete_cents' => 'integer',
        'desconto_cents' => 'integer',
        'total_cents' => 'integer',
        'consent_given' => 'boolean',
        'pago_em' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    /** O token nunca sai do modelo: só a rota pública montada pelo controlador. */
    protected $hidden = ['token', 'ip_hash'];

    protected static function booted(): void
    {
        // O número comercial vem do id (GR26-00007), então não há corrida de MAX()+1
        // quando dois pedidos chegam no mesmo instante. O provisório só existe para
        // satisfazer o NOT NULL do insert e é substituído logo em seguida.
        static::creating(function (self $pedido) {
            $pedido->numero ??= 'GR-'.now()->format('ymdHis').'-'.Str::random(6);
            $pedido->token ??= Str::random(32);
        });

        static::created(function (self $pedido) {
            $pedido->forceFill(['numero' => sprintf('GR%s-%05d', now()->format('y'), $pedido->id)])->saveQuietly();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->oldest();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Registro público do pedido: número + token aleatório, sem expor o id. */
    public function registroUrl(): string
    {
        return route('site.loja.pedido', ['numero' => $this->numero, 'token' => $this->token]);
    }

    public function recalcular(): void
    {
        $subtotal = (int) $this->items()->sum('subtotal_cents');
        $this->subtotal_cents = $subtotal;
        $this->total_cents = max(0, $subtotal + (int) $this->frete_cents - (int) $this->desconto_cents);
        $this->save();
    }

    /**
     * Move o pedido na jornada e escreve o marco da linha do tempo. As datas de
     * pagamento/envio são consequência do status, não campo de formulário.
     */
    public function registrarStatus(string $status, ?string $observacao = null, ?int $userId = null): void
    {
        $anterior = $this->status;
        $this->status = $status;

        if ($status === 'pago' && $anterior !== 'pago') {
            $this->pago_em = now();
        }
        if (in_array($status, ['enviado', 'entregue'], true) && ! $this->enviado_em) {
            $this->enviado_em = now();
        }

        $this->save();

        if ($anterior !== $status || $observacao != null) {
            $this->histories()->create([
                'status' => $status,
                'observacao' => $observacao,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Linha do tempo vista pelo cliente: os marcos já percorridos da jornada, com a data
     * do primeiro registro de cada um.
     */
    public function jornada(): array
    {
        $marcos = $this->histories
            ->groupBy(fn (OrderHistory $h) => $h->status)
            ->map(fn ($grupo) => $grupo->first()->created_at);

        $etapas = [];
        $parou = $this->status === 'cancelado';

        foreach (self::JORNADA as $status) {
            $feito = $marcos->has($status);
            $etapas[] = [
                'status' => $status,
                'titulo' => self::STATUS[$status],
                'quando' => $feito ? $marcos[$status] : null,
                'atual' => ! $parou && $this->status === $status,
            ];

            if ($this->status === $status) {
                break;
            }
        }

        if ($parou) {
            $etapas[] = [
                'status' => 'cancelado',
                'titulo' => self::STATUS['cancelado'],
                'quando' => $marcos['cancelado'] ?? null,
                'atual' => true,
            ];
        }

        return $etapas;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function pagamentoLabel(): string
    {
        return self::PAGAMENTOS[$this->pagamento] ?? $this->pagamento;
    }

    public function entregaLabel(): string
    {
        return self::ENTREGAS[$this->entrega] ?? $this->entrega;
    }

    public function documentoFormatado(): string
    {
        $d = preg_replace('/\D/', '', (string) $this->documento);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => $d,
        };
    }

    /** Endereço em uma linha, na ordem usada pelos Correios. */
    public function enderecoFormatado(): string
    {
        $partes = array_filter([
            $this->endereco,
            $this->complemento,
            $this->bairro,
            trim($this->cidade.', '.$this->uf, ', '),
            $this->cep ? 'CEP '.preg_replace('/(\d{5})(\d{3})/', '$1-$2', $this->cep) : null,
        ]);

        return implode(' — ', $partes);
    }
}
