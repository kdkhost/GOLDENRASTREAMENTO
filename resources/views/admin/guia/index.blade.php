@extends('admin.layouts.app')
@section('title', 'Guia de uso')
@section('page_title', 'Guia de uso')
@section('page_description', 'Como cada tela funciona e o que configurar antes de usar — o guia fica aberto ao lado do sistema, sem ocupar a tela em que você está.')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-guia.css') }}?v={{ filemtime(public_path('css/golden-guia.css')) }}">
@endsection

@section('content')
{{--
    A tela é o painel, então ela obedece ao painel: o índice fica na coluna da esquerda, como a
    sidebar do sistema, e o conteúdo abre na da direita. Nada aqui desenha cor, tamanho de campo
    ou cabeçalho por conta própria — `x-admin.card` e `x-admin.filter` já fazem isso em todas as
    outras telas, e o texto vem do config.

    Uma seção por vez: as trinta seções continuam no HTML (a busca e o link `#secao` precisam
    delas), mas só a escolhida aparece. Quem troca de seção é `golden-guia.js`, e a troca troca o
    endereço sem pedir ao navegador que role até a âncora — é o que deixa a janela parada.
--}}
<div class="row g-3 g-xl-4 align-items-start" data-guia>
    <div class="col-lg-4 col-xl-3">
        <div class="guia-lado">
            <x-admin.card title="Procurar no guia" icon="fa-magnifying-glass">
                <x-admin.filter
                    name="busca"
                    id="guia-busca"
                    label="Assunto"
                    type="search"
                    col=""
                    placeholder="telefone, anúncio, e-mail…"
                    autocomplete="off"
                />
                <nav class="guia-nav mt-3" aria-label="Seções do guia">
                    @foreach ($grupos as $grupo => $secoes_do_grupo)
                        <div class="guia-nav-grupo" data-nav-grupo>{{ $grupo }}</div>
                        @foreach ($secoes_do_grupo as $secao)
                            <a class="guia-nav-link" href="#{{ $secao['id'] }}" data-alvo="{{ $secao['id'] }}">
                                <i class="fas {{ $secao['icone'] }} text-gold" aria-hidden="true"></i>
                                <span>{{ $secao['titulo'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>
                <p class="guia-nav-vazio d-none">Nenhuma seção com esse termo.</p>
                <p class="guia-fim">
                    Mesma fonte do link no alto de cada tela e do cartão "Como conectar" do Google Ads.
                    Faltou algo? Avise quem mantém o sistema.
                </p>
            </x-admin.card>
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        <div class="guia-conteudo" data-guia-conteudo>
            @foreach ($grupos as $grupo => $secoes_do_grupo)
                <x-admin.card :title="$grupo" :icon="\App\Support\GuiaPainel::iconeDoGrupo($grupo)" class="guia-grupo" data-grupo="1">
                    @foreach ($secoes_do_grupo as $secao)
                        <section class="guia-secao" id="{{ $secao['id'] }}" data-secao>
                            <header class="guia-secao-cabeca">
                                <h4 class="guia-secao-titulo">
                                    <i class="fas {{ $secao['icone'] }} text-gold" aria-hidden="true"></i>{{ $secao['titulo'] }}
                                </h4>
                                @if (! empty($secao['rota']))
                                    <a href="{{ route($secao['rota']) }}" class="btn btn-outline-secondary btn-sm guia-abrir">
                                        Abrir a tela <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                @endif
                            </header>

                            <p class="guia-resumo">{{ $secao['resumo'] }}</p>

                            @include('admin.guia.partials.blocos', ['blocos' => $secao['blocos']])
                        </section>
                    @endforeach
                </x-admin.card>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/golden-guia.js') }}?v={{ filemtime(public_path('js/golden-guia.js')) }}"></script>
@endsection
