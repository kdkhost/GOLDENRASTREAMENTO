@extends('layouts.site')
@section('title', 'Como funciona — Golden Rastreamento')
@section('meta_description', 'Veja como funciona: escolha o kit, receba os equipamentos, providencie a instalação com profissional de sua confiança e comece a acompanhar pela plataforma.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · Como funciona</div>
        <h1>Da aquisição ao controle, de forma simples</h1>
        <p>Quatro passos para colocar sua frota ou veículo na plataforma Golden.</p>
    </div>
</section>

<section class="sec-light">
    <div class="wrap">
        <div class="steps" style="margin-top:0">
            @foreach($passos as $p)
            <div class="step">
                <div class="ic"><i class="fa-solid {{ $p['icone'] }}"></i></div>
                <h4>{{ $p['titulo'] }}</h4>
                <p>{{ $p['texto'] }}</p>
            </div>
            @endforeach
        </div>
        <div class="card" style="margin-top:44px;border-left:4px solid var(--gold)">
            <h3><i class="fa-solid fa-circle-info" style="color:var(--gold-2)"></i> Sobre a instalação</h3>
            <p>A Golden <strong>não cobra instalação</strong>. A instalação é providenciada e paga pelo cliente a um profissional de sua confiança. Isso garante liberdade para escolher onde e com quem instalar, sem taxa adicional da Golden.</p>
        </div>
    </div>
</section>

<section class="sec-alt">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Ainda com dúvidas?</span>
            <h2>Perguntas frequentes têm página própria.</h2>
            <p class="lead-sub">Instalação, mensalidade, cobertura de sinal, bloqueio remoto e privacidade — tudo respondido.</p>
            <div style="margin-top:22px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="{{ route('site.faq') }}" class="btn btn-dark" data-track="internal_link" data-cta="como_funciona_para_faq">Ver dúvidas frequentes →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="como_funciona">Falar com um especialista →</a>
            </div>
        </div>
    </div>
</section>
@endsection
