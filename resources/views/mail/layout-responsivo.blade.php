<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <title>{{ $assunto !== '' ? $assunto : ($layout['marca_nome'] ?? 'Golden Rastreamento') }}</title>
    <style type="text/css">
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: {{ $layout['cor_fundo_pagina'] ?? '#f4f1e9' }};
            color: {{ $layout['cor_texto'] ?? '#1b2127' }};
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
        }
        a {
            color: {{ $layout['cor_destaque'] ?? '#e0a33e' }};
        }
        .golden-email-body p {
            margin: 0 0 14px;
            line-height: 1.62;
        }
        .golden-email-body h1,
        .golden-email-body h2,
        .golden-email-body h3 {
            color: {{ $layout['cor_texto'] ?? '#1b2127' }};
            margin: 0 0 12px;
            line-height: 1.3;
        }
        .golden-btn {
            display: inline-block;
            background-color: {{ $layout['cor_botao_bg'] ?? '#e0a33e' }} !important;
            color: {{ $layout['cor_botao_texto'] ?? '#12161a' }} !important;
            text-decoration: none !important;
            font-weight: 700;
            font-size: 14px;
            padding: 13px 24px;
            border-radius: 10px;
        }
        @media only screen and (max-width: 620px) {
            .golden-email-outer {
                padding: 10px 8px !important;
            }
            .golden-email-container {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 12px !important;
            }
            .golden-email-header,
            .golden-email-padding,
            .golden-email-footer {
                padding-left: 18px !important;
                padding-right: 18px !important;
            }
            .golden-email-stack {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding-bottom: 8px !important;
            }
            .golden-btn,
            .golden-email-body a[data-btn="true"] {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                box-sizing: border-box !important;
            }
        }
        {!! strip_tags((string) ($layout['css_adicional'] ?? '')) !!}
    </style>
</head>
<body style="margin:0;padding:0;background-color:{{ $layout['cor_fundo_pagina'] ?? '#f4f1e9' }};font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:{{ $layout['cor_texto'] ?? '#1b2127' }};">
    @if(!empty($layout['mostrar_pre_cabecalho']) && !empty($preCabecalho))
        <div style="display:none;font-size:1px;color:{{ $layout['cor_fundo_pagina'] ?? '#f4f1e9' }};line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
            {{ $preCabecalho }} &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $layout['cor_fundo_pagina'] ?? '#f4f1e9' }};">
        <tr>
            <td align="center" class="golden-email-outer" style="padding:26px 14px;">
                <table role="presentation" width="{{ (int) ($layout['largura_maxima_px'] ?? 580) }}" cellpadding="0" cellspacing="0" border="0" class="golden-email-container" style="width:100%;max-width:{{ (int) ($layout['largura_maxima_px'] ?? 580) }}px;margin:0 auto;background-color:{{ $layout['cor_cartao'] ?? '#ffffff' }};border-radius:{{ (int) ($layout['raio_borda_px'] ?? 16) }}px;overflow:hidden;box-shadow:0 6px 24px rgba(18,22,26,0.08);">
                    @if(!empty($layout['mostrar_barra_superior']))
                        <tr>
                            <td style="height:4px;line-height:4px;font-size:1px;background-color:{{ $layout['cor_destaque'] ?? '#e0a33e' }};">&nbsp;</td>
                        </tr>
                    @endif
                    <tr>
                        @php
                            $empresaDados = $empresa ?? golden_empresa();
                            $marcaTitulo = trim((string) ($layout['marca_nome'] ?? '')) ?: ($empresaDados['nome'] ?? 'GOLDEN');
                            $logoSrc = trim((string) ($layout['logo_url'] ?? '')) ?: (string) ($empresaDados['logo_url'] ?? asset('img/logo-golden.png'));
                            $alinhamentoHeader = ($layout['alinhamento_cabecalho'] ?? 'left') === 'center' ? 'center' : 'left';
                        @endphp
                        <td class="golden-email-header" align="{{ $alinhamentoHeader }}" style="background-color:{{ $layout['cor_cabecalho_bg'] ?? '#12161a' }};padding:22px 26px;">
                            @if($logoSrc !== '')
                                <img src="{{ $logoSrc }}" alt="{{ $marcaTitulo }}" style="max-height:42px;max-width:180px;width:auto;height:auto;display:inline-block;margin-bottom:8px;vertical-align:middle;">
                            @endif
                            <div style="color:{{ $layout['cor_destaque'] ?? '#e0a33e' }};font-weight:800;font-size:20px;letter-spacing:.07em;line-height:1.2;">
                                {{ $marcaTitulo }}
                            </div>
                            @if(!empty($layout['subtitulo']))
                                <div style="color:{{ $layout['cor_cabecalho_texto'] ?? '#cfd6dd' }};opacity:.86;font-size:12px;margin-top:3px;">
                                    {{ $layout['subtitulo'] }}
                                </div>
                            @endif
                            @if(!empty($layout['cabecalho_extra_html']))
                                <div style="margin-top:8px;font-size:12px;color:{{ $layout['cor_cabecalho_texto'] ?? '#cfd6dd' }};">
                                    {!! $layout['cabecalho_extra_html'] !!}
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="golden-email-padding golden-email-body" style="padding:28px 26px;color:{{ $layout['cor_texto'] ?? '#1b2127' }};font-size:14px;line-height:1.6;">
                            {!! $corpoHtml !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="golden-email-footer" style="padding:18px 26px;background-color:{{ $layout['cor_rodape_bg'] ?? '#faf7f0' }};color:{{ $layout['cor_rodape_texto'] ?? '#6b7280' }};font-size:12px;line-height:1.55;border-top:1px solid rgba(18,22,26,0.08);">
                            @if(!empty($layout['texto_rodape']))
                                <p style="margin:0 0 8px;">
                                    {{ $layout['texto_rodape'] }}
                                    @if(!empty($layout['mostrar_whatsapp']))
                                        WhatsApp: <strong style="color:{{ $layout['cor_texto'] ?? '#1b2127' }};">{{ $whatsapp ?: ($empresaDados['whatsapp'] ?? '') }}</strong>
                                    @endif
                                </p>
                            @endif
                            @php
                                $linhaIdentidadeEmpresa = implode(' · ', array_filter([
                                    $empresaDados['nome'] ?? '',
                                    !empty($empresaDados['documento']) ? 'CNPJ/Doc: '.$empresaDados['documento'] : '',
                                ], static fn (string $v): bool => trim($v) !== ''));
                                $linhaContatosEmpresa = implode(' · ', array_filter([
                                    !empty($empresaDados['endereco_completo']) ? $empresaDados['endereco_completo'] : '',
                                    !empty($empresaDados['telefone']) ? 'Tel: '.$empresaDados['telefone'] : '',
                                    !empty($layout['mostrar_whatsapp']) && !empty($empresaDados['whatsapp']) ? 'WhatsApp: '.$empresaDados['whatsapp'] : '',
                                    !empty($empresaDados['email']) ? $empresaDados['email'] : '',
                                ], static fn (string $v): bool => trim($v) !== ''));
                            @endphp
                            @if($linhaIdentidadeEmpresa !== '' || $linhaContatosEmpresa !== '')
                                <p style="margin:0 0 8px;color:{{ $layout['cor_rodape_texto'] ?? '#6b7280' }};font-size:11.5px;line-height:1.5;">
                                    @if($linhaIdentidadeEmpresa !== '')
                                        <strong style="color:{{ $layout['cor_texto'] ?? '#1b2127' }};">{{ $linhaIdentidadeEmpresa }}</strong><br>
                                    @endif
                                    @if($linhaContatosEmpresa !== '')
                                        {{ $linhaContatosEmpresa }}
                                    @endif
                                </p>
                            @endif
                            @if(!empty($layout['mostrar_links_rodape']))
                                <p style="margin:0 0 8px;">
                                    <a href="{{ url('/') }}" style="color:{{ $layout['cor_destaque'] ?? '#e0a33e' }};text-decoration:none;font-weight:600;">Site Oficial</a>
                                    &nbsp;&middot;&nbsp;
                                    <a href="{{ url('/portal') }}" style="color:{{ $layout['cor_destaque'] ?? '#e0a33e' }};text-decoration:none;font-weight:600;">Portal do Cliente</a>
                                    &nbsp;&middot;&nbsp;
                                    <a href="{{ url('/rastrear') }}" style="color:{{ $layout['cor_destaque'] ?? '#e0a33e' }};text-decoration:none;font-weight:600;">Rastrear Pedido</a>
                                </p>
                            @endif
                            @if(!empty($layout['rodape_legal']))
                                <p style="margin:0;opacity:.85;">{{ $layout['rodape_legal'] }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
