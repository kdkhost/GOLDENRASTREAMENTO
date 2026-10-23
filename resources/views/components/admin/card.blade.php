@props([
    'title' => null,
    'icon' => null,
    'descricao' => null,
    'flush' => false,
])
<div {{ $attributes->merge(['class' => 'card shadow-sm']) }}>
    @if($title)
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                @if($icon)<i class="fas {{ $icon }} me-1 text-gold" aria-hidden="true"></i>@endif
                <span class="fw-bold">{{ $title }}</span>
                @if($descricao)<span class="form-hint d-block fw-normal small text-secondary">{{ $descricao }}</span>@endif
            </span>
            @if(isset($actions))<span class="table-actions">{{ $actions }}</span>@endif
        </div>
    @endif
    <div class="card-body {{ $flush ? 'p-0' : '' }}">{{ $slot }}</div>
</div>
