@props([
    'headers' => [],
    'paginator' => null,
    'empty' => 'Nada por aqui ainda.',
])
@php
    $linhas = trim((string) $slot);
    $alinhamentos = ['center' => 'text-center', 'end' => 'text-end'];
@endphp
<div {{ $attributes->merge(['class' => 'card shadow-sm']) }}>
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
