@php
    $foto = $b->imagemUrl();
@endphp
<section class="f-conteudo{{ $foto ? ' com-foto' : '' }}">
    @if($foto)
    <img class="f-foto" src="{{ $foto }}" alt="{{ $b->imagem_alt }}" loading="lazy" decoding="async">
    @endif
    <div class="wrap f-conteudo-caixa">
        <div>
            @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
            <div class="cms-body">{!! $b->corpo !!}</div>
            <div class="f-acoes">
                @include('site.funil.partials.cta', ['rotulo' => $b->cta_texto, 'destino' => $b->cta_destino, 'cta' => 'conteudo_'.$b->id, 'estilo' => 'btn-gold'])
            </div>
        </div>
    </div>
</section>
