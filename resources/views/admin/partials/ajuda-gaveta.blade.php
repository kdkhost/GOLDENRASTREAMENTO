{{--
    A gaveta da ajuda: um painel à direita que abre sobre a tela, sem tirar o formulário de onde
    ele está. Um só painel para todo o painel — o ícone do cabeçalho conta a tela inteira, o ícone
    ao lado de um campo conta aquele campo, e os dois escrevem no mesmo corpo.

    O conteúdo não vem da rede: cada ajuda já está na página dentro de um `<template>`, que o
    navegador não desenha. `golden-ajuda.js` copia o escolhido para o corpo da gaveta e abre. Assim
    a ajuda não pesa nada em quem não a pede e não há meia tela carregando quando ela é chamada.

    O corpo fica vazio antes do primeiro clique: é por isso que ele é um `<div>` à parte — a
    gaveta existe sempre, o texto é o que muda.
--}}
@php
    $secaoGuia = \App\Support\GuiaPainel::secaoDaRota(optional(request()->route())->getName());
@endphp
<div class="offcanvas offcanvas-end" id="golden-ajuda" tabindex="-1" data-bs-scroll="true"
    aria-labelledby="golden-ajuda-titulo">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="golden-ajuda-titulo">
            <i class="fas fa-circle-question text-gold" aria-hidden="true"></i>
            <span>Ajuda</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <div class="ajuda-corpo" id="golden-ajuda-corpo"></div>

        <p class="ajuda-rodape">
            A documentação inteira do painel está no
            <a href="{{ route('admin.guia.index') }}" target="_blank" rel="noopener">Guia de uso</a> —
            ele abre em outra aba e deixa esta tela onde ela está.
        </p>
    </div>

    @if ($secaoGuia)
        {{-- A tela inteira, no mesmo formato dos itens: o resumo da seção e os blocos do guia. --}}
        <template data-ajuda-corpo="ajuda-tela">
            @if (($secaoGuia['resumo'] ?? '') !== '')
                <p class="guia-resumo">{{ $secaoGuia['resumo'] }}</p>
            @endif
            @include('admin.guia.partials.blocos', ['blocos' => $secaoGuia['blocos'] ?? []])
        </template>
    @endif
</div>
