@extends('layouts.site')

@section('title', $pagina->meta_title ?: ($pagina->titulo . ' — ' . $golden['marca']['nome']))
@section('meta_description', $pagina->meta_description ?: $pagina->intro)
@section('og_image', $pagina->imagem ? asset('storage/' . $pagina->imagem) : site_img('site_compartilhar'))

@section('content')
<section class="page-hero {{ $pagina->imagem ? 'com-foto' : '' }}">
    @if($pagina->imagem)
        <img class="foto" src="{{ asset('storage/' . $pagina->imagem) }}" alt="{{ $pagina->imagem_alt ?: $pagina->titulo }}" width="1600" height="720" fetchpriority="high">
    @endif
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · {{ $pagina->titulo }}</div>
        @if($pagina->eyebrow)<span class="eyebrow on-dark">{{ $pagina->eyebrow }}</span>@endif
        <h1>{{ $pagina->titulo }}</h1>
        @if($pagina->intro)<p>{{ $pagina->intro }}</p>@endif
    </div>
</section>

<section class="sec-light">
    <div class="wrap page-cms">
        <div class="cms-body">
            @if($pagina->corpo)
                {!! $pagina->corpo !!}
            @else
                <p class="cms-vazio">Conteúdo em breve.</p>
            @endif
        </div>

        <div class="cms-ctas">
            <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-gold" data-track="whatsapp_click" data-cta="cms"><i class="fa-brands fa-whatsapp"></i> Falar com especialista</a>
            <a href="{{ route('site.contato') }}" class="btn btn-ghost">Solicitar proposta</a>
        </div>
    </div>
</section>
@endsection
