<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma coisa que o assistente sabe.
 *
 * O item é sempre escrito por alguém ou lido de algum lugar, e as duas colunas de procedência
 * (`url` e `referencia`) existem para isso: sem elas a resposta da máquina não tem de onde veio,
 * e a equipe não tem como conferir. `lido_em` marca o momento da leitura — informação velha é
 * dita como velha, nunca como se fosse a palavra de hoje.
 *
 * O texto pesquisável vive em `corpo` e `tags`. A busca é léxica, feita no PHP sobre estes
 * campos, porque a hospedagem é compartilhada: não há banco vetorial nem servidor de busca aqui,
 * e um atendimento que depende de índice vetorial para funcionar é um atendimento que para.
 */
class ConhecimentoItem extends Model
{
    protected $table = 'conhecimento_itens';

    protected $fillable = [
        'titulo', 'corpo', 'tags', 'origem', 'url', 'referencia', 'ativo', 'lido_em', 'criado_por',
    ];

    protected $casts = ['ativo' => 'boolean', 'lido_em' => 'datetime'];

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** As origens que a tela oferece — a lista fechada está na config, não na view. */
    public static function origens(): array
    {
        return (array) config('assistente.origens_conhecimento');
    }

    public function origemLabel(): string
    {
        return (string) (self::origens()[$this->origem] ?? $this->origem);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** O que a IA lê: título, corpo e tags numa peça só, na ordem que a busca espera. */
    public function textoParaIA(): string
    {
        $texto = $this->titulo."\n".$this->corpo;

        return filled($this->tags) ? $texto."\nAssuntos: ".$this->tags : $texto;
    }

    /**
     * A marca que a resposta mostra à equipe: de onde veio e quando foi lido. Um item escrito no
     * painel não tem endereço — diz isso em palavras em vez de deixar a linha em branco.
     */
    public function procedencia(): string
    {
        $leitura = $this->lido_em ? 'lido em '.$this->lido_em->format('d/m/Y') : 'gravado no painel';

        return $this->url ? $this->origemLabel().' — '.$this->url.' ('.$leitura.')' : $this->origemLabel().' ('.$leitura.')';
    }
}
