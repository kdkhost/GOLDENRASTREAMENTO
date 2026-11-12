<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ErrorPage extends Model
{
    use HasFactory;

    protected $table = 'error_pages';

    protected $fillable = [
        'code',
        'title',
        'subtitle',
        'message',
        'primary_button_text',
        'primary_button_url',
        'show_whatsapp_button',
        'whatsapp_custom_text',
        'icon',
        'active',
    ];

    protected $casts = [
        'code' => 'integer',
        'show_whatsapp_button' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (ErrorPage $model): void {
            Cache::forget("golden_error_page_{$model->code}");
            Cache::forget('golden_error_pages_all');
        });

        static::deleted(function (ErrorPage $model): void {
            Cache::forget("golden_error_page_{$model->code}");
            Cache::forget('golden_error_pages_all');
        });
    }

    /**
     * Retorna a descrição padrão de um código HTTP.
     */
    public static function nomePadraoDoCodigo(int $code): string
    {
        return match ($code) {
            400 => 'Requisição Inválida (Bad Request)',
            401 => 'Acesso Não Autorizado (Unauthorized)',
            403 => 'Acesso Proibido (Forbidden)',
            404 => 'Página Não Encontrada (Not Found)',
            419 => 'Sessão Expirada (Page Expired)',
            429 => 'Muitas Requisições (Rate Limit)',
            500 => 'Erro Interno do Servidor (Internal Server Error)',
            503 => 'Serviço Indisponível (Service Unavailable)',
            default => "Erro HTTP {$code}",
        };
    }
}

