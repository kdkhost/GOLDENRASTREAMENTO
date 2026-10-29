{{--
    A ajuda da tela, no cabeçalho: um ícone miúdo ao lado do título, que abre a gaveta lateral com
    a seção do guia documentando esta página.

    Antes era uma linha de texto embaixo do título — empurrava o cabeçalho para baixo e competia
    com a descrição da tela. O ícone diz a mesma coisa sem ocupar linha nenhuma, e fecha com um
    clique no mesmo lugar em que abriu.

    Quem decide se ele aparece é o mapa em config('guia_painel.rotas'), lido pelo nome da rota
    atual: a view da tela não sabe que existe guia, e nenhuma das vinte telas precisa lembrar de
    oferecer ajuda. Tela sem seção no mapa simplesmente não mostra o ícone.
--}}
@php
    $secaoGuia = \App\Support\GuiaPainel::secaoDaRota(optional(request()->route())->getName());
@endphp
@if ($secaoGuia)
    <button type="button"
        class="ajuda-botao ajuda-botao--tela"
        data-ajuda="ajuda-tela"
        data-ajuda-titulo="{{ $secaoGuia['titulo'] }}"
        aria-haspopup="dialog"
        aria-label="Como usar esta tela: {{ $secaoGuia['titulo'] }}">
        <i class="fas fa-circle-question" aria-hidden="true"></i>
    </button>
@endif
