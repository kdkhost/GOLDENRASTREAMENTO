<?php

namespace App\Models;

use App\Services\Atribuidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

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
        'visit_id', 'canal', 'rede', 'pago', 'canal_primeiro', 'rede_primeiro',
        'primeiro_toque_em', 'toques', 'dispositivo', 'navegador',
        'status', 'owner_id', 'consent_given', 'value', 'won_at', 'lost_at', 'customer_id',
        'funnel_page_id', 'clique_id', 'clique_valor',
    ];

    protected $casts = [
        'consent_given' => 'boolean',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'primeiro_toque_em' => 'datetime',
        'pago' => 'boolean',
        'value' => 'integer',
        'toques' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** A última visita antes do cadastro — a jornada completa está nela e nos eventos dela. */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** Todas as visitas deste mesmo visitante, da primeira ao cadastro. */
    public function jornada(): HasMany
    {
        return $this->hasMany(Visit::class, 'visitor_id', 'visitor_id')->orderBy('aberta_em');
    }

    /** A página de campanha que gerou o cadastro — null para todo lead do site institucional. */
    public function funnelPage(): BelongsTo
    {
        return $this->belongsTo(FunnelPage::class);
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

    public function canalRotulo(): string
    {
        return Atribuidor::rotuloCanal($this->canal);
    }

    public function redeRotulo(): string
    {
        return Atribuidor::rotuloRede($this->rede);
    }

    public function iconeCanal(): string
    {
        return Atribuidor::iconeCanal($this->canal);
    }

    /** O tom do selo de origem, na mesma régua do painel de atribuição. */
    public function tomCanal(): string
    {
        return tom_por_status('canal', $this->canal);
    }

    /**
     * "Conheceu no Instagram, fechou pelo anúncio": primeiro e último toque quando
     * eles diferem. Iguais, é uma origem única e a frase não precisa se repetir.
     */
    public function origemResumida(): string
    {
        $ultimo = $this->canalRotulo();
        $primeiro = Atribuidor::rotuloCanal($this->canal_primeiro);

        if (! $this->canal_primeiro || $this->canal_primeiro === $this->canal) {
            return $ultimo;
        }

        return $primeiro.' → '.$ultimo;
    }

    public function scopeFunil($query)
    {
        return $query->whereIn('status', ['novo', 'contatado', 'proposta', 'vendido']);
    }
}
