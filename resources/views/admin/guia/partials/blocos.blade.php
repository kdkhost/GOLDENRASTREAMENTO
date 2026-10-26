{{--
    O desenho dos blocos do guia. Um partial só porque dois lugares diferentes consomem a mesma
    fonte: a tela Guia de uso e o cartão "Como conectar" da tela de Google Ads. Texto copiado de
    um para o outro seria ter duas versões do mesmo passo a passo, e uma delas ia envelhecer.

    Os tipos são os declarados no topo de config/guia_painel.php: texto, passos, lista, código,
    tabela e aviso. Passo não tem número — ícone, título e texto, e a ordem já está na lista.

    Todo texto passa por GuiaPainel::inline(), que escapa primeiro e desenha depois; por isso o
    {!! !!} aqui não é porta de entrada para marca vinda do config.
--}}
@foreach ($blocos as $bloco)
    @switch($bloco['tipo'])
        @case('texto')
            <p class="guia-texto">{!! \App\Support\GuiaPainel::inline($bloco['conteudo']) !!}</p>
            @break

        @case('passos')
            @if (isset($bloco['titulo']))
                <div class="guia-subtitulo">{{ $bloco['titulo'] }}</div>
            @endif
            <ul class="guia-passos list-unstyled mb-0">
                @foreach ($bloco['itens'] as $passo)
                    <li class="guia-passo">
                        <span class="guia-passo-icone tone-gold"><i class="fas {{ $passo['icone'] }}" aria-hidden="true"></i></span>
                        <span class="guia-passo-texto">
                            <span class="d-block fw-semibold">{{ $passo['titulo'] }}</span>
                            <span class="d-block small text-secondary">{!! \App\Support\GuiaPainel::inline($passo['texto']) !!}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            @break

        @case('lista')
            <ul class="guia-lista">
                @foreach ($bloco['itens'] as $item)
                    <li>{!! \App\Support\GuiaPainel::inline($item) !!}</li>
                @endforeach
            </ul>
            @break

        @case('codigo')
            <pre class="guia-codigo" tabindex="0"><code>{{ implode(PHP_EOL, $bloco['linhas']) }}</code></pre>
            @break

        @case('tabela')
            <div class="table-responsive">
                <table class="table guia-tabela align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach ($bloco['cabecalho'] as $coluna)
                                <th scope="col">{{ $coluna }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bloco['linhas'] as $linha)
                            <tr>
                                @foreach ($linha as $celula)
                                    <td>{!! \App\Support\GuiaPainel::inline((string) $celula) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @break

        @case('aviso')
            <div class="guia-aviso guia-aviso--{{ $bloco['tom'] ?? 'amber' }}">
                {!! \App\Support\GuiaPainel::inline($bloco['conteudo']) !!}
            </div>
            @break

        @default
            {{-- Um tipo novo no config sem desenho aqui apareceria como texto vazio na tela; o
                 teste da tela inteira pega isso antes. --}}
    @endswitch
@endforeach
