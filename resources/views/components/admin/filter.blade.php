@props([
    'name',
    'label',
    'type' => 'text',
    'options' => null,
    'empty' => null,
    'col' => 'col-6 col-md-3',
    'placeholder' => null,
])
@php
    $id = 'flt-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $atual = (string) request()->query($name, '');
@endphp
<div class="{{ $col }}">
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    @if($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="form-select form-select-sm {!! $attributes->get('class') !!}" {!! $attributes->except('class') !!}>
            @if($empty !== null)<option value="">{{ $empty }}</option>@endif
            @foreach($options as $chave => $rotulo)
                <option value="{{ $chave }}" @selected($atual === (string) $chave)>{{ $rotulo }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $atual }}"
            class="form-control form-control-sm {!! $attributes->get('class') !!}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif {!! $attributes->except('class') !!}>
    @endif
</div>
