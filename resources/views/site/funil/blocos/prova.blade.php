@php $itens = $b->itens(); @endphp
@if($itens !== [])
<section class="f-prova">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="f-provas">
            @foreach($itens as $item)
            <div class="depo-card">
                @if($item['nota'])
                <div class="stars" aria-label="{{ $item['nota'] }} de 5">{{ str_repeat('★', max(1, min(5, (int) $item['nota']))) }}</div>
                @endif
                <p>"{{ $item['texto'] }}"</p>
                @if($item['nome'])
                <div class="depo-who">
                    <div>
                        <b>{{ $item['nome'] }}</b>
                        @if($item['papel'])<small>{{ $item['papel'] }}</small>@endif
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
