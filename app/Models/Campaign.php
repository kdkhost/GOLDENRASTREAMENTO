<?php

namespace App\Models;

use App\Services\Atribuidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = ['name', 'source', 'medium', 'content', 'channel', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Os canais possíveis. É a mesma lista que o Atribuidor produz e que o painel de
     * atribuição agrupa — campanha nenhuma pode existir num canal que o site não sabe medir.
     * 'indexador' fica de fora: robô varrendo a web não é uma campanha que se cria.
     *
     * @return array<string, string>
     */
    public static function canais(): array
    {
        return collect(config('golden.canais'))
            ->except('indexador')
            ->map(fn (array $canal): string => $canal['rotulo'])
            ->all();
    }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->name, $this->source, $this->medium]);

        return implode(' · ', $parts);
    }

    public function canalLabel(): string
    {
        return Atribuidor::rotuloCanal($this->channel);
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

    /**
     * A URL pronta para colar no anúncio. Ela carrega a marcação que o próprio sistema
     * sabe ler, então o clique volta para esta tela em vez de virar "tráfego direto".
     */
    public function linkRastreado(?string $destino = null): string
    {
        $parametros = array_filter([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->name,
            'utm_content' => $this->content,
        ], fn (?string $v): bool => $v !== null && $v !== '');

        $base = $destino ?: url('/');

        return $parametros ? $base.(str_contains($base, '?') ? '&' : '?').http_build_query($parametros) : $base;
    }
}
