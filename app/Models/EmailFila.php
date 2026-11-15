<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Item enfileirado na fila anti-spam/cadenciada de disparos da plataforma.
 */
class EmailFila extends Model
{
    public const PENDENTE = 'pendente';

    public const PROCESSANDO = 'processando';

    public const ENVIADO = 'enviado';

    public const FALHOU = 'falhou';

    public const CANCELADO = 'cancelado';

    /** @var array<string, string> */
    public const STATUS_ROTULOS = [
        self::PENDENTE => 'Pendente',
        self::PROCESSANDO => 'Enviando',
        self::ENVIADO => 'Enviado',
        self::FALHOU => 'Falhou',
        self::CANCELADO => 'Cancelado',
    ];

    protected $table = 'email_fila';

    protected $fillable = [
        'destinatario_email',
        'destinatario_nome',
        'template_chave',
        'assunto',
        'corpo_html',
        'corpo_texto',
        'anexos',
        'metadados',
        'status',
        'prioridade',
        'tentativas',
        'max_tentativas',
        'agendado_para',
        'enviado_em',
        'ultimo_erro',
        'lote_id',
    ];

    protected $casts = [
        'anexos' => 'array',
        'metadados' => 'array',
        'prioridade' => 'integer',
        'tentativas' => 'integer',
        'max_tentativas' => 'integer',
        'agendado_para' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    /**
     * Seleciona mensagens pendentes prontas para envio no momento atual.
     */
    public function scopeProntosParaEnvio(Builder $query): Builder
    {
        return $query->where('status', self::PENDENTE)
            ->where(function (Builder $sub): void {
                $sub->whereNull('agendado_para')
                    ->orWhere('agendado_para', '<=', now());
            })
            ->orderBy('prioridade')
            ->orderBy('agendado_para')
            ->orderBy('id');
    }

    /**
     * Indica se o item ainda pode ser reprocessado manualmente.
     */
    public function podeReenviar(): bool
    {
        return in_array($this->status, [self::FALHOU, self::CANCELADO], true);
    }
}
