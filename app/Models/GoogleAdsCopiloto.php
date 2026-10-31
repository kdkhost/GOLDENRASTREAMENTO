<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ficha do copiloto de tráfego — a segunda assistente da casa.
 *
 * É uma linha só, como a ficha da Ana: nome, cargo, tom e as instruções que viram a ordem de
 * sistema da chamada. Sem linha no banco, `garanta()` devolve o padrão de `config('google_ads.
 * copiloto')` numa instância não salva; a tela mostra o padrão e grava quando a pessoa salvar.
 * Assim uma instalação nova já tem um copiloto coerente, e o painel continua sendo a fonte
 * única de quem ele é.
 */
class GoogleAdsCopiloto extends Model
{
    protected $table = 'google_ads_copiloto';

    protected $fillable = ['nome', 'cargo', 'tom', 'instrucoes'];

    /** A ficha vigente: a do banco ou, quando ainda não há, o padrão da configuração. */
    public static function garanta(): self
    {
        return static::query()->orderBy('id')->first() ?? new static([
            'nome' => (string) config('google_ads.copiloto.nome', 'Max'),
            'cargo' => (string) config('google_ads.copiloto.cargo', 'Copiloto de tráfego'),
            'tom' => (string) config('google_ads.copiloto.tom', ''),
            'instrucoes' => (string) config('google_ads.copiloto.instrucoes', ''),
        ]);
    }

    /** A ficha é uma só; salvar sempre reescreve a mesma linha em vez de abrir duplicata. */
    public static function gravar(array $dados): self
    {
        $ficha = static::query()->orderBy('id')->first() ?? new static;

        $ficha->fill($dados)->save();

        return $ficha;
    }
}
