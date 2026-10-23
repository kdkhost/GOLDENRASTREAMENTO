<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Cópia da rodada em memória: o site lê dezenas de chaves por requisição. */
    protected static ?array $memoria = null;

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('golden_settings');
            static::$memoria = null;
        });
        static::deleted(function () {
            Cache::forget('golden_settings');
            static::$memoria = null;
        });
    }

    public static function all_values(): array
    {
        return static::$memoria ??= Cache::rememberForever('golden_settings', function () {
            return static::pluck('value', 'key')->all();
        });
    }

    /** Descarta a cópia em memória (testes e scripts que escrevem em massa). */
    public static function esquecerMemoria(): void
    {
        static::$memoria = null;
    }

    public static function get(string $key, $default = null)
    {
        $value = self::all_values()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Apaga a chave: o que o painel definiu some e o padrão de config() volta a valer.
     * É assim que "voltar à foto original" funciona sem regravar nada.
     */
    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();

        // Delete pela consulta não dispara o evento saved/deleted: a invalidação
        // precisa acontecer aqui, senão a cópia em memória continua com a chave.
        Cache::forget('golden_settings');
        static::$memoria = null;
    }
}
