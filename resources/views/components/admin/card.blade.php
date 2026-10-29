@props([
    'title' => null,
    'icon' => null,
    'descricao' => null,
    'flush' => false,
    'ajuda' => null,
])
@php
    // O cartão também pode ter a sua ajuda, pelo nome do assunto que ele trata — é assim que um
    // bloco inteiro (a caixa multicanal, a veiculação do financeiro) conta o seu passo a passo sem
    // despejar parágrafo na frente do formulário.
    $gaveta = $ajuda ? \App\Support\GuiaConfiguracao::gaveta($ajuda, $title) : null;
@endphp
<div {{ $attributes->merge(['class' => 'card shadow-sm']) }}>
    @if($title)
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                @if($icon)<i class="fas {{ $icon }} me-1 text-gold" aria-hidden="true"></i>@endif
                <span class="fw-bold">{{ $title }}</span>
                @if($gaveta)<x-admin.ajuda :gaveta="$gaveta" />@endif
                @if($descricao)<span class="form-hint d-block fw-normal small text-secondary">{{ $descricao }}</span>@endif
            </span>
            @if(isset($actions))<span class="table-actions">{{ $actions }}</span>@endif
        </div>
    @endif
    <div class="card-body {{ $flush ? 'p-0' : '' }}">{{ $slot }}</div>
</div>
