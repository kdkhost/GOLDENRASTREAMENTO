<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha da caixa-preta das chamadas de IA.
 *
 * É o mesmo desenho de `google_ads_api_logs`, pela mesma razão: a discussão sobre uma resposta
 * do assistente precisa de prova. O que foi mandado, o que voltou, quanto demorou, quantos
 * tokens e — quando falhou — o texto exato do erro, porque "a IA respondeu errado" sem o corpo
 * da chamada não se corrige.
 *
 * Mídia nunca entra em claro. Um PDF ou um áudio viraria base64 de vários megabytes numa
 * coluna de texto, e o que a equipe precisa ler não é o arquivo: é a marca dele (`imagem jpg,
 * 231 KB`) e o endereço do original, que continua em `conversas_mensagens`.
 *
 * Só `created_at`: a linha é um registro, ninguém a edita depois de escrita.
 */
class AssistenteApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'assistente_api_logs';

    protected $fillable = [
        'capacidade', 'provedor', 'modelo', 'user_id', 'conversa_id',
        'request_json', 'response_json', 'error_message',
        'duracao_ms', 'tokens_entrada', 'tokens_saida', 'audio_bytes',
    ];

    protected $casts = [
        'request_json' => 'array',
        'response_json' => 'array',
        'duracao_ms' => 'integer',
        'tokens_entrada' => 'integer',
        'tokens_saida' => 'integer',
        'audio_bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function capacidade(string $capacidade): string
    {
        return (string) (config('assistente.capacidades')[$capacidade]['rotulo'] ?? $capacidade);
    }

    /** O rótulo do provedor na tela, sem nenhuma view saber endereço ou código de API. */
    public function provedorLabel(): string
    {
        return (string) (config('assistente.provedores')[$this->provedor]['rotulo'] ?? $this->provedor);
    }
}
