{{-- A paginação de toda lista do painel e do portal. Registrada uma única vez em
     AppServiceProvider, e é ela que evita o padrão do framework: o view default do
     Laravel é o do Tailwind, que dentro do Bootstrap sai sem estilo e com um SVG de
     ícone sem largura — desenhado do tamanho da tela. --}}
@if ($paginator->hasPages())
    <nav class="paginacao" role="navigation" aria-label="Paginação da lista">
        @isset($elements)
            <p class="paginacao-resumo">
                {{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }}
                de <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong>
            </p>
        @endisset

        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}" @if ($paginator->onFirstPage()) aria-disabled="true" @endif>
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></a>
                @endif
            </li>

            @foreach ($elements ?? [] as $parte)
                @if (is_string($parte))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $parte }}</span></li>
                @else
                    @foreach ($parte as $pagina => $url)
                        @if ($pagina == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $pagina }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="Ir para a página {{ $pagina }}">{{ $pagina }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}" @if (! $paginator->hasMorePages()) aria-disabled="true" @endif>
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima página"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="page-link" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
