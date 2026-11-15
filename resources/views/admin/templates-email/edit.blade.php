@php
    $variaveisTemplate = is_array($template->variaveis) && count($template->variaveis) > 0
        ? $template->variaveis
        : [
            'cliente_nome' => 'Nome do destinatário',
            'empresa_nome' => 'Nome da empresa',
            'fatura_numero' => 'Número da fatura (ex: GR-501)',
            'fatura_valor' => 'Valor total (ex: R$ 59,90)',
            'fatura_vencimento' => 'Data de vencimento',
            'fatura_status' => 'Situação atual',
            'fatura_url' => 'Link seguro do portal',
            'pix_copia_cola' => 'Código PIX Copia e Cola',
            'url_portal' => 'Link da Área do Cliente',
        ];
    $variaveisEmpresaLista = [
        'empresa_nome' => 'Razão social / fantasia',
        'empresa_documento' => 'CNPJ / CPF da empresa',
        'empresa_telefone' => 'Telefone fixo',
        'empresa_whatsapp' => 'WhatsApp oficial',
        'empresa_email' => 'E-mail financeiro',
        'empresa_endereco' => 'Endereço completo',
    ];
    $actionForm = $modoCriacao
        ? route('admin.templates-email.store')
        : route('admin.templates-email.update', $template->chave);
    $modelosClonaveis = $todosTemplates->mapWithKeys(static fn ($item) => [
        $item->chave => [
            'chave' => $item->chave,
            'nome' => $item->nome,
            'categoria' => $item->categoria,
            'descricao' => (string) ($item->descricao ?? ''),
            'assunto' => $item->assunto,
            'pre_cabecalho' => (string) ($item->pre_cabecalho ?? ''),
            'corpo_html' => $item->corpo_html,
            'corpo_texto' => (string) ($item->corpo_texto ?? ''),
            'variaveis' => is_array($item->variaveis) ? $item->variaveis : [],
            'usar_layout' => (bool) $item->usar_layout,
        ],
    ])->all();
    $gruposCoresLayout = [
        'cor_fundo_pagina' => 'Fundo Externo',
        'cor_cartao' => 'Cartão Central',
        'cor_cabecalho_bg' => 'Fundo Cabeçalho',
        'cor_cabecalho_texto' => 'Texto Cabeçalho',
        'cor_destaque' => 'Destaque / Marca',
        'cor_texto' => 'Texto Corpo',
        'cor_botao_bg' => 'Fundo Botão',
        'cor_botao_texto' => 'Texto Botão',
        'cor_rodape_bg' => 'Fundo Rodapé',
        'cor_rodape_texto' => 'Texto Rodapé',
    ];
@endphp

@extends('admin.layouts.app')

@section('title', $modoCriacao ? 'Novo Template de E-mail' : 'Editar Template: ' . $template->nome)
@section('page_title', $modoCriacao ? 'Criar template de e-mail · preview ao vivo' : 'Editor ao vivo · ' . $template->nome)

@section('page_actions')
    <a href="{{ route('admin.templates-email.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> Catálogo
    </a>
    @if(! $modoCriacao)
        <a href="{{ route('admin.templates-email.create') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-plus" aria-hidden="true"></i> Novo
        </a>
    @endif
    <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-palette" aria-hidden="true"></i> Layout Global
    </a>
    <a href="{{ route('admin.templates-email.fila') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-layer-group" aria-hidden="true"></i> Fila Anti-Spam
    </a>
@endsection

@section('content')
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-center">
                @if(! $modoCriacao)
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-exchange-alt" aria-hidden="true"></i></span>
                            <select id="seletorRapidoTemplate" class="form-select form-select-sm" aria-label="Trocar template rapidamente">
                                @foreach($todosTemplates as $itemNav)
                                    <option value="{{ route('admin.templates-email.edit', $itemNav->chave) }}" @selected($itemNav->chave === $template->chave)>
                                        {{ $itemNav->nome }} ({{ $itemNav->chave }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
                <div class="{{ $modoCriacao ? 'col-md-8' : 'col-md-7' }}">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="fas fa-copy me-1" aria-hidden="true"></i>
                            {{ $modoCriacao ? 'Partir de um modelo' : 'Copiar conteúdo de' }}
                        </span>
                        <select id="selectModeloBase" class="form-select form-select-sm" aria-label="Selecionar modelo base">
                            <option value="">Selecione um template do catálogo...</option>
                            @foreach($todosTemplates as $itemBase)
                                @if($modoCriacao || $itemBase->chave !== $template->chave)
                                    <option value="{{ $itemBase->chave }}">{{ $itemBase->nome }} ({{ $itemBase->categoria }})</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="button" id="btnCarregarModeloBase" class="btn btn-outline-secondary">
                            <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Aplicar no Editor
                        </button>
                    </div>
                </div>
                @if($modoCriacao)
                    <div class="col-md-4 text-md-end small text-secondary">
                        A rota <code>/admin/templates-email/&lt;chave&gt;</code> será criada ao salvar.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="abas" data-abas="template-email">
                <div class="abas-barra mb-3">
                    <nav class="abas-nav" role="tablist" aria-label="Configurações do template de e-mail">
                        <button type="button" class="nav-link active" id="tab-conteudo-btn"
                            data-bs-toggle="tab" data-bs-target="#aba-conteudo" data-aba="conteudo" role="tab" aria-selected="true">
                            <i class="fas fa-code" aria-hidden="true"></i>
                            <span>Conteúdo &amp; Mensagem</span>
                        </button>
                        <button type="button" class="nav-link" id="tab-layout-btn"
                            data-bs-toggle="tab" data-bs-target="#aba-layout" data-aba="layout" role="tab" aria-selected="false">
                            <i class="fas fa-palette" aria-hidden="true"></i>
                            <span>Layout, Cores &amp; Rodapé</span>
                        </button>
                        <button type="button" class="nav-link" id="tab-variaveis-btn"
                            data-bs-toggle="tab" data-bs-target="#aba-variaveis" data-aba="variaveis" role="tab" aria-selected="false">
                            <i class="fas fa-sliders-h" aria-hidden="true"></i>
                            <span>Variáveis &amp; Simulação</span>
                            <span class="abas-contagem" id="contadorVariaveis">{{ count($variaveisTemplate) }}</span>
                        </button>
                        <button type="button" class="nav-link" id="tab-teste-btn"
                            data-bs-toggle="tab" data-bs-target="#aba-teste" data-aba="teste" role="tab" aria-selected="false">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            <span>Envio de Teste &amp; Reset</span>
                        </button>
                    </nav>
                </div>

                <form id="formTemplateEmail" method="POST" action="{{ $actionForm }}" data-salvar>
                    @csrf
                    @if(! $modoCriacao)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="variaveis_custom_presente" value="1">

                    <div class="tab-content">
                        {{-- ABA 1: CONTEUDO E MENSAGEM --}}
                        <div class="tab-pane fade show active" id="aba-conteudo" data-aba-painel="conteudo" role="tabpanel" aria-labelledby="tab-conteudo-btn">
                            <x-admin.card :title="$modoCriacao ? 'Novo Template de E-mail' : $template->nome" icon="fa-code"
                                :descricao="$modoCriacao ? 'Preencha os dados e acompanhe o e-mail montado ao vivo ao lado.' : ('Rota dedicada: ' . ($template->rota_preview ?: '/admin/templates-email/' . $template->chave))">
                                <div class="row g-3">
                                    @if($modoCriacao)
                                        <x-admin.field name="chave" id="chave" label="Chave única da rota (slug)" :value="old('chave', $template->chave)" col="col-md-5" required hint="Apenas minúsculas, números, _ ou -" />
                                        <x-admin.field name="nome" id="nome" label="Nome de identificação" :value="old('nome', $template->nome)" col="col-md-7" required />
                                    @else
                                        <x-admin.field name="nome" id="nome" label="Nome de identificação" :value="old('nome', $template->nome)" col="col-md-7" required />
                                        <div class="col-md-5 d-flex flex-column justify-content-end gap-2 pb-1">
                                            <input type="hidden" name="usar_layout" value="0">
                                            <x-admin.switch name="usar_layout" label="Usar Layout Global Separado" :checked="(bool) old('usar_layout', $template->usar_layout)" />
                                            <input type="hidden" name="ativo" value="0">
                                            <x-admin.switch name="ativo" label="Template Ativo" :checked="(bool) old('ativo', $template->ativo)" />
                                        </div>
                                    @endif

                                    <div class="col-md-5">
                                        <label for="categoria" class="form-label small fw-semibold">Categoria</label>
                                        <select id="categoria" name="categoria" class="form-select form-select-sm @error('categoria') is-invalid @enderror">
                                            @foreach($categorias as $catChave => $catLabel)
                                                <option value="{{ $catChave }}" @selected(old('categoria', $template->categoria) === $catChave)>{{ $catLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('categoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <x-admin.field name="descricao" id="descricao" label="Descrição interna" :value="old('descricao', $template->descricao)" col="col-md-7" :opcional="true" />

                                    @if($modoCriacao)
                                        <div class="col-12 d-flex flex-wrap gap-4 pt-1">
                                            <input type="hidden" name="usar_layout" value="0">
                                            <x-admin.switch name="usar_layout" label="Usar Layout Global Separado" :checked="(bool) old('usar_layout', $template->usar_layout)" />
                                            <input type="hidden" name="ativo" value="0">
                                            <x-admin.switch name="ativo" label="Template Ativo" :checked="(bool) old('ativo', $template->ativo)" />
                                        </div>
                                    @endif

                                    <x-admin.field name="assunto" id="assunto" label="Assunto do E-mail" :value="old('assunto', $template->assunto)" col="col-12" required
                                        :placeholder="'Ex: Fatura {{fatura_numero}} · {{empresa_nome}}'" />

                                    <x-admin.field name="pre_cabecalho" id="pre_cabecalho" label="Texto Pré-Cabeçalho (Preheader Mobile)" :value="old('pre_cabecalho', $template->pre_cabecalho)" col="col-12" :opcional="true"
                                        placeholder="Resumo exibido abaixo do assunto no Gmail, Outlook e celular" />

                                    <div class="col-12">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                            <span class="small fw-semibold">Variáveis Dinâmicas (clique para inserir no campo ativo)</span>
                                            <span class="small text-secondary">As alterações aparecem no preview ao vivo</span>
                                        </div>
                                        <div id="containerChipsVariaveis" class="d-flex flex-wrap gap-1 mb-2">
                                            @foreach($variaveisTemplate as $token => $explicacao)
                                                <button type="button" class="var-chip" data-inserir-token="{{ '{' . '{' . $token . '}' . '}' }}" title="{{ $explicacao }}">
                                                    + {{ '{' . '{' . $token . '}' . '}' }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <span class="small text-secondary me-1">Empresa:</span>
                                            @foreach($variaveisEmpresaLista as $tokenEmp => $descEmp)
                                                <button type="button" class="var-chip" data-inserir-token="{{ '{' . '{' . $tokenEmp . '}' . '}' }}" title="{{ $descEmp }}">
                                                    + {{ '{' . '{' . $tokenEmp . '}' . '}' }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                            <span class="small fw-semibold">Blocos Prontos Responsivos (inserir no corpo)</span>
                                            <span class="small text-secondary">Usa as cores da aba Layout</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="botao_cta">
                                                <i class="fas fa-mouse-pointer" aria-hidden="true"></i> Botão CTA
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="tabela_resumo">
                                                <i class="fas fa-table" aria-hidden="true"></i> Tabela Resumo
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="caixa_pix">
                                                <i class="fas fa-qrcode" aria-hidden="true"></i> Bloco PIX
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="alerta_destaque">
                                                <i class="fas fa-bullhorn" aria-hidden="true"></i> Destaque
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="assinatura_empresa">
                                                <i class="fas fa-signature" aria-hidden="true"></i> Assinatura
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-inserir-bloco="divisoria">
                                                <i class="fas fa-minus" aria-hidden="true"></i> Divisória
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="corpo_html" class="form-label small fw-semibold">
                                            Corpo HTML do Template (conteúdo puro, sem cabeçalho/rodapé)
                                        </label>
                                        <textarea id="corpo_html" name="corpo_html" rows="15" required
                                            class="form-control font-monospace small @error('corpo_html') is-invalid @enderror"
                                            placeholder="<h1 style=&quot;font-size:20px;margin:0 0 10px;&quot;>Olá, @{{cliente_nome}}</h1>">{{ old('corpo_html', $template->corpo_html) }}</textarea>
                                        @error('corpo_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                            <label for="corpo_texto" class="form-label small fw-semibold mb-0">
                                                Versão em Texto Puro (Fallback Anti-Spam / Leitores Simples)
                                            </label>
                                            <button type="button" id="btnGerarTextoPuro" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-wand-sparkles" aria-hidden="true"></i> Extrair do HTML
                                            </button>
                                        </div>
                                        <textarea id="corpo_texto" name="corpo_texto" rows="4" data-summernote="false"
                                            class="form-control font-monospace small @error('corpo_texto') is-invalid @enderror"
                                            placeholder="Deixe em branco para gerar automaticamente ou digite o texto alternativo sem HTML.">{{ old('corpo_texto', $template->corpo_texto) }}</textarea>
                                        @error('corpo_texto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </x-admin.card>
                        </div>

                        {{-- ABA 2: LAYOUT, CORES E RODAPE INLINE --}}
                        <div class="tab-pane fade" id="aba-layout" data-aba-painel="layout" role="tabpanel" aria-labelledby="tab-layout-btn">
                            <x-admin.card title="Identidade Visual, Cores & Rodapé do Layout" icon="fa-palette"
                                descricao="Ajuste aqui com preview ao vivo. Marque a opção abaixo caso deseje gravar essas alterações no layout global ao salvar.">
                                <div class="callout warn mb-3">
                                    <div>
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="salvar_layout_junto" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="salvarLayoutJunto" name="salvar_layout_junto" value="1">
                                            <label class="form-check-label fw-semibold" for="salvarLayoutJunto">
                                                Salvar estas alterações no Layout Global ao gravar o template
                                            </label>
                                        </div>
                                        <div class="small text-secondary mt-1">
                                            Desmarcado: usa estas configurações para simular o visual. Marcado: aplica no layout de todos os templates que usam layout separado.
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-outline-secondary">Abrir Estúdio Global</a>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="layout_marca_nome" class="form-label small fw-semibold">Nome da Marca no Cabeçalho</label>
                                        <input type="text" id="layout_marca_nome" name="layout[marca_nome]" value="{{ $layout['marca_nome'] }}" class="form-control form-control-sm" data-layout-campo="marca_nome">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="layout_subtitulo" class="form-label small fw-semibold">Subtítulo do Cabeçalho</label>
                                        <input type="text" id="layout_subtitulo" name="layout[subtitulo]" value="{{ $layout['subtitulo'] }}" class="form-control form-control-sm" data-layout-campo="subtitulo">
                                    </div>
                                    <div class="col-12">
                                        <label for="layout_logo_url" class="form-label small fw-semibold">URL da Logomarca (opcional)</label>
                                        <input type="text" id="layout_logo_url" name="layout[logo_url]" value="{{ $layout['logo_url'] }}" class="form-control form-control-sm" data-layout-campo="logo_url">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="layout_alinhamento" class="form-label small fw-semibold">Alinhamento</label>
                                        <select id="layout_alinhamento" name="layout[alinhamento_cabecalho]" class="form-select form-select-sm" data-layout-campo="alinhamento_cabecalho">
                                            <option value="left" @selected($layout['alinhamento_cabecalho'] === 'left')>Esquerda</option>
                                            <option value="center" @selected($layout['alinhamento_cabecalho'] === 'center')>Centralizado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="layout_largura" class="form-label small fw-semibold">Largura Máx. (px)</label>
                                        <input type="number" id="layout_largura" name="layout[largura_maxima_px]" min="440" max="720" value="{{ $layout['largura_maxima_px'] }}" class="form-control form-control-sm" data-layout-campo="largura_maxima_px">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="layout_raio" class="form-label small fw-semibold">Raio Borda (px)</label>
                                        <input type="number" id="layout_raio" name="layout[raio_borda_px]" min="0" max="28" value="{{ $layout['raio_borda_px'] }}" class="form-control form-control-sm" data-layout-campo="raio_borda_px">
                                    </div>

                                    <div class="col-12">
                                        <div class="zone-title">Paleta Responsiva do E-mail</div>
                                        <div class="row g-2">
                                            @foreach($gruposCoresLayout as $campoCor => $rotuloCor)
                                                <div class="col-sm-4 col-md-3">
                                                    <label for="layout_{{ $campoCor }}" class="form-label small fw-semibold mb-1">{{ $rotuloCor }}</label>
                                                    <input type="color" id="layout_{{ $campoCor }}" name="layout[{{ $campoCor }}]" value="{{ $layout[$campoCor] }}" class="form-control form-control-color w-100" data-layout-campo="{{ $campoCor }}">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="zone-title">Blocos Opcionais da Moldura</div>
                                        <div class="row g-2">
                                            @foreach([
                                                'mostrar_barra_superior' => 'Faixa Superior Gold',
                                                'mostrar_pre_cabecalho' => 'Pré-Cabeçalho Oculto',
                                                'mostrar_whatsapp' => 'Exibir WhatsApp no Rodapé',
                                                'mostrar_links_rodape' => 'Links Site / Portal / Rastreio',
                                            ] as $campoBool => $rotuloBool)
                                                <div class="col-sm-6">
                                                    <div class="form-check form-switch">
                                                        <input type="hidden" name="layout[{{ $campoBool }}]" value="0">
                                                        <input class="form-check-input" type="checkbox" role="switch"
                                                            id="layout_{{ $campoBool }}" name="layout[{{ $campoBool }}]" value="1"
                                                            data-layout-campo="{{ $campoBool }}"
                                                            @checked(!empty($layout[$campoBool]))>
                                                        <label class="form-check-label" for="layout_{{ $campoBool }}">{{ $rotuloBool }}</label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="layout_cabecalho_extra" class="form-label small fw-semibold">HTML Extra no Cabeçalho (opcional)</label>
                                        <textarea id="layout_cabecalho_extra" name="layout[cabecalho_extra_html]" rows="2" data-summernote="false"
                                            class="form-control font-monospace small" data-layout-campo="cabecalho_extra_html"
                                            placeholder="Ex: <span style='opacity:.8'>Atendimento Financeiro 24h</span>">{{ $layout['cabecalho_extra_html'] ?? '' }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="layout_texto_rodape" class="form-label small fw-semibold">Mensagem do Rodapé</label>
                                        <input type="text" id="layout_texto_rodape" name="layout[texto_rodape]" value="{{ $layout['texto_rodape'] }}" class="form-control form-control-sm" data-layout-campo="texto_rodape">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="layout_rodape_legal" class="form-label small fw-semibold">Assinatura Legal</label>
                                        <input type="text" id="layout_rodape_legal" name="layout[rodape_legal]" value="{{ $layout['rodape_legal'] }}" class="form-control form-control-sm" data-layout-campo="rodape_legal">
                                    </div>
                                    <div class="col-12">
                                        <label for="layout_css_adicional" class="form-label small fw-semibold">CSS Responsivo Extra (opcional)</label>
                                        <textarea id="layout_css_adicional" name="layout[css_adicional]" rows="3" data-summernote="false"
                                            class="form-control font-monospace small" data-layout-campo="css_adicional">{{ $layout['css_adicional'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            </x-admin.card>
                        </div>

                        {{-- ABA 3: VARIAVEIS E SIMULACAO --}}
                        <div class="tab-pane fade" id="aba-variaveis" data-aba-painel="variaveis" role="tabpanel" aria-labelledby="tab-variaveis-btn">
                            <x-admin.card title="Dados de Teste / Variáveis no Preview (Tempo Real)" icon="fa-sliders-h"
                                descricao="Cadastre variáveis personalizadas do template e simule os valores exibidos no preview ao vivo.">
                                <div class="p-3 rounded border mb-3 bg-body-tertiary">
                                    <div class="small fw-semibold mb-2">
                                        <i class="fas fa-plus-circle me-1 text-gold" aria-hidden="true"></i> Adicionar Variável Dinâmica
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-sm-3">
                                            <label for="novaVarChave" class="form-label small mb-1">Chave (slug)</label>
                                            <input type="text" id="novaVarChave" class="form-control form-control-sm font-monospace" placeholder="placa_veiculo">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="novaVarDescricao" class="form-label small mb-1">Descrição</label>
                                            <input type="text" id="novaVarDescricao" class="form-control form-control-sm" placeholder="Placa monitorada">
                                        </div>
                                        <div class="col-sm-3">
                                            <label for="novaVarExemplo" class="form-label small mb-1">Valor Preview</label>
                                            <input type="text" id="novaVarExemplo" class="form-control form-control-sm" placeholder="ABC-1D23">
                                        </div>
                                        <div class="col-sm-2 d-grid">
                                            <button type="button" id="btnAdicionarVariavel" class="btn btn-sm btn-golden">
                                                <i class="fas fa-plus" aria-hidden="true"></i> Criar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="listaVariaveisEditor" class="row g-2">
                                    @foreach($variaveisTemplate as $token => $explicacao)
                                        <div class="col-md-6" data-var-row="{{ $token }}">
                                            <div class="p-2 border rounded h-100 bg-body">
                                                <div class="d-flex justify-content-between align-items-center gap-1 mb-1">
                                                    <code class="small fw-semibold">{{ '{' . '{' . $token . '}' . '}' }}</code>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" data-remover-var="{{ $token }}" title="Remover variável">
                                                        <i class="fas fa-times" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                                <input type="text" name="variaveis_custom[{{ $token }}]"
                                                    value="{{ $explicacao }}"
                                                    class="form-control form-control-sm mb-1"
                                                    placeholder="Descrição da variável">
                                                <input type="text"
                                                    name="variaveis_preview[{{ $token }}]"
                                                    value="{{ $exemplos[$token] ?? '' }}"
                                                    class="form-control form-control-sm"
                                                    placeholder="Valor de teste para {{ $token }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <hr class="my-3">
                                <div class="small fw-semibold mb-2">Simular Dados da Empresa neste Preview</div>
                                <div class="row g-2">
                                    @foreach($variaveisEmpresaLista as $tokenEmp => $descEmp)
                                        <div class="col-sm-6">
                                            <label class="form-label small mb-1 text-secondary" for="prev_{{ $tokenEmp }}">{{ $tokenEmp }} — {{ $descEmp }}</label>
                                            <input type="text" id="prev_{{ $tokenEmp }}"
                                                name="variaveis_preview[{{ $tokenEmp }}]"
                                                value="{{ $exemplos[$tokenEmp] ?? '' }}"
                                                class="form-control form-control-sm">
                                        </div>
                                    @endforeach
                                </div>
                            </x-admin.card>
                        </div>
                    </div>

                    <div class="abas-salvar aderente">
                        <p class="abas-salvar-note">
                            Todas as abas (Conteúdo, Texto Puro, Variáveis e Layout opcional) são salvas em conjunto.
                        </p>
                        <button type="button" id="btnForcarPreview" class="btn btn-outline-secondary">
                            <i class="fas fa-sync" aria-hidden="true"></i> Atualizar Preview
                        </button>
                        <button type="submit" class="btn btn-golden">
                            <i class="fas fa-save me-1" aria-hidden="true"></i>
                            {{ $modoCriacao ? 'Criar Template com Rota' : 'Salvar Template' }}
                        </button>
                    </div>
                </form>

                {{-- ABA 4: ENVIO DE TESTE E RESTAURAR (FORA DE #formTemplateEmail PARA NAO ANINHAR <form>) --}}
                <div class="tab-content mt-0">
                    <div class="tab-pane fade" id="aba-teste" data-aba-painel="teste" role="tabpanel" aria-labelledby="tab-teste-btn">
                        @if(! $modoCriacao)
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <x-admin.card title="Disparar Teste (Fila Anti-Spam ou Imediato)" icon="fa-paper-plane" class="h-100">
                                        <form id="formTestarTemplate" method="POST" action="{{ route('admin.templates-email.testar', $template->chave) }}" class="row g-2 align-items-end" data-salvar>
                                            @csrf
                                            <div class="col-12">
                                                <label for="email_destino_teste" class="form-label small fw-semibold">E-mail de Destino</label>
                                                <input type="email" id="email_destino_teste" name="email_destino" required class="form-control form-control-sm" value="{{ auth()->user()?->email ?? '' }}">
                                            </div>
                                            <div class="col-7">
                                                <label for="modo_envio_teste" class="form-label small fw-semibold">Modo de Disparo</label>
                                                <select id="modo_envio_teste" name="modo_envio" class="form-select form-select-sm">
                                                    <option value="fila">Enfileirar (anti-spam com cadência)</option>
                                                    <option value="imediato">Enviar Imediatamente</option>
                                                </select>
                                            </div>
                                            <div class="col-5 d-grid">
                                                <button type="submit" class="btn btn-sm btn-golden">
                                                    <i class="fas fa-paper-plane me-1" aria-hidden="true"></i> Disparar Teste
                                                </button>
                                            </div>
                                        </form>
                                    </x-admin.card>
                                </div>
                                <div class="col-md-5">
                                    <x-admin.card title="Restaurar Padrão" icon="fa-clock-rotate-left" class="h-100">
                                        <p class="small text-secondary mb-3">Restaura assunto, pré-cabeçalho, corpo e variáveis originais se este for um template padrão.</p>
                                        <form id="formRestaurarTemplate" method="POST" action="{{ route('admin.templates-email.restaurar', $template->chave) }}" data-confirmar="Restaurar este template para o padrão de fábrica?">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                <i class="fas fa-undo me-1" aria-hidden="true"></i> Restaurar Padrão de Fábrica
                                            </button>
                                        </form>
                                    </x-admin.card>
                                </div>
                            </div>
                        @else
                            <x-admin.card title="Envio de Teste" icon="fa-info-circle">
                                <p class="small text-secondary mb-0">
                                    Salve o novo template primeiro para habilitar o disparo de teste na fila anti-spam e o histórico da rota dedicada.
                                    Enquanto isso, você já pode conferir toda a renderização no painel ao vivo à direita.
                                </p>
                            </x-admin.card>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <x-admin.card title="Preview em Tempo Real (Layout + Conteúdo)" icon="fa-eye" class="sticky-top" style="top: 1rem;">
                <x-slot:actions>
                    <button type="button" class="btn btn-golden active" data-viewport-preview="desktop">
                        <i class="fas fa-desktop me-1" aria-hidden="true"></i> Desktop
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-viewport-preview="mobile">
                        <i class="fas fa-mobile-alt me-1" aria-hidden="true"></i> Mobile
                    </button>
                </x-slot:actions>

                <div class="mb-2 p-2 rounded border bg-body-tertiary d-flex justify-content-between align-items-start gap-2">
                    <div class="small min-w-0">
                        <div>
                            <span class="text-secondary">Template:</span>
                            <span id="previewNomeLive" class="fw-semibold">{{ $template->nome }}</span>
                        </div>
                        <div class="text-truncate">
                            <span class="text-secondary">Assunto:</span>
                            <span id="previewAssuntoLive" class="fw-semibold">{{ $previewInicial['assunto'] }}</span>
                        </div>
                        <div class="text-truncate">
                            <span class="text-secondary">Preheader:</span>
                            <span id="previewPreCabecalhoLive" class="text-secondary">{{ ($previewInicial['pre_cabecalho'] ?? '') !== '' ? $previewInicial['pre_cabecalho'] : '—' }}</span>
                        </div>
                    </div>
                    <span id="statusPreviewLive" class="badge-status tone-green flex-shrink-0">Ao vivo</span>
                </div>

                <div class="preview-shell">
                    <iframe id="iframePreviewEmail" title="Preview ao vivo do e-mail responsivo" class="preview-frame" srcdoc="{{ $previewInicial['html'] }}"></iframe>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(() => {
    const previewUrl = @json($modoCriacao ? route('admin.templates-email.preview-livre') : route('admin.templates-email.preview', $template->chave));
    const layoutInicial = @json($layout);
    const modelosClonaveis = @json($modelosClonaveis);
    const exemplosBase = @json($exemplos);
    const form = document.getElementById('formTemplateEmail');
    const iframe = document.getElementById('iframePreviewEmail');
    const statusBadge = document.getElementById('statusPreviewLive');
    const previewNome = document.getElementById('previewNomeLive');
    const previewAssunto = document.getElementById('previewAssuntoLive');
    const previewPre = document.getElementById('previewPreCabecalhoLive');
    const corpoTextarea = document.getElementById('corpo_html');
    const textoPuroTextarea = document.getElementById('corpo_texto');
    const campoChave = document.getElementById('chave');
    const campoNome = document.getElementById('nome');

    if (!form || !iframe) {
        return;
    }

    // Sincroniza alternância de abas entre #formTemplateEmail (abas 1-3) e #aba-teste (fora do form principal)
    document.querySelectorAll('[data-abas="template-email"] [data-aba]').forEach((btn) => {
        btn.addEventListener('shown.bs.tab', () => {
            const alvo = btn.getAttribute('data-aba');
            document.querySelectorAll('[data-abas="template-email"] [data-aba-painel]').forEach((painel) => {
                const ativo = painel.getAttribute('data-aba-painel') === alvo;
                painel.classList.toggle('show', ativo);
                painel.classList.toggle('active', ativo);
            });
            if (window.GoldenSummernote && typeof window.GoldenSummernote.initAll === 'function') {
                window.GoldenSummernote.initAll(form);
            }
        });
    });

    // Seletor rápido de template no modo edição
    const seletorRapido = document.getElementById('seletorRapidoTemplate');
    if (seletorRapido) {
        seletorRapido.addEventListener('change', () => {
            if (seletorRapido.value) {
                window.location.assign(seletorRapido.value);
            }
        });
    }

    // Auto-slug da chave ao digitar o nome no modo criação
    let chaveEditadaManual = false;
    function slugifyChave(texto) {
        return String(texto || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '_')
            .replace(/^[_-]+|[_-]+$/g, '')
            .slice(0, 80);
    }

    if (campoChave && campoNome) {
        campoChave.addEventListener('input', () => {
            const valorLimpo = slugifyChave(campoChave.value);
            if (campoChave.value !== valorLimpo) {
                campoChave.value = valorLimpo;
            }
            chaveEditadaManual = valorLimpo !== '';
        });

        campoNome.addEventListener('input', () => {
            if (!chaveEditadaManual) {
                campoChave.value = slugifyChave(campoNome.value);
            }
        });
    }

    // Foco rastreado para inserção de tokens no campo certo
    let ultimoCampoTexto = corpoTextarea;
    ['assunto', 'pre_cabecalho', 'corpo_texto'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('focus', () => {
                ultimoCampoTexto = el;
            });
        }
    });
    document.addEventListener('focusin', (ev) => {
        const alvo = ev.target instanceof Element ? ev.target : null;
        if (!alvo) {
            return;
        }
        const editor = alvo.closest('.note-editor');
        if (editor && corpoTextarea) {
            const pertence = editor.previousElementSibling === corpoTextarea || form.contains(editor);
            if (pertence) {
                ultimoCampoTexto = corpoTextarea;
            }
        }
    });

    function lerHtmlAoVivoDoEditor() {
        if (!corpoTextarea) {
            return '';
        }
        if (window.GoldenSummernote && typeof window.GoldenSummernote.obterHtml === 'function') {
            const htmlSn = window.GoldenSummernote.obterHtml(corpoTextarea);
            if (htmlSn !== undefined && htmlSn !== null) {
                if (corpoTextarea.value !== htmlSn) {
                    corpoTextarea.value = htmlSn;
                }
                return htmlSn;
            }
        }
        return corpoTextarea.value || '';
    }

    function lerEstadoLayoutAtual() {
        const estado = Object.assign({}, layoutInicial || {});
        form.querySelectorAll('[data-layout-campo]').forEach((campo) => {
            const chave = campo.getAttribute('data-layout-campo');
            if (!chave) {
                return;
            }
            if (campo instanceof HTMLInputElement && campo.type === 'checkbox') {
                estado[chave] = campo.checked;
            } else if (campo instanceof HTMLInputElement && campo.type === 'number') {
                const parsed = parseInt(campo.value, 10);
                if (!Number.isNaN(parsed)) {
                    estado[chave] = parsed;
                }
            } else {
                estado[chave] = campo.value || '';
            }
        });
        return estado;
    }

    function obterMapaVariaveis() {
        const mapa = Object.assign({}, exemplosBase || {});
        form.querySelectorAll('input[name^="variaveis_preview["]').forEach((inp) => {
            const match = inp.name.match(/^variaveis_preview\[([^\]]+)\]$/);
            if (match && inp.value !== '') {
                mapa[match[1]] = inp.value;
            }
        });
        return mapa;
    }

    function escaparHtmlSeguro(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function interpolarTextoAoVivo(texto, escaparValores, variaveis) {
        return String(texto ?? '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (match, chave) => {
            if (!Object.prototype.hasOwnProperty.call(variaveis, chave) || variaveis[chave] == null) {
                return match;
            }
            const valor = String(variaveis[chave]);
            return (!escaparValores || chave.endsWith('_html')) ? valor : escaparHtmlSeguro(valor);
        });
    }

    function aplicarLayoutNoDocumentoIframe(doc, layout) {
        if (!doc || !layout) {
            return;
        }
        const outerTable = doc.querySelector('[data-email-outer-table]');
        const container = doc.querySelector('[data-email-container]');
        const topBar = doc.querySelector('[data-email-top-bar]');
        const headerCell = doc.querySelector('[data-email-header-cell]');
        const marcaSlot = doc.querySelector('[data-email-marca-slot]');
        const subSlot = doc.querySelector('[data-email-subtitulo-slot]');
        const logoSlot = doc.querySelector('[data-email-logo-slot]');
        const bodySlot = doc.querySelector('[data-email-body-slot]');
        const footerCell = doc.querySelector('[data-email-footer-cell]');
        const textoRodapeSlot = doc.querySelector('[data-email-texto-rodape-slot]');
        const legalSlot = doc.querySelector('[data-email-rodape-legal-slot]');
        const customCssSlot = doc.querySelector('[data-email-custom-css]');

        if (doc.body) {
            doc.body.style.backgroundColor = layout.cor_fundo_pagina;
            doc.body.style.color = layout.cor_texto;
        }
        if (outerTable) {
            outerTable.style.backgroundColor = layout.cor_fundo_pagina;
        }
        if (container) {
            container.style.maxWidth = `${parseInt(layout.largura_maxima_px, 10) || 580}px`;
            container.style.backgroundColor = layout.cor_cartao;
            container.style.borderRadius = `${parseInt(layout.raio_borda_px, 10) || 0}px`;
        }
        if (topBar) {
            topBar.style.display = layout.mostrar_barra_superior ? '' : 'none';
            topBar.style.backgroundColor = layout.cor_destaque;
        }
        if (headerCell) {
            headerCell.style.backgroundColor = layout.cor_cabecalho_bg;
            headerCell.setAttribute('align', layout.alinhamento_cabecalho === 'center' ? 'center' : 'left');
            headerCell.style.textAlign = layout.alinhamento_cabecalho === 'center' ? 'center' : 'left';
        }
        if (marcaSlot) {
            marcaSlot.textContent = layout.marca_nome || 'Golden Rastreamento';
            marcaSlot.style.color = layout.cor_destaque;
        }
        if (subSlot) {
            subSlot.textContent = layout.subtitulo || '';
            subSlot.style.color = layout.cor_cabecalho_texto;
            subSlot.style.display = layout.subtitulo ? '' : 'none';
        }
        if (headerCell) {
            let cabExtraSlot = doc.querySelector('[data-email-cabecalho-extra-slot]');
            const extraHtml = (layout.cabecalho_extra_html || '').trim();
            if (extraHtml !== '') {
                if (!cabExtraSlot) {
                    cabExtraSlot = doc.createElement('div');
                    cabExtraSlot.setAttribute('data-email-cabecalho-extra-slot', '');
                    cabExtraSlot.style.marginTop = '8px';
                    cabExtraSlot.style.fontSize = '12px';
                    headerCell.appendChild(cabExtraSlot);
                }
                cabExtraSlot.style.display = '';
                cabExtraSlot.style.color = layout.cor_cabecalho_texto;
                cabExtraSlot.innerHTML = extraHtml;
            } else if (cabExtraSlot) {
                cabExtraSlot.style.display = 'none';
            }
        }
        if (logoSlot && layout.logo_url) {
            logoSlot.setAttribute('src', layout.logo_url);
        }
        if (bodySlot) {
            bodySlot.style.color = layout.cor_texto;
            bodySlot.querySelectorAll('h1, h2, h3').forEach((h) => {
                h.style.color = layout.cor_texto;
            });
            bodySlot.querySelectorAll('a.golden-btn, a[data-btn="true"]').forEach((a) => {
                a.style.setProperty('background-color', layout.cor_botao_bg, 'important');
                a.style.setProperty('color', layout.cor_botao_texto, 'important');
            });
        }
        if (footerCell) {
            footerCell.style.backgroundColor = layout.cor_rodape_bg;
            footerCell.style.color = layout.cor_rodape_texto;
        }
        if (textoRodapeSlot) {
            textoRodapeSlot.textContent = layout.texto_rodape || '';
        }
        if (legalSlot) {
            legalSlot.textContent = layout.rodape_legal || '';
        }
        if (customCssSlot) {
            customCssSlot.textContent = layout.css_adicional || '';
        }
    }

    let timerDebounce = null;
    let frameRaf = null;
    let controladorAbort = null;
    let versaoEdicao = 0;

    function atualizarPreviewInstantaneo(forcarDom = false) {
        const variaveis = obterMapaVariaveis();
        const layout = lerEstadoLayoutAtual();
        const nomeBruto = form.querySelector('input[name="nome"]')?.value?.trim() || 'Novo Template';
        const assuntoBruto = form.querySelector('input[name="assunto"]')?.value || '';
        const preBruto = form.querySelector('input[name="pre_cabecalho"]')?.value || '';
        const usarLayout = Boolean(form.querySelector('input[name="usar_layout"][type="checkbox"]')?.checked);
        const corpoBruto = lerHtmlAoVivoDoEditor();

        const assuntoResolvido = interpolarTextoAoVivo(assuntoBruto, false, variaveis);
        const preResolvido = interpolarTextoAoVivo(preBruto, false, variaveis);
        const corpoResolvido = interpolarTextoAoVivo(corpoBruto, true, variaveis);

        if (previewNome) {
            previewNome.textContent = nomeBruto;
        }
        if (previewAssunto) {
            previewAssunto.textContent = assuntoResolvido || '—';
        }
        if (previewPre) {
            previewPre.textContent = preResolvido || '—';
        }

        const doc = iframe.contentDocument;
        if (!doc || !doc.body) {
            return;
        }

        const slotCorpo = doc.querySelector('[data-email-body-slot]');
        const slotPre = doc.querySelector('[data-email-preheader-slot]');

        if (usarLayout && slotCorpo) {
            slotCorpo.innerHTML = corpoResolvido;
            if (slotPre) {
                slotPre.textContent = preResolvido;
            }
            aplicarLayoutNoDocumentoIframe(doc, layout);
        } else if (forcarDom || !usarLayout) {
            doc.body.innerHTML = corpoResolvido;
        }
    }

    async function atualizarPreview() {
        const ticket = versaoEdicao;
        if (controladorAbort) {
            controladorAbort.abort();
        }
        controladorAbort = new AbortController();

        if (statusBadge) {
            statusBadge.textContent = 'Sincronizando...';
            statusBadge.className = 'badge-status tone-gold flex-shrink-0';
        }

        const htmlAoVivo = lerHtmlAoVivoDoEditor();
        const fd = new FormData(form);
        fd.delete('_method');
        fd.set('corpo_html', htmlAoVivo);
        const usarLayout = form.querySelector('input[name="usar_layout"][type="checkbox"]')?.checked;
        fd.set('usar_layout', usarLayout ? '1' : '0');

        try {
            const resp = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
                signal: controladorAbort.signal,
            });
            if (!resp.ok) {
                return;
            }
            const data = await resp.json();

            if (ticket !== versaoEdicao) {
                atualizarPreviewInstantaneo(false);
                return;
            }

            if (previewNome) {
                previewNome.textContent = data.nome || '—';
            }
            if (previewAssunto) {
                previewAssunto.textContent = data.assunto || '—';
            }
            if (previewPre) {
                previewPre.textContent = data.pre_cabecalho || '—';
            }

            iframe.srcdoc = data.html || '<p>Sem conteúdo</p>';
            iframe.addEventListener('load', () => {
                if (versaoEdicao > ticket) {
                    atualizarPreviewInstantaneo(false);
                }
            }, { once: true });

            if (statusBadge) {
                statusBadge.textContent = 'Ao vivo';
                statusBadge.className = 'badge-status tone-green flex-shrink-0';
            }
        } catch (err) {
            if (err && err.name === 'AbortError') {
                return;
            }
            if (statusBadge) {
                statusBadge.textContent = 'Prévia local';
                statusBadge.className = 'badge-status tone-amber flex-shrink-0';
            }
        }
    }

    function agendarPreview() {
        clearTimeout(timerDebounce);
        timerDebounce = setTimeout(atualizarPreview, 180);
    }

    function marcarEdicao(evento) {
        const alvo = evento && evento.target instanceof Element ? evento.target : null;
        if (alvo && alvo.closest('[data-ignorar-preview]')) {
            return;
        }
        if (frameRaf !== null) {
            return;
        }
        frameRaf = window.requestAnimationFrame(() => {
            frameRaf = null;
            versaoEdicao++;
            if (statusBadge) {
                statusBadge.textContent = 'Digitando...';
                statusBadge.className = 'badge-status tone-gold flex-shrink-0';
            }
            atualizarPreviewInstantaneo(false);
            agendarPreview();
        });
    }

    // 1-clique inserir bloco HTML estilizado com a paleta atual do layout
    function construirBlocoHtml(tipo) {
        const L = lerEstadoLayoutAtual();
        if (tipo === 'botao_cta') {
            return `<p style="margin:20px 0;text-align:center;"><a data-btn="true" href="@{{fatura_url}}" class="golden-btn" style="display:inline-block;background-color:${L.cor_botao_bg};color:${L.cor_botao_texto};text-decoration:none;font-weight:700;font-size:14px;padding:13px 26px;border-radius:10px;">Acessar Agora</a></p>`;
        }
        if (tipo === 'tabela_resumo') {
            return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border:1px solid ${L.cor_rodape_bg};border-radius:10px;margin:16px 0;background-color:${L.cor_cartao};color:${L.cor_texto};"><tr><td style="padding:10px 14px;border-bottom:1px solid ${L.cor_rodape_bg};color:${L.cor_rodape_texto};font-size:13px;">Fatura</td><td style="padding:10px 14px;border-bottom:1px solid ${L.cor_rodape_bg};text-align:right;font-weight:700;color:${L.cor_texto};">@{{fatura_numero}}</td></tr><tr><td style="padding:10px 14px;border-bottom:1px solid ${L.cor_rodape_bg};color:${L.cor_rodape_texto};font-size:13px;">Valor</td><td style="padding:10px 14px;border-bottom:1px solid ${L.cor_rodape_bg};text-align:right;font-weight:700;color:${L.cor_texto};">@{{fatura_valor}}</td></tr><tr><td style="padding:10px 14px;color:${L.cor_rodape_texto};font-size:13px;">Vencimento</td><td style="padding:10px 14px;text-align:right;font-weight:600;color:${L.cor_texto};">@{{fatura_vencimento}}</td></tr></table>`;
        }
        if (tipo === 'caixa_pix') {
            return `<div style="margin:16px 0;padding:14px 16px;border-left:4px solid ${L.cor_destaque};background-color:${L.cor_rodape_bg};border-radius:8px;color:${L.cor_texto};"><strong style="display:block;margin-bottom:4px;font-size:13px;">PIX Copia e Cola:</strong><span style="font-family:monospace;font-size:12px;word-break:break-all;">@{{pix_copia_cola}}</span></div>`;
        }
        if (tipo === 'alerta_destaque') {
            return `<div style="margin:16px 0;padding:14px 16px;border:1px solid ${L.cor_destaque};background-color:${L.cor_rodape_bg};border-radius:10px;color:${L.cor_texto};"><strong>Importante:</strong> Confirme seus dados no <a href="@{{url_portal}}" style="color:${L.cor_destaque};font-weight:600;">Portal do Cliente</a>.</div>`;
        }
        if (tipo === 'assinatura_empresa') {
            return `<p style="margin:18px 0 0;color:${L.cor_rodape_texto};font-size:13px;">Atenciosamente,<br><strong style="color:${L.cor_texto};">@{{empresa_nome}}</strong><br>Tel: @{{empresa_telefone}} &middot; WhatsApp: @{{empresa_whatsapp}}</p>`;
        }
        return `<hr style="border:0;border-top:1px solid ${L.cor_rodape_bg};margin:18px 0;">`;
    }

    document.querySelectorAll('[data-inserir-bloco]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tipo = btn.getAttribute('data-inserir-bloco') || '';
            const html = construirBlocoHtml(tipo);
            if (window.GoldenSummernote && typeof window.GoldenSummernote.inserirHtml === 'function') {
                window.GoldenSummernote.inserirHtml(corpoTextarea, html);
            } else if (window.GoldenSummernote && typeof window.GoldenSummernote.inserirTexto === 'function') {
                window.GoldenSummernote.inserirTexto(corpoTextarea, html);
            }
            marcarEdicao();
        });
    });

    // Clique em chips de variáveis (delega no container para suportar chips adicionados dinamicamente)
    function vincularClickToken(botao) {
        botao.addEventListener('click', () => {
            const token = botao.getAttribute('data-inserir-token') || '';
            const destino = ultimoCampoTexto || corpoTextarea;
            if (!destino || !token) {
                return;
            }
            if (destino === corpoTextarea && window.GoldenSummernote && typeof window.GoldenSummernote.inserirTexto === 'function') {
                window.GoldenSummernote.inserirTexto(corpoTextarea, token);
                marcarEdicao();
                return;
            }
            const inicio = destino.selectionStart ?? destino.value.length;
            const fim = destino.selectionEnd ?? destino.value.length;
            destino.value = destino.value.slice(0, inicio) + token + destino.value.slice(fim);
            destino.focus();
            destino.selectionStart = destino.selectionEnd = inicio + token.length;
            marcarEdicao();
        });
    }
    document.querySelectorAll('[data-inserir-token]').forEach(vincularClickToken);

    // Gerador automático de texto puro a partir do HTML atual
    document.getElementById('btnGerarTextoPuro')?.addEventListener('click', () => {
        if (!textoPuroTextarea) {
            return;
        }
        const bruto = lerHtmlAoVivoDoEditor()
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/(p|div|h[1-6]|tr|li)>/gi, '\n')
            .replace(/<[^>]+>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .replace(/[ \t]{2,}/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
        textoPuroTextarea.value = bruto;
    });

    // Construtor dinâmico de variáveis na Aba 3
    const listaVars = document.getElementById('listaVariaveisEditor');
    const chipsContainer = document.getElementById('containerChipsVariaveis');
    const contadorVars = document.getElementById('contadorVariaveis');

    function atualizarContadorVars() {
        if (contadorVars && listaVars) {
            contadorVars.textContent = String(listaVars.querySelectorAll('[data-var-row]').length);
        }
    }

    function criarLinhaVariavel(chaveLimpa, descricao, valorExemplo) {
        if (!listaVars || !chaveLimpa) {
            return;
        }
        if (listaVars.querySelector(`[data-var-row="${chaveLimpa}"]`)) {
            const prevInput = listaVars.querySelector(`input[name="variaveis_preview[${chaveLimpa}]"]`);
            if (prevInput && valorExemplo) {
                prevInput.value = valorExemplo;
            }
            return;
        }

        const col = document.createElement('div');
        col.className = 'col-md-6';
        col.setAttribute('data-var-row', chaveLimpa);

        const card = document.createElement('div');
        card.className = 'p-2 border rounded h-100 bg-body';

        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-center gap-1 mb-1';
        const code = document.createElement('code');
        code.className = 'small fw-semibold';
        code.textContent = '{' + '{' + chaveLimpa + '}' + '}';
        const btnRem = document.createElement('button');
        btnRem.type = 'button';
        btnRem.className = 'btn btn-sm btn-outline-danger py-0 px-1';
        btnRem.setAttribute('data-remover-var', chaveLimpa);
        btnRem.title = 'Remover variável';
        btnRem.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        header.appendChild(code);
        header.appendChild(btnRem);

        const inpDesc = document.createElement('input');
        inpDesc.type = 'text';
        inpDesc.name = `variaveis_custom[${chaveLimpa}]`;
        inpDesc.value = descricao || `Variável personalizada ${chaveLimpa}`;
        inpDesc.className = 'form-control form-control-sm mb-1';
        inpDesc.placeholder = 'Descrição da variável';

        const inpPrev = document.createElement('input');
        inpPrev.type = 'text';
        inpPrev.name = `variaveis_preview[${chaveLimpa}]`;
        inpPrev.value = valorExemplo || '';
        inpPrev.className = 'form-control form-control-sm';
        inpPrev.placeholder = `Valor de teste para ${chaveLimpa}`;

        card.appendChild(header);
        card.appendChild(inpDesc);
        card.appendChild(inpPrev);
        col.appendChild(card);
        listaVars.appendChild(col);

        const tokenCompleto = '{' + '{' + chaveLimpa + '}' + '}';
        if (chipsContainer && !chipsContainer.querySelector(`[data-inserir-token="${tokenCompleto}"]`)) {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'var-chip';
            chip.setAttribute('data-inserir-token', tokenCompleto);
            chip.title = inpDesc.value;
            chip.textContent = `+ ${tokenCompleto}`;
            vincularClickToken(chip);
            chipsContainer.appendChild(chip);
        }

        atualizarContadorVars();
    }

    document.getElementById('btnAdicionarVariavel')?.addEventListener('click', () => {
        const inpChave = document.getElementById('novaVarChave');
        const inpDesc = document.getElementById('novaVarDescricao');
        const inpEx = document.getElementById('novaVarExemplo');
        const chaveLimpa = slugifyChave(inpChave?.value || '').replace(/-/g, '_');
        if (!chaveLimpa) {
            inpChave?.focus();
            return;
        }
        criarLinhaVariavel(chaveLimpa, inpDesc?.value?.trim() || '', inpEx?.value || '');
        if (inpChave) inpChave.value = '';
        if (inpDesc) inpDesc.value = '';
        if (inpEx) inpEx.value = '';
        marcarEdicao();
    });

    listaVars?.addEventListener('click', (ev) => {
        const alvo = ev.target instanceof Element ? ev.target.closest('[data-remover-var]') : null;
        if (!alvo) {
            return;
        }
        const token = alvo.getAttribute('data-remover-var');
        const tokenCompleto = '{' + '{' + token + '}' + '}';
        listaVars.querySelector(`[data-var-row="${token}"]`)?.remove();
        chipsContainer?.querySelector(`[data-inserir-token="${tokenCompleto}"]`)?.remove();
        atualizarContadorVars();
        marcarEdicao();
    });

    // Clonar modelo base do catálogo no editor atual
    document.getElementById('btnCarregarModeloBase')?.addEventListener('click', () => {
        const selBase = document.getElementById('selectModeloBase');
        const chaveBase = selBase?.value || '';
        const modelo = chaveBase ? modelosClonaveis[chaveBase] : null;
        if (!modelo) {
            return;
        }

        const catEl = document.getElementById('categoria');
        const descEl = document.getElementById('descricao');
        const assEl = document.getElementById('assunto');
        const preEl = document.getElementById('pre_cabecalho');
        const usarLayoutCb = form.querySelector('input[name="usar_layout"][type="checkbox"]');

        if (catEl && modelo.categoria) catEl.value = modelo.categoria;
        if (descEl && modelo.descricao) descEl.value = modelo.descricao;
        if (assEl) assEl.value = modelo.assunto || '';
        if (preEl) preEl.value = modelo.pre_cabecalho || '';
        if (usarLayoutCb) usarLayoutCb.checked = Boolean(modelo.usar_layout);
        if (textoPuroTextarea) textoPuroTextarea.value = modelo.corpo_texto || '';

        if (corpoTextarea) {
            corpoTextarea.value = modelo.corpo_html || '';
            if (window.GoldenSummernote && typeof window.GoldenSummernote.sincronizarDoTextarea === 'function') {
                window.GoldenSummernote.sincronizarDoTextarea(corpoTextarea);
            }
        }

        Object.entries(modelo.variaveis || {}).forEach(([k, d]) => {
            criarLinhaVariavel(k, String(d), exemplosBase[k] || '');
        });

        marcarEdicao();
        window.GoldenAvisos?.info(`Conteúdo de "${modelo.nome}" carregado no editor.`);
    });

    // Listeners em tempo real para todos os campos do formulário + editor Summernote
    ['input', 'change'].forEach((ev) => {
        form.addEventListener(ev, marcarEdicao);
    });

    if (corpoTextarea) {
        corpoTextarea.addEventListener('golden:summernote-input', marcarEdicao);
    }

    ['input', 'keyup', 'paste', 'cut'].forEach((ev) => {
        document.addEventListener(ev, (evento) => {
            const alvo = evento.target instanceof Element ? evento.target : null;
            if (!alvo || !corpoTextarea) {
                return;
            }
            const editor = alvo.closest('.note-editor');
            if (!editor) {
                return;
            }
            const ehNossoEditor = editor.previousElementSibling === corpoTextarea || form.contains(editor);
            if (!ehNossoEditor) {
                return;
            }
            if (alvo.classList.contains('note-editable') || alvo.closest('.note-editable') || alvo.classList.contains('note-codable')) {
                if (ev === 'paste' || ev === 'cut') {
                    window.setTimeout(marcarEdicao, 0);
                } else {
                    marcarEdicao();
                }
            }
        }, true);
    });

    document.getElementById('btnForcarPreview')?.addEventListener('click', () => {
        versaoEdicao++;
        atualizarPreviewInstantaneo(true);
        atualizarPreview();
    });

    document.querySelectorAll('[data-viewport-preview]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modo = btn.getAttribute('data-viewport-preview');
            document.querySelectorAll('[data-viewport-preview]').forEach((b) => {
                b.classList.remove('btn-golden', 'active');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-golden', 'active');
            iframe.classList.toggle('is-mobile', modo === 'mobile');
        });
    });

    window.addEventListener('DOMContentLoaded', () => {
        if (window.jQuery && corpoTextarea) {
            window.jQuery(corpoTextarea).on('summernote.change summernote.keyup summernote.paste summernote.codeview.toggled', marcarEdicao);
        }
        atualizarPreview();
    });
})();
</script>
@endsection
