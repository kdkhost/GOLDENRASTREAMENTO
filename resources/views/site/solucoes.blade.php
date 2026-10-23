@extends('layouts.site')
@section('title', 'Soluções e funcionalidades — Golden Rastreamento')
@section('meta_description', 'Funcionalidades da plataforma Golden: localização em tempo real, histórico, cercas virtuais, alertas, relatórios, bloqueio remoto e gestão de manutenções.')

@section('content')
@include('site.partials.page-hero', ['chave' => 'solucoes'])

<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Recursos da plataforma</span>
        <h2>Mais informação, mais controle da operação.</h2>
        <div class="grid-3">
            @foreach($beneficios as $b)
            <article class="card">
                <div class="ic"><i class="fa-solid {{ $b['icone'] }}"></i></div>
                <h3>{{ $b['titulo'] }}</h3>
                <p>{{ $b['texto'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

<section class="sec-alt">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Como funciona</span>
            <h2>Da aquisição ao acompanhamento, em quatro passos.</h2>
            <p class="lead-sub">O passo a passo completo — incluindo a regra de instalação — mora na página dedicada.</p>
            <p style="margin-top:18px"><a href="{{ route('site.como-funciona') }}" class="btn btn-dark" data-track="internal_link" data-cta="solucoes_para_como_funciona">Ver como funciona →</a></p>
        </div>
    </div>
</section>

<section class="sec-dark">
    <div class="wrap">
        <div class="cta-final" style="background:transparent;border:0;padding:20px 0">
            <span class="eyebrow on-dark">{{ $equipamento['eyebrow'] }}</span>
            <h2>{{ Str::before($equipamento['titulo'], $equipamento['destaque']) }}<span class="destaque">{{ $equipamento['destaque'] }}</span>.</h2>
            <p style="max-width:60ch;margin:0 auto">{{ $equipamento['texto'] }}</p>
            <div class="cta-actions">
                <a href="{{ route('site.planos') }}" class="btn btn-gold">Ver kits →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-outline">Solicitar proposta</a>
            </div>
        </div>
    </div>
</section>
@endsection
