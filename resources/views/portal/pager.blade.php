@if($paginas->hasPages())
    <nav class="pagineacao" aria-label="Paginação">
        @if($paginas->onFirstPage())
            <span class="btn btn-ghost btn-sm inativa"><i class="fa-solid fa-arrow-left"></i> Anterior</span>
        @else
            <a class="btn btn-ghost btn-sm" href="{{ $paginas->previousPageUrl() }}"><i class="fa-solid fa-arrow-left"></i> Anterior</a>
        @endif

        <span>Página {{ $paginas->currentPage() }} de {{ $paginas->lastPage() }}</span>

        @if($paginas->hasMorePages())
            <a class="btn btn-ghost btn-sm" href="{{ $paginas->nextPageUrl() }}">Próxima <i class="fa-solid fa-arrow-right"></i></a>
        @else
            <span class="btn btn-ghost btn-sm inativa">Próxima <i class="fa-solid fa-arrow-right"></i></span>
        @endif
    </nav>
@endif
