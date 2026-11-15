<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de template de e-mail personalizável no painel administrativo.
 *
 * O corpo HTML (`corpo_html`) é armazenado separadamente do layout externo
 * responsivo, permitindo editar estrutura/cores/rodapé global sem tocar nos
 * textos de cada disparo transacional.
 */
class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'chave',
        'nome',
        'categoria',
        'descricao',
        'assunto',
        'pre_cabecalho',
        'corpo_html',
        'corpo_texto',
        'variaveis',
        'usar_layout',
        'ativo',
        'rota_preview',
    ];

    protected $casts = [
        'variaveis' => 'array',
        'usar_layout' => 'boolean',
        'ativo' => 'boolean',
    ];

    /**
     * Filtra apenas templates ativos para disparo.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Busca um template pela chave única.
     */
    public static function porChave(string $chave): ?self
    {
        return static::where('chave', $chave)->first();
    }
}
