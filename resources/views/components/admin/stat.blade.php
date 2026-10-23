@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'gold',
    'href' => null,
    'hint' => null,
    'pequeno' => false,
])
@if($href)<a href="{{ $href }}" class="stat-link">@endif
<div {{ $attributes->merge(['class' => 'card stat-card shadow-sm h-100']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        @if($icon)<div class="icon icon-{{ $tone }}"><i class="fas {{ $icon }}" aria-hidden="true"></i></div>@endif
        <div class="overflow-hidden">
            <div class="value {{ $pequeno ? 'fs-4' : '' }}">{{ $value }}</div>
            <div class="label">{{ $label }}</div>
            @if($hint)<div class="form-text">{{ $hint }}</div>@endif
        </div>
    </div>
</div>
@if($href)</a>@endif
