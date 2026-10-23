@props([
    'rotulo',
    'tom' => 'neutral',
])
{{-- Selo de situação do painel: a forma é .badge-status, a cor vem dos utilitários .tone-*
     do tema (claro e escuro iguais). Quem escolhe o tom é tom_por_status() /
     tom_por_flag(), com a tabela em config('golden.tons_status') — nenhuma view decide cor. --}}
<span {{ $attributes->merge(['class' => 'badge-status tone-'.$tom]) }}>{{ $rotulo }}</span>
