@extends('layouts.site')
@section('title', 'Rastreamento para ' . $segmento['titulo'] . ' — ' . $golden['marca']['nome'])
@section('meta_description', $segmento['lead'])

@section('content')
<section class="page-hero com-foto">
    <x-site.img :imagem="$segmento['slot']" class="foto" :lazy="false" :prioridade="true" />
    <div class="wrap">
        <div class="breadcrumb">
            <a href="{{ route('site.home') }}">Início</a> · 
            <a href="{{ route('site.segmentos') }}">Para quem é</a> · 
            <span>{{ $segmento['titulo'] }}</span>
        </div>
        <span class="eyebrow on-dark">{{ $segmento['eyebrow'] }}</span>
        <h1>Rastreamento para {{ $segmento['titulo'] }}</h1>
        <p>{{ $segmento['subtitulo'] }}</p>
        <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="hero_segmento_{{ $segmento['slug'] }}">Solicitar proposta personalizada →</a>
            <a href="{{ wa_url($segmento['whatsapp_msg'], $golden['whatsapp']) }}" target="_blank" rel="noopener" class="btn btn-ghost" data-track="whatsapp_click" data-cta="hero_segmento_{{ $segmento['slug'] }}"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
        </div>
    </div>
</section>

{{-- APRESENTAÇÃO E DESAFIOS --}}
<section class="sec-light">
    <div class="wrap">
        <div class="split" style="align-items:center">
            <div>
                <span class="eyebrow"><i class="fa-solid {{ $segmento['icone'] }}"></i> Solução dedicada</span>
                <h2>{{ $segmento['lead'] }}</h2>
                <p class="lead-sub" style="margin-top:16px">{{ $segmento['descricao'] }}</p>
                <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap">
                    <a href="{{ route('site.contato') }}" class="btn btn-dark" data-track="contact" data-cta="split_segmento_{{ $segmento['slug'] }}">Receber cotação para {{ $segmento['titulo'] }} →</a>
                    <a href="{{ wa_url($segmento['whatsapp_msg'], $golden['whatsapp']) }}" target="_blank" rel="noopener" class="btn btn-outline" data-track="whatsapp_click" data-cta="split_segmento_{{ $segmento['slug'] }}"><i class="fa-brands fa-whatsapp"></i> Atendimento direto</a>
                </div>
            </div>
            <div>
                <div style="position:relative;border-radius:20px;overflow:hidden;box-shadow:var(--shadow);border:1px solid var(--line);max-height:420px">
                    <x-site.img :imagem="$segmento['slot']" style="width:100%;height:100%;object-fit:cover;display:block" />
                </div>
            </div>
        </div>
    </div>
</section>

{{-- DIFERENCIAIS ESPECÍFICOS --}}
<section class="sec-alt">
    <div class="wrap">
        <div class="center">
            <span class="eyebrow">Recursos & Benefícios</span>
            <h2>Por que escolher a Golden para {{ strtolower($segmento['titulo']) }}?</h2>
            <p class="lead-sub">Tecnologia, agilidade e suporte completo para garantir a proteção máxima da sua operação.</p>
        </div>
        <div class="grid-3" style="margin-top:36px">
            @foreach($segmento['diferenciais'] as $dif)
            <article class="card">
                <div class="ic"><i class="fa-solid {{ $dif['icone'] }}"></i></div>
                <h3>{{ $dif['titulo'] }}</h3>
                <p>{{ $dif['texto'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- EQUIPAMENTO J16 HOMOLOGADO --}}
<section class="sec-dark">
    <div class="wrap">
        <div class="cta-final" style="background:transparent;border:0;padding:20px 0">
            <span class="eyebrow on-dark">{{ $equipamento['eyebrow'] }}</span>
            <h2>{{ Str::before($equipamento['titulo'], $equipamento['destaque']) }}<span class="destaque">{{ $equipamento['destaque'] }}</span>.</h2>
            <p style="max-width:60ch;margin:0 auto">{{ $equipamento['texto'] }}</p>
            <div class="cta-actions">
                <a href="{{ route('site.planos') }}" class="btn btn-gold">Ver kits e planos →</a>
                <a href="{{ route('site.contato') }}" class="btn btn-outline">Solicitar proposta</a>
            </div>
        </div>
    </div>
</section>

{{-- OUTROS SEGMENTOS --}}
<section class="sec-light">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow">Outras soluções</span>
                <h2>Conheça também nossos outros segmentos.</h2>
            </div>
            <p class="lead-sub">Soluções feitas sob medida para cada tipo de veículo, perfil de motorista ou tamanho de operação empresarial.</p>
        </div>
        <div class="seg-grid">
            @foreach($outrosSegmentos as $outro)
            <a href="{{ route('site.segmento', $outro['slug']) }}" class="seg-foto" data-track="internal_link" data-cta="relacionado_{{ $outro['slug'] }}">
                <x-site.img :imagem="$outro['slot']" class="foto" />
                <i class="fa-solid {{ $outro['icone'] }}" aria-hidden="true"></i>
                <h4>{{ $outro['titulo'] }}</h4>
                <p>{{ $outro['texto'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA FINAL --}}
<section class="sec-alt">
    <div class="wrap center">
        <span class="eyebrow">Pronto para começar?</span>
        <h2>Proteja seus veículos com a Golden Rastreamento.</h2>
        <p class="lead-sub">Atendimento rápido, sem burocracia e com a tecnologia que o seu dia a dia exige.</p>
        <div style="margin-top:22px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="{{ route('site.contato') }}" class="btn btn-gold" data-track="contact" data-cta="segmento_final_contato">Solicitar proposta comercial →</a>
            <a href="{{ wa_url($segmento['whatsapp_msg'], $golden['whatsapp']) }}" target="_blank" rel="noopener" class="btn btn-ghost" data-track="whatsapp_click" data-cta="segmento_final_wa"><i class="fa-brands fa-whatsapp"></i> Falar no WhatsApp</a>
        </div>
    </div>
</section>
@endsection
