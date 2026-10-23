@extends('layouts.site')

@section('title', $pagina->meta_title ?: ($pagina->titulo . ' — ' . $golden['marca']['nome']))
@section('meta_description', $pagina->meta_description ?: $pagina->intro)

@section('content')
<section class="sec-light">
    <div class="wrap" style="max-width:820px">
        @if($pagina->eyebrow)<span class="eyebrow">{{ $pagina->eyebrow }}</span>@endif
        <h1 style="font-size:2rem;margin:.2rem 0 .6rem">{{ $pagina->titulo }}</h1>
        @if($pagina->intro)<p class="lead-sub">{{ $pagina->intro }}</p>@endif

        <div class="cms-body">
            @if($pagina->corpo)
                {!! $pagina->corpo !!}
            @else
                <p style="color:var(--muted)">Conteúdo em breve.</p>
            @endif
        </div>

        <div style="margin-top:30px;display:flex;gap:12px;flex-wrap:wrap">
            <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn btn-gold" data-track="whatsapp_click" data-cta="cms"><i class="fa-brands fa-whatsapp"></i> Falar com especialista</a>
            <a href="{{ route('site.contato') }}" class="btn btn-ghost">Solicitar proposta</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<style>
.cms-body{line-height:1.7;color:var(--ink)}
.cms-body h2{font-size:1.4rem;margin:1.4rem 0 .5rem}
.cms-body h3{font-size:1.15rem;margin:1.2rem 0 .4rem}
.cms-body p{margin:.7rem 0;color:var(--muted)}
.cms-body ul{padding-left:1.2rem;color:var(--muted)}
.cms-body li{margin:.3rem 0}
.cms-body img{max-width:100%;border-radius:14px}
</style>
@endpush
