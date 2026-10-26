<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma fala dentro de uma conversa do site ou do funil.
 *
 * O autor é uma palavra (`cliente`, `assistente`, `equipe`, `sistema`) e não um id, porque as
 * quatro pontes da conversa vêm de tabelas diferentes: o mesmo `user_id` diria coisas opostas
 * conforme a fala tivesse nascido no painel ou no portal. Quando é da equipe, o `user_id`
 * acompanha e a tela assina com o nome de quem escreveu — o mesmo princípio do chamado.
 *
 * Mídia e texto coexistem na mesma linha: quem manda uma foto quase sempre escreve uma frase
 * junto, e a frase é o que a equipe lê antes de abrir o arquivo. Para áudio, `transcricao` é a
 * prova do que foi dito — o que o assistente respondeu partiu dela, não de uma leitura solta do
 * arquivo.
 */
class ConversaMensagem extends Model
{
    protected $table = 'conversas_mensagens';

    protected $fillable = [
        'conversa_id', 'autor', 'user_id', 'tipo', 'corpo',
        'midia', 'midia_nome', 'midia_mime', 'midia_bytes', 'duracao_segundos',
        'transcricao', 'resposta_audio', 'fontes_json',
        'provedor', 'modelo', 'tokens_entrada', 'tokens_saida',
    ];

    protected $casts = [
        'fontes_json' => 'array',
        'midia_bytes' => 'integer',
        'duracao_segundos' => 'integer',
        'tokens_entrada' => 'integer',
        'tokens_saida' => 'integer',
    ];

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function tipos(): array
    {
        return (array) config('assistente.tipos_mensagem');
    }

    public static function autores(): array
    {
        return (array) config('assistente.autores');
    }

    public function tipoLabel(): string
    {
        return (string) (self::tipos()[$this->tipo] ?? $this->tipo);
    }

    public function autorLabel(): string
    {
        return (string) (self::autores()[$this->autor] ?? $this->autor);
    }

    /** Quem assina a fala na tela: o nome da pessoa no painel, a ficha do assistente, o cliente. */
    public function assinatura(): string
    {
        return match ($this->autor) {
            'equipe' => (string) ($this->user?->name ?? 'Equipe Golden'),
            'assistente' => Assistente::garanta()->nome,
            'sistema' => 'Sistema',
            default => (string) ($this->conversa?->quem() ?? 'Cliente'),
        };
    }

    public function doAssistente(): bool
    {
        return $this->autor === 'assistente';
    }

    public function daEquipe(): bool
    {
        return $this->autor === 'equipe';
    }

    public function temMidia(): bool
    {
        return filled($this->midia);
    }

    /** O endereço público do arquivo, ou nulo quando ele não está mais no disco. */
    public function midiaUrl(): ?string
    {
        return $this->caminhoUrl($this->midia);
    }

    /** O áudio falado para esta resposta, quando o painel ligou a voz. */
    public function audioUrl(): ?string
    {
        return $this->caminhoUrl($this->resposta_audio);
    }

    private function caminhoUrl(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $publico = 'storage/'.ltrim((string) $caminho, '/');

        return file_exists(public_path($publico)) ? asset($publico) : null;
    }

    /** O que a equipe precisa ler para conferir a resposta: o que a máquina usou como fonte. */
    public function fontes(): array
    {
        return (array) ($this->fontes_json ?? []);
    }

    /** O texto que a tela mostra: o corpo, ou o que o arquivo dizia quando não há corpo. */
    public function textoVisivel(): string
    {
        if (filled($this->corpo)) {
            return (string) $this->corpo;
        }

        if ($this->tipo === 'audio' && filled($this->transcricao)) {
            return (string) $this->transcricao;
        }

        return (string) ($this->midia_nome ?: '['.$this->tipoLabel().']');
    }
}
