{{--
    Benefícios. No notebook é uma linha só com os cards lado a lado; no celular a mesma
    fileira vira carrossel de um por vez, andando para trás e parado quando o dedo segura.
    Quem faz isso é o carrossel compartilhado do site (.carrossel + .js-carrossel), então a
    regra de deslize está num lugar só — aqui só os breakpoints e o ritmo.
--}}
@php $itens = $b->itens(); @endphp
@if($itens !== [])
@php
    $total = count($itens);
    // Linha única na tela larga: cabem até quatro cards de frente; abaixo disso a fileira
    // desliza, um por vez no celular e dois na tablet.
    $passos = [
        '640' => ['slidesPerView' => 2, 'spaceBetween' => 18],
        '1100' => ['slidesPerView' => min(4, $total), 'spaceBetween' => 22],
    ];
@endphp
<section class="f-beneficios">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="carrossel f-ben-carro">
            <div class="swiper js-carrossel" data-rotulo="benefício" data-avanco="4200"
                data-sentido="reverso" data-passos='@json($passos)'>
                <div class="swiper-wrapper">
                    @foreach($itens as $item)
                    <div class="swiper-slide">
                        <article class="f-ben">
                            <span class="f-ben-ic">
                                <i class="fa-solid {{ trim((string) $item['icone']) !== '' ? $item['icone'] : 'fa-star' }}" aria-hidden="true"></i>
                            </span>
                            <h3>{{ $item['titulo'] }}</h3>
                            @if($item['texto'])<p>{{ $item['texto'] }}</p>@endif
                        </article>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif
