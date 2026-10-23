@props([
    'valor',
    'label' => 'Link',
    'hint' => null,
    'id' => null,
])
@php
    $id ??= 'link-'.substr(md5($label.'|'.$valor), 0, 8);
@endphp
<label class="form-label" for="{{ $id }}">
    {{ $label }}
    @if($hint)<span class="form-hint">{{ $hint }}</span>@endif
</label>
<div class="input-group input-group-sm">
    <input id="{{ $id }}" type="text" class="form-control font-monospace" readonly value="{{ $valor }}">
    <button class="btn btn-outline-secondary" type="button" data-copiar="#{{ $id }}"><i class="fas fa-copy" aria-hidden="true"></i> Copiar</button>
</div>
