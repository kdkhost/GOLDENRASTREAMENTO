@props([
    'reset' => null,
])
<div class="card shadow-sm filters-card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            {{ $slot }}
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-sm btn-golden"><i class="fas fa-filter me-1" aria-hidden="true"></i> Filtrar</button>
                @if($reset)
                    <a href="{{ $reset }}" class="btn btn-sm btn-outline-secondary">Limpar filtros</a>
                @endif
                @if(isset($extra)){{ $extra }}@endif
            </div>
        </form>
    </div>
</div>
