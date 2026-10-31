<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A foto de um período com a leitura do copiloto.
 *
 * O relatório nasce quando alguém pede a análise: os números daquele momento (resumo, campanhas
 * e dias) e o que o copiloto enxergou neles ficam gravados juntos. A cache do painel continua
 * sendo a verdade do presente; esta linha é o histórico, e é por ela que se compara a leitura de
 * ontem com a de hoje sem depender de a métrica ter sido sincronizada de novo.
 */
class GoogleAdsRelatorio extends Model
{
    protected $fillable = [
        'user_id', 'google_ads_account_id', 'dias', 'resumo_json',
        'campanhas_json', 'analise_json', 'modelo', 'gerado_em',
    ];

    protected $casts = [
        'resumo_json' => 'array',
        'campanhas_json' => 'array',
        'analise_json' => 'array',
        'gerado_em' => 'datetime',
        'dias' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class, 'google_ads_account_id');
    }

    public function diagnostico(): string
    {
        return (string) ($this->analise_json['diagnostico'] ?? '');
    }

    /** @return array<int, array{titulo:string, detalhe:string, impacto:string}> */
    public function recomendacoes(): array
    {
        $lista = $this->analise_json['recomendacoes'] ?? [];

        return is_array($lista) ? array_values(array_filter($lista, 'is_array')) : [];
    }

    /** @return array<int, string> */
    public function destaques(): array
    {
        $lista = $this->analise_json['destaques'] ?? [];

        return is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
    }
}
