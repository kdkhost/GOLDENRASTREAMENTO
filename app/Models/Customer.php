<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const STATUS = ['prospecto' => 'Prospecto', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];

    protected $fillable = [
        'nome', 'documento', 'tipo', 'telefone', 'email', 'endereco', 'cidade', 'uf', 'cep',
        'segmento', 'frota_tamanho', 'status', 'owner_id', 'lead_id', 'valor_anual_cents', 'observacoes',
    ];

    protected $casts = ['frota_tamanho' => 'integer', 'valor_anual_cents' => 'integer'];

    /** Rótulo de um status, com o próprio valor por baixo quando o status não é conhecido. */
    public static function label(?string $status): string
    {
        return self::STATUS[$status ?? ''] ?? (string) $status;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public static function deLead(Lead $lead): self
    {
        $c = new self([
            'nome' => $lead->name,
            'telefone' => $lead->phone,
            'email' => $lead->email,
            'segmento' => $lead->profile,
            'frota_tamanho' => is_numeric($lead->fleet_size) ? (int) $lead->fleet_size : null,
            'status' => 'ativo',
            'owner_id' => $lead->owner_id,
            'lead_id' => $lead->id,
            'valor_anual_cents' => $lead->value,
        ]);
        $c->save();
        $lead->update(['customer_id' => $c->id]);
        return $c;
    }
}
