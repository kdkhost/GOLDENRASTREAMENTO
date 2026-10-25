@extends('layouts.funil')

@section('cabecalho')
    @foreach($pagina->esquemas() as $esquema)
    <script type="application/ld+json">{!! json_encode($esquema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    @if($pagina->temCarrossel())
    {{-- O Swiper é da casa inteira, mas só entra na campanha que tem faixa deslizante: sem
         ele os cards ficam abertos em flex-wrap e a página continua legível. --}}
    @push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}?v={{ filemtime(public_path('vendor/swiper/swiper-bundle.min.css')) }}">
    @endpush
    @push('scripts')
    <script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}?v={{ filemtime(public_path('vendor/swiper/swiper-bundle.min.js')) }}"></script>
    @endpush
    @endif
@endsection

@section('content')
    @foreach($pagina->blocosParaSite() as $bloco)
    @include('site.funil.blocos.'.$bloco->tipo, ['b' => $bloco])
    @endforeach

    @if($pagina->blocosParaSite() === [])
    <section class="f-vazio">
        <div class="wrap center">
            <h1>{{ $pagina->tituloSite() }}</h1>
            <p class="lead-sub">Esta campanha ainda não tem conteúdo publicado. Fale com a equipe pelo
                <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" style="color:var(--gold)">WhatsApp</a>.</p>
        </div>
    </section>
    @endif
@endsection
