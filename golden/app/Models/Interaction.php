<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
{
    public const TIPOS = [
        'ligacao' => 'Ligação',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'reuniao' => 'Reunião',
        'nota' => 'Nota',
    ];

    protected $fillable = ['customer_id', 'tipo', 'assunto', 'descricao', 'concluida', 'proxima_acao_em', 'user_id'];

    protected $casts = ['concluida' => 'boolean', 'proxima_acao_em' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }
}
