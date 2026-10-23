@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'options' => null,
    'empty' => null,
    'col' => 'col-12',
    'placeholder' => null,
    'hint' => null,
    'rows' => 4,
    'required' => false,
    'opcional' => false,
])
@php
    $id = 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $atual = old($name, $value);
    $comErro = $errors->has($name) ? ' is-invalid' : '';
    $classe = ($type === 'select' ? 'form-select' : 'form-control').$comErro;
    $extra = $attributes->except('class');
@endphp
<div class="{{ $col }}">
    <label class="form-label" for="{{ $id }}">
        {{ $label }}
        @if($opcional)<span class="form-hint">(opcional)</span>@endif
        @if($hint)<span class="form-hint d-block">{{ $hint }}</span>@endif
    </label>

    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif {!! $extra !!}>{{ $atual }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($required) required @endif {!! $extra !!}>
            @if($empty !== null)<option value="">{{ $empty }}</option>@endif
            @foreach($options as $chave => $rotulo)
                <option value="{{ $chave }}" @selected((string) $atual === (string) $chave)>{{ $rotulo }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $atual }}"
            class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif {!! $extra !!}>
    @endif

    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
