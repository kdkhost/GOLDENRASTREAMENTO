@php
    $empresa = $empresa ?? golden_empresa();
    $logoUrl = trim((string) ($empresa['logo_url'] ?? ''));
    $nomeEmpresa = trim((string) ($empresa['nome'] ?? '')) ?: 'GOLDEN';
@endphp
<tr>
    <td style="background:#12161a;padding:20px 24px">
        @if($logoUrl !== '')
            <img src="{{ $logoUrl }}" alt="{{ $nomeEmpresa }}" style="max-height:38px;max-width:180px;width:auto;height:auto;display:block;margin-bottom:8px">
        @endif
        <span style="color:#e0a33e;font-weight:800;font-size:18px;letter-spacing:.06em;display:block">{{ $nomeEmpresa }}</span>
        @if(!empty($subtitulo))
            <span style="color:#cfd6dd;font-size:12px;display:block;margin-top:2px">{{ $subtitulo }}</span>
        @endif
    </td>
</tr>
