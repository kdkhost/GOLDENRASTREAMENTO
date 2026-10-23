<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const CANAIS = ['pago' => 'Pago', 'organico' => 'Orgânico', 'direto' => 'Direto', 'referencia' => 'Indicação'];

    protected $fillable = ['name', 'source', 'medium', 'content', 'channel', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->name, $this->source, $this->medium]);
        return implode(' · ', $parts);
    }

    public function canalLabel(): string
    {
        return self::CANAIS[$this->channel] ?? $this->channel;
    }

    /**
     * Percentual de leads que viraram venda. Usa os contadores que a própria listagem
     * carregou (leads_count e leads_vendidos); sem eles, conta pela relação.
     */
    public function conversao(): float
    {
        $total = (int) ($this->attributes['leads_count'] ?? $this->leads()->count());

        if ($total === 0) {
            return 0.0;
        }

        $vendidos = (int) ($this->attributes['leads_vendidos'] ?? $this->leads()->where('status', 'vendido')->count());

        return round($vendidos / $total * 100, 1);
    }
}
