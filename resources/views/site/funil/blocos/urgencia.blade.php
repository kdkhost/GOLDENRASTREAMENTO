<section class="f-urgencia">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        @if($b->limite_em)
        <p class="f-cont-data">Termina em {{ $b->limite_em->format('d/m/Y') }} às {{ $b->limite_em->format('H:i') }}</p>
        <div class="f-contagem" data-contagem="{{ $b->limite_em->toIso8601String() }}" aria-hidden="true">
            <div class="f-cont-item"><b data-cont="dias">--</b><small>dias</small></div>
            <div class="f-cont-item"><b data-cont="horas">--</b><small>horas</small></div>
            <div class="f-cont-item"><b data-cont="minutos">--</b><small>min</small></div>
            <div class="f-cont-item"><b data-cont="segundos">--</b><small>seg</small></div>
        </div>
        <p class="f-cont-fim">A condição desta página já terminou — fale com a equipe para conferir as datas em vigor.</p>
        @endif

        <div class="f-acoes">
            @include('site.funil.partials.cta', ['rotulo' => $b->cta_texto, 'destino' => $b->cta_destino, 'cta' => 'urgencia', 'estilo' => 'btn-gold'])
        </div>
    </div>
</section>
