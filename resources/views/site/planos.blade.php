@extends('layouts.site')
@section('title', 'Planos e kits — Golden Rastreamento')
@section('meta_description', 'Kits de rastreamento com chip M2M para 5 veículos, 10 veículos e frotas maiores. Equipamento e mensalidade informados na proposta.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · Planos</div>
        <h1>Escolha o tamanho da sua frota</h1>
        <p>Equipamentos e mensalidade informados separadamente na proposta comercial. A instalação é por conta do cliente.</p>
    </div>
</section>

<section class="sec-light">
    <div class="wrap">
        <div class="kits" style="margin-top:0">
            @foreach($kits as $kit)
            <article class="kit {{ $kit['destaque'] ? 'feature' : '' }}">
                @if(!empty($kit['rotulo']))<span class="kit-tag">{{ $kit['rotulo'] }}</span>@endif
                <span class="eyebrow">{{ $kit['eyebrow'] }}</span>
                <div class="num">{{ $kit['titulo'] }} <small>veículos</small></div>
                <ul>
                    @foreach($kit['itens'] as $item)
                    <li><i class="fa-solid fa-check"></i> {{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('site.contato') }}?kit={{ urlencode($kit['titulo']) }}" class="btn {{ $kit['destaque'] ? 'btn-gold' : 'btn-dark' }}" data-kit="{{ $kit['titulo'] }}">{{ $kit['botao'] }} →</a>
            </article>
            @endforeach
        </div>
        <p class="fine center" style="margin-top:26px">Precisa de apenas um equipamento? Fale com um especialista para consultar a unidade avulsa.</p>
    </div>
</section>

<section class="sec-alt">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Antes de fechar</span>
            <h2>Ficou alguma dúvida sobre planos?</h2>
            <p class="lead-sub">As perguntas que mais recebemos — instalação, mensalidade, sinal e bloqueio — têm página própria.</p>
            <div style="margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="{{ route('site.faq') }}" class="btn btn-dark" data-track="internal_link" data-cta="planos_para_faq">Ver dúvidas frequentes →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-ghost" data-track="contact" data-cta="planos">Pedir uma proposta →</a>
            </div>
        </div>
    </div>
</section>
@endsection
