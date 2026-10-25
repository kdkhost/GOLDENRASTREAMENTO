{{-- Paginação das listas públicas do site. O painel e o portal têm o view irmão
     (pagination.painel, registrado no provider): aqui a superfície não veste Bootstrap,
     então os números vêm com as classes e os tokens do CSS do próprio site. --}}
@if ($paginator->hasPages())
    <nav class="paginacao-site" role="navigation" aria-label="Páginas da lista">
        @if ($paginator->onFirstPage())
            <span class="pagina-seta" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <a class="pagina-seta" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a>
        @endif

        @foreach ($elements as $parte)
            @if (is_string($parte))
                <span class="pagina-elipse">{{ $parte }}</span>
            @else
                @foreach ($parte as $pagina => $url)
                    @if ((int) $pagina === $paginator->currentPage())
                        <span class="pagina pagina-on" aria-current="page">{{ $pagina }}</span>
                    @else
                        <a class="pagina" href="{{ $url }}" aria-label="Ir para a página {{ $pagina }}">{{ $pagina }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pagina-seta" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima página"><i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="pagina-seta" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
