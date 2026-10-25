@php $itens = $b->itens(); @endphp
@if($itens !== [])
<section class="f-faq">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="faq">
            @foreach($itens as $item)
            @if($item['resposta'])
            <details>
                <summary>{{ $item['pergunta'] }}</summary>
                <p>{{ $item['resposta'] }}</p>
            </details>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif
