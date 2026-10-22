<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('golden_settings'));
        static::deleted(fn () => Cache::forget('golden_settings'));
    }

    public static function all_values(): array
    {
        return Cache::rememberForever('golden_settings', function () {
            return static::pluck('value', 'key')->all();
        });
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
}
