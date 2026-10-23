<?php

namespace App\Models;

use App\Services\Atribuidor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma visita ao site: a janela de atividade de um visitante anônimo, com a origem
 * já resolvida pelo Atribuidor. Cada visita tem seus cliques em VisitEvent.
 */
class Visit extends Model
{
    protected $fillable = [
        'visitor_id', 'sessao', 'canal', 'rede', 'pago', 'motivo',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'clique_id', 'clique_valor', 'referer', 'origem_host', 'origem_path', 'landing_path',
        'dispositivo', 'navegador', 'sistema', 'ip_hash', 'bot', 'visualizacoes', 'eventos',
        'aberta_em', 'ultima_em',
    ];

    protected $casts = [
        'pago' => 'boolean',
        'bot' => 'boolean',
        'aberta_em' => 'datetime',
        'ultima_em' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(VisitEvent::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** Só gente: indexador visita o site, mas não entra em nenhum número do painel. */
    public function scopePessoas(Builder $q): Builder
    {
        return $q->where('bot', false)->where('canal', '!=', 'indexador');
    }

    public function scopeEntre(Builder $q, ?string $de, ?string $ate): Builder
    {
        return $q->when($de, fn (Builder $inner) => $inner->whereDate('ultima_em', '>=', $de))
            ->when($ate, fn (Builder $inner) => $inner->whereDate('ultima_em', '<=', $ate));
    }

    public function canalRotulo(): string
    {
        return Atribuidor::rotuloCanal($this->canal);
    }

    /** Os dispositivos possíveis no filtro do painel — robô não é dispositivo de cliente. */
    public static function dispositivos(): array
    {
        return collect(config('golden.dispositivos'))
            ->reject(fn (array $d, string $chave): bool => $chave === 'robô')
            ->map(fn (array $d): string => $d['rotulo'])
            ->all();
    }

    public static function rotuloDispositivo(?string $dispositivo): string
    {
        return config('golden.dispositivos.'.$dispositivo.'.rotulo') ?? 'Não informado';
    }

    public function redeRotulo(): string
    {
        return Atribuidor::rotuloRede($this->rede);
    }

    public function iconeCanal(): string
    {
        return Atribuidor::iconeCanal($this->canal);
    }

    /**
     * A frase que aparece na jornada: onde a pessoa estava antes de cair no site.
     * Sem referer conhecido é acesso direto — digitou, ou abriu de um favoritos.
     */
    public function origemDetalhada(): string
    {
        if ($this->canal === 'direto') {
            return 'Acesso direto, sem origem';
        }

        if ($this->origem_path && $this->origem_path !== '/') {
            return $this->redeRotulo().' · '.$this->origem_path;
        }

        return $this->origem_host
            ? $this->redeRotulo().' · '.$this->origem_host
            : $this->redeRotulo();
    }
}
