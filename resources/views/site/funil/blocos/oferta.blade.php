@php
    $itens = $b->itens();
    $destino = trim((string) $b->cta_destino) ?: '#formulario';
@endphp
@if($itens !== [])
<section class="f-oferta">
    <div class="wrap">
        <div class="center">
            @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
            @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif
        </div>

        <div class="kits">
            @foreach($itens as $item)
            <article class="kit{{ $item['selo'] ? ' feature' : '' }}">
                @if($item['selo'])<span class="kit-tag">{{ $item['selo'] }}</span>@endif
                <span class="eyebrow on-dark">{{ $item['kit'] }}</span>

                @if($item['avista'])
                <div class="num"><small>R$</small> {{ $item['avista'] }} <small>à vista</small></div>
                @endif
                @if($item['mensal'])
                <p class="f-mensal">ou <b>R$ {{ $item['mensal'] }}</b> por mês</p>
                @endif

                @if(is_array($item['inclui']) && $item['inclui'] !== [])
                <ul>
                    @foreach($item['inclui'] as $incluso)
                    <li><i class="fa-solid fa-check" aria-hidden="true"></i> {{ $incluso }}</li>
                    @endforeach
                </ul>
                @endif

                <a href="{{ $destino }}" class="btn {{ $item['selo'] ? 'btn-gold' : 'btn-dark' }}"
                    data-kit="{{ $item['kit'] }}">{{ $b->cta_texto ?: 'Quero este kit' }} →</a>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
