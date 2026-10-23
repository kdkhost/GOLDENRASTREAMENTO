<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma fala dentro do chamado. O autor é sempre resolvido pelo id de quem escreveu — quem
 * é da equipe assina com o nome do painel, o cliente com o nome da ficha —, então a view
 * não precisa de um campo "de" que poderia mentir.
 */
class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'customer_id', 'user_id', 'corpo'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function daEquipe(): bool
    {
        return $this->user_id !== null;
    }

    public function autor(): string
    {
        return $this->daEquipe()
            ? (string) ($this->user?->name ?? 'Equipe Golden')
            : (string) ($this->customer?->nome ?? 'Cliente');
    }
}
