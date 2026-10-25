@php
    $foto = $b->imagemUrl();
    $selos = $b->itens();
@endphp
<section class="f-hero{{ $foto ? ' com-foto' : '' }}">
    @if($foto)
    <img class="f-foto" src="{{ $foto }}" alt="{{ $b->imagem_alt }}" fetchpriority="high" decoding="async">
    @endif
    <div class="wrap f-hero-caixa">
        <span class="eyebrow on-dark">{{ $golden['marca']['slogan'] }}</span>
        <h1>{{ $b->titulo }}</h1>
        @if($b->texto)
        <p class="f-hero-lead">{{ $b->texto }}</p>
        @endif

        @if($selos !== [])
        <ul class="f-selos">
            @foreach($selos as $selo)
            <li>
                <i class="fa-solid {{ trim((string) $selo['icone']) !== '' ? $selo['icone'] : 'fa-circle-check' }}" aria-hidden="true"></i>
                <span>{{ $selo['texto'] }}</span>
            </li>
            @endforeach
        </ul>
        @endif

        <div class="f-acoes">
            @include('site.funil.partials.cta', ['rotulo' => $b->cta_texto, 'destino' => $b->cta_destino, 'cta' => 'hero_principal', 'estilo' => 'btn-gold'])
            @include('site.funil.partials.cta', ['rotulo' => $b->cta2_texto, 'destino' => $b->cta2_destino, 'cta' => 'hero_secundario', 'estilo' => 'btn-outline'])
        </div>
    </div>
</section>
