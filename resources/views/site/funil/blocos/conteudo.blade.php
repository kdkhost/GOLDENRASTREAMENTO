<section class="f-conteudo">
    <div class="wrap f-conteudo-caixa{{ $b->imagemUrl() ? ' com-foto' : '' }}">
        <div>
            @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
            <div class="cms-body">{!! $b->corpo !!}</div>
            <div class="f-acoes">
                @include('site.funil.partials.cta', ['rotulo' => $b->cta_texto, 'destino' => $b->cta_destino, 'cta' => 'conteudo_'.$b->id, 'estilo' => 'btn-gold'])
            </div>
        </div>
        @if($b->imagemUrl())
        <img class="f-conteudo-foto" src="{{ $b->imagemUrl() }}" alt="{{ $b->imagem_alt }}" loading="lazy" decoding="async">
        @endif
    </div>
</section>
