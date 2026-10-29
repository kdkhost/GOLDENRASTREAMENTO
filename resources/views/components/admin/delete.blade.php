@props([
    'action',
    'label' => null,
    'confirm' => 'Excluir mesmo assim?',
    'icon' => 'fa-trash',
    'atualiza' => '.app-content',
])
{{-- A exclusão muda a volta da tela: a linha que era aquela some, e contagem, paginação e o
     estado da listagem vêm do servidor. `data-atualiza` é o que devolve essa verdade sem recarregar. --}}
<form method="POST" action="{{ $action }}" class="d-inline" data-confirmar="{{ $confirm }}" data-atualiza="{{ $atualiza }}">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" title="{{ $label ?: 'Excluir' }}">
        <i class="fas {{ $icon }}" aria-hidden="true"></i>@if($label)<span class="ms-1">{{ $label }}</span>@endif
    </button>
</form>
