<?php

use App\Models\Setting;

if (!function_exists('brl')) {
    /** Formata centavos como moeda brasileira. */
    function brl(int|float|null $cents): string
    {
        $cents = (int) ($cents ?? 0);
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}

if (!function_exists('wa_url')) {
    /**
     * Link wa.me no padrão do sistema: número vindo do painel (só dígitos) e mensagem
     * pré-preenchida, para todo CTA de WhatsApp chegar ao atendimento já contextualizado.
     */
    function wa_url(?string $msg = null, ?string $numero = null): string
    {
        $numero = preg_replace(
            '/\D/',
            '',
            (string) ($numero ?: Setting::get('contato_whatsapp', config('golden.whatsapp')))
        );
        $url = 'https://wa.me/' . $numero;

        return ($msg === null || trim($msg) === '') ? $url : $url . '?text=' . urlencode($msg);
    }
}

if (!function_exists('loja_ativa')) {
    /** A loja virtual pode ser desligada a qualquer momento pelo painel. */
    function loja_ativa(): bool
    {
        return Setting::get('loja_ativa', '1') === '1';
    }
}

if (!function_exists('slugify_pt')) {
    function slugify_pt(string $text): string
    {
        $converted = \Str::slug($text, '-');
        return $converted !== '' ? $converted : \Str::random(8);
    }
}
