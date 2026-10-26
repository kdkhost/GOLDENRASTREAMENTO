{{--
    A ajuda discreta do cabeçalho: uma linha miúda abaixo do título da tela, que leva direto à
    seção do guia documentando aquela página.

    Quem decide se ela aparece é o mapa em config('guia_painel.rotas'), lido pelo nome da rota
    atual — a view da tela não sabe que existe guia, e nenhuma das vinte telas precisa lembrar de
    oferecer o link. Tela sem seção no mapa simplesmente não mostra a linha.
--}}
@php
    $secaoGuia = \App\Support\GuiaPainel::secaoDaRota(optional(request()->route())->getName());
@endphp
@if ($secaoGuia)
    {{-- Outra aba: a pessoa lê a explicação sem perder o formulário que estava preenchendo. --}}
    <a class="guia-chamada mt-1" href="{{ route('admin.guia.index') }}#{{ $secaoGuia['id'] }}" target="_blank" rel="noopener">
        <i class="fas fa-circle-question" aria-hidden="true"></i>
        <span>Como usar esta tela</span>
    </a>
@endif
