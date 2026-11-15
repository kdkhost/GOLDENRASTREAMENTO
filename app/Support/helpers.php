<?php

use App\Models\Setting;
use App\Support\ConteudoSite;

if (! function_exists('brl')) {
    /** Formata centavos como moeda brasileira. */
    function brl(int|float|null $cents): string
    {
        $cents = (int) ($cents ?? 0);

        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}

if (! function_exists('golden_texto_puro')) {
    /** Converte HTML gerado por editor rico (Summernote) em texto puro preservando quebras de linha. */
    function golden_texto_puro(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $texto = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/<\s*br\s*\/?>/i', "\n", $texto) ?? $texto;
        $texto = preg_replace('/<\/\s*(?:p|div|li|tr|blockquote|h[1-6])\s*>/i', "\n", $texto) ?? $texto;
        $texto = str_replace("\u{00A0}", ' ', strip_tags($texto));
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}

if (! function_exists('golden_html')) {
    /**
     * Renderiza com segurança HTML vindo do Summernote (sem escapar <p>, <strong>, <br> etc.),
     * decodificando entidades duplamente escapadas e removendo tags/atributos inseguros.
     */
    function golden_html(?string $valor): string
    {
        if ($valor === null || trim($valor) === '') {
            return '';
        }

        $limpo = trim($valor);

        if (preg_match('/&lt;\s*\/?(?:p|br|strong|b|em|i|u|s|strike|ul|ol|li|a|span|div|h[1-6]|blockquote|table|hr|font)\b/i', $limpo) === 1) {
            $limpo = html_entity_decode($limpo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $limpo = preg_replace('/<\s*(script|style|iframe|object|embed|form)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $limpo) ?? $limpo;
        $limpo = preg_replace('/<\s*(?:script|style|iframe|object|embed|form|input|textarea|button|select|meta|link)\b[^>]*\/?>/is', '', $limpo) ?? $limpo;
        $limpo = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/is', '', $limpo) ?? $limpo;
        $limpo = preg_replace('/(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript|data\s*:\s*text\/html)\s*:.*?\2/is', '$1="#"', $limpo) ?? $limpo;

        $contemTagPermitida = preg_match('/<\s*\/?(?:p|br|strong|b|em|i|u|s|strike|sub|sup|ul|ol|li|a|span|div|h[1-6]|blockquote|pre|code|hr|table|thead|tbody|tfoot|tr|th|td|img|figure|figcaption|small|mark|font)\b/i', $limpo) === 1;
        if (! $contemTagPermitida) {
            return nl2br(e(trim($limpo)));
        }

        $tagsPermitidas = '<p><br><strong><b><em><i><u><s><strike><sub><sup><ul><ol><li><a><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><table><thead><tbody><tfoot><tr><th><td><img><figure><figcaption><small><mark><font>';
        $filtrado = trim(strip_tags($limpo, $tagsPermitidas));

        if ($filtrado === '' && trim(strip_tags($limpo)) === '') {
            return '';
        }

        if (strip_tags($filtrado) === $filtrado) {
            return nl2br(e($filtrado));
        }

        return $filtrado;
    }
}

if (! function_exists('wa_url')) {
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
        $url = 'https://wa.me/'.$numero;
        $textoLimpo = $msg !== null ? golden_texto_puro($msg) : '';

        return $textoLimpo === '' ? $url : $url.'?text='.urlencode($textoLimpo);
    }
}

if (! function_exists('loja_ativa')) {
    /** A loja virtual pode ser desligada a qualquer momento pelo painel. */
    function loja_ativa(): bool
    {
        return Setting::get('loja_ativa', '1') === '1';
    }
}

if (! function_exists('slugify_pt')) {
    function slugify_pt(string $text): string
    {
        $converted = Str::slug($text, '-');

        return $converted !== '' ? $converted : Str::random(8);
    }
}

if (! function_exists('site_img')) {
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

if (! function_exists('site_img_alt')) {
    /** Descrição alternativa do <img>, também editável no painel. */
    function site_img_alt(string $slot): string
    {
        return ConteudoSite::alt($slot);
    }
}

if (! function_exists('site_img_enviada')) {
    /** Este slot já tem o arquivo enviado pelo painel (e não a foto do pacote)? */
    function site_img_enviada(string $slot): bool
    {
        return ConteudoSite::enviada($slot) !== null;
    }
}

if (! function_exists('site_pagina')) {
    /**
     * Texto de abertura de uma página fixa ('titulo', 'subtitulo', 'eyebrow',
     * 'rotulo'), lido do que o painel editou e com o padrão do pacote por baixo.
     */
    function site_pagina(string $chave, string $campo): ?string
    {
        return ConteudoSite::pagina($chave, $campo);
    }
}

if (! function_exists('itens_por_pagina')) {
    /** Regra única de paginação das listagens do painel. */
    function itens_por_pagina(): int
    {
        return (int) config('golden.itens_por_pagina', 20);
    }
}

if (! function_exists('tom_por_status')) {
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

if (! function_exists('tom_por_flag')) {
    /** Todo sim/não do painel usa o mesmo par de tons: verde aceso, neutro apagado. */
    function tom_por_flag(bool $valor): string
    {
        return $valor ? 'green' : 'neutral';
    }
}

if (! function_exists('produto_foto_url')) {
    /** Resolve a URL pública de uma foto de produto (arquivo em storage ou do pacote em public/img). */
    function produto_foto_url(?string $foto): ?string
    {
        if (empty($foto)) {
            return null;
        }

        if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://') || str_starts_with($foto, 'img/')) {
            return asset($foto);
        }

        return asset('storage/'.$foto);
    }
}
