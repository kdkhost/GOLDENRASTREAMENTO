@extends('layouts.site')
@section('title', 'Para quem é — Golden Rastreamento')
@section('meta_description', 'A Golden atende motoristas de aplicativo, veículos particulares, frotas empresariais, locadoras e operações rurais.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · Para quem é</div>
        <h1>Soluções para quem faz o negócio acontecer</h1>
        <p>Atendemos desde motoristas que utilizam o próprio veículo até empresas com grandes frotas, sempre com soluções adequadas a cada tipo de operação.</p>
    </div>
</section>

<section class="sec-light">
    <div class="wrap">
        <div class="seg-grid" style="margin-top:0">
            @foreach($segmentos as $s)
            <div class="seg">
                <i class="fa-solid {{ $s['icone'] }}"></i>
                <h4>{{ $s['titulo'] }}</h4>
                <p>{{ $s['texto'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="sec-alt">
    <div class="wrap lead-panel">
        <div>
            <span class="eyebrow">Seu segmento</span>
            <h2>Tem uma frota ou um único veículo?</h2>
            <p class="lead-sub">Independentemente do tamanho da operação, montamos a proposta certa. Informe sua quantidade de veículos e perfil para receber condições de kit e mensalidade.</p>
            <a href="{{ route('site.planos') }}" class="btn btn-dark" style="margin-top:10px">Conhecer os kits →</a>
        </div>
        @include('site.partials.lead-form')
    </div>
</section>
@endsection
