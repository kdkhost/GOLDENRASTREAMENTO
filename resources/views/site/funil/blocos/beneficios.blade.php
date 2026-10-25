@php $itens = $b->itens(); @endphp
@if($itens !== [])
<section class="f-beneficios">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="f-ben-grid">
            @foreach($itens as $item)
            <article class="f-ben">
                <span class="f-ben-ic">
                    <i class="fa-solid {{ trim((string) $item['icone']) !== '' ? $item['icone'] : 'fa-star' }}" aria-hidden="true"></i>
                </span>
                <h3>{{ $item['titulo'] }}</h3>
                @if($item['texto'])<p>{{ $item['texto'] }}</p>@endif
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
