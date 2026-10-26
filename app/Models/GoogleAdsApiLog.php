<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha da caixa-preta das chamadas à API do Google Ads.
 *
 * Não é log de arquivo: a conversa com o Google precisa ser consultável por conta e por data,
 * porque o erro que chega da API costuma ser uma discussão com o suporte — e sem a consulta
 * GAQL executada e o texto exato da resposta não há como provar o que foi enviado.
 *
 * Só `created_at`: a linha é um registro, ninguém a edita depois de escrita.
 */
class GoogleAdsApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'google_ads_account_id', 'endpoint',
        'request_json', 'response_json', 'error_message', 'duracao_ms',
    ];

    protected $casts = [
        'request_json' => 'array',
        'response_json' => 'array',
        'duracao_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class, 'google_ads_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
