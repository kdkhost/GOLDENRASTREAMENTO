<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Chamado de suporte do portal. A conversa inteira vive em `ticket_messages` — a primeira
 * mensagem é o que o cliente escreveu ao abrir —, então painel e portal leem a mesma fila
 * e não há campo "última resposta" para manter sincronizado à mão.
 */
class Ticket extends Model
{
    public const STATUS = [
        'aberto' => 'Aberto',
        'em_analise' => 'Em análise',
        'aguardando_cliente' => 'Aguardando você',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ];

    /** Chamados que ainda esperam a equipe (bolha do menu e KPIs). */
    public const ABERTOS = ['aberto', 'em_analise'];

    public const PRIORIDADES = ['baixa' => 'Baixa', 'normal' => 'Normal', 'alta' => 'Alta', 'urgente' => 'Urgente'];

    public const CANAIS = ['portal' => 'Portal', 'telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'painel' => 'Painel'];

    protected $fillable = [
        'numero', 'customer_id', 'order_id', 'assunto', 'canal',
        'status', 'prioridade', 'owner_id', 'respondido_em', 'resolvido_em',
    ];

    protected $casts = ['respondido_em' => 'datetime', 'resolvido_em' => 'datetime'];

    protected static function booted(): void
    {
        // Igual ao pedido: o número comercial sai do id (CH26-00007), então dois chamados
        // abertos no mesmo segundo não disputam o MAX()+1.
        static::creating(function (self $chamado) {
            $chamado->numero ??= 'CH-'.now()->format('ymdHis').'-'.Str::random(4);
        });

        static::created(function (self $chamado) {
            $chamado->forceFill(['numero' => sprintf('CH%s-%05d', now()->format('y'), $chamado->id)])->saveQuietly();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    /** Última mensagem da fila — é ela que a listagem mostra como prévia. */
    public function ultimaMensagem(): ?TicketMessage
    {
        return $this->messages()->latest('id')->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? (string) $this->status;
    }

    public function prioridadeLabel(): string
    {
        return self::PRIORIDADES[$this->prioridade] ?? (string) $this->prioridade;
    }

    public function canalLabel(): string
    {
        return self::CANAIS[$this->canal] ?? (string) $this->canal;
    }

    /**
     * Move o chamado e carimba as datas que dependem do status: a primeira resposta da
     * equipe marca `respondido_em` (é dela que sai o "sem resposta há 3 dias") e resolver
     * ou fechar marca `resolvido_em`.
     */
    public function registrarStatus(string $status): void
    {
        $this->status = $status;

        if (in_array($status, ['resolvido', 'fechado'], true) && ! $this->resolvido_em) {
            $this->resolvido_em = now();
        }

        if (! in_array($status, ['resolvido', 'fechado'], true)) {
            $this->resolvido_em = null;
        }

        $this->save();
    }

    public function registradoPelaEquipe(): bool
    {
        return in_array($this->status, self::ABERTOS, true) && $this->respondido_em === null;
    }
}
