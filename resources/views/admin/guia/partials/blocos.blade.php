{{--
    O desenho dos blocos do guia. Um partial só porque dois lugares diferentes consomem a mesma
    fonte: a tela Guia de uso e o cartão "Como conectar" da tela de Google Ads. Texto copiado de
    um para o outro seria ter duas versões do mesmo passo a passo, e uma delas ia envelhecer.

    Os tipos são os declarados no topo de config/guia_painel.php: texto, passos, lista, código,
    tabela, links e aviso. Passo não tem número — ícone, título e texto, e a ordem já está na
    lista. Os mesmos tipos servem à gaveta de ajuda de Configurações
    (`App\Support\GuiaConfiguracao`), que é como um item conta o seu passo a passo.

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
            <ul class="guia-passos">
                @foreach ($bloco['itens'] as $passo)
                    <li class="guia-passo">
                        <span class="guia-passo-icone tone-gold"><i class="fas {{ $passo['icone'] }}" aria-hidden="true"></i></span>
                        <span class="guia-passo-texto">
                            <span class="d-block fw-semibold">{{ $passo['titulo'] }}</span>
                            <span class="d-block guia-passo-descricao">{!! \App\Support\GuiaPainel::inline($passo['texto']) !!}</span>
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
                <table class="table guia-tabela">
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

        @case('links')
            {{-- Os endereços onde a credencial nasce. Só chegam aqui os que a instalação consegue
                 preencher hoje: `GuiaConfiguracao` descarta o que depende de algo ainda vazio, e a
                 lista nunca mostra link quebrado. Abrem em outra aba de propósito — quem vai buscar
                 o token não perde o formulário que estava preenchendo. --}}
            <div class="guia-subtitulo">{{ $bloco['titulo'] ?? 'Onde buscar' }}</div>
            <div class="guia-links">
                @foreach ($bloco['itens'] as $link)
                    <a class="guia-link" href="{{ $link['url'] }}" target="_blank" rel="noopener">
                        <i class="fas fa-up-right-from-square" aria-hidden="true"></i>
                        <span>{{ $link['rotulo'] }}</span>
                    </a>
                @endforeach
            </div>
            @break

        @default
            {{-- Um tipo novo no config sem desenho aqui apareceria como texto vazio na tela; o
                 teste da tela inteira pega isso antes. --}}
    @endswitch
@endforeach
