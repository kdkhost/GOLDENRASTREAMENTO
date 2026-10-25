<section class="f-form" id="formulario">
    <div class="wrap f-form-caixa">
        <div class="f-form-texto">
            @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        </div>
        @include('site.partials.lead-form', [
            'cta_texto' => $b->cta_texto,
            'confianca' => $b->texto,
            'pagina_funil' => $b->funnel_page_id,
        ])
    </div>
</section>
