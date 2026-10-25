@extends('layouts.funil')

@section('cabecalho')
    @foreach($pagina->esquemas() as $esquema)
    <script type="application/ld+json">{!! json_encode($esquema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
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
