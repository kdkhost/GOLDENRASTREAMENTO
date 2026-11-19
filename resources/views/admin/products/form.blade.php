@extends('admin.layouts.app')
@php $editando = $produto->exists; @endphp
@section('title', $editando ? 'Editar produto' : 'Novo produto')
@section('page_title', $editando ? 'Editar: '.$produto->nome : 'Novo produto')
@section('page_description', 'Configure preços de venda, mensalidade por veículo, conectividade, recursos e fotos do produto.')

@section('page_actions')
@if($editando)
    <a href="{{ route('admin.catalogo.produtos.estoque.show', $produto) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-boxes-stacked me-1"></i> Movimentar estoque</a>
    <a href="{{ route('site.loja.produto', $produto->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info" title="Ver produto na loja virtual"><i class="fas fa-external-link me-1"></i> Ver na loja</a>
@endif
<button type="submit" form="formProduto" class="btn btn-sm btn-golden shadow-sm" id="btnSalvarTopo" onclick="var b=document.getElementById('btnSalvarProduto'); if(b){ b.click(); return false; }"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar alterações' : 'Criar produto' }}</button>
<a href="{{ route('admin.catalogo.produtos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
@if($errors->any())
    <div class="alert alert-danger mb-3" role="alert">
        <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i> Não foi possível salvar o produto:</h6>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="formProduto" method="POST" action="{{ $editando ? route('admin.catalogo.produtos.update', $produto, false) : route('admin.catalogo.produtos.store', [], false) }}" data-sem-ajax novalidate>
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Apresentação do Produto" icon="fa-box">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome do produto ou plano" :value="$produto->nome" col="col-12 col-md-8" :required="true" maxlength="190" placeholder="Ex.: Rastreador Veicular J16 4G ou Plano Frota" />
                    <x-admin.field name="sku" label="SKU / Código" :value="$produto->sku" col="col-12 col-md-4" maxlength="64" :opcional="true" placeholder="Ex.: J16-4G-VIVO" />
                    <x-admin.field name="resumo" label="Resumo rápido (aparece no card da vitrine)" :value="$produto->resumo" maxlength="255" :opcional="true" hint="Uma frase clara resumindo para quem é o produto." placeholder="Ex.: Rastreamento 24h com bloqueio pelo celular e chip M2M incluso." />
                    <x-admin.field name="descricao" type="textarea" label="Descrição detalhada" :value="$produto->descricao" rows="6" :opcional="true" hint="Explique o funcionamento, o que acompanha, diferenciais e compatibilidade veicular." />
                </div>
            </x-admin.card>

            <x-admin.card title="Preços, Cobrança e Mensalidades" icon="fa-tag" class="mt-3">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Tipo de Cobrança</label>
                        <select name="tipo_cobranca" class="form-select">
                            <option value="combo" @selected(old('tipo_cobranca', $produto->tipo_cobranca) === 'combo')>Combo (Aparelho/Adesão + Mensalidade)</option>
                            <option value="mensal" @selected(old('tipo_cobranca', $produto->tipo_cobranca ?? 'mensal') === 'mensal')>Plano Mensal (Mensalidade recorrente)</option>
                            <option value="unico" @selected(old('tipo_cobranca', $produto->tipo_cobranca) === 'unico')>Venda Única (Apenas Equipamento / Sem mensalidade)</option>
                        </select>
                        <small class="form-text text-muted">Define como o produto é apresentado e cobrado do cliente.</small>
                    </div>
                    <x-admin.field name="preco" type="text" inputmode="decimal" label="Valor do Aparelho (Venda à vista / Taxa de Adesão)" :value="$produto->preco_cents ? number_format($produto->preco_cents / 100, 2, ',', '') : ''" col="col-12 col-md-4" :opcional="true" placeholder="0,00" hint="Valor à vista cobrado hoje pelo equipamento. Deixe 0 para adesão grátis." />
                    <x-admin.field name="preco_promocional" type="text" inputmode="decimal" label="Preço promocional do aparelho" :value="$produto->preco_promocional_cents ? number_format($produto->preco_promocional_cents / 100, 2, ',', '') : ''" col="col-12 col-md-4" :opcional="true" placeholder="0,00" hint="Se preenchido, risca o valor de tabela do aparelho." />
                    <x-admin.field name="mensalidade" type="text" inputmode="decimal" label="Valor de Recorrência (Mensalidade por veículo/mês)" :value="$produto->mensalidade_cents ? number_format($produto->mensalidade_cents / 100, 2, ',', '') : ''" col="col-12 col-md-4" :opcional="true" placeholder="0,00" hint="Valor mensal cobrado por veículo para acesso à plataforma e dados móveis." />
                    <div class="col-12 col-md-4 d-flex align-items-center">
                        <div class="p-3 bg-light rounded border w-100">
                            <x-admin.switch name="cobrar_primeira_mensalidade" label="Cobrar 1ª mensalidade do chip no ato da compra" :checked="old('cobrar_primeira_mensalidade', (bool) ($produto->cobrar_primeira_mensalidade ?? true))" hint="Se ativo, soma o valor do produto + a 1ª mensalidade do chip escolhido hoje. Se desmarcado, a mensalidade é cobrada no próximo mês (recorrente)." />
                        </div>
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-center">
                        <div class="p-3 bg-light rounded border w-100">
                            <x-admin.switch name="sob_consulta" label="Preço sob consulta" :checked="old('sob_consulta', (bool) ($produto->sob_consulta ?? false))" hint="Oculta os preços na loja e orienta o cliente a falar com um consultor via WhatsApp, sem apagar os valores cadastrados." />
                        </div>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Rastreamento, Chip M2M e Conectividade" icon="fa-tower-cell" class="mt-3">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <x-admin.switch name="permite_escolha_chip" label="Permitir que o cliente escolha a operadora do chip durante o pedido" :checked="old('permite_escolha_chip', ($produto->id ? $produto->permiteEscolhaChip() : true))" hint="Exibe o seletor de operadora na página do produto, no carrinho e no checkout." />
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold mb-1"><i class="fas fa-sim-card me-1 text-golden"></i> Operadoras e Valores de Cada Chip</label>
                        <p class="small text-muted mb-2">Marque as operadoras disponíveis para este produto e defina o valor da mensalidade de cada chip (se deixar em branco, o sistema aplica o valor padrão de {{ brl($produto->mensalidade_cents ?? 5990) }}/mês):</p>
                        @php
                            $operadorasAtuais = (array) (old('operadoras_chip', $produto->operadoras_chip ?? ['algar', 'vivo', 'claro', 'tim']));
                            $precosAtuais = (array) (old('precos_chip', $produto->precos_chip ?? []));
                            $catalogoOperadoras = $operadorasDisponiveis ?? \App\Models\Product::operadorasDisponiveis();
                        @endphp
                        <div class="row g-2 mb-2">
                            @foreach($catalogoOperadoras as $chaveOp => $op)
                                @php
                                    $valCentsOp = isset($precosAtuais[$chaveOp]) && is_numeric($precosAtuais[$chaveOp]) ? (int) $precosAtuais[$chaveOp] : null;
                                @endphp
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded border bg-light-subtle h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check mb-0 d-flex align-items-center">
                                                <input class="form-check-input ms-1 me-2" type="checkbox" name="operadoras_chip[]" value="{{ $chaveOp }}" id="op_{{ $chaveOp }}" @checked(in_array($chaveOp, $operadorasAtuais, true))>
                                                <label class="form-check-label small fw-bold text-dark mb-0 cursor-pointer" for="op_{{ $chaveOp }}">
                                                    <i class="fas {{ $op['icone'] }} me-1 text-secondary"></i> {{ $op['rotulo'] }}
                                                </label>
                                            </div>
                                            <span class="badge {{ $op['tipo'] === 'multioperadora' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-info-subtle text-info-emphasis' }}" style="font-size:.7rem">{{ $op['destaque'] }}</span>
                                        </div>
                                        <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between gap-2">
                                            <span class="small text-muted fw-semibold"><i class="fas fa-tag me-1 text-golden"></i> Valor do chip:</span>
                                            <div class="input-group input-group-sm" style="max-width:170px">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="precos_chip[{{ $chaveOp }}]" class="form-control text-end fw-semibold" placeholder="{{ $produto->mensalidade_cents ? number_format($produto->mensalidade_cents / 100, 2, ',', '.') : '59,90' }}" value="{{ $valCentsOp !== null ? number_format($valCentsOp / 100, 2, ',', '.') : '' }}">
                                                <span class="input-group-text">/mês</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12 mt-2 pt-3 border-top">
                        <label class="form-label fw-semibold mb-1"><i class="fas fa-sliders me-1 text-golden"></i> Textos e Selos Exibidos no Site</label>
                        <p class="small text-muted mb-3">Estes textos são exibidos diretamente nos selos e na caixa do chip da página do produto, carrinho e checkout:</p>
                        @php
                            $valorChipOperadora = $produto->chip_operadora ?: 'Algar, Vivo, Claro e TIM';
                            $valorAnatel = $produto->anatel_homologado ?: 'Homologado Anatel';
                            $valorGarantia = $produto->garantia ?: 'Vitalícia no plano ou 12 meses';
                            $valorBeneficioChip = $produto->beneficio_chip_texto ?: 'Chip de telemetria M2M incluso no plano (R$ 0,00 de adesão do chip).';
                        @endphp
                        <div class="row g-3 align-items-start">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold" for="chip_padrao">Operadora Padrão</label>
                                <select name="chip_padrao" id="chip_padrao" class="form-select">
                                    <option value="algar" @selected(old('chip_padrao', $produto->chip_padrao ?? 'algar') === 'algar')>Chip Multioperadora (Algar, Claro, Vivo e TIM)</option>
                                    <option value="vivo" @selected(old('chip_padrao', $produto->chip_padrao) === 'vivo')>Vivo M2M (Individual)</option>
                                    <option value="claro" @selected(old('chip_padrao', $produto->chip_padrao) === 'claro')>Claro M2M (Individual)</option>
                                    <option value="tim" @selected(old('chip_padrao', $produto->chip_padrao) === 'tim')>TIM M2M (Individual)</option>
                                </select>
                                <small class="form-text text-muted">Opção que já vem selecionada por padrão na loja.</small>
                            </div>
                            <x-admin.field name="chip_operadora" label="Conectividade do Chip" :value="$valorChipOperadora" col="col-12 col-md-6" :opcional="true" placeholder="Ex.: Algar, Vivo, Claro e TIM" hint="Exibido no selo Chip Multioperadora na página do produto." />
                            <x-admin.field name="anatel_homologado" label="Homologação Anatel" :value="$valorAnatel" col="col-12 col-md-6" :opcional="true" placeholder="Ex.: Homologado Anatel" hint="Título do selo de conformidade técnica na página do produto." />
                            <x-admin.field name="garantia" label="Garantia do Equipamento" :value="$valorGarantia" col="col-12 col-md-6" :opcional="true" placeholder="Ex.: Vitalícia no plano ou 12 meses" hint="Exibido no selo de certificação e garantia na página do produto." />
                            <x-admin.field name="beneficio_chip_texto" label="Texto do Benefício do Chip (Site, Carrinho e Pedido)" :value="$valorBeneficioChip" col="col-12" :opcional="true" placeholder="Chip de telemetria M2M incluso no plano (R$ 0,00 de adesão do chip)." hint="Frase de benefício garantido exibida na seleção do chip, no carrinho e no checkout." />
                        </div>
                    </div>

                    <div class="col-12 mt-3 pt-3 border-top">
                        <input type="hidden" name="recursos_estruturado" value="1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div>
                                <label class="form-label fw-semibold mb-0"><i class="fas fa-list-check me-1 text-golden"></i> Recursos e Benefícios Inclusos</label>
                                <p class="small text-muted mb-0">Cadastre, edite, defina valores ou remova os recursos específicos deste produto.</p>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @php
                                    $catalogoRecursos = $recursosDisponiveis ?? \App\Models\Product::recursosDisponiveis();
                                    $recursosItensAtuais = old('recursos_itens');
                                    if ($recursosItensAtuais === null) {
                                        $recursosItensAtuais = $produto->recursosEstruturados(false);
                                    }
                                @endphp
                                <div class="input-group input-group-sm" style="width:auto;max-width:300px">
                                    <select id="selectModeloRecurso" class="form-select form-select-sm">
                                        <option value="">Adicionar modelo pronto...</option>
                                        @foreach($catalogoRecursos as $rotuloModelo)
                                            <option value="{{ $rotuloModelo }}">{{ \Illuminate\Support\Str::limit($rotuloModelo, 44) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="btnAddModeloRecurso" title="Inserir modelo selecionado">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRecursoItem">
                                    <i class="fas fa-plus me-1"></i> Novo recurso
                                </button>
                            </div>
                        </div>

                        <div id="recursosItensContainer" class="d-flex flex-column gap-2 mt-3">
                            @foreach((array) $recursosItensAtuais as $idxRec => $rec)
                                @php
                                    $tipoValRec = $rec['tipo_valor'] ?? 'incluso';
                                    $valRec = isset($rec['valor'])
                                        ? $rec['valor']
                                        : (isset($rec['valor_cents']) && is_numeric($rec['valor_cents']) && (int) $rec['valor_cents'] > 0 ? number_format(((int) $rec['valor_cents']) / 100, 2, ',', '.') : '');
                                    $ativoRec = array_key_exists('ativo', (array) $rec) ? (bool) $rec['ativo'] : true;
                                    $usaValor = in_array($tipoValRec, ['mensal', 'unico'], true);
                                @endphp
                                <div class="recurso-item-card p-3 rounded border bg-light-subtle" data-recurso-index="{{ $idxRec }}">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small fw-semibold mb-1">Recurso / Benefício</label>
                                            <input type="text" name="recursos_itens[{{ $idxRec }}][titulo]" class="form-control form-control-sm" value="{{ $rec['titulo'] ?? '' }}" placeholder="Ex.: Aplicativo Celular (Android e iOS)">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label small fw-semibold mb-1">Tipo de valor</label>
                                            <select name="recursos_itens[{{ $idxRec }}][tipo_valor]" class="form-select form-select-sm recurso-tipo-valor">
                                                <option value="incluso" @selected($tipoValRec === 'incluso')>Incluso (grátis)</option>
                                                <option value="sem_valor" @selected($tipoValRec === 'sem_valor')>Sem selo de valor</option>
                                                <option value="mensal" @selected($tipoValRec === 'mensal')>Valor mensal (/mês)</option>
                                                <option value="unico" @selected($tipoValRec === 'unico')>Valor único</option>
                                            </select>
                                        </div>
                                        <div class="col-8 col-md-2">
                                            <label class="form-label small fw-semibold mb-1">Valor (R$)</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="recursos_itens[{{ $idxRec }}][valor]" class="form-control text-end recurso-valor-input" value="{{ $valRec }}" placeholder="0,00" @disabled(!$usaValor)>
                                            </div>
                                        </div>
                                        <div class="col-4 col-md-2 d-flex align-items-center justify-content-end gap-2 pb-1">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="recursos_itens[{{ $idxRec }}][ativo]" value="1" id="rec_ativo_{{ $idxRec }}" @checked($ativoRec) title="Ativo">
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remover-recurso" title="Remover recurso">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="recursos_itens[{{ $idxRec }}][detalhe]" class="form-control form-control-sm" value="{{ $rec['detalhe'] ?? '' }}" placeholder="Detalhe ou observação opcional (ex.: Sem custo de ativação, 90 dias de histórico, etc.)">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div id="recursosItensVazio" class="text-center py-3 px-2 mt-2 rounded border border-dashed text-muted small @if(!empty($recursosItensAtuais)) d-none @endif">
                            <i class="fas fa-list-check me-1"></i> Nenhum recurso cadastrado neste produto. Clique em <strong>Novo recurso</strong> ou escolha um <strong>modelo pronto</strong> acima.
                        </div>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Pacotes Adicionais de Alertas (Opcional no Carrinho)" icon="fa-bell" class="mt-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <p class="small text-muted mb-0">Configure pacotes opcionais com valor mensal recorrente que o cliente poderá adicionar no carrinho de compras deste produto.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddPacoteAlerta">
                        <i class="fas fa-plus me-1"></i> Adicionar pacote de alertas
                    </button>
                </div>

                @php
                    $pacotesAlertasAtuais = (array) old('pacotes_alertas', $produto->pacotes_alertas ?? []);
                @endphp

                <div id="pacotesAlertasContainer" class="d-flex flex-column gap-3">
                    @foreach($pacotesAlertasAtuais as $idx => $pacote)
                        @php
                            $valPacote = isset($pacote['valor'])
                                ? $pacote['valor']
                                : (isset($pacote['valor_cents']) && is_numeric($pacote['valor_cents']) ? number_format(((int) $pacote['valor_cents']) / 100, 2, ',', '.') : '');
                            $servicosPacote = is_array($pacote['servicos'] ?? null)
                                ? implode("\n", $pacote['servicos'])
                                : (string) ($pacote['servicos'] ?? '');
                            $ativoPacote = array_key_exists('ativo', (array) $pacote) ? (bool) $pacote['ativo'] : true;
                        @endphp
                        <div class="pacote-alerta-item p-3 rounded border bg-light-subtle" data-pacote-index="{{ $idx }}">
                            <input type="hidden" name="pacotes_alertas[{{ $idx }}][id]" value="{{ $pacote['id'] ?? '' }}">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold mb-1">Nome do pacote</label>
                                    <input type="text" name="pacotes_alertas[{{ $idx }}][nome]" class="form-control form-control-sm" value="{{ $pacote['nome'] ?? '' }}" placeholder="Ex.: Alertas WhatsApp + SMS">
                                </div>
                                <div class="col-8 col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Valor recorrente</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" name="pacotes_alertas[{{ $idx }}][valor]" class="form-control text-end fw-semibold" value="{{ $valPacote }}" placeholder="14,90">
                                        <span class="input-group-text">/mês</span>
                                    </div>
                                </div>
                                <div class="col-4 col-md-2 d-flex flex-column align-items-end justify-content-between pt-1">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="pacotes_alertas[{{ $idx }}][ativo]" value="1" id="pacote_ativo_{{ $idx }}" @checked($ativoPacote)>
                                        <label class="form-check-label small" for="pacote_ativo_{{ $idx }}">Ativo</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remover-pacote" title="Remover pacote">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1">Serviços inclusos neste pacote</label>
                                    <textarea name="pacotes_alertas[{{ $idx }}][servicos]" class="form-control form-control-sm" rows="2" placeholder="Informe um serviço por linha ou separados por vírgula. Ex.:&#10;Alertas instantâneos no WhatsApp&#10;Alertas de ignição e movimento via SMS">{{ $servicosPacote }}</textarea>
                                    <small class="form-text text-muted">Cada linha (ou item separado por vírgula) aparece como benefício listado para o cliente no carrinho.</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="pacotesAlertasVazio" class="text-center py-3 px-2 rounded border border-dashed text-muted small @if(!empty($pacotesAlertasAtuais)) d-none @endif">
                    <i class="fas fa-bell-slash me-1"></i> Nenhum pacote adicional de alertas configurado para este produto. Clique em <strong>Adicionar pacote de alertas</strong> para criar.
                </div>
            </x-admin.card>

            <x-admin.card title="Dimensões e Embalagem (Frete Correios / Melhor Envio)" icon="fa-truck-fast" class="mt-3">
                <p class="small text-muted mb-3">Dimensões do pacote para cálculo de frete no carrinho. Se deixar em branco, o sistema aplica os padrões gerais da loja.</p>
                <div class="row g-3">
                    <x-admin.field name="peso_gramas" type="number" label="Peso (gramas)" :value="$produto->peso_gramas" col="col-6 col-md-3" :opcional="true" min="1" max="100000" placeholder="Ex.: 250" hint="250g = 0,25 kg" />
                    <x-admin.field name="comprimento_cm" type="number" label="Comprimento (cm)" :value="$produto->comprimento_cm" col="col-6 col-md-3" :opcional="true" min="1" max="300" placeholder="Ex.: 18" />
                    <x-admin.field name="largura_cm" type="number" label="Largura (cm)" :value="$produto->largura_cm" col="col-6 col-md-3" :opcional="true" min="1" max="300" placeholder="Ex.: 14" />
                    <x-admin.field name="altura_cm" type="number" label="Altura (cm)" :value="$produto->altura_cm" col="col-6 col-md-3" :opcional="true" min="1" max="300" placeholder="Ex.: 6" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Publicação e Categoria" icon="fa-store">
                <div class="row g-3">
                    <x-admin.field name="category_id" type="select" label="Categoria" :value="$produto->category_id" :options="$categorias->pluck('nome', 'id')->all()" empty="Sem categoria" />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$produto->ordem ?? 0" col="col-6" min="0" hint="Menor aparece primeiro." />
                    <div class="col-6 d-flex flex-column justify-content-end pb-2">
                        <x-admin.switch name="ativo" label="Ativo na vitrine" :checked="$produto->ativo ?? true" />
                        <x-admin.switch name="destaque" label="Destaque na vitrine" :checked="$produto->destaque ?? false" col="col-12" />
                    </div>
                </div>
                <div class="border-top mt-3 pt-3">
                    <x-admin.switch name="controle_estoque" label="Controlar estoque" :checked="$produto->controle_estoque ?? true" hint="Com controle ativo, a venda bloqueia quando o estoque zera. Para planos, deixe desmarcado." />
                    <div class="row g-3 mt-1">
                        <x-admin.field name="estoque" type="number" label="Estoque disponível" :value="$produto->estoque ?? 0" col="col-6" min="0" />
                        <x-admin.field name="estoque_minimo" type="number" label="Estoque mínimo" :value="$produto->estoque_minimo ?? 0" col="col-6" min="0" />
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Mídia do Produto" icon="fa-photo-film" class="mt-3">
                @php
                    $imagemAtual = old('imagem', $produto->imagem ?: ($editando ? \App\Models\Product::imagemPadraoPorSlug($produto->slug, $produto->category?->slug) : null));
                    $galeriaAtual = old('galeria', $produto->galeria ?? []);
                    $previewFotos = array_values(array_unique(array_filter(array_merge([$imagemAtual], is_array($galeriaAtual) ? $galeriaAtual : []))));
                @endphp
                @if($editando && count($previewFotos) > 0)
                    <div class="mb-3">
                        <div class="small fw-semibold text-secondary mb-2">Fotos atuais publicadas ({{ count($previewFotos) }})</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($previewFotos as $idx => $foto)
                                <a href="{{ produto_foto_url($foto) }}" target="_blank" rel="noopener" class="position-relative d-block rounded border overflow-hidden" style="width:68px;height:68px;background:#f8fafc" title="{{ $idx === 0 ? 'Foto principal' : 'Galeria #'.($idx + 1) }}">
                                    <img src="{{ produto_foto_url($foto) }}" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
                                    @if($idx === 0)
                                        <span class="badge bg-dark text-warning position-absolute bottom-0 start-0 end-0 rounded-0" style="font-size:.6rem;padding:2px">Capa</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @include('partials.upload', [
                    'campo' => 'produto_imagem',
                    'name' => 'imagem',
                    'valor' => $imagemAtual,
                    'titulo' => 'Foto Principal (Capa)',
                ])
                <div class="mt-3">
                    @include('partials.upload', [
                        'campo' => 'produto_galeria',
                        'name' => 'galeria',
                        'valor' => $galeriaAtual,
                        'multiplo' => true,
                        'titulo' => 'Fotos adicionais (Galeria)',
                        'dica' => 'Adicione fotos de outros ângulos, detalhes e embalagem.',
                    ])
                </div>
                <div class="mt-3">
                    @include('partials.upload', [
                        'campo' => 'produto_video',
                        'name' => 'video',
                        'valor' => old('video', $produto->video),
                        'dica' => 'Aparece na aba de vídeo do produto na loja.',
                    ])
                </div>
            </x-admin.card>

            <div class="d-flex flex-column gap-2 mt-3">
                <button type="submit" class="btn btn-golden w-100 py-2 fw-semibold shadow-sm" id="btnSalvarProduto">
                    <i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar alterações' : 'Criar produto' }}
                </button>
                <button type="submit" name="salvar_e_continuar" value="1" class="btn btn-outline-secondary w-100 btn-sm" id="btnSalvarContinuar">
                    <i class="fas fa-floppy-disk me-1"></i> {{ $editando ? 'Salvar e continuar editando' : 'Criar e continuar editando' }}
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    var form = document.getElementById('formProduto');
    if (form) {
        form.addEventListener('submit', function() {
            if (window.GoldenSummernote && typeof window.GoldenSummernote.syncForm === 'function') {
                window.GoldenSummernote.syncForm(form);
            }
        });
    }

    var container = document.getElementById('pacotesAlertasContainer');
    var btnAdd = document.getElementById('btnAddPacoteAlerta');
    var vazio = document.getElementById('pacotesAlertasVazio');
    if (!container || !btnAdd) return;

    var proximoIdx = container.querySelectorAll('.pacote-alerta-item').length;

    function atualizarEstadoVazio() {
        if (!vazio) return;
        var tem = container.querySelectorAll('.pacote-alerta-item').length > 0;
        vazio.classList.toggle('d-none', tem);
    }

    btnAdd.addEventListener('click', function() {
        var idx = proximoIdx++;
        var div = document.createElement('div');
        div.className = 'pacote-alerta-item p-3 rounded border bg-light-subtle';
        div.setAttribute('data-pacote-index', String(idx));
        div.innerHTML =
            '<input type="hidden" name="pacotes_alertas[' + idx + '][id]" value="">' +
            '<div class="row g-3 align-items-start">' +
                '<div class="col-12 col-md-6">' +
                    '<label class="form-label small fw-semibold mb-1">Nome do pacote</label>' +
                    '<input type="text" name="pacotes_alertas[' + idx + '][nome]" class="form-control form-control-sm" placeholder="Ex.: Alertas WhatsApp + SMS">' +
                '</div>' +
                '<div class="col-8 col-md-4">' +
                    '<label class="form-label small fw-semibold mb-1">Valor recorrente</label>' +
                    '<div class="input-group input-group-sm">' +
                        '<span class="input-group-text">R$</span>' +
                        '<input type="text" name="pacotes_alertas[' + idx + '][valor]" class="form-control text-end fw-semibold" placeholder="14,90">' +
                        '<span class="input-group-text">/mês</span>' +
                    '</div>' +
                '</div>' +
                '<div class="col-4 col-md-2 d-flex flex-column align-items-end justify-content-between pt-1">' +
                    '<div class="form-check form-switch mb-1">' +
                        '<input class="form-check-input" type="checkbox" name="pacotes_alertas[' + idx + '][ativo]" value="1" id="pacote_ativo_' + idx + '" checked>' +
                        '<label class="form-check-label small" for="pacote_ativo_' + idx + '">Ativo</label>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remover-pacote" title="Remover pacote">' +
                        '<i class="fas fa-trash-can"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="col-12">' +
                    '<label class="form-label small fw-semibold mb-1">Serviços inclusos neste pacote</label>' +
                    '<textarea name="pacotes_alertas[' + idx + '][servicos]" class="form-control form-control-sm" rows="2" placeholder="Informe um serviço por linha ou separados por vírgula. Ex.:\nAlertas instantâneos no WhatsApp\nAlertas de ignição e movimento via SMS"></textarea>' +
                    '<small class="form-text text-muted">Cada linha (ou item separado por vírgula) aparece como benefício listado para o cliente no carrinho.</small>' +
                '</div>' +
            '</div>';
        container.appendChild(div);
        atualizarEstadoVazio();
        var inputNome = div.querySelector('input[type="text"]');
        if (inputNome) inputNome.focus();
    });

    container.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-remover-pacote');
        if (!btn) return;
        var card = btn.closest('.pacote-alerta-item');
        if (card) {
            card.remove();
            atualizarEstadoVazio();
        }
    });

    // Gerenciador individual de Recursos e Benefícios Inclusos
    var recContainer = document.getElementById('recursosItensContainer');
    var btnAddRec = document.getElementById('btnAddRecursoItem');
    var btnAddModelo = document.getElementById('btnAddModeloRecurso');
    var selectModelo = document.getElementById('selectModeloRecurso');
    var recVazio = document.getElementById('recursosItensVazio');

    if (recContainer && btnAddRec) {
        var proximoRecIdx = recContainer.querySelectorAll('.recurso-item-card').length;

        function atualizarRecVazio() {
            if (!recVazio) return;
            var tem = recContainer.querySelectorAll('.recurso-item-card').length > 0;
            recVazio.classList.toggle('d-none', tem);
        }

        function criarCardRecurso(tituloInicial) {
            var idx = proximoRecIdx++;
            var div = document.createElement('div');
            div.className = 'recurso-item-card p-3 rounded border bg-light-subtle';
            div.setAttribute('data-recurso-index', String(idx));
            var safeTitle = (tituloInicial || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            div.innerHTML =
                '<div class="row g-2 align-items-end">' +
                    '<div class="col-12 col-md-5">' +
                        '<label class="form-label small fw-semibold mb-1">Recurso / Benefício</label>' +
                        '<input type="text" name="recursos_itens[' + idx + '][titulo]" class="form-control form-control-sm" value="' + safeTitle + '" placeholder="Ex.: Aplicativo Celular (Android e iOS)">' +
                    '</div>' +
                    '<div class="col-12 col-md-3">' +
                        '<label class="form-label small fw-semibold mb-1">Tipo de valor</label>' +
                        '<select name="recursos_itens[' + idx + '][tipo_valor]" class="form-select form-select-sm recurso-tipo-valor">' +
                            '<option value="incluso" selected>Incluso (grátis)</option>' +
                            '<option value="sem_valor">Sem selo de valor</option>' +
                            '<option value="mensal">Valor mensal (/mês)</option>' +
                            '<option value="unico">Valor único</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="col-8 col-md-2">' +
                        '<label class="form-label small fw-semibold mb-1">Valor (R$)</label>' +
                        '<div class="input-group input-group-sm">' +
                            '<span class="input-group-text">R$</span>' +
                            '<input type="text" name="recursos_itens[' + idx + '][valor]" class="form-control text-end recurso-valor-input" placeholder="0,00" disabled>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-4 col-md-2 d-flex align-items-center justify-content-end gap-2 pb-1">' +
                        '<div class="form-check form-switch mb-0">' +
                            '<input class="form-check-input" type="checkbox" name="recursos_itens[' + idx + '][ativo]" value="1" id="rec_ativo_' + idx + '" checked title="Ativo">' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remover-recurso" title="Remover recurso">' +
                            '<i class="fas fa-trash-can"></i>' +
                        '</button>' +
                    '</div>' +
                    '<div class="col-12">' +
                        '<input type="text" name="recursos_itens[' + idx + '][detalhe]" class="form-control form-control-sm" placeholder="Detalhe ou observação opcional (ex.: Sem custo de ativação, 90 dias de histórico, etc.)">' +
                    '</div>' +
                '</div>';
            recContainer.appendChild(div);
            atualizarRecVazio();
            var inp = div.querySelector('input[type="text"]');
            if (inp && !tituloInicial) inp.focus();
        }

        btnAddRec.addEventListener('click', function() {
            criarCardRecurso('');
        });

        if (btnAddModelo && selectModelo) {
            btnAddModelo.addEventListener('click', function() {
                if (selectModelo.value) {
                    criarCardRecurso(selectModelo.value);
                    selectModelo.value = '';
                }
            });
        }

        recContainer.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-remover-recurso');
            if (!btn) return;
            var card = btn.closest('.recurso-item-card');
            if (card) {
                card.remove();
                atualizarRecVazio();
            }
        });

        recContainer.addEventListener('change', function(e) {
            if (!e.target.matches('.recurso-tipo-valor')) return;
            var card = e.target.closest('.recurso-item-card');
            if (!card) return;
            var valInput = card.querySelector('.recurso-valor-input');
            if (!valInput) return;
            var precisaValor = (e.target.value === 'mensal' || e.target.value === 'unico');
            valInput.disabled = !precisaValor;
            if (!precisaValor) {
                valInput.value = '';
            } else {
                valInput.focus();
            }
        });
    }
})();
</script>
@endsection
