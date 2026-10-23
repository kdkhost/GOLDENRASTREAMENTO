@extends('layouts.site')
@section('title', 'Soluções e funcionalidades — Golden Rastreamento')
@section('meta_description', 'Funcionalidades da plataforma Golden: localização em tempo real, histórico, cercas virtuais, alertas, relatórios, bloqueio remoto e gestão de manutenções.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · Soluções</div>
        <span class="eyebrow on-dark" style="justify-content:center">Funcionalidades</span>
        <h1>Tudo o que a sua operação precisa</h1>
        <p>Autorrastreamento e gestão em um só lugar. Você acompanha os veículos e utiliza as ferramentas disponível na plataforma.</p>
    </div>
</section>

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
            <span class="eyebrow on-dark">Rastreador J16 4G/2G</span>
            <h2>Equipamento confiável e homologado pela Anatel.</h2>
            <p style="max-width:60ch;margin:0 auto">O J16 é uma solução completa para o seu veículo, com conectividade 4G e 2G, chip M2M e recursos avançados de segurança e gestão. Compatível com ampla variedade de modelos.</p>
            <div class="cta-actions">
                <a href="{{ route('site.planos') }}" class="btn btn-gold">Ver kits →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-outline">Solicitar proposta</a>
            </div>
        </div>
    </div>
</section>
@endsection
