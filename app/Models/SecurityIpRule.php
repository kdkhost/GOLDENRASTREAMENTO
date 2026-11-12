<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SecurityIpRule extends Model
{
    use HasFactory;

    public const TIPO_WHITELIST = 'whitelist';

    public const TIPO_BLACKLIST = 'blacklist';

    public const NIVEL_BAIXO = 'baixo';

    public const NIVEL_MEDIO = 'medio';

    public const NIVEL_ALTO = 'alto';

    public const NIVEL_CRITICO = 'critico';

    protected $table = 'security_ip_rules';

    protected $fillable = [
        'ip',
        'tipo',
        'motivo',
        'nivel_ameaca',
        'expira_em',
        'tentativas_bloqueadas',
        'ultimo_bloqueio_em',
        'created_by_user_id',
    ];

    protected $casts = [
        'expira_em' => 'datetime',
        'ultimo_bloqueio_em' => 'datetime',
        'tentativas_bloqueadas' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expira_em')
                ->orWhere('expira_em', '>', Carbon::now());
        });
    }

    public function scopeWhitelist(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_WHITELIST);
    }

    public function scopeBlacklist(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_BLACKLIST);
    }

    public function estaExpirada(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }
}

