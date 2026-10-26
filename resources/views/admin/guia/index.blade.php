@extends('admin.layouts.app')
@section('title', 'Guia de uso')
@section('page_title', 'Guia de uso')
@section('page_description', 'Como cada tela funciona e o que configurar antes de usar — a consulta é aqui, sem sair do painel.')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-guia.css') }}?v={{ filemtime(public_path('css/golden-guia.css')) }}">
@endsection

@section('content')
<div class="row g-3 align-items-start">
    <div class="col-lg-4 col-xl-3">
        <div class="guia-lado">
            <x-admin.card title="Procurar no guia" icon="fa-magnifying-glass">
                <label class="visually-hidden" for="guia-busca">Assunto</label>
                <input type="search" id="guia-busca" class="form-control form-control-sm"
                       placeholder="telefone, anúncio, e-mail…" autocomplete="off">
                <nav class="guia-nav mt-3" aria-label="Seções do guia">
                    @foreach ($grupos as $grupo => $secoes_do_grupo)
                        <div class="guia-nav-grupo">{{ $grupo }}</div>
                        @foreach ($secoes_do_grupo as $secao)
                            <a class="guia-nav-link" href="#{{ $secao['id'] }}" data-alvo="{{ $secao['id'] }}">
                                <i class="fas {{ $secao['icone'] }} text-gold" aria-hidden="true"></i>
                                <span>{{ $secao['titulo'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>
                <p class="guia-nav-vazio d-none">Nenhuma seção com esse termo.</p>
            </x-admin.card>
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        @foreach ($grupos as $grupo => $secoes_do_grupo)
            <div class="guia-grupo" data-grupo>{{ $grupo }}</div>

            @foreach ($secoes_do_grupo as $secao)
                <section class="guia-secao" id="{{ $secao['id'] }}" data-secao>
                    <header class="guia-secao-cabeca">
                        <h4 class="mb-0 fw-bold text-body">
                            <i class="fas {{ $secao['icone'] }} text-gold me-1" aria-hidden="true"></i>{{ $secao['titulo'] }}
                        </h4>
                        @if (! empty($secao['rota']))
                            <a href="{{ route($secao['rota']) }}" class="btn btn-outline-secondary btn-sm guia-abrir">
                                Abrir a tela <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                            </a>
                        @endif
                    </header>

                    <p class="guia-resumo">{{ $secao['resumo'] }}</p>

                    @include('admin.guia.partials.blocos', ['blocos' => $secao['blocos']])
                </section>
            @endforeach
        @endforeach

        <p class="guia-fim small text-secondary">
            Este guia é a mesma fonte do link discreto que aparece no alto de cada tela e do cartão
            "Como conectar" do Google Ads. Faltou aqui alguma coisa que a tela faz? É caso de avisar
            quem mantém o sistema, não de seguir sem o texto.
        </p>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/golden-guia.js') }}?v={{ filemtime(public_path('js/golden-guia.js')) }}"></script>
@endsection
