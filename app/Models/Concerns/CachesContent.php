<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * O conteúdo do site é lido em toda requisição pública. Estas consultas passam pelo
 * cache e só são desfeitas quando o painel grava no banco — a página pública serve
 * sem emitir SELECT.
 *
 * A classe que usa este trait declara `protected array $cacheKeys` com as chaves que
 * ela mesma invalida.
 */
trait CachesContent
{
    protected static function bootCachesContent(): void
    {
        static::saved(fn (self $modelo) => $modelo->esquecerCache());
        static::deleted(fn (self $modelo) => $modelo->esquecerCache());
    }

    public function esquecerCache(): void
    {
        foreach ($this->cacheKeys as $chave) {
            Cache::forget($chave);
        }
    }
}
