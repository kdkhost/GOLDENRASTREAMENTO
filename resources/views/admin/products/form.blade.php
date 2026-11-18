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
                            <x-admin.switch name="cobrar_primeira_mensalidade" label="Cobrar 1ª mensalidade hoje" :checked="old('cobrar_primeira_mensalidade', $produto->cobrar_primeira_mensalidade ?? true)" hint="Se ativo, soma a 1ª mensalidade no ato da compra. Se desmarcado, fatura após 30 dias." />
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

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Operadora Padrão</label>
                        <select name="chip_padrao" class="form-select">
                            <option value="algar" @selected(old('chip_padrao', $produto->chip_padrao ?? 'algar') === 'algar')>Algar Multioperadora (Recomendado)</option>
                            <option value="vivo" @selected(old('chip_padrao', $produto->chip_padrao) === 'vivo')>Vivo M2M (Individual)</option>
                            <option value="claro" @selected(old('chip_padrao', $produto->chip_padrao) === 'claro')>Claro M2M (Individual)</option>
                            <option value="tim" @selected(old('chip_padrao', $produto->chip_padrao) === 'tim')>TIM M2M (Individual)</option>
                        </select>
                        <small class="form-text text-muted">Opção que já vem marcada por padrão.</small>
                    </div>
                    <x-admin.field name="chip_operadora" label="Texto descritivo do Chip / Conectividade" :value="$produto->chip_operadora" col="col-12 col-md-4" :opcional="true" placeholder="Ex.: Multi-operadora 4G (Vivo/Claro/TIM/Algar)" hint="Texto exibido na ficha técnica." />
                    <x-admin.field name="garantia" label="Garantia do Equipamento" :value="$produto->garantia" col="col-12 col-md-4" :opcional="true" placeholder="Ex.: Vitalícia no plano ou 12 meses" hint="Garantia oferecida ao cliente." />
                    <x-admin.field name="anatel_homologado" label="Homologação Anatel" :value="$produto->anatel_homologado" col="col-12 col-md-4" :opcional="true" placeholder="Ex.: Homologado Anatel" hint="Selo de conformidade técnica." />

                    <div class="col-12 mt-3 pt-3 border-top">
                        <label class="form-label fw-semibold mb-2"><i class="fas fa-list-check me-1 text-golden"></i> Recursos e Benefícios Inclusos</label>
                        <p class="small text-muted mb-3">Marque os recursos que acompanham este rastreador ou plano de monitoramento:</p>
                        @php
                            $recursosAtuais = (array) (old('recursos', $produto->recursos ?? []));
                            $catalogoRecursos = $recursosDisponiveis ?? \App\Models\Product::recursosDisponiveis();
                        @endphp
                        <div class="row g-2">
                            @foreach($catalogoRecursos as $chave => $rotulo)
                                <div class="col-12 col-md-6">
                                    <div class="form-check p-2 rounded border bg-light-subtle d-flex align-items-center">
                                        <input class="form-check-input ms-1 me-2" type="checkbox" name="recursos[]" value="{{ $chave }}" id="rec_{{ $chave }}" @checked(in_array($chave, $recursosAtuais, true))>
                                        <label class="form-check-label small fw-semibold text-dark mb-0 cursor-pointer" for="rec_{{ $chave }}">
                                            {{ $rotulo }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
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
    if (!form) return;
    form.addEventListener('submit', function() {
        if (window.GoldenSummernote && typeof window.GoldenSummernote.syncForm === 'function') {
            window.GoldenSummernote.syncForm(form);
        }
    });
})();
</script>
@endsection
