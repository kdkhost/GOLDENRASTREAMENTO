<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ficha do CRM é também a conta do portal: `email_portal` + `password` dão acesso ao
 * /portal e nada mais é copiado. Quem entra aqui vê exatamente os pedidos, os chamados e
 * os contratos da mesma ficha que o vendedor cuida no painel.
 */
class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public const STATUS = ['prospecto' => 'Prospecto', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];

    protected $fillable = [
        'whmcs_id',
        'nome', 'documento', 'nascimento', 'placas', 'tipo', 'telefone', 'email', 'email_portal', 'password',
        'endereco', 'cidade', 'uf', 'cep',
        'segmento', 'frota_tamanho', 'status', 'owner_id', 'lead_id', 'valor_anual_cents', 'observacoes',
    ];

    /** A senha nunca sai do banco para uma view, um export ou um dump de depuração. */
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'whmcs_id' => 'integer',
        'frota_tamanho' => 'integer',
        'valor_anual_cents' => 'integer',
        'nascimento' => 'date',
        'password' => 'hashed',
        'ultimo_acesso_em' => 'datetime',
    ];

    /** Rótulo de um status, com o próprio valor por baixo quando o status não é conhecido. */
    public static function label(?string $status): string
    {
        return self::STATUS[$status ?? ''] ?? (string) $status;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->latest();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest('celebrado_em');
    }

    /** Esta ficha já tem conta no portal? É o que a tela de clientes mostra como selo. */
    public function temConta(): bool
    {
        return $this->password !== null;
    }

    /** Tipo de pessoa deduzido do documento: 11 dígitos é CPF, 14 é CNPJ. */
    public static function tipoPorDocumento(?string $documento): string
    {
        return match (strlen(preg_replace('/\D/', '', (string) $documento))) {
            14 => 'juridica',
            default => 'fisica',
        };
    }

    /**
     * Puxa para a ficha os pedidos de loja feitos com o mesmo e-mail antes do cadastro.
     * Sem isso o cliente criaria a conta e a primeira tela viria vazia, porque a loja
     * grava o pedido sem ficha quando o comprador não tinha cadastro.
     */
    public function vincularPedidosSemFicha(): int
    {
        $email = mb_strtolower((string) $this->emailDeAcesso());

        if ($email === '') {
            return 0;
        }

        return Order::whereNull('customer_id')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->update(['customer_id' => $this->id]);
    }

    /**
     * O endereço pelo qual esta pessoa é alcançada: o de acesso ao portal quando ela tem conta,
     * o da ficha comercial nos outros casos. É a única fonte desta resposta no sistema — o aviso
     * de chamado, o link de senha e a segunda via da fatura saem todos por aqui, e é por isso
     * também que um endereço digitado num chat nunca decide o destino de um envio.
     */
    public function emailDeAcesso(): ?string
    {
        return $this->email_portal ?: $this->email;
    }

    /** As placas da ficha, normalizadas: maiúsculas, sem espaço, sem traço, sem repetida. */
    public function placasListadas(): array
    {
        $placas = array_map(
            fn (string $placa): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa)),
            explode(',', (string) $this->placas)
        );

        return array_values(array_unique(array_filter($placas, fn (string $p): bool => $p !== '')));
    }

    /**
     * A ficha que atende por este e-mail, seja ele o de acesso ao portal ou o comercial.
     *
     * A comparação é insensível a maiúsculas porque ninguém digita e-mail igual duas vezes, e o
     * `limit(1)` não é descuido: e-mail repetido em duas fichas é um problema de cadastro da casa,
     * e a primeira é a que o CRM mostra em toda listagem também.
     */
    public static function porEmail(string $email): ?self
    {
        $normalizado = mb_strtolower(trim($email));

        if ($normalizado === '') {
            return null;
        }

        return static::query()
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(email) = ?', [$normalizado])
                ->orWhereRaw('LOWER(email_portal) = ?', [$normalizado]))
            ->orderBy('id')
            ->first();
    }

    public function registrarAcesso(): void
    {
        $this->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();
    }

    public static function deLead(Lead $lead): self
    {
        $c = new self([
            'nome' => $lead->name,
            'telefone' => $lead->phone,
            'email' => $lead->email,
            'segmento' => $lead->profile,
            'frota_tamanho' => is_numeric($lead->fleet_size) ? (int) $lead->fleet_size : null,
            'status' => 'ativo',
            'owner_id' => $lead->owner_id,
            'lead_id' => $lead->id,
            'valor_anual_cents' => $lead->value,
        ]);
        $c->save();
        $lead->update(['customer_id' => $c->id]);

        return $c;
    }
}
