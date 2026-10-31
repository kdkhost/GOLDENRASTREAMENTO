<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um plano de campanha que o copiloto escreveu a partir de um briefing.
 *
 * O plano nasce rascunho, vira aprovado com o aval de um administrador e só então pode ser
 * aplicado no Google — e mesmo assim nasce pausado. Essa ordem é o freio da casa: a IA propõe,
 * uma pessoa confere, e nada começa a gastar sem um segundo ato explícito.
 *
 * `briefing_json` guarda o pedido como foi feito, e `plano_json` o resultado já normalizado
 * pelas travas do Google. Os dois ficam: se uma campanha render menos que o esperado, a
 * pergunta "o que foi pedido?" tem resposta, e não depende da memória de quem digitou.
 */
class GoogleAdsPlano extends Model
{
    public const RASCUNHO = 'rascunho';

    public const APROVADO = 'aprovado';

    public const APLICADO = 'aplicado';

    public const FALHOU = 'falhou';

    public const DESCARTADO = 'descartado';

    public const STATUS = [
        self::RASCUNHO => 'Rascunho',
        self::APROVADO => 'Aprovado',
        self::APLICADO => 'Aplicado no Google',
        self::FALHOU => 'Falhou ao aplicar',
        self::DESCARTADO => 'Descartado',
    ];

    protected $fillable = [
        'user_id', 'google_ads_account_id', 'nome', 'status', 'objetivo',
        'orcamento_diario_centavos', 'briefing_json', 'plano_json', 'validacao_json',
        'aplicacao_json', 'modelo', 'erro', 'aplicado_em',
    ];

    protected $casts = [
        'briefing_json' => 'array',
        'plano_json' => 'array',
        'validacao_json' => 'array',
        'aplicacao_json' => 'array',
        'orcamento_diario_centavos' => 'integer',
        'aplicado_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class, 'google_ads_account_id');
    }

    /** A mesma régua de visibilidade da conta: administrador vê a equipe, o resto vê o seu. */
    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        return $usuario->isAdmin() ? $query : $query->where('user_id', $usuario->id);
    }

    public function rotuloStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** O plano normalizado, sempre como lista — um JSON corrompido não pode derrubar a tela. */
    public function plano(): array
    {
        return is_array($this->plano_json) ? $this->plano_json : [];
    }

    public function validacao(): array
    {
        return is_array($this->validacao_json) ? $this->validacao_json : [];
    }

    public function problemas(): array
    {
        return (array) ($this->validacao()['problemas'] ?? []);
    }

    public function avisos(): array
    {
        return (array) ($this->validacao()['avisos'] ?? []);
    }

    /** Só um plano aprovado pode ser escrito no Google — e nunca duas vezes. */
    public function podeAplicar(): bool
    {
        return $this->status === self::APROVADO;
    }

    /** O identificador da campanha que a aplicação criou, quando ela criou alguma. */
    public function campanhaCriada(): ?string
    {
        $id = data_get($this->aplicacao_json, 'campanha_id');

        return filled($id) ? (string) $id : null;
    }
}
