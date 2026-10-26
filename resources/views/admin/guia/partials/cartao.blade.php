{{--
    O cartão dobrável do guia dentro de uma tela: a instrução mora onde a dúvida acontece, e não
    num documento que ninguém abre antes de ligar para a equipe.

    É o mesmo bloco de texto da tela Guia de uso — o partial de blocos é um só —, então a tela do
    Google Ads e o guia não podem se desmentir. Fechado por padrão (discreto); quem abre, abre.
    A exceção é o estado em que falta credencial: aí o cartão nasce aberto, porque a pessoa está
    exatamente no passo que ele explica.

    Variáveis: `secaoGuia` (id da seção no config), `abertoGuia` (bool) e `tituloCartao`.
--}}
@php
    $secao = \App\Support\GuiaPainel::secao($secaoGuia);
@endphp
@if ($secao)
    @php $alvo = 'guia-cartao-'.$secao['id']; @endphp
    <div class="card shadow-sm mb-3 guia-cartao">
        <button class="btn btn-link text-decoration-none guia-cartao-cabeca" type="button"
                data-bs-toggle="collapse" data-bs-target="#{{ $alvo }}"
                aria-expanded="{{ ! empty($abertoGuia) ? 'true' : 'false' }}" aria-controls="{{ $alvo }}">
            <i class="fas {{ $secao['icone'] }} text-gold" aria-hidden="true"></i>
            <span class="flex-grow-1">{{ $tituloCartao ?? 'Como funciona' }}</span>
            <i class="fas fa-chevron-down guia-cartao-seta" aria-hidden="true"></i>
        </button>

        <div id="{{ $alvo }}" class="collapse{{ ! empty($abertoGuia) ? ' show' : '' }}">
            <div class="card-body border-top">
                <p class="guia-resumo">{{ $secao['resumo'] }}</p>
                @include('admin.guia.partials.blocos', ['blocos' => $secao['blocos']])

                <a class="guia-chamada" href="{{ route('admin.guia.index') }}#{{ $secao['id'] }}">
                    <i class="fas fa-book-open" aria-hidden="true"></i>
                    <span>Ler no Guia de uso</span>
                </a>
            </div>
        </div>
    </div>
@endif
