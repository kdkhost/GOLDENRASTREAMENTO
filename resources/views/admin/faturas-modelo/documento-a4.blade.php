@php
    $corPrimaria = $estilo['cor_primaria'] ?? '#12161a';
    $corDestaque = $estilo['cor_destaque'] ?? '#e0a33e';
    $corBorda = $estilo['cor_borda'] ?? '#e2ddd1';
    $raioPx = (int) ($estilo['raio_borda_px'] ?? 6);
    $fontePt = (float) ($estilo['tamanho_fonte_pt'] ?? 9.5);
    $fmtMoeda = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
@endphp
@if(empty($modoEmbutido))
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fatura {{ $fatura['numero'] }} · Impressão A4</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            color: {{ $estilo['cor_texto'] ?? '#1b2127' }};
        }
        .barra-impressao {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #12161a;
            color: #fff;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            border-bottom: 2px solid {{ $corDestaque }};
        }
        .barra-impressao .acoes {
            display: flex;
            gap: 8px;
        }
        .barra-impressao button {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-imprimir-a4 {
            background: {{ $corDestaque }};
            color: #12161a;
        }
        .btn-fechar-a4 {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .palco-a4 {
            padding: 20px 12px 40px;
            display: flex;
            justify-content: center;
        }
        @media print {
            body {
                background: #fff !important;
            }
            .barra-impressao {
                display: none !important;
            }
            .palco-a4 {
                padding: 0 !important;
            }
            .folha-a4 {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                min-height: 297mm !important;
            }
        }
@endif
        .folha-a4 {
            width: 210mm;
            min-height: 297mm;
            background: #ffffff;
            color: {{ $estilo['cor_texto'] ?? '#1b2127' }};
            padding: {{ (int) ($estilo['margem_topo_mm'] ?? 12) }}mm {{ (int) ($estilo['margem_lateral_mm'] ?? 12) }}mm {{ (int) ($estilo['margem_inferior_mm'] ?? 12) }}mm;
            font-size: {{ $fontePt }}pt;
            line-height: 1.42;
            box-shadow: 0 18px 48px rgba(18, 22, 26, 0.18);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }
        .a4-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            align-items: stretch;
        }
        .a4-bloco {
            border-radius: {{ $raioPx }}px;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            position: relative;
        }
        .a4-bloco--100 {
            grid-column: span 2;
        }
        .a4-bloco--50 {
            grid-column: span 1;
        }
        .a4-pad-compacto { padding: 8px 11px; }
        .a4-pad-padrao { padding: 12px 14px; }
        .a4-pad-amplo { padding: 16px 18px; }
        .a4-kicker {
            font-size: 7.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $corDestaque }};
            margin-bottom: 5px;
        }
        .a4-tabela {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .a4-tabela th {
            background: {{ $corPrimaria }};
            color: #ffffff;
            text-align: left;
            padding: 6px 8px;
            font-size: 8pt;
            font-weight: 700;
        }
        .a4-tabela td {
            padding: 6px 8px;
            border-bottom: 1px solid {{ $corBorda }};
            font-size: 8.5pt;
        }
        .a4-tabela tr:nth-child(even) td {
            background: #faf8f2;
        }
        .a4-rodape {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid {{ $corBorda }};
            font-size: 7.5pt;
            color: #6b7280;
            text-align: center;
        }
@if(empty($modoEmbutido))
    </style>
</head>
<body>
    <div class="barra-impressao">
        <div>
            <strong>Golden · Impressão Cirúrgica A4</strong> · Fatura #{{ $fatura['numero'] }} (210mm × 297mm)
        </div>
        <div class="acoes">
            <button type="button" class="btn-imprimir-a4" onclick="window.print()">Imprimir / Salvar PDF</button>
            <button type="button" class="btn-fechar-a4" onclick="window.close()">Fechar</button>
        </div>
    </div>
    <div class="palco-a4">
@endif
        <div class="folha-a4" data-fatura-a4>
            <div class="a4-grid">
                @foreach($blocos as $bloco)
                    @php
                        $larguraClass = ((int) ($bloco['largura'] ?? 100)) === 50 ? 'a4-bloco--50' : 'a4-bloco--100';
                        $padClass = 'a4-pad-' . ($bloco['padding'] ?? 'padrao');
                        $bordaCss = !empty($bloco['borda']) ? "1px solid {$corBorda}" : '1px solid transparent';
                        $estiloInline = sprintf(
                            'background:%s;color:%s;border:%s;text-align:%s;',
                            e($bloco['fundo'] ?? '#ffffff'),
                            e($bloco['texto_cor'] ?? '#1b2127'),
                            $bordaCss,
                            e($bloco['alinhamento'] ?? 'left')
                        );
                    @endphp
                    <section class="a4-bloco {{ $larguraClass }} {{ $padClass }}" data-bloco-id="{{ $bloco['id'] }}" style="{{ $estiloInline }}">
                        @switch($bloco['id'])
                            @case('cabecalho_empresa')
                                <div style="height:3px;background:{{ $corDestaque }};margin:-12px -14px 10px;"></div>
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;">
                                    <div>
                                        @if(!empty($estilo['logo_url']))
                                            <img src="{{ $estilo['logo_url'] }}" alt="{{ $estilo['emitente_nome'] }}" style="max-height:34px;width:auto;margin-bottom:4px;display:block;">
                                        @endif
                                        <div style="font-size:14pt;font-weight:800;color:{{ $corDestaque }};letter-spacing:.04em;">
                                            {{ $estilo['emitente_nome'] }}
                                        </div>
                                        @if(!empty($estilo['emitente_documento']))
                                            <div style="font-size:8pt;opacity:.85;margin-top:2px;">CNPJ/Doc: {{ $estilo['emitente_documento'] }}</div>
                                        @endif
                                        @if(!empty($estilo['emitente_endereco']))
                                            <div style="font-size:8pt;opacity:.85;">{{ $estilo['emitente_endereco'] }}</div>
                                        @endif
                                        @if(!empty($estilo['emitente_contato']))
                                            <div style="font-size:8pt;opacity:.85;">{{ $estilo['emitente_contato'] }}</div>
                                        @endif
                                    </div>
                                    <div style="text-align:right;min-width:155px;">
                                        <div style="font-size:13pt;font-weight:800;">FATURA #{{ $fatura['numero'] }}</div>
                                        <div style="display:inline-block;background:{{ $corDestaque }};color:#12161a;font-size:7.5pt;font-weight:800;padding:2px 8px;border-radius:999px;margin:4px 0;">
                                            {{ mb_strtoupper($fatura['status_rotulo']) }}
                                        </div>
                                        <div style="font-size:8.5pt;opacity:.9;">Vencimento: <strong>{{ $fatura['vencimento'] }}</strong></div>
                                        <div style="font-size:11pt;font-weight:800;color:{{ $corDestaque }};margin-top:2px;">{{ $fmtMoeda($fatura['total_cents']) }}</div>
                                    </div>
                                </div>
                                @break

                            @case('dados_cliente')
                                <div class="a4-kicker">Dados do Sacado / Cliente</div>
                                <div style="font-weight:700;font-size:10pt;">{{ $fatura['cliente']['nome'] ?: 'Cliente Golden' }}</div>
                                @if($fatura['cliente']['empresa'] !== '')
                                    <div style="font-size:8.5pt;">{{ $fatura['cliente']['empresa'] }}</div>
                                @endif
                                <div style="font-size:8.5pt;margin-top:3px;">
                                    <strong>CPF/CNPJ:</strong> {{ $fatura['cliente']['documento'] ?: 'Não informado' }}
                                </div>
                                <div style="font-size:8.5pt;"><strong>E-mail:</strong> {{ $fatura['cliente']['email'] ?: '-' }}</div>
                                @if($fatura['cliente']['telefone'] !== '')
                                    <div style="font-size:8.5pt;"><strong>Telefone:</strong> {{ $fatura['cliente']['telefone'] }}</div>
                                @endif
                                @php
                                    $endCompleto = trim($fatura['cliente']['endereco'].' · '.$fatura['cliente']['cidade'].($fatura['cliente']['uf'] !== '' ? '/'.$fatura['cliente']['uf'] : '').' '.$fatura['cliente']['cep'], ' ·');
                                @endphp
                                @if($endCompleto !== '')
                                    <div style="font-size:8pt;opacity:.85;margin-top:2px;">{{ $endCompleto }}</div>
                                @endif
                                @break

                            @case('resumo_cobranca')
                                <div class="a4-kicker">Resumo Financeiro & Vencimento</div>
                                <div style="display:flex;justify-content:space-between;font-size:8.5pt;margin-bottom:3px;">
                                    <span>Data de emissão:</span><strong>{{ $fatura['emitida'] }}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:8.5pt;margin-bottom:3px;">
                                    <span>Vencimento:</span><strong>{{ $fatura['vencimento'] }}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:8.5pt;margin-bottom:3px;">
                                    <span>Subtotal:</span><span>{{ $fmtMoeda($fatura['subtotal_cents']) }}</span>
                                </div>
                                @if($fatura['credito_cents'] > 0)
                                    <div style="display:flex;justify-content:space-between;font-size:8.5pt;margin-bottom:3px;">
                                        <span>Créditos / Descontos:</span><span>- {{ $fmtMoeda($fatura['credito_cents']) }}</span>
                                    </div>
                                @endif
                                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid {{ $corBorda }};margin-top:6px;padding-top:6px;font-size:10.5pt;font-weight:800;">
                                    <span>Total da Fatura:</span>
                                    <span style="color:{{ $corPrimaria }};">{{ $fmtMoeda($fatura['total_cents']) }}</span>
                                </div>
                                @break

                            @case('itens_fatura')
                                <div class="a4-kicker">Discriminação dos Serviços / Itens</div>
                                <table class="a4-tabela">
                                    <thead>
                                        <tr>
                                            <th>Descrição</th>
                                            <th style="width:55px;text-align:center;">Qtd</th>
                                            <th style="width:90px;text-align:right;">Unitário</th>
                                            <th style="width:95px;text-align:right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($fatura['itens'] as $item)
                                            <tr>
                                                <td>{{ $item['descricao'] }}</td>
                                                <td style="text-align:center;">{{ $item['quantidade'] }}</td>
                                                <td style="text-align:right;">{{ $fmtMoeda($item['valor_unitario_cents']) }}</td>
                                                <td style="text-align:right;font-weight:700;">{{ $fmtMoeda($item['valor_cents']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @break

                            @case('pagamento_gateway')
                                <div class="a4-kicker">Gateway WHMCS & Pagamento PIX</div>
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;">
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:9.5pt;font-weight:700;margin-bottom:4px;">
                                            Gateway conectado: {{ $fatura['metodo_rotulo'] }}
                                        </div>
                                        @if($pixConteudo !== '')
                                            <div style="font-size:8pt;font-weight:700;margin-bottom:2px;">PIX Copia e Cola / Código de Pagamento:</div>
                                            <div style="font-family:monospace;font-size:7.4pt;background:#ffffff;border:1px solid {{ $corBorda }};padding:6px 8px;border-radius:4px;word-break:break-all;">
                                                {{ $pixConteudo }}
                                            </div>
                                        @endif
                                        @if($fatura['checkout_url'] !== '')
                                            <div style="font-size:7.8pt;margin-top:5px;word-break:break-all;opacity:.85;">
                                                Pagamento online: {{ $fatura['checkout_url'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if($qrSvg !== '')
                                        <div style="flex-shrink:0;text-align:center;background:#ffffff;padding:5px;border:1px solid {{ $corBorda }};border-radius:6px;">
                                            {!! $qrSvg !!}
                                            <div style="font-size:6.8pt;font-weight:700;margin-top:2px;">Aponte a câmera (PIX)</div>
                                        </div>
                                    @endif
                                </div>
                                @break

                            @case('codigo_barras_boleto')
                                <div class="a4-kicker">Linha Digitável & Ficha de Compensação</div>
                                @if($fatura['nosso_numero'] !== '')
                                    <div style="font-size:8pt;margin-bottom:3px;"><strong>Nosso Número:</strong> {{ $fatura['nosso_numero'] }}</div>
                                @endif
                                @if($fatura['linha_digitavel'] !== '')
                                    <div style="font-family:monospace;font-size:9pt;font-weight:700;letter-spacing:.03em;margin-bottom:6px;">
                                        {{ $fatura['linha_digitavel'] }}
                                    </div>
                                    @if($barrasSvg !== '')
                                        <div>{!! $barrasSvg !!}</div>
                                    @endif
                                @else
                                    <div style="font-size:8pt;opacity:.78;">Boleto bancário não anexado pelo gateway desta fatura — utilize o QR Code PIX ou o portal financeiro.</div>
                                @endif
                                @break

                            @case('observacoes_legais')
                                <div class="a4-kicker">Instruções & Observações</div>
                                <div style="font-size:8.2pt;line-height:1.45;">{!! $fatura['observacoes'] !== '' ? golden_html($fatura['observacoes']) : 'Fatura emitida automaticamente pelo gateway conectado ao WHMCS.' !!}</div>
                                @break

                            @case('assinatura_rodape')
                                <div class="a4-kicker">Autenticação & Portal do Cliente</div>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                    <div style="font-size:8pt;">
                                        <div><strong>{{ $estilo['emitente_nome'] }}</strong></div>
                                        <div style="opacity:.8;">Emitido em {{ now()->format('d/m/Y H:i') }}</div>
                                        <div style="font-family:monospace;font-size:7.3pt;opacity:.75;">Verificador: #{{ strtoupper(substr(md5((string) $fatura['numero']), 0, 10)) }}</div>
                                    </div>
                                    @if($qrPortalSvg !== '')
                                        <div style="background:#ffffff;padding:3px;border:1px solid {{ $corBorda }};border-radius:4px;flex-shrink:0;">
                                            {!! $qrPortalSvg !!}
                                        </div>
                                    @endif
                                </div>
                                @break
                        @endswitch
                    </section>
                @endforeach
            </div>

            <footer class="a4-rodape">
                {!! golden_html($estilo['rodape_texto'] ?? 'Golden Rastreamento · Fatura A4') !!}
            </footer>
        </div>
@if(empty($modoEmbutido))
    </div>
</body>
</html>
@endif
