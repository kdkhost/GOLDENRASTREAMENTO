@props([
    'name',
    'label',
    'type' => 'text',
    'options' => null,
    'empty' => null,
    'col' => 'col-6 col-md-3',
    'placeholder' => null,
    'value' => null,
    'id' => null,
])
@php
    // O id vem do nome porque o rótulo e o campo têm de se achar sozinhos; a exceção é o campo que
    // um script do próprio endereça por id (a busca do guia), que passa o seu.
    $id = $id ?: 'flt-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    // O value é o padrão da tela (um período, por exemplo); o query string da URL vence.
    $atual = (string) request()->query($name, (string) $value);
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
