@props([
    'gaveta' => null,
    'chave' => null,
    'titulo' => null,
])
@php
    // O ícone de ajuda de um item: um botão miúdo que abre a gaveta lateral com o passo a passo
    // daquele campo. A view não sabe o que ele mostra — o corpo vem de `config/guia_configuracao.php`
    // e vive num `<template>`, que o navegador não desenha: nenhum píxel de layout é ocupado por
    // uma ajuda que ninguém pediu.
    //
    // Quem já calculou a gaveta (o campo, o cartão) passa `gaveta`; passando só `chave`, o
    // componente calcula. Item sem ajuda escrita não renderiza nada — nem espaço.
    $conteudo = $gaveta ?? \App\Support\GuiaConfiguracao::gaveta((string) $chave, $titulo);
@endphp
@if($conteudo)
    @php
        // O corpo vem pronto do partial compartilhado do guia — o que ele não sabe é que aqui o
        // texto vai morar dentro de um `<template>`, onde nenhum espaço de indentação tem efeito.
        // São 102 ajudas numa tela só: amassar a sobra é o que mantém a página do tamanho da página.
        $corpo = \Illuminate\Support\Str::squish(
            view('admin.guia.partials.blocos', ['blocos' => $conteudo['blocos']])->render()
        );
    @endphp
    <button type="button"
        class="ajuda-botao"
        data-ajuda="{{ $conteudo['id'] }}"
        data-ajuda-titulo="{{ $conteudo['titulo'] }}"
        aria-haspopup="dialog"
        aria-label="Como configurar: {{ $conteudo['titulo'] }}">
        <i class="fas fa-circle-question" aria-hidden="true"></i>
    </button>

    <template data-ajuda-corpo="{{ $conteudo['id'] }}">{!! $corpo !!}</template>
@endif
