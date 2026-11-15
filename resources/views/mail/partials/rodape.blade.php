@php
    $empresa = $empresa ?? golden_empresa();
    $detalhesEmpresa = implode(' · ', array_filter([
        $empresa['endereco_completo'] ?? '',
        !empty($empresa['telefone']) ? 'Tel: '.$empresa['telefone'] : '',
        !empty($empresa['whatsapp']) ? 'WhatsApp: '.$empresa['whatsapp'] : '',
        $empresa['email'] ?? '',
    ], static fn (string $v): bool => trim($v) !== ''));
@endphp
<tr>
    <td style="padding:16px 24px;background:#faf7f0;font-size:12px;color:#6b7280;line-height:1.55;border-top:1px solid #f3ede2">
        @if(!empty($aviso))
            <div style="margin-bottom:8px">{!! $aviso !!}</div>
        @endif
        <div style="color:#4b5563">
            <strong>{{ $empresa['nome'] ?? 'GOLDEN' }}</strong>
            @if(!empty($empresa['documento']))
                · CNPJ/Doc: {{ $empresa['documento'] }}
            @endif
        </div>
        @if($detalhesEmpresa !== '')
            <div style="margin-top:2px">{{ $detalhesEmpresa }}</div>
        @endif
    </td>
</tr>
