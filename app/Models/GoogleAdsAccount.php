<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta de Google Ads conectada a um usuário do painel.
 *
 * A linha é o que permite ao sistema falar com a API sem que ninguém precise fazer login no
 * Google de novo: guarda o número da conta consultada e o token de atualização, que o cast
 * `encrypted` cifra antes de sair do PHP — no banco, e em qualquer dump, o que se vê é
 * criptograma.
 *
 * Uma conta por usuário e por `customer_id` (a chave única): reconectar a mesma conta
 * atualiza a linha existente em vez de abrir duplicata, e quem conectou pelo MCC pode trocar
 * de conta sem pedir consentimento de novo, porque o token vale para toda a árvore.
 */
class GoogleAdsAccount extends Model
{
    use HasFactory;

    public const ATIVO = 'ativo';

    public const DESCONECTADO = 'desconectado';

    /** Os dois únicos estados que a tela conhece: conecta, desconecta. */
    public const STATUS = [self::ATIVO, self::DESCONECTADO];

    protected $fillable = [
        'user_id', 'customer_id', 'refresh_token',
        'descriptive_name', 'currency_code', 'status', 'motivo_desconexao', 'sincronizado_em',
    ];

    protected $casts = [
        // Nunca em claro: o token dá acesso à conta inteira do anunciante.
        'refresh_token' => 'encrypted',
        'sincronizado_em' => 'datetime',
    ];

    /** O token cifrado jamais volta para a tela como valor do formulário. */
    protected $hidden = ['refresh_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GoogleAdsApiLog::class)->latest('created_at');
    }

    /** Só o que está conectado: a fila de sincronização e o painel consultam isto. */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status', self::ATIVO);
    }

    /**
     * As contas que este usuário pode ver. É uma regra só, escrita uma vez: o administrador
     * vê as conexões da equipe (a agência opera as contas dos outros), qualquer outro papel
     * vê a dele. Toda listagem e toda ação do painel de Google Ads passam por aqui — por
     * isso nenhuma consulta consegue trazer a conta de outro usuário.
     */
    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        return $usuario->isAdmin() ? $query : $query->where('user_id', $usuario->id);
    }

    /** A conexão deste usuário, se houver. Um usuário conecta uma conta por `customer_id`. */
    public static function doUsuario(User $usuario): ?self
    {
        return static::where('user_id', $usuario->id)->latest('id')->first();
    }

    /**
     * O número como o Google o escreve na própria tela: 123-456-7890. Guardamos sem hífen
     * (é assim que a API pede) e o identificador tem dez dígitos; qualquer outra largura —
     * um número antigo, um teste — sai como está, sem hífen inventado.
     */
    public function numeroConta(): string
    {
        $id = (string) $this->customer_id;

        return strlen($id) === 10
            ? substr($id, 0, 3).'-'.substr($id, 3, 3).'-'.substr($id, 6, 4)
            : $id;
    }

    public function rotuloStatus(): string
    {
        return $this->status === self::ATIVO ? 'Conectada' : 'Desconectada';
    }

    /** Nome que a conta anuncia no painel: o descritivo do Google, ou o número. */
    public function nomeTela(): string
    {
        return $this->descriptive_name ?: 'Conta '.$this->numeroConta();
    }

    /**
     * Desconecta a conta guardando o motivo.
     *
     * Uma escrita só para as duas pontas que descobrem uma credencial recusada — o job de
     * sincronização e a ação de pausar/ativar —, porque as duas precisam parar de chamar o
     * Google pelo mesmo motivo e dizer o mesmo na tela.
     */
    public function desconectar(string $motivo): void
    {
        $this->forceFill([
            'status' => self::DESCONECTADO,
            'motivo_desconexao' => mb_substr($motivo, 0, 190),
        ])->save();
    }
}
