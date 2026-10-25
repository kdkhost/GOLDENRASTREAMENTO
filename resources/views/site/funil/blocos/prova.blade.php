{{--
    Prova social: o mesmo carrossel do site, vestido de cartão de cliente com avatar. A
    lista vem de FunnelBlock::depoimentos() — os aprovados da plataforma (foto, nota e selo
    do Google) à frente dos digitados aqui, sem repetir ninguém. No notebook três por vez,
    no celular um por vez, com arraste e avanço automático.
--}}
@php $itens = $b->depoimentos(); @endphp
@if($itens !== [])
@php $passos = [
    '760' => ['slidesPerView' => 2, 'spaceBetween' => 20],
    '1100' => ['slidesPerView' => min(3, count($itens)), 'spaceBetween' => 24],
]; @endphp
<section class="f-prova">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="carrossel f-prova-carro">
            <div class="swiper js-carrossel" data-rotulo="depoimento" data-avanco="5500"
                data-passos='@json($passos)'>
                <div class="swiper-wrapper">
                    @foreach($itens as $item)
                    <div class="swiper-slide">
                        <div class="depo-card">
                            @if($item['nota'])
                            <div class="stars" aria-label="{{ $item['nota'] }} de 5">{{ str_repeat('★', max(1, min(5, (int) $item['nota']))) }}</div>
                            @endif
                            <p>"{{ $item['texto'] }}"</p>
                            <div class="depo-who">
                                <div class="avatar">@if($item['avatar'])<img src="{{ $item['avatar'] }}" alt="Foto de {{ $item['nome'] }}" loading="lazy" width="46" height="46">@else{{ $item['iniciais'] }}@endif</div>
                                <div>
                                    <b>{{ $item['nome'] }}</b>
                                    @if($item['papel'])<small>{{ $item['papel'] }}</small>@endif
                                </div>
                                @if($item['google'])<span class="depo-origem" title="Avaliação pública no Google"><i class="fa-brands fa-google" aria-hidden="true"></i> Google</span>@endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="carro-ctl">
                <button type="button" class="carro-nav carro-prev" aria-label="Depoimento anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <div class="swiper-pagination carro-dots"></div>
                <button type="button" class="carro-nav carro-next" aria-label="Próximo depoimento"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
</section>
@endif
