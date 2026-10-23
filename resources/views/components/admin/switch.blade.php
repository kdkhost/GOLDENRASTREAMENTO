@props([
    'name',
    'label',
    'checked' => false,
    'col' => 'col-12',
    'hint' => null,
])
@php
    $id = 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $chave = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
@endphp
<div class="{{ $col }}">
    <div class="form-check form-switch pt-1">
        <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1"
            @checked((bool) old($chave, $checked)) {{ $attributes }}>
        <label class="form-check-label" for="{{ $id }}">
            @if($slot->isEmpty()){{ $label }}@else{!! $slot !!}@endif
        </label>
        @if($hint)<div class="form-text">{{ $hint }}</div>@endif
    </div>
    @error($chave)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
