@extends('layouts.site')
@section('title', 'Fale com a Golden — solicite sua proposta')
@section('meta_description', 'Solicite uma proposta de rastreamento e gestão veicular. Preencha o formulário ou fale com nossa equipe pelo WhatsApp.')

@section('content')
@include('site.partials.page-hero', ['chave' => 'contato'])

<section class="sec-light">
    <div class="wrap lead-panel">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h2>Sem complicação, do jeito certo</h2>
            <p class="lead-sub">Conte quantos veículos você tem e o seu perfil. Nossa equipe prepara uma proposta com equipamento e mensalidade separados.</p>
            <div style="display:grid;gap:16px;margin-top:24px">
                <div class="card" style="padding:18px 20px;display:flex;gap:14px;align-items:center">
                    <div class="ic" style="width:46px;height:46px;flex:0 0 46px;font-size:1.1rem;margin:0"><i class="fa-brands fa-whatsapp"></i></div>
                    <div><strong>WhatsApp comercial</strong><br><a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" style="color:var(--gold-2)">Conversar agora →</a></div>
                </div>
                <div class="card" style="padding:18px 20px;display:flex;gap:14px;align-items:center">
                    <div class="ic" style="width:46px;height:46px;flex:0 0 46px;font-size:1.1rem;margin:0"><i class="fa-solid fa-envelope"></i></div>
                    <div><strong>E-mail</strong><br><a href="mailto:{{ $golden['email'] }}" style="color:var(--gold-2)">{{ $golden['email'] }}</a></div>
                </div>
                <div class="card" style="padding:18px 20px;display:flex;gap:14px;align-items:center">
                    <div class="ic" style="width:46px;height:46px;flex:0 0 46px;font-size:1.1rem;margin:0"><i class="fa-solid fa-location-dot"></i></div>
                    <div><strong>Onde estamos</strong><br><span class="fine">{{ $golden['marca']['cidade'] }}</span></div>
                </div>
            </div>
        </div>
        @include('site.partials.lead-form')
    </div>
</section>
@endsection
