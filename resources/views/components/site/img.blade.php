@props([
    'imagem',
    'alt' => null,
    'largura' => true,
    'altura' => true,
    'lazy' => true,
    'prioridade' => false,
])
@php
    // Uma tag <img> por slot do CMS: o caminho, o texto alternativo e a dimensão
    // vêm do registro (config/golden.php) com o que o painel enviou por cima, então
    // nenhuma view volta a citar arquivo de imagem — e nenhuma fica sem width/height.
    $url = site_img($imagem);
    $registro = $url === null ? [] : \App\Support\ConteudoSite::slot($imagem);
    $w = is_numeric($largura) ? (int) $largura : ($largura ? ($registro['largura'] ?? null) : null);
    $h = is_numeric($altura) ? (int) $altura : ($altura ? ($registro['altura'] ?? null) : null);
@endphp
@if($url)
<img src="{{ $url }}" alt="{{ $alt ?? site_img_alt($imagem) }}"
    @if($w) width="{{ $w }}" @endif
    @if($h) height="{{ $h }}" @endif
    @if($prioridade) fetchpriority="high" @elseif($lazy) loading="lazy" @endif
    {{ $attributes->except('alt') }}>
@endif
