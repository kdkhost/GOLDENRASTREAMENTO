<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma conversa de atendimento no site ou na página de funil.
 *
 * Quem já tem conta no portal fala pelo chamado — a fila daquela conversa continua sendo
 * `ticket_messages`, e nada aqui a duplica. Esta tabela é o outro lado: o visitante anônimo que
 * abriu a bolha e ainda não é ninguém para o sistema. Por isso a conversa carrega as quatro
 * pontes (`visit_id`, `lead_id`, `customer_id`, `ticket_id`) em vez de pedir um cadastro na
 * primeira frase: ela nasce anônima, pode virar lead no meio e virar chamado quando o cliente
 * precisa de atendimento humano, sempre com o histórico junto.
 */
class Conversa extends Model
{
    public const ESCALAS = [
        'sem_fonte' => 'A máquina não tinha fonte',
        'midia_nao_lida' => 'A mídia não pôde ser lida',
        'pediu_humano' => 'O cliente pediu uma pessoa',
        'provedor_sem_chave' => 'Nenhum provedor de IA ligado',
        'provedor_com_erro' => 'A chamada de IA falhou',
    ];

    protected $fillable = [
        'canal', 'status', 'visit_id', 'lead_id', 'customer_id', 'ticket_id',
        'nome', 'contato', 'pagina', 'escala_motivo',
        'respondido_por_equipe_em', 'resolvido_em', 'ultima_fala_em',
    ];

    protected $casts = [
        'respondido_por_equipe_em' => 'datetime',
        'resolvido_em' => 'datetime',
        'ultima_fala_em' => 'datetime',
    ];

    public function mensagens(): HasMany
    {
        return $this->hasMany(ConversaMensagem::class)->oldest();
    }

    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function chamado(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /** O status que a caixa de entrada mostra: quem ainda é da máquina e quem já é da equipe. */
    public static function statuses(): array
    {
        return (array) config('assistente.status_conversa');
    }

    public static function canais(): array
    {
        return (array) config('assistente.canais');
    }

    public function statusLabel(): string
    {
        return (string) (self::statuses()[$this->status] ?? $this->status);
    }

    public function canalLabel(): string
    {
        return (string) (self::canais()[$this->canal] ?? $this->canal);
    }

    public function escalaLabel(): string
    {
        return (string) (self::ESCALAS[$this->escala_motivo] ?? $this->escala_motivo ?? '');
    }

    /** A última fala — é ela que a listagem prévia e o painel de atendimento mostram. */
    public function ultimaMensagem(): ?ConversaMensagem
    {
        return $this->mensagens()->latest('id')->first();
    }

    public function scopeEmAtendimento(Builder $query): Builder
    {
        return $query->whereIn('status', ['aberta', 'aguardando_equipe']);
    }

    /**
     * Abre conversa nova de um visitante anônimo. `pagina` é o endereço de onde a bolha foi
     * apertada — é por ela que a equipe sabe se a dúvida nasceu na home ou no funil do anúncio.
     */
    public static function abrir(string $canal, ?string $pagina = null, ?int $visitId = null): self
    {
        return static::create([
            'canal' => $canal,
            'status' => 'aberta',
            'pagina' => $pagina,
            'visit_id' => $visitId,
            'ultima_fala_em' => now(),
        ]);
    }

    /**
     * Passa a conversa para a equipe. É a única maneira de um atendimento virar humano: a
     * máquina nunca responde o que não tem fonte, e aqui fica a marca de por que parou.
     */
    public function escalar(string $motivo): void
    {
        $this->status = 'aguardando_equipe';
        $this->escala_motivo = $motivo;
        $this->save();
    }

    /** Alguém do painel respondeu: a máquina sai de cena e o histórico fica com a equipe. */
    public function assumirPelaEquipe(): void
    {
        if ($this->respondido_por_equipe_em === null) {
            $this->respondido_por_equipe_em = now();
        }

        $this->status = 'aberta';
        $this->save();
    }

    public function registrarStatus(string $status): void
    {
        $this->status = $status;

        if (in_array($status, ['resolvida', 'fechada'], true) && ! $this->resolvido_em) {
            $this->resolvido_em = now();
        }

        if (! in_array($status, ['resolvida', 'fechada'], true)) {
            $this->resolvido_em = null;
        }

        $this->save();
    }

    /** Quem a tela mostra: o nome dito no chat, o cliente do portal, ou "visitante anônimo". */
    public function quem(): string
    {
        return (string) ($this->nome
            ?: $this->cliente?->nome
            ?: $this->lead?->name
            ?: 'Visitante anônimo');
    }
}
