<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'status', 'telefone', 'avatar',
        'cargo', 'setor', 'bio', 'admissao',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // A segunda chave da casa não pode sobreviver a um despejo do banco: cifrada aqui,
            // ela só existe em texto na memória de quem está verificando um código. Os códigos de
            // recuperação são uma lista, e a cifra dela entra como lista e sai como lista.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'admissao' => 'date',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    /**
     * A foto redonda da pessoa — no topo do painel, na lista e na ficha.
     *
     * Devolve null quando não há arquivo (ou ele sumiu do disco): quem chama desenha as iniciais,
     * em vez de deixar um `img` quebrado no lugar do rosto.
     */
    public function avatarUrl(): ?string
    {
        $caminho = trim((string) $this->avatar);

        if ($caminho === '' || ! Storage::disk('public')->exists($caminho)) {
            return null;
        }

        return asset('storage/'.$caminho);
    }

    /** Até duas letras para o círculo sem foto: "Diego Alisson" vira "DA". */
    public function iniciais(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return mb_strtoupper(mb_substr($partes[0] ?? 'G', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    /** A última porta que esta pessoa abriu — a aba de Segurança mostra a hora e a rede. */
    public function marcarAcesso(?string $ip): void
    {
        $this->forceFill([
            'ultimo_acesso_em' => now(),
            'ultimo_acesso_ip' => $ip,
        ])->saveQuietly();
    }

    /** Com o segredo guardado e o aplicativo confirmado: é isto que fecha a porta no login. */
    public function doisFatoresAtivo(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function podeGerir(): bool
    {
        return in_array($this->role, ['admin', 'vendedor'], true);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_id');
    }
}
