@php
    $fotos = !empty($p['imagens']) ? $p['imagens'] : (!empty($p['imagem']) ? [$p['imagem']] : []);
    $totalFotos = count($fotos);
@endphp
<article class="prod-card" data-card-prod>
    <div class="prod-thumb" data-prod-thumb>
        @if($totalFotos > 0)
            <a href="{{ route('site.loja.produto', $p['slug']) }}" class="prod-slides" data-slides aria-label="Ver {{ $p['nome'] }}">
                @foreach($fotos as $idx => $foto)
                    <img src="{{ produto_foto_url($foto) }}" alt="{{ $p['nome'] }}" class="prod-slide-img {{ $idx === 0 ? 'active' : '' }}" data-slide-index="{{ $idx }}" loading="lazy">
                @endforeach
            </a>
            @if($totalFotos > 1)
                <div class="prod-dots" data-dots>
                    @foreach($fotos as $idx => $foto)
                        <button type="button" class="prod-dot {{ $idx === 0 ? 'active' : '' }}" data-dot="{{ $idx }}" aria-label="Foto {{ $idx + 1 }} de {{ $p['nome'] }}"></button>
                    @endforeach
                </div>
                <span class="prod-count-badge" title="Este produto possui {{ $totalFotos }} fotos"><i class="fa-solid fa-camera"></i> {{ $totalFotos }}</span>
            @endif
        @else
            <a href="{{ route('site.loja.produto', $p['slug']) }}" class="prod-thumb-empty" aria-label="Ver {{ $p['nome'] }}">
                <i class="fa-solid fa-satellite-dish"></i>
            </a>
        @endif
        @if(!empty($p['promocao']))<span class="prod-badge">Promoção</span>@endif
        @if(!empty($p['destaque']))<span class="prod-badge feat">Destaque</span>@endif
    </div>

    <div class="prod-body">
        <div class="prod-head">
            <h3 class="prod-title">
                <a href="{{ route('site.loja.produto', $p['slug']) }}">{{ $p['nome'] }}</a>
            </h3>
            <div class="prod-price">
                @if(!empty($p['promocao']))<s>{{ brl($p['preco_cents']) }}</s>@endif
                @if($p['preco'] > 0)
                    <b>{{ brl($p['preco']) }}</b>
                    @if(!empty($p['mensalidade']))<small>+ {{ brl($p['mensalidade']) }}/mês</small>@endif
                @elseif(!empty($p['mensalidade']))
                    <b>{{ brl($p['mensalidade']) }}/mês</b>
                @else
                    <b>Consulte</b>
                @endif
            </div>
        </div>

        @if(!empty($p['resumo']))
            <p class="prod-desc-short">{{ $p['resumo'] }}</p>
        @endif

        <div class="prod-footer">
            <div class="prod-stock">
                @if(empty($p['controle_estoque']))
                    <span class="ok"><i class="fa-solid fa-circle-check"></i> Pronta entrega</span>
                @elseif($p['estoque'] > 0)
                    <span class="ok"><i class="fa-solid fa-circle-check"></i> {{ $p['estoque'] }} em estoque</span>
                @else
                    <span class="out"><i class="fa-solid fa-circle-xmark"></i> Sem estoque</span>
                @endif
            </div>

            @if(!empty($p['disponivel']))
                <form method="POST" action="{{ route('site.loja.carrinho.adicionar') }}" data-farol="sacola" data-detalhe="{{ $p['slug'] }}">
                    @csrf
                    <input type="hidden" name="produto" value="{{ $p['id'] }}">
                    <input type="hidden" name="quantidade" value="1">
                    <button type="submit" class="prod-buy-btn">Comprar</button>
                </form>
            @else
                <a href="{{ $golden['whatsapp_url'] }}?text={{ urlencode('Olá! Quero encomendar o ' . $p['nome'] . ' da loja Golden.') }}" target="_blank" rel="noopener" class="prod-buy-btn prod-buy-wa">
                    <i class="fa-brands fa-whatsapp"></i> Encomendar
                </a>
            @endif
        </div>
    </div>
</article>
