<?php

namespace App\Models;

use App\Support\Envio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Contrato do cliente. O arquivo entra pelo campo de arrastar-e-soltar do painel (regra
 * `contrato_documento` em config/envios.php) e o portal só oferece a leitura: o que o
 * cliente vê é sempre o que a equipe publicou nesta ficha.
 */
class Contract extends Model
{
    public const STATUS = [
        'rascunho' => 'Rascunho',
        'assinado' => 'Assinado',
        'vigente' => 'Vigente',
        'encerrado' => 'Encerrado',
    ];

    public const TIPOS = ['adesao' => 'Termo de adesão', 'aditivo' => 'Aditivo', 'renovacao' => 'Renovação'];

    /** Campos que só existem depois que a equipe escolhe um arquivo. */
    public const CAMPO_ENVIO = 'contrato_documento';

    protected $fillable = [
        'numero', 'customer_id', 'titulo', 'tipo', 'status', 'arquivo',
        'celebrado_em', 'vigencia_ate', 'valor_mensal_cents', 'observacoes',
    ];

    protected $casts = [
        'celebrado_em' => 'date',
        'vigencia_ate' => 'date',
        'valor_mensal_cents' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contrato) {
            $contrato->numero ??= 'CT-'.now()->format('ym').'-'.Str::random(4);
        });

        static::created(function (self $contrato) {
            $contrato->forceFill(['numero' => sprintf('CT%s-%04d', now()->format('y'), $contrato->id)])->saveQuietly();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? (string) $this->status;
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? (string) $this->tipo;
    }

    /** O PDF está no disco? Sem arquivo o contrato ainda é lista, não documento. */
    public function temArquivo(): bool
    {
        return Envio::caminhoConfere($this->arquivo, self::CAMPO_ENVIO);
    }

    public function vencendoEm(): bool
    {
        return $this->vigencia_ate !== null && $this->vigencia_ate->isBetween(now(), now()->addDays(30));
    }
}
