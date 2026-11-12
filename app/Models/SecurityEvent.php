<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'security_events';

    protected $fillable = [
        'ip',
        'evento',
        'nivel',
        'metodo',
        'url',
        'user_agent',
        'payload_suspeito',
        'acao_tomada',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRecentes(Builder $query, int $dias = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($dias))->latest('created_at');
    }

    /**
     * Rótulo visual e ícone do evento.
     */
    public function rotuloEvento(): string
    {
        return match ($this->evento) {
            'sqli' => 'Tentativa de Injeção SQL',
            'xss' => 'Cross-Site Scripting (XSS)',
            'rce' => 'Execução Remota de Código (RCE)',
            'path_traversal' => 'Quebra de Diretório (Path Traversal)',
            'scanner' => 'Scanner Hostil / Bot Malicioso',
            'sensitive_probe' => 'Varredura de Arquivo Crítico (.env, git, wp)',
            'honeypot' => 'Invasão de Rota Honeypot (Isca)',
            'rate_limit' => 'Excesso de Requisições (Rate Limit)',
            'blacklisted_access' => 'Acesso de IP na Lista Negra',
            'bloqueio_manual' => 'Bloqueio Manual pelo Administrador',
            default => ucfirst(str_replace('_', ' ', $this->evento)),
        };
    }

    public function icone(): string
    {
        return match ($this->evento) {
            'sqli' => 'fa-database',
            'xss' => 'fa-code',
            'rce', 'path_traversal' => 'fa-terminal',
            'scanner' => 'fa-spider',
            'sensitive_probe', 'honeypot' => 'fa-shield-halved',
            'rate_limit' => 'fa-gauge-high',
            default => 'fa-triangle-exclamation',
        };
    }

    public function tom(): string
    {
        return match ($this->nivel) {
            'critico' => 'danger',
            'alto' => 'warning',
            'medio' => 'info',
            default => 'secondary',
        };
    }
}

