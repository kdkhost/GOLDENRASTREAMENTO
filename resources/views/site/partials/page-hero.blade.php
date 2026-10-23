{{--
    Cartaz de abertura de uma página fixa do site. Título, linha de destaque,
    subtítulo e a foto de fundo (opcional) vêm do que o painel define em
    Admin → Conteúdo → Textos das páginas / Imagens do site, com o padrão do
    pacote em config('golden.paginas_site') por baixo.
--}}
@php
    $cartaz = \App\Support\ConteudoSite::slotDoCartaz($chave);
    $temFoto = $cartaz !== null && site_img($cartaz) !== null;
    $eyebrow = site_pagina($chave, 'eyebrow');
    $subtitulo = site_pagina($chave, 'subtitulo');
@endphp
<section class="page-hero {{ $temFoto ? 'com-foto' : '' }}">
    @if($temFoto)
        <x-site.img :imagem="$cartaz" class="foto" :lazy="false" :prioridade="true" />
    @endif
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · {{ site_pagina($chave, 'rotulo') }}</div>
        @if($eyebrow)<span class="eyebrow on-dark">{{ $eyebrow }}</span>@endif
        <h1>{{ site_pagina($chave, 'titulo') }}</h1>
        @if($subtitulo)<p>{{ $subtitulo }}</p>@endif
    </div>
</section>
