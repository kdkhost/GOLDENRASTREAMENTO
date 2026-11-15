<?php

namespace App\Services\Fatura;

use App\Models\Setting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Gera a fatura cirúrgica em HTML A4 (210mm x 297mm) e em binário PDF 1.4
 * respeitando 1:1 a ordenação, larguras (50%/100%), cores, margens e visibilidade
 * configuradas no editor arrasta-e-solta do painel administrativo.
 */
class FaturaPdfService
{
    public const CONFIG_SETTING_KEY = 'fatura_modelo_config';

    private const PT_PER_MM = 2.83464567;

    private const A4_WIDTH_PT = 595.28;

    private const A4_HEIGHT_PT = 841.89;

    /** @var array<int, string> */
    private const ITF_MAP = [
        0 => 'NNWWN',
        1 => 'WNNNW',
        2 => 'NWNNW',
        3 => 'WWNNN',
        4 => 'NNWNW',
        5 => 'WNWNN',
        6 => 'NWWNN',
        7 => 'NNNWW',
        8 => 'WNNWN',
        9 => 'NWNWN',
    ];

    /**
     * Modelo padrão inicial de blocos A4 (arrasta-e-solta) e estilo da folha.
     *
     * @return array{estilo: array<string, mixed>, blocos: array<int, array<string, mixed>>}
     */
    public function configuracaoPadrao(): array
    {
        $empresa = golden_empresa();

        return [
            'estilo' => [
                'emitente_nome' => $empresa['nome'],
                'emitente_documento' => $empresa['documento'],
                'emitente_endereco' => $empresa['endereco_completo'],
                'emitente_contato' => $empresa['contato_completo'],
                'logo_url' => $empresa['logo_url'],
                'cor_primaria' => '#12161a',
                'cor_destaque' => '#e0a33e',
                'cor_fundo_bloco' => '#faf8f2',
                'cor_borda' => '#e2ddd1',
                'cor_texto' => '#1b2127',
                'margem_topo_mm' => 12,
                'margem_lateral_mm' => 12,
                'margem_inferior_mm' => 12,
                'tamanho_fonte_pt' => 9.5,
                'raio_borda_px' => 6,
                'gateway_padrao_modulo' => '',
                'pix_chave_fallback' => '',
                'rodape_texto' => 'Documento emitido eletronicamente pela plataforma '.$empresa['nome'].'. Pagamentos compensados liberam o acesso automaticamente.',
            ],
            'blocos' => [
                [
                    'id' => 'cabecalho_empresa',
                    'nome' => 'Cabeçalho da Empresa & Fatura',
                    'descricao' => 'Identidade Golden, CNPJ/endereço, número da fatura e selo de status.',
                    'ordem' => 1,
                    'largura' => 100,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#12161a',
                    'texto_cor' => '#ffffff',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'dados_cliente',
                    'nome' => 'Dados do Cliente (Sacado)',
                    'descricao' => 'Nome/Razão social, CPF/CNPJ, e-mail e endereço de cobrança.',
                    'ordem' => 2,
                    'largura' => 50,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#faf8f2',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'resumo_cobranca',
                    'nome' => 'Resumo Financeiro & Vencimento',
                    'descricao' => 'Data de emissão, vencimento, subtotal, créditos/impostos e total.',
                    'ordem' => 3,
                    'largura' => 50,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#faf8f2',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'itens_fatura',
                    'nome' => 'Discriminação dos Serviços / Itens',
                    'descricao' => 'Tabela detalhada com quantidade, descrição, valor unitário e subtotal.',
                    'ordem' => 4,
                    'largura' => 100,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#ffffff',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'pagamento_gateway',
                    'nome' => 'Gateway WHMCS & QR Code PIX',
                    'descricao' => 'Gateway ativo do WHMCS, QR Code PIX vetorial e código Copia e Cola.',
                    'ordem' => 5,
                    'largura' => 100,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#faf8f2',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'codigo_barras_boleto',
                    'nome' => 'Linha Digitável & Código de Barras',
                    'descricao' => 'Ficha de compensação Febraban com código de barras ITF-25 vetorial.',
                    'ordem' => 6,
                    'largura' => 100,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#ffffff',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'observacoes_legais',
                    'nome' => 'Instruções & Observações',
                    'descricao' => 'Notas da fatura do WHMCS, juros/multa e orientações ao cliente.',
                    'ordem' => 7,
                    'largura' => 50,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#ffffff',
                    'texto_cor' => '#4b5563',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
                [
                    'id' => 'assinatura_rodape',
                    'nome' => 'Autenticação & Canais de Atendimento',
                    'descricao' => 'Selo eletrônico de emissão, código verificador e link do Portal do Cliente.',
                    'ordem' => 8,
                    'largura' => 50,
                    'visivel' => true,
                    'alinhamento' => 'left',
                    'fundo' => '#faf8f2',
                    'texto_cor' => '#1b2127',
                    'borda' => true,
                    'padding' => 'padrao',
                ],
            ],
        ];
    }

    /**
     * Retorna a configuração atual salva no banco mesclada com o catálogo de blocos.
     *
     * @return array{estilo: array<string, mixed>, blocos: array<int, array<string, mixed>>}
     */
    public function configuracaoAtual(): array
    {
        $padrao = $this->configuracaoPadrao();
        $bruto = Setting::get(self::CONFIG_SETTING_KEY);

        if (! is_string($bruto) || trim($bruto) === '') {
            return $padrao;
        }

        $decodificado = json_decode($bruto, true);
        if (! is_array($decodificado)) {
            return $padrao;
        }

        $estiloSalvo = is_array($decodificado['estilo'] ?? null) ? $decodificado['estilo'] : [];
        $estilo = array_merge($padrao['estilo'], $estiloSalvo);
        $estilo = $this->preencherIdentidadeEmpresaSePlaceholder($estilo, $padrao['estilo']);
        $salvosPorId = collect(is_array($decodificado['blocos'] ?? null) ? $decodificado['blocos'] : [])
            ->filter(fn (mixed $item): bool => is_array($item) && ! empty($item['id']))
            ->keyBy('id');

        $blocos = collect($padrao['blocos'])->map(function (array $blocoPadrao) use ($salvosPorId): array {
            $custom = $salvosPorId->get($blocoPadrao['id']);
            if (! is_array($custom)) {
                return $blocoPadrao;
            }

            return [
                'id' => $blocoPadrao['id'],
                'nome' => trim((string) ($custom['nome'] ?? '')) ?: $blocoPadrao['nome'],
                'descricao' => $blocoPadrao['descricao'],
                'ordem' => (int) ($custom['ordem'] ?? $blocoPadrao['ordem']),
                'largura' => (int) ($custom['largura'] ?? $blocoPadrao['largura']) === 50 ? 50 : 100,
                'visivel' => array_key_exists('visivel', $custom) ? (bool) $custom['visivel'] : (bool) $blocoPadrao['visivel'],
                'alinhamento' => in_array($custom['alinhamento'] ?? '', ['left', 'center', 'right'], true)
                    ? (string) $custom['alinhamento']
                    : $blocoPadrao['alinhamento'],
                'fundo' => $this->higienizarCor((string) ($custom['fundo'] ?? $blocoPadrao['fundo']), $blocoPadrao['fundo']),
                'texto_cor' => $this->higienizarCor((string) ($custom['texto_cor'] ?? $blocoPadrao['texto_cor']), $blocoPadrao['texto_cor']),
                'borda' => array_key_exists('borda', $custom) ? (bool) $custom['borda'] : (bool) $blocoPadrao['borda'],
                'padding' => in_array($custom['padding'] ?? '', ['compacto', 'padrao', 'amplo'], true)
                    ? (string) $custom['padding']
                    : 'padrao',
            ];
        })->sortBy('ordem')->values()->all();

        return [
            'estilo' => $estilo,
            'blocos' => $blocos,
        ];
    }

    /**
     * Preenche campos de identidade do emitente quando ainda estiverem vazios
     * ou usando valores placeholder legados antes de configurar a empresa no painel.
     *
     * @param  array<string, mixed>  $estilo
     * @param  array<string, mixed>  $estiloEmpresaPadrao
     * @return array<string, mixed>
     */
    private function preencherIdentidadeEmpresaSePlaceholder(array $estilo, array $estiloEmpresaPadrao): array
    {
        $placeholders = [
            'emitente_nome' => ['Golden Rastreamento', 'GOLDEN'],
            'emitente_documento' => ['00.000.000/0001-00', '00.000.000/0000-00', 'Não informado'],
            'emitente_endereco' => ['Av. Paulista, 1000'],
            'emitente_contato' => ['disparos@goldenrastreamento.com.br', 'financeiro@goldenrastreamento.com.br', '55119997101403'],
        ];

        foreach ($placeholders as $campo => $marcadores) {
            $atual = trim((string) ($estilo[$campo] ?? ''));
            $padrao = trim((string) ($estiloEmpresaPadrao[$campo] ?? ''));

            if ($padrao === '') {
                continue;
            }

            if ($atual === '') {
                $estilo[$campo] = $padrao;

                continue;
            }

            foreach ($marcadores as $marcador) {
                $ehMatch = $campo === 'emitente_nome'
                    ? strcasecmp($atual, $marcador) === 0
                    : (strcasecmp($atual, $marcador) === 0 || str_contains($atual, $marcador));

                if ($ehMatch) {
                    $estilo[$campo] = $padrao;

                    break;
                }
            }
        }

        return $estilo;
    }

    /**
     * Valida, normaliza e grava o layout arrasta-e-solta no banco de configurações.
     *
     * @param  array<string, mixed>  $dados
     * @return array{estilo: array<string, mixed>, blocos: array<int, array<string, mixed>>}
     */
    public function salvarConfiguracao(array $dados): array
    {
        $padrao = $this->configuracaoPadrao();
        $estiloEntrada = is_array($dados['estilo'] ?? null) ? $dados['estilo'] : [];

        $estiloLimpo = [
            'emitente_nome' => trim((string) ($estiloEntrada['emitente_nome'] ?? $padrao['estilo']['emitente_nome'])) ?: $padrao['estilo']['emitente_nome'],
            'emitente_documento' => trim((string) ($estiloEntrada['emitente_documento'] ?? $padrao['estilo']['emitente_documento'])),
            'emitente_endereco' => trim((string) ($estiloEntrada['emitente_endereco'] ?? $padrao['estilo']['emitente_endereco'])),
            'emitente_contato' => trim((string) ($estiloEntrada['emitente_contato'] ?? $padrao['estilo']['emitente_contato'])),
            'logo_url' => trim((string) ($estiloEntrada['logo_url'] ?? '')),
            'cor_primaria' => $this->higienizarCor((string) ($estiloEntrada['cor_primaria'] ?? ''), $padrao['estilo']['cor_primaria']),
            'cor_destaque' => $this->higienizarCor((string) ($estiloEntrada['cor_destaque'] ?? ''), $padrao['estilo']['cor_destaque']),
            'cor_fundo_bloco' => $this->higienizarCor((string) ($estiloEntrada['cor_fundo_bloco'] ?? ''), $padrao['estilo']['cor_fundo_bloco']),
            'cor_borda' => $this->higienizarCor((string) ($estiloEntrada['cor_borda'] ?? ''), $padrao['estilo']['cor_borda']),
            'cor_texto' => $this->higienizarCor((string) ($estiloEntrada['cor_texto'] ?? ''), $padrao['estilo']['cor_texto']),
            'margem_topo_mm' => max(6, min(25, (int) ($estiloEntrada['margem_topo_mm'] ?? 12))),
            'margem_lateral_mm' => max(6, min(25, (int) ($estiloEntrada['margem_lateral_mm'] ?? 12))),
            'margem_inferior_mm' => max(6, min(25, (int) ($estiloEntrada['margem_inferior_mm'] ?? 12))),
            'tamanho_fonte_pt' => max(8.0, min(12.0, (float) ($estiloEntrada['tamanho_fonte_pt'] ?? 9.5))),
            'raio_borda_px' => max(0, min(14, (int) ($estiloEntrada['raio_borda_px'] ?? 6))),
            'gateway_padrao_modulo' => trim((string) ($estiloEntrada['gateway_padrao_modulo'] ?? '')),
            'pix_chave_fallback' => trim((string) ($estiloEntrada['pix_chave_fallback'] ?? '')),
            'rodape_texto' => trim((string) ($estiloEntrada['rodape_texto'] ?? $padrao['estilo']['rodape_texto'])),
        ];

        $blocosEntrada = collect(is_array($dados['blocos'] ?? null) ? $dados['blocos'] : [])
            ->filter(fn (mixed $item): bool => is_array($item) && ! empty($item['id']))
            ->keyBy('id');

        $ordemFallback = 1;
        $blocosLimpos = collect($padrao['blocos'])->map(function (array $bloco) use ($blocosEntrada, &$ordemFallback): array {
            $entrada = $blocosEntrada->get($bloco['id']);
            if (! is_array($entrada)) {
                $bloco['ordem'] = 100 + $ordemFallback++;

                return $bloco;
            }

            return [
                'id' => $bloco['id'],
                'nome' => trim((string) ($entrada['nome'] ?? '')) ?: $bloco['nome'],
                'descricao' => $bloco['descricao'],
                'ordem' => max(1, (int) ($entrada['ordem'] ?? $ordemFallback++)),
                'largura' => (int) ($entrada['largura'] ?? $bloco['largura']) === 50 ? 50 : 100,
                'visivel' => array_key_exists('visivel', $entrada) ? (bool) $entrada['visivel'] : true,
                'alinhamento' => in_array($entrada['alinhamento'] ?? '', ['left', 'center', 'right'], true)
                    ? (string) $entrada['alinhamento']
                    : 'left',
                'fundo' => $this->higienizarCor((string) ($entrada['fundo'] ?? ''), $bloco['fundo']),
                'texto_cor' => $this->higienizarCor((string) ($entrada['texto_cor'] ?? ''), $bloco['texto_cor']),
                'borda' => array_key_exists('borda', $entrada) ? (bool) $entrada['borda'] : true,
                'padding' => in_array($entrada['padding'] ?? '', ['compacto', 'padrao', 'amplo'], true)
                    ? (string) $entrada['padding']
                    : 'padrao',
            ];
        })->sortBy('ordem')->values()->map(function (array $bloco, int $indice): array {
            $bloco['ordem'] = $indice + 1;

            return $bloco;
        })->all();

        $resultado = [
            'estilo' => $estiloLimpo,
            'blocos' => $blocosLimpos,
        ];

        Setting::set(self::CONFIG_SETTING_KEY, json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $resultado;
    }

    /**
     * Restaura o modelo A4 ao layout padrão de fábrica.
     */
    public function restaurarConfiguracao(): void
    {
        Setting::forget(self::CONFIG_SETTING_KEY);
    }

    /**
     * Fatura realística de demonstração para o editor arrasta-e-solta e testes de impressão.
     *
     * @return array<string, mixed>
     */
    public function faturaExemplo(): array
    {
        return [
            'id' => 8841,
            'numero' => 'GR-2026-8841',
            'status' => 'Unpaid',
            'status_rotulo' => 'Em aberto',
            'emitida' => now()->format('d/m/Y'),
            'vencimento' => now()->addDays(5)->format('d/m/Y'),
            'paga_em' => '',
            'subtotal_cents' => 14990,
            'credito_cents' => 2000,
            'imposto_cents' => 0,
            'total_cents' => 12990,
            'saldo_cents' => 12990,
            'metodo' => 'pix',
            'metodo_rotulo' => 'PIX Instantâneo · Gateway WHMCS',
            'gateway_tipo' => 'pix',
            'url' => route('portal.faturas'),
            'checkout_url' => route('portal.faturas'),
            'pix_copia_cola' => '00020126580014br.gov.bcb.pix0136golden-rastreamento-fatura-88415204000053039865802BR',
            'linha_digitavel' => '23793.38128 60000.000003 00000.000401 1 99990000012990',
            'codigo_barras' => '23791999900000129903381260000000000000000040',
            'nosso_numero' => '09/88410000123-7',
            'observacoes' => 'Mensalidade referente ao monitoramento 24h via satélite, telemetria e chip M2M multioperadora.',
            'cliente' => [
                'nome' => 'Mariana Oliveira Silva',
                'empresa' => 'Transportes Oliveira Ltda',
                'documento' => '12.345.678/0001-99',
                'email' => 'financeiro@oliveiratransportes.com.br',
                'telefone' => '(11) 98765-4321',
                'endereco' => 'Rua Vergueiro, 2245 · Vila Mariana',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => '04101-100',
            ],
            'itens' => [
                [
                    'descricao' => 'Plano Frota Monitoramento 24h + App + Bloqueio Remoto (Placa ABC-1D23)',
                    'quantidade' => 1,
                    'valor_unitario_cents' => 9990,
                    'valor_cents' => 9990,
                ],
                [
                    'descricao' => 'Pacote de Dados M2M Vivo/Claro/TIM + Backup Satelital',
                    'quantidade' => 1,
                    'valor_unitario_cents' => 5000,
                    'valor_cents' => 5000,
                ],
            ],
        ];
    }

    /**
     * Normaliza qualquer estrutura de fatura (WHMCS, Portal ou Demonstração) para o motor A4/PDF.
     *
     * @param  array<string, mixed>  $bruta
     * @return array<string, mixed>
     */
    public function normalizarFatura(array $bruta): array
    {
        $padrao = $this->faturaExemplo();
        $clienteBruto = is_array($bruta['cliente'] ?? null) ? $bruta['cliente'] : [];

        $itens = collect(is_array($bruta['itens'] ?? null) ? $bruta['itens'] : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'descricao' => trim((string) ($item['descricao'] ?? 'Serviço de rastreamento')),
                'quantidade' => max(1, (int) ($item['quantidade'] ?? 1)),
                'valor_unitario_cents' => (int) ($item['valor_unitario_cents'] ?? $item['valor_cents'] ?? 0),
                'valor_cents' => (int) ($item['valor_cents'] ?? 0),
            ])
            ->values()
            ->all();

        $totalCents = (int) ($bruta['total_cents'] ?? 0);
        if ($itens === [] && $totalCents > 0) {
            $itens = [[
                'descricao' => 'Serviço financeiro Golden Rastreamento · Fatura #'.($bruta['numero'] ?? $bruta['id'] ?? ''),
                'quantidade' => 1,
                'valor_unitario_cents' => $totalCents,
                'valor_cents' => $totalCents,
            ]];
        }

        $codigoBarras = preg_replace('/\D/', '', (string) ($bruta['codigo_barras'] ?? '')) ?? '';
        $linhaDigitavel = trim((string) ($bruta['linha_digitavel'] ?? ''));
        if ($codigoBarras === '' && $linhaDigitavel !== '') {
            $digitosLinha = preg_replace('/\D/', '', $linhaDigitavel) ?? '';
            if (strlen($digitosLinha) >= 44) {
                $codigoBarras = substr($digitosLinha, 0, 44);
            }
        }

        return [
            'id' => (int) ($bruta['id'] ?? $padrao['id']),
            'numero' => (string) ($bruta['numero'] ?? $bruta['id'] ?? $padrao['numero']),
            'status' => (string) ($bruta['status'] ?? 'Unpaid'),
            'status_rotulo' => (string) ($bruta['status_rotulo'] ?? $this->rotuloStatus((string) ($bruta['status'] ?? 'Unpaid'))),
            'emitida' => $this->formatarData($bruta['emitida'] ?? null),
            'vencimento' => $this->formatarData($bruta['vencimento'] ?? null),
            'paga_em' => $this->formatarData($bruta['paga_em'] ?? null),
            'subtotal_cents' => (int) ($bruta['subtotal_cents'] ?? $totalCents),
            'credito_cents' => (int) ($bruta['credito_cents'] ?? $bruta['credito_aplicado_cents'] ?? 0),
            'imposto_cents' => (int) ($bruta['imposto_cents'] ?? 0),
            'total_cents' => $totalCents,
            'saldo_cents' => (int) ($bruta['saldo_cents'] ?? $totalCents),
            'metodo' => (string) ($bruta['metodo'] ?? ''),
            'metodo_rotulo' => (string) ($bruta['metodo_rotulo'] ?? ($bruta['metodo'] ?: 'Gateway WHMCS')),
            'gateway_tipo' => (string) ($bruta['gateway_tipo'] ?? 'gateway'),
            'url' => isset($bruta['url']) ? trim((string) $bruta['url']) : route('portal.faturas'),
            'checkout_url' => isset($bruta['checkout_url'])
                ? trim((string) $bruta['checkout_url'])
                : (trim((string) ($bruta['url'] ?? '')) !== '' ? trim((string) $bruta['url']) : route('portal.faturas')),
            'pix_copia_cola' => trim((string) ($bruta['pix_copia_cola'] ?? '')),
            'linha_digitavel' => $linhaDigitavel,
            'codigo_barras' => $codigoBarras,
            'nosso_numero' => trim((string) ($bruta['nosso_numero'] ?? '')),
            'observacoes' => trim((string) ($bruta['observacoes'] ?? '')),
            'cliente' => [
                'nome' => trim((string) ($clienteBruto['nome'] ?? 'Cliente Golden')),
                'empresa' => trim((string) ($clienteBruto['empresa'] ?? '')),
                'documento' => trim((string) ($clienteBruto['documento'] ?? '')),
                'email' => trim((string) ($clienteBruto['email'] ?? '')),
                'telefone' => trim((string) ($clienteBruto['telefone'] ?? '')),
                'endereco' => trim((string) ($clienteBruto['endereco'] ?? '')),
                'cidade' => trim((string) ($clienteBruto['cidade'] ?? '')),
                'uf' => trim((string) ($clienteBruto['uf'] ?? '')),
                'cep' => trim((string) ($clienteBruto['cep'] ?? '')),
            ],
            'itens' => $itens,
        ];
    }

    /**
     * Gera SVG vetorial puro para QR Code (PIX Copia e Cola ou link de pagamento).
     */
    public function gerarQrCodeSvg(string $conteudo, int $tamanhoPx = 112): string
    {
        if (trim($conteudo) === '') {
            return '';
        }

        $qr = Encoder::encode($conteudo, ErrorCorrectionLevel::M());
        $matriz = $qr->getMatrix();
        $largura = $matriz->getWidth();
        $altura = $matriz->getHeight();
        $rows = $matriz->getArray();

        $paths = '';
        for ($y = 0; $y < $altura; $y++) {
            for ($x = 0; $x < $largura; $x++) {
                if ((int) $rows[$y][$x] === 1) {
                    $paths .= "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $vb = $largura + 2;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="-1 -1 %d %d" shape-rendering="crispEdges"><rect x="-1" y="-1" width="%d" height="%d" fill="#ffffff"/><path d="%s" fill="#12161a"/></svg>',
            $tamanhoPx,
            $tamanhoPx,
            $vb,
            $vb,
            $vb,
            $vb,
            $paths
        );
    }

    /**
     * Gera SVG vetorial padrão Febraban Interleaved 2-of-5 (ITF-25) para código de barras bancário.
     */
    public function gerarCodigoBarrasSvg(string $digitos): string
    {
        $barras = $this->codificarBarrasItf25($digitos);
        if ($barras === []) {
            return '';
        }

        $x = 6;
        $rects = '';
        foreach ($barras as [$preto, $larguraBarra]) {
            if ($preto) {
                $rects .= sprintf('<rect x="%.2F" y="0" width="%.2F" height="44" fill="#12161a"/>', $x, $larguraBarra);
            }
            $x += $larguraBarra;
        }
        $larguraTotal = $x + 6;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2F 44" preserveAspectRatio="none" style="width:100%%;height:44px;display:block;background:#ffffff;">%s</svg>',
            $larguraTotal,
            $rects
        );
    }

    /**
     * Renderiza o HTML cirúrgico A4 pronto para visualização ao vivo no builder ou impressão direta.
     *
     * @param  array<string, mixed>|null  $faturaBruta
     * @param  array<string, mixed>|null  $configOverride
     */
    /**
     * Aplica preferências globais de gateway/PIX do estúdio quando a fatura ainda usa placeholders de demonstração.
     *
     * @param  array<string, mixed>  $fatura
     * @param  array<string, mixed>  $estilo
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEstiloNaFatura(array $fatura, array $estilo): array
    {
        $exemplo = $this->faturaExemplo();

        $pixFallback = trim((string) ($estilo['pix_chave_fallback'] ?? ''));
        $pixExemplo = trim((string) ($exemplo['pix_copia_cola'] ?? ''));
        $pixAtual = trim((string) ($fatura['pix_copia_cola'] ?? ''));

        if ($pixFallback !== '' && ($pixAtual === '' || $pixAtual === $pixExemplo)) {
            $fatura['pix_copia_cola'] = $pixFallback;
        }

        $gatewayModulo = trim((string) ($estilo['gateway_padrao_modulo'] ?? ''));
        $metodoExemplo = trim((string) ($exemplo['metodo_rotulo'] ?? ''));
        $metodoAtual = trim((string) ($fatura['metodo_rotulo'] ?? ''));

        if ($gatewayModulo !== '' && ($metodoAtual === '' || $metodoAtual === 'Gateway WHMCS' || $metodoAtual === $metodoExemplo)) {
            $rotuloGateway = trim((string) ($estilo['gateway_padrao_rotulo'] ?? ''));
            $fatura['metodo_rotulo'] = $rotuloGateway !== ''
                ? $rotuloGateway
                : 'Gateway WHMCS · '.$gatewayModulo;
        }

        return $fatura;
    }

    /**
     * Renderiza HTML cirúrgico A4 1:1 para tela, impressão ou geração de PDF.
     */
    public function renderizarHtmlCirurgico(
        ?array $faturaBruta = null,
        ?array $configOverride = null,
        bool $modoEmbutido = false
    ): string {
        $config = $configOverride ?? $this->configuracaoAtual();
        $fatura = $this->normalizarFatura($faturaBruta ?? $this->faturaExemplo());
        $fatura = $this->aplicarPreferenciasEstiloNaFatura($fatura, $config['estilo']);

        $pixConteudo = $fatura['pix_copia_cola'] !== ''
            ? $fatura['pix_copia_cola']
            : (($config['estilo']['pix_chave_fallback'] ?? '') !== '' ? (string) $config['estilo']['pix_chave_fallback'] : $fatura['checkout_url']);

        $qrSvg = $pixConteudo !== '' ? $this->gerarQrCodeSvg($pixConteudo, 98) : '';
        $qrPortalSvg = $this->gerarQrCodeSvg($fatura['checkout_url'] !== '' ? $fatura['checkout_url'] : url('/portal'), 68);
        $barrasSvg = $this->gerarCodigoBarrasSvg($fatura['codigo_barras']);

        return view('admin.faturas-modelo.documento-a4', [
            'config' => $config,
            'estilo' => $config['estilo'],
            'blocos' => collect($config['blocos'])->where('visivel', true)->sortBy('ordem')->values()->all(),
            'fatura' => $fatura,
            'empresa' => golden_empresa(),
            'pixConteudo' => $pixConteudo,
            'qrSvg' => $qrSvg,
            'qrPortalSvg' => $qrPortalSvg,
            'barrasSvg' => $barrasSvg,
            'modoEmbutido' => $modoEmbutido,
        ])->render();
    }

    /**
     * Gera o documento PDF 1.4 (`%PDF-1.4`) nativo e cirúrgico seguindo 1:1
     * os blocos visíveis, larguras (50%/100%), ordenação, margens e cores do builder.
     *
     * @param  array<string, mixed>|null  $faturaBruta
     * @param  array<string, mixed>|null  $configOverride
     */
    public function gerarPdfBinario(?array $faturaBruta = null, ?array $configOverride = null): string
    {
        $config = $configOverride ?? $this->configuracaoAtual();
        $estilo = $config['estilo'];
        $fatura = $this->normalizarFatura($faturaBruta ?? $this->faturaExemplo());
        $fatura = $this->aplicarPreferenciasEstiloNaFatura($fatura, $estilo);
        $blocos = collect($config['blocos'])->where('visivel', true)->sortBy('ordem')->values()->all();

        $empresa = golden_empresa();
        $blocoCabecalho = collect($blocos)->firstWhere('id', 'cabecalho_empresa');
        $headerFundoHex = is_array($blocoCabecalho) ? (string) ($blocoCabecalho['fundo'] ?? '#14171a') : '#14171a';
        $logoXObject = $this->prepararLogoPdfXObject((string) ($empresa['logo_caminho'] ?? ''), $headerFundoHex);

        $marginLeft = (float) $estilo['margem_lateral_mm'] * self::PT_PER_MM;
        $marginTop = (float) $estilo['margem_topo_mm'] * self::PT_PER_MM;
        $marginBottom = (float) $estilo['margem_inferior_mm'] * self::PT_PER_MM;
        $usableWidth = self::A4_WIDTH_PT - ($marginLeft * 2);
        $gutterPt = 10.0;

        $linhas = $this->agruparBlocosEmLinhas($blocos);

        $paginasStreams = [];
        $stream = '';
        $cursorY = self::A4_HEIGHT_PT - $marginTop;

        $novaPagina = function () use (&$paginasStreams, &$stream, &$cursorY, $marginTop): void {
            if ($stream !== '') {
                $paginasStreams[] = $stream;
            }
            $stream = '';
            $cursorY = self::A4_HEIGHT_PT - $marginTop;
        };

        foreach ($linhas as $linha) {
            if (count($linha) === 2) {
                $colWidth = ($usableWidth - $gutterPt) / 2.0;
                $alt1 = $this->calcularAlturaBloco($linha[0], $fatura, $colWidth);
                $alt2 = $this->calcularAlturaBloco($linha[1], $fatura, $colWidth);
                $alturaLinha = max($alt1, $alt2);

                if (($cursorY - $alturaLinha) < $marginBottom) {
                    $novaPagina();
                }

                $stream .= $this->desenharBlocoPdf($linha[0], $estilo, $fatura, $marginLeft, $cursorY, $colWidth, $alturaLinha, $logoXObject);
                $stream .= $this->desenharBlocoPdf($linha[1], $estilo, $fatura, $marginLeft + $colWidth + $gutterPt, $cursorY, $colWidth, $alturaLinha, $logoXObject);
                $cursorY -= ($alturaLinha + 10.0);
            } else {
                $bloco = $linha[0];
                $alturaBloco = $this->calcularAlturaBloco($bloco, $fatura, $usableWidth);

                if (($cursorY - $alturaBloco) < $marginBottom) {
                    $novaPagina();
                }

                $stream .= $this->desenharBlocoPdf($bloco, $estilo, $fatura, $marginLeft, $cursorY, $usableWidth, $alturaBloco, $logoXObject);
                $cursorY -= ($alturaBloco + 10.0);
            }
        }

        // Rodapé institucional discreto em toda última página
        $rodapeY = max(18.0, $marginBottom - 12.0);
        $rodapePuro = golden_texto_puro((string) ($estilo['rodape_texto'] ?? ''));
        $partesRodape = array_filter([
            $rodapePuro,
            (string) ($estilo['emitente_nome'] ?? ''),
            (string) ($estilo['emitente_documento'] ?? ''),
            (string) ($estilo['emitente_contato'] ?? ''),
        ], static fn (string $v): bool => trim($v) !== '');
        $linhaRodape = mb_strimwidth(implode(' · ', array_unique($partesRodape)), 0, 125, '...');
        $stream .= $this->pdfTexto($marginLeft, $rodapeY, 7.2, '#6B7280', $linhaRodape, false);
        $paginasStreams[] = $stream;

        return $this->empacotarDocumentoPdf14($paginasStreams, $logoXObject);
    }

    /**
     * Agrupa blocos visíveis em linhas de 1 coluna (100%) ou 2 colunas pareadas (50% + 50%).
     *
     * @param  array<int, array<string, mixed>>  $blocos
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function agruparBlocosEmLinhas(array $blocos): array
    {
        $linhas = [];
        $parAtual = null;

        foreach ($blocos as $bloco) {
            if ((int) ($bloco['largura'] ?? 100) === 50) {
                if ($parAtual === null) {
                    $parAtual = $bloco;
                } else {
                    $linhas[] = [$parAtual, $bloco];
                    $parAtual = null;
                }
            } else {
                if ($parAtual !== null) {
                    $linhas[] = [$parAtual];
                    $parAtual = null;
                }
                $linhas[] = [$bloco];
            }
        }

        if ($parAtual !== null) {
            $linhas[] = [$parAtual];
        }

        return $linhas;
    }

    private function calcularAlturaBloco(array $bloco, array $fatura, float $larguraPt): float
    {
        $padExtra = match ($bloco['padding'] ?? 'padrao') {
            'compacto' => -8.0,
            'amplo' => 10.0,
            default => 0.0,
        };

        return match ($bloco['id']) {
            'cabecalho_empresa' => 84.0 + $padExtra,
            'dados_cliente' => 104.0 + $padExtra,
            'resumo_cobranca' => 104.0 + $padExtra,
            'itens_fatura' => 54.0 + (max(1, count($fatura['itens'])) * 19.0) + $padExtra,
            'pagamento_gateway' => 112.0 + $padExtra,
            'codigo_barras_boleto' => ($fatura['linha_digitavel'] !== '' || $fatura['codigo_barras'] !== '' ? 88.0 : 52.0) + $padExtra,
            'observacoes_legais' => ($larguraPt < 300 ? 82.0 : 72.0) + $padExtra,
            'assinatura_rodape' => 82.0 + $padExtra,
            default => 68.0 + $padExtra,
        };
    }

    /**
     * @param  array<string, mixed>|null  $logoXObject
     */
    private function desenharBlocoPdf(
        array $bloco,
        array $estilo,
        array $fatura,
        float $x,
        float $topY,
        float $width,
        float $height,
        ?array $logoXObject = null
    ): string {
        $bottomY = $topY - $height;
        $fundo = (string) ($bloco['fundo'] ?? '#ffffff');
        $textoCor = (string) ($bloco['texto_cor'] ?? '#1b2127');
        $bordaCor = (string) ($estilo['cor_borda'] ?? '#e2ddd1');
        $destaque = (string) ($estilo['cor_destaque'] ?? '#e0a33e');
        $temBorda = (bool) ($bloco['borda'] ?? true);

        $out = $this->pdfRetangulo($x, $bottomY, $width, $height, $fundo, $temBorda ? $bordaCor : null, 0.75);
        $pad = match ($bloco['padding'] ?? 'padrao') {
            'compacto' => 8.0,
            'amplo' => 16.0,
            default => 12.0,
        };

        $curY = $topY - $pad - 9.0;

        switch ($bloco['id']) {
            case 'cabecalho_empresa':
                $out .= $this->pdfRetangulo($x, $topY - 3.5, $width, 3.5, $destaque, null, 0);

                $textoInicioX = $x + $pad;
                if (is_array($logoXObject) && ($logoXObject['width'] ?? 0) > 0 && ($logoXObject['height'] ?? 0) > 0) {
                    $maxLogoW = min(84.0, $width * 0.22);
                    $maxLogoH = 38.0;
                    $scale = min($maxLogoW / (float) $logoXObject['width'], $maxLogoH / (float) $logoXObject['height']);
                    $drawW = round((float) $logoXObject['width'] * $scale, 2);
                    $drawH = round((float) $logoXObject['height'] * $scale, 2);
                    $imgX = $x + $pad;
                    $imgY = ($topY - $pad) - $drawH - 2.0;
                    $out .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $drawW, $drawH, $imgX, $imgY);
                    $textoInicioX = $imgX + $drawW + 10.0;
                }

                $colDirX = $x + $width - 172.0;
                $maxCharsEsq = max(32, (int) floor(($colDirX - $textoInicioX - 10.0) / 4.2));
                $docEmitente = trim((string) ($estilo['emitente_documento'] ?? ''));
                $endEmitente = mb_strimwidth(trim((string) ($estilo['emitente_endereco'] ?? '')), 0, $maxCharsEsq, '...');
                $contEmitente = mb_strimwidth(trim((string) ($estilo['emitente_contato'] ?? '')), 0, $maxCharsEsq, '...');

                $out .= $this->pdfTexto($textoInicioX, $curY - 3.0, 14.0, $destaque, (string) $estilo['emitente_nome'], true);
                if ($docEmitente !== '') {
                    $out .= $this->pdfTexto($textoInicioX, $curY - 18.0, 8.2, $textoCor, 'CNPJ/Doc: '.$docEmitente, false);
                }
                if ($endEmitente !== '') {
                    $out .= $this->pdfTexto($textoInicioX, $curY - 30.0, 8.0, $textoCor, $endEmitente, false);
                }
                if ($contEmitente !== '') {
                    $out .= $this->pdfTexto($textoInicioX, $curY - 42.0, 7.8, $textoCor, $contEmitente, false);
                }

                $out .= $this->pdfTexto($colDirX, $curY - 3.0, 13.0, '#ffffff', 'FATURA #'.$fatura['numero'], true);
                $out .= $this->pdfTexto($colDirX, $curY - 18.0, 9.0, $destaque, 'Status: '.$fatura['status_rotulo'], true);
                $out .= $this->pdfTexto($colDirX, $curY - 31.0, 8.5, '#e5e7eb', 'Vencimento: '.$fatura['vencimento'], false);
                $out .= $this->pdfTexto($colDirX, $curY - 43.0, 10.0, '#ffffff', 'Total: '.$this->formatarMoeda($fatura['total_cents']), true);
                break;

            case 'dados_cliente':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'DADOS DO SACADO / CLIENTE', true);
                $cli = $fatura['cliente'];
                $out .= $this->pdfTexto($x + $pad, $curY - 16.0, 10.0, $textoCor, $cli['nome'] !== '' ? $cli['nome'] : 'Cliente Golden', true);
                if ($cli['empresa'] !== '') {
                    $out .= $this->pdfTexto($x + $pad, $curY - 28.5, 8.2, $textoCor, $cli['empresa'], false);
                }
                $doc = $cli['documento'] !== '' ? 'CPF/CNPJ: '.$cli['documento'] : 'Documento não informado';
                $out .= $this->pdfTexto($x + $pad, $curY - 41.0, 8.2, $textoCor, $doc, false);
                $out .= $this->pdfTexto($x + $pad, $curY - 53.0, 8.2, $textoCor, 'E-mail: '.($cli['email'] ?: '-'), false);
                $end = trim($cli['endereco'].' '.$cli['cidade'].($cli['uf'] !== '' ? '/'.$cli['uf'] : '').' '.$cli['cep']);
                $out .= $this->pdfTexto($x + $pad, $curY - 65.5, 7.8, $textoCor, mb_strimwidth($end ?: 'Endereço cadastrado no portal', 0, 56, '...'), false);
                break;

            case 'resumo_cobranca':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'RESUMO DA COBRANÇA', true);
                $out .= $this->pdfTexto($x + $pad, $curY - 17.0, 8.5, $textoCor, 'Emissão: '.$fatura['emitida'], false);
                $out .= $this->pdfTexto($x + $pad, $curY - 30.0, 8.5, $textoCor, 'Vencimento: '.$fatura['vencimento'], true);
                $out .= $this->pdfTexto($x + $pad, $curY - 43.0, 8.5, $textoCor, 'Subtotal: '.$this->formatarMoeda($fatura['subtotal_cents']), false);
                $out .= $this->pdfTexto($x + $pad, $curY - 56.0, 8.5, $textoCor, 'Créditos/Desc.: '.$this->formatarMoeda($fatura['credito_cents']), false);
                $out .= $this->pdfTexto($x + $pad, $curY - 72.0, 11.5, $destaque, 'TOTAL: '.$this->formatarMoeda($fatura['total_cents']), true);
                break;

            case 'itens_fatura':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'ITENS E SERVIÇOS DISCRIMINADOS', true);
                $headerY = $curY - 14.0;
                $out .= $this->pdfRetangulo($x + $pad, $headerY - 14.0, $width - ($pad * 2), 16.0, '#12161a', null, 0);
                $out .= $this->pdfTexto($x + $pad + 6.0, $headerY - 9.5, 8.0, '#ffffff', 'Descrição do Serviço', true);
                $out .= $this->pdfTexto($x + $width - $pad - 125.0, $headerY - 9.5, 8.0, '#ffffff', 'Qtd', true);
                $out .= $this->pdfTexto($x + $width - $pad - 68.0, $headerY - 9.5, 8.0, '#e0a33e', 'Valor', true);

                $itemY = $headerY - 28.0;
                foreach ($fatura['itens'] as $idx => $item) {
                    if ($idx % 2 === 1) {
                        $out .= $this->pdfRetangulo($x + $pad, $itemY - 5.0, $width - ($pad * 2), 17.5, '#faf8f2', null, 0);
                    }
                    $desc = mb_strimwidth((string) ($item['descricao'] ?? ''), 0, 74, '...');
                    $out .= $this->pdfTexto($x + $pad + 6.0, $itemY, 8.3, $textoCor, $desc, false);
                    $out .= $this->pdfTexto($x + $width - $pad - 122.0, $itemY, 8.3, $textoCor, (string) ($item['quantidade'] ?? 1), false);
                    $out .= $this->pdfTexto($x + $width - $pad - 68.0, $itemY, 8.3, $textoCor, $this->formatarMoeda((int) ($item['valor_cents'] ?? 0)), true);
                    $itemY -= 19.0;
                }
                break;

            case 'pagamento_gateway':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'GATEWAY WHMCS & PAGAMENTO DIGITAL', true);
                $out .= $this->pdfTexto($x + $pad, $curY - 16.0, 9.5, $textoCor, 'Gateway: '.$fatura['metodo_rotulo'], true);

                $pixPayload = $fatura['pix_copia_cola'] !== ''
                    ? $fatura['pix_copia_cola']
                    : (($estilo['pix_chave_fallback'] ?? '') !== '' ? (string) $estilo['pix_chave_fallback'] : $fatura['checkout_url']);

                if ($pixPayload !== '') {
                    $qrSize = 72.0;
                    $qrX = $x + $width - $pad - $qrSize;
                    $qrTopY = $topY - $pad - 4.0;
                    $out .= $this->desenharQrCodePdf($pixPayload, $qrX, $qrTopY, $qrSize);
                    $out .= $this->pdfTexto($x + $pad, $curY - 32.0, 8.0, $textoCor, 'PIX Copia e Cola / Chave:', true);
                    $out .= $this->pdfTexto($x + $pad, $curY - 44.0, 7.2, $textoCor, mb_strimwidth($pixPayload, 0, 74, ''), false);
                    if (strlen($pixPayload) > 74) {
                        $out .= $this->pdfTexto($x + $pad, $curY - 54.0, 7.2, $textoCor, mb_strimwidth(substr($pixPayload, 74), 0, 74, '...'), false);
                    }
                }
                if ($fatura['checkout_url'] !== '') {
                    $out .= $this->pdfTexto($x + $pad, $curY - 70.0, 7.6, $textoCor, 'Link Checkout: '.mb_strimwidth($fatura['checkout_url'], 0, 70, '...'), false);
                }
                break;

            case 'codigo_barras_boleto':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'BOLETO BANCÁRIO / LINHA DIGITÁVEL', true);
                $linha = $fatura['linha_digitavel'] !== '' ? $fatura['linha_digitavel'] : 'Disponível para liquidação via PIX ou Portal Financeiro';
                $out .= $this->pdfTexto($x + $pad, $curY - 15.0, 9.0, $textoCor, $linha, true);
                if ($fatura['codigo_barras'] !== '') {
                    $out .= $this->desenharCodigoBarrasPdf($fatura['codigo_barras'], $x + $pad, $curY - 56.0, 32.0);
                }
                break;

            case 'observacoes_legais':
                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'INSTRUÇÕES & OBSERVAÇÕES', true);
                $obs = $fatura['observacoes'] !== '' ? golden_texto_puro($fatura['observacoes']) : 'Fatura emitida pelo sistema financeiro integrado.';
                $linhasObs = $this->quebrarLinhasTexto($obs, $width < 300 ? 44 : 92, 3);
                foreach ($linhasObs as $idx => $linhaObs) {
                    $out .= $this->pdfTexto($x + $pad, $curY - 16.0 - ($idx * 12.0), 8.0, $textoCor, $linhaObs, false);
                }
                break;

            case 'assinatura_rodape':
                $docRodape = trim((string) ($estilo['emitente_documento'] ?? ''));
                $emissorLinha = 'Emissor: '.$estilo['emitente_nome'].($docRodape !== '' ? ' · CNPJ/Doc: '.$docRodape : '');
                $dadosEmpresaLinha = implode(' · ', array_filter([
                    trim((string) ($estilo['emitente_endereco'] ?? '')),
                    trim((string) ($estilo['emitente_contato'] ?? '')),
                ], static fn (string $v): bool => $v !== ''));

                $out .= $this->pdfTexto($x + $pad, $curY, 8.5, $destaque, 'AUTENTICAÇÃO & PORTAL DO CLIENTE', true);
                $out .= $this->pdfTexto($x + $pad, $curY - 14.0, 8.0, $textoCor, mb_strimwidth($emissorLinha, 0, 95, '...'), true);
                if ($dadosEmpresaLinha !== '') {
                    $out .= $this->pdfTexto($x + $pad, $curY - 27.0, 7.6, $textoCor, mb_strimwidth($dadosEmpresaLinha, 0, 100, '...'), false);
                }
                $out .= $this->pdfTexto($x + $pad, $curY - 40.0, 7.5, $textoCor, 'Emitido em '.now()->format('d/m/Y H:i').' · Hash #'.strtoupper(substr(md5((string) $fatura['numero']), 0, 10)).' · /portal/faturas', false);
                break;
        }

        return $out;
    }

    private function desenharQrCodePdf(string $conteudo, float $x, float $topY, float $sizePt): string
    {
        $qr = Encoder::encode($conteudo, ErrorCorrectionLevel::M());
        $matriz = $qr->getMatrix();
        $n = $matriz->getWidth();
        $rows = $matriz->getArray();
        $cell = $sizePt / max(1, $n);

        $out = $this->pdfRetangulo($x - 2.0, $topY - $sizePt - 2.0, $sizePt + 4.0, $sizePt + 4.0, '#ffffff', '#e5e7eb', 0.4);
        $out .= "0 0 0 rg\n";

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ((int) $rows[$r][$c] === 1) {
                    $px = $x + ($c * $cell);
                    $py = $topY - (($r + 1) * $cell);
                    $out .= sprintf("%.2F %.2F %.2F %.2F re f\n", $px, $py, $cell, $cell);
                }
            }
        }

        return $out;
    }

    private function desenharCodigoBarrasPdf(string $digitos, float $x, float $bottomY, float $heightPt): string
    {
        $barras = $this->codificarBarrasItf25($digitos);
        if ($barras === []) {
            return '';
        }

        $out = "0 0 0 rg\n";
        $curX = $x;
        foreach ($barras as [$preto, $larguraUnidades]) {
            $larguraPt = $larguraUnidades * 0.92;
            if ($preto) {
                $out .= sprintf("%.2F %.2F %.2F %.2F re f\n", $curX, $bottomY, $larguraPt, $heightPt);
            }
            $curX += $larguraPt;
        }

        return $out;
    }

    /**
     * Codifica cadeia numérica no padrão Interleaved 2-of-5 (ITF-25).
     *
     * @return array<int, array{0: bool, 1: float}>
     */
    private function codificarBarrasItf25(string $digitos): array
    {
        $limpos = preg_replace('/\D/', '', $digitos) ?? '';
        if (strlen($limpos) < 4) {
            return [];
        }

        if (strlen($limpos) % 2 !== 0) {
            $limpos = '0'.$limpos;
        }

        $resultado = [
            [true, 1.0],
            [false, 1.0],
            [true, 1.0],
            [false, 1.0],
        ];

        $len = strlen($limpos);
        for ($i = 0; $i < $len; $i += 2) {
            $d1 = (int) $limpos[$i];
            $d2 = (int) $limpos[$i + 1];
            $p1 = self::ITF_MAP[$d1];
            $p2 = self::ITF_MAP[$d2];

            for ($b = 0; $b < 5; $b++) {
                $resultado[] = [true, $p1[$b] === 'W' ? 2.6 : 1.0];
                $resultado[] = [false, $p2[$b] === 'W' ? 2.6 : 1.0];
            }
        }

        $resultado[] = [true, 2.6];
        $resultado[] = [false, 1.0];
        $resultado[] = [true, 1.0];

        return $resultado;
    }

    /**
     * Converte a logo da empresa em um Image XObject RGB compatível com PDF 1.4,
     * achatando eventuais transparências sobre a cor de fundo do cabeçalho.
     *
     * @return array{width:int,height:int,data:string,filter:string}|null
     */
    private function prepararLogoPdfXObject(string $caminhoArquivo, string $fundoHex): ?array
    {
        if ($caminhoArquivo === '' || ! is_file($caminhoArquivo) || ! is_readable($caminhoArquivo)) {
            return null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $bytes = @file_get_contents($caminhoArquivo);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        if (str_ends_with(strtolower($caminhoArquivo), '.svg') || str_contains(strtolower(substr($bytes, 0, 256)), '<svg')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($bytes);
            if (! $src) {
                return null;
            }

            $origW = imagesx($src);
            $origH = imagesy($src);
            if ($origW <= 0 || $origH <= 0) {
                imagedestroy($src);

                return null;
            }

            $maxDim = 180;
            $scale = min(1.0, $maxDim / max($origW, $origH));
            $dstW = max(1, (int) round($origW * $scale));
            $dstH = max(1, (int) round($origH * $scale));

            $dst = imagecreatetruecolor($dstW, $dstH);
            if (! $dst) {
                imagedestroy($src);

                return null;
            }

            $limpo = ltrim(trim($fundoHex), '#');
            if (strlen($limpo) === 3) {
                $limpo = $limpo[0].$limpo[0].$limpo[1].$limpo[1].$limpo[2].$limpo[2];
            }
            $hexValido = strlen($limpo) === 6 && ctype_xdigit($limpo);
            $bgR = $hexValido ? (int) hexdec(substr($limpo, 0, 2)) : 20;
            $bgG = $hexValido ? (int) hexdec(substr($limpo, 2, 2)) : 23;
            $bgB = $hexValido ? (int) hexdec(substr($limpo, 4, 2)) : 26;

            $bgColor = imagecolorallocate($dst, $bgR, $bgG, $bgB);
            imagefill($dst, 0, 0, $bgColor);
            imagealphablending($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $origW, $origH);
            imagedestroy($src);

            $rgb = '';
            for ($y = 0; $y < $dstH; $y++) {
                for ($x = 0; $x < $dstW; $x++) {
                    $colorIndex = imagecolorat($dst, $x, $y);
                    $rgb .= chr(($colorIndex >> 16) & 0xFF)
                        .chr(($colorIndex >> 8) & 0xFF)
                        .chr($colorIndex & 0xFF);
                }
            }
            imagedestroy($dst);

            $comprimido = function_exists('gzcompress') ? @gzcompress($rgb, 6) : false;
            $usaFlate = is_string($comprimido) && $comprimido !== '';

            return [
                'width' => $dstW,
                'height' => $dstH,
                'data' => $usaFlate ? $comprimido : $rgb,
                'filter' => $usaFlate ? '/FlateDecode' : '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Empacota streams em um documento PDF 1.4 válido com xref e trailer.
     *
     * @param  array<int, string>  $paginasStreams
     * @param  array{width:int,height:int,data:string,filter:string}|null  $logoXObject
     */
    private function empacotarDocumentoPdf14(array $paginasStreams, ?array $logoXObject = null): string
    {
        $objetos = [];

        // 1: Catalog, 2: Pages, 3: Helvetica Regular, 4: Helvetica Bold
        $objetos[1] = '';
        $objetos[2] = '';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kidsRefs = [];
        $proxId = 5;
        $xObjectResource = '';

        if (is_array($logoXObject) && ! empty($logoXObject['data'])) {
            $imageId = $proxId++;
            $filtroDict = ! empty($logoXObject['filter']) ? ' /Filter '.$logoXObject['filter'] : '';
            $imgData = (string) $logoXObject['data'];
            $objetos[$imageId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8%s /Length %d >>\nstream\n%s\nendstream",
                (int) $logoXObject['width'],
                (int) $logoXObject['height'],
                $filtroDict,
                strlen($imgData),
                $imgData
            );
            $xObjectResource = " /XObject << /Im1 {$imageId} 0 R >>";
        }

        foreach ($paginasStreams as $stream) {
            $contentId = $proxId++;
            $pageId = $proxId++;

            $objetos[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objetos[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>%s >> /Contents %d 0 R >>',
                self::A4_WIDTH_PT,
                self::A4_HEIGHT_PT,
                $xObjectResource,
                $contentId
            );
            $kidsRefs[] = "{$pageId} 0 R";
        }

        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $kidsRefs).'] /Count '.count($kidsRefs).' >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objetos as $id => $conteudo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$conteudo}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjetos = count($objetos) + 1;
        $pdf .= "xref\n0 {$totalObjetos}\n0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjetos; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$totalObjetos} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function pdfRetangulo(
        float $x,
        float $y,
        float $w,
        float $h,
        string $fillHex,
        ?string $strokeHex,
        float $lineWidth
    ): string {
        [$fr, $fg, $fb] = $this->hexParaRgbFloat($fillHex);
        if ($strokeHex !== null) {
            [$sr, $sg, $sb] = $this->hexParaRgbFloat($strokeHex);

            return sprintf(
                "%.3F %.3F %.3F rg %.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re B\n",
                $fr, $fg, $fb, $sr, $sg, $sb, max(0.3, $lineWidth), $x, $y, $w, $h
            );
        }

        return sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $fr, $fg, $fb, $x, $y, $w, $h);
    }

    private function pdfTexto(float $x, float $y, float $fontSize, string $corHex, string $texto, bool $negrito): string
    {
        [$r, $g, $b] = $this->hexParaRgbFloat($corHex);
        $fonte = $negrito ? '/F2' : '/F1';
        $winAnsi = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
        if (! is_string($winAnsi)) {
            $winAnsi = $texto;
        }
        $escapado = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $winAnsi);

        return sprintf(
            "BT %.3F %.3F %.3F rg %s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $r, $g, $b, $fonte, $fontSize, $x, $y, $escapado
        );
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function hexParaRgbFloat(string $hex): array
    {
        $limpo = ltrim(trim($hex), '#');
        if (strlen($limpo) === 3) {
            $limpo = $limpo[0].$limpo[0].$limpo[1].$limpo[1].$limpo[2].$limpo[2];
        }
        if (strlen($limpo) !== 6) {
            return [0.1, 0.1, 0.1];
        }

        return [
            hexdec(substr($limpo, 0, 2)) / 255.0,
            hexdec(substr($limpo, 2, 2)) / 255.0,
            hexdec(substr($limpo, 4, 2)) / 255.0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function quebrarLinhasTexto(string $texto, int $maxChars, int $maxLinhas): array
    {
        $wrapped = explode("\n", wordwrap(trim($texto), max(20, $maxChars), "\n", true));

        return array_slice($wrapped, 0, $maxLinhas);
    }

    private function formatarMoeda(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function formatarData(mixed $valor): string
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->format('d/m/Y');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '-';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texto) === 1) {
            try {
                return Carbon::parse($texto)->format('d/m/Y');
            } catch (\Throwable) {
                return $texto;
            }
        }

        return $texto;
    }

    private function rotuloStatus(string $status): string
    {
        return (string) (config('whmcs.faturas_status')[$status]['rotulo'] ?? ($status !== '' ? $status : 'Em aberto'));
    }

    private function higienizarCor(string $valor, string $padrao): string
    {
        $valor = trim($valor);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor) === 1) {
            return $valor;
        }

        return $padrao;
    }
}
