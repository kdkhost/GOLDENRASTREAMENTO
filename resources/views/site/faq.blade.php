@extends('layouts.site')
@section('title', 'Dúvidas frequentes — Golden Rastreamento')
@section('meta_description', 'Respostas sobre funcionamento do rastreador, instalação, mensalidade, bloqueio remoto, cobertura do chip M2M e acesso pelo celular.')

@section('content')
@include('site.partials.page-hero', ['chave' => 'faq'])

<section class="sec-light">
    <div class="wrap">
        <div class="faq" style="margin-top:0">
            @foreach($faq as $f)
            <details><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
            @endforeach
        </div>
        <p class="center" style="margin-top:30px">Não encontrou sua resposta? <a href="{{ route('site.contato') }}" style="color:var(--gold-2);font-weight:700">Fale com um especialista →</a></p>
    </div>
</section>
@endsection
