@props([
    'headers' => [],
    'paginator' => null,
    'empty' => 'Nada por aqui ainda.',
    'title' => null,
    'icon' => null,
    'descricao' => null,
])
@php
    $linhas = trim((string) $slot);
    $alinhamentos = ['center' => 'text-center', 'end' => 'text-end'];
@endphp
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
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @foreach($headers as $coluna)
                            @php
                                $chave = strtok($coluna, ':');
                                $rotulo = str_contains($coluna, ':') && isset($alinhamentos[$chave]) ? substr($coluna, strlen($chave) + 1) : $coluna;
                            @endphp
                            <th class="{{ $alinhamentos[$chave] ?? '' }}">{{ $rotulo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if($linhas !== '')
                        {!! $slot !!}
                    @else
                        <tr><td colspan="{{ count($headers) }}" class="text-center text-secondary py-5">{{ $empty }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @if($paginator)
        <div class="card-footer bg-body-tertiary">{{ $paginator->links() }}</div>
    @endif
</div>
