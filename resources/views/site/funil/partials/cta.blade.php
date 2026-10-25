@php
    // Botão de bloco do funil: sem rótulo ou sem destino ele simplesmente não aparece,
    // porque um botão vazio no meio de uma campanha custa o clique de quem já quis ir.
    $rotulo = trim((string) ($rotulo ?? ''));
    $destino = trim((string) ($destino ?? ''));
    $externo = str_starts_with($destino, 'http');
    $whatsapp = $externo && (str_contains($destino, 'wa.me') || str_contains($destino, 'whatsapp.com'));
@endphp
@if($rotulo !== '' && $destino !== '')
<a class="btn {{ $estilo ?? 'btn-gold' }}" href="{{ $destino }}"
    @if($externo) target="_blank" rel="noopener" @endif
    data-track="{{ $whatsapp ? 'whatsapp_click' : 'contact' }}" data-cta="{{ $cta ?? 'funil' }}">{{ $rotulo }} →</a>
@endif
