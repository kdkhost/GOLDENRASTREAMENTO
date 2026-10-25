{{--
    Vídeo da campanha. Toca na própria faixa, no player da casa (site.partials.video-player):
    nada de modal, nada de autoplay, nada do YouTube no quadro — o vidro cobre o que a
    superfície dele mostraria. A faixa só muda de estado pelo que o visitante manda.
--}}
@php $codigo = \App\Models\Video::codigoDe($b->cta_destino); @endphp
@if($codigo)
<section class="f-video">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        @include('site.partials.video-player', [
            'codigo' => $codigo,
            'thumb' => \App\Models\Video::thumb($codigo),
            'rotulo' => $b->titulo ?: 'Apresentação da plataforma Golden',
            'cta' => $b->cta_texto ?: 'Assistir a apresentação',
            'farol' => $b->titulo,
        ])
    </div>
</section>
@endif
