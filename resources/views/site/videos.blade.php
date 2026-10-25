@extends('layouts.site')
@section('title', 'Vídeos tutoriais — Golden Rastreamento')
@section('meta_description', 'Tutoriais em vídeo do aplicativo e da plataforma Golden: alertas, histórico, cercas virtuais, bloqueio, relatórios, despesas e faturas.')

{{-- Só esta galeria puxa miniatura do YouTube, então só ela adianta a conexão. Nas
     outras páginas do site o pré-conectamento seria uma mão dada a um servidor que
     nunca chega a ser consultado. --}}
@push('styles')
<link rel="preconnect" href="https://i.ytimg.com">
@endpush

@section('content')
@include('site.partials.page-hero', ['chave' => 'videos'])

<section class="sec-light">
    <div class="wrap">
        <nav class="videos-filtros" aria-label="Categorias de tutorial">
            <a href="{{ route('site.videos') }}" class="video-filtro{{ $atual === '' ? ' on' : '' }}" @if($atual === '') aria-current="page" @endif>
                Todos <small>{{ $todos }}</small>
            </a>
            @foreach($categorias as $chave => $cat)
            <a href="{{ route('site.videos', ['categoria' => $chave]) }}" class="video-filtro{{ $atual === $chave ? ' on' : '' }}" @if($atual === $chave) aria-current="page" @endif>
                <i class="fa-solid {{ $cat['icone'] }}" aria-hidden="true"></i> {{ $cat['rotulo'] }} <small>{{ $contagem[$chave] ?? 0 }}</small>
            </a>
            @endforeach
        </nav>

        @if($videos->count() > 0)
        <p class="videos-resumo">
            {{ number_format($videos->firstItem(), 0, ',', '.') }}–{{ number_format($videos->lastItem(), 0, ',', '.') }}
            de <strong>{{ number_format($videos->total(), 0, ',', '.') }}</strong>
            {{ Str::plural('tutorial', $videos->total()) }}@if($atual !== '') em {{ \App\Models\Video::rotuloDaCategoria($atual) }}@endif
        </p>
        <div class="videos-grid">
            @foreach($videos as $v)
            <a href="{{ $v['assistir'] }}" target="_blank" rel="noopener" class="video-card"
                data-video="{{ $v['codigo'] }}" data-titulo="{{ $v['titulo'] }}" data-farol="video" data-detalhe="{{ $v['titulo'] }}">
                <span class="video-thumb">
                    <img src="{{ $v['thumb'] }}" alt="" width="480" height="360" loading="lazy" decoding="async">
                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                </span>
                <span class="video-info">
                    <small>{{ $v['categoria_rotulo'] }}</small>
                    <b>{{ $v['titulo'] }}</b>
                </span>
            </a>
            @endforeach
        </div>

        {{ $videos->links('pagination.site') }}
        @else
        <p class="videos-vazio">Ainda não há vídeo nessa categoria. <a href="{{ route('site.videos') }}">Ver a galeria inteira →</a></p>
        @endif

        <p class="center" style="margin-top:30px">Ficou uma dúvida depois do tutorial? <a href="{{ route('site.contato') }}" style="color:var(--gold-2);font-weight:700" data-track="internal_link" data-cta="videos_suporte">Falar com o suporte →</a></p>
    </div>
</section>

{{-- O player só entra no DOM quando o visitante abre o vídeo: a página carrega leve e o
     YouTube não grava nada em quem apenas passou os olhos pela galeria. --}}
@include('site.partials.video-modal')
@endsection
