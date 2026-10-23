<?php

use App\Models\Setting;
use App\Support\ConteudoSite;

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

if (!function_exists('site_img')) {
    /**
     * URL pública de uma imagem do site, pelo slot registrado em
     * config('golden.imagens'). Vale o que o painel enviou; sem envio, a foto
     * empacotada. Slot sem padrão e sem envio devolve null — é como as views
     * decidem que aquela seção (o cartaz de uma página) ainda não existe.
     */
    function site_img(?string $slot): ?string
    {
        return ConteudoSite::url($slot);
    }
}

if (!function_exists('site_img_alt')) {
    /** Descrição alternativa do <img>, também editável no painel. */
    function site_img_alt(string $slot): string
    {
        return ConteudoSite::alt($slot);
    }
}

if (!function_exists('site_img_enviada')) {
    /** Este slot já tem o arquivo enviado pelo painel (e não a foto do pacote)? */
    function site_img_enviada(string $slot): bool
    {
        return ConteudoSite::enviada($slot) !== null;
    }
}

if (!function_exists('site_pagina')) {
    /**
     * Texto de abertura de uma página fixa ('titulo', 'subtitulo', 'eyebrow',
     * 'rotulo'), lido do que o painel editou e com o padrão do pacote por baixo.
     */
    function site_pagina(string $chave, string $campo): ?string
    {
        return ConteudoSite::pagina($chave, $campo);
    }
}

if (!function_exists('itens_por_pagina')) {
    /** Regra única de paginação das listagens do painel. */
    function itens_por_pagina(): int
    {
        return (int) config('golden.itens_por_pagina', 20);
    }
}

if (!function_exists('tom_por_status')) {
    /**
     * Tom (classe .tone-*) de um status do painel, lido de
     * config('golden.tons_status')[$grupo]. O grupo é o domínio ('funil', 'cliente',
     * 'pedido', 'usuario'); status desconhecido cai no tom de fallback — a tela continua
     * legível mesmo se alguém inventar um status novo no banco.
     */
    function tom_por_status(string $grupo, ?string $valor): string
    {
        $mapa = (array) config("golden.tons_status.{$grupo}");

        return $mapa[$valor ?? ''] ?? config('golden.tons_status.fallback', 'neutral');
    }
}

if (!function_exists('tom_por_flag')) {
    /** Todo sim/não do painel usa o mesmo par de tons: verde aceso, neutro apagado. */
    function tom_por_flag(bool $valor): string
    {
        return $valor ? 'green' : 'neutral';
    }
}
