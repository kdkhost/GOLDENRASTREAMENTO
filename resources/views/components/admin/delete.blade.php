@props([
    'action',
    'label' => null,
    'confirm' => 'Excluir mesmo assim?',
    'icon' => 'fa-trash',
])
<form method="POST" action="{{ $action }}" class="d-inline" onsubmit="return confirm(@js($confirm))">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" title="{{ $label ?: 'Excluir' }}">
        <i class="fas {{ $icon }}" aria-hidden="true"></i>@if($label)<span class="ms-1">{{ $label }}</span>@endif
    </button>
</form>
