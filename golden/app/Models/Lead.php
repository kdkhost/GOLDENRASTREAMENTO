<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    public const STATUSES = ['novo', 'contatado', 'proposta', 'vendido', 'perdido'];

    public const STATUS_LABELS = [
        'novo' => 'Novo',
        'contatado' => 'Contatado',
        'proposta' => 'Proposta',
        'vendido' => 'Vendido',
        'perdido' => 'Perdido',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'profile', 'fleet_size', 'kit', 'message',
        'campaign_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'gclid', 'fbclid', 'visitor_id', 'landing_page', 'referrer', 'ip_hash', 'user_agent',
        'status', 'owner_id', 'consent_given', 'value', 'won_at', 'lost_at', 'customer_id',
    ];

    protected $casts = [
        'consent_given' => 'boolean',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'value' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public static function label(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public function scopeFunil($query)
    {
        return $query->whereIn('status', ['novo', 'contatado', 'proposta', 'vendido']);
    }
}
