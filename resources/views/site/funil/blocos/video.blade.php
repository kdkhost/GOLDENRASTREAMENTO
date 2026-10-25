@php $codigo = \App\Models\Video::codigoDe($b->cta_destino); @endphp
@if($codigo)
<section class="f-video">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <a class="f-video-abrir" href="https://www.youtube.com/watch?v={{ $codigo }}"
            data-video="{{ $codigo }}" data-titulo="{{ $b->titulo ?: 'Apresentação da Golden' }}"
            data-farol="video" data-detalhe="{{ $b->titulo }}">
            <span class="f-video-thumb">
                <img src="{{ \App\Models\Video::thumb($codigo) }}" alt="" width="480" height="360" loading="lazy" decoding="async">
                <i class="fa-solid fa-play" aria-hidden="true"></i>
            </span>
            <b>{{ $b->cta_texto ?: 'Assistir a apresentação' }} →</b>
        </a>
    </div>
</section>
@endif
