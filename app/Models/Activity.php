<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'action', 'subject_type', 'subject_id',
        'description', 'ip', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra um evento na trilha de auditoria.
     *
     * `$por` existe para o portal: lá quem age é o cliente (ficha de `customers`), não um
     * usuário do painel. Passado, ele assina a linha e nenhum `user_id` é gravado — sem
     * isso uma ação do cliente apareceria no nome da equipe que por acaso está logada no
     * painel do mesmo navegador.
     */
    public static function log(string $action, ?string $description = null, ?Model $subject = null, array $meta = [], ?string $por = null): void
    {
        $user = $por === null ? auth()->user() : null;

        static::create([
            'user_id' => $user?->id,
            'user_name' => $por ?? $user?->name ?? 'Sistema',
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip' => request()->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
