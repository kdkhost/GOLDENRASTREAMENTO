@extends('admin.layouts.app')
@php $editando = $produto->exists; @endphp
@section('title', $editando ? 'Editar produto' : 'Novo produto')
@section('page_title', $editando ? 'Editar: '.$produto->nome : 'Novo produto')
@section('page_description', 'O que for preenchido aqui é o que o cliente vê na loja virtual.')

@section('page_actions')
@if($editando)
    <a href="{{ route('admin.catalogo.produtos.estoque.show', $produto) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-boxes-stacked me-1"></i> Movimentar estoque</a>
@endif
<a href="{{ route('admin.catalogo.produtos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.catalogo.produtos.update', $produto) : route('admin.catalogo.produtos.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Apresentação" icon="fa-box">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$produto->nome" col="col-12 col-md-8" :required="true" maxlength="190" />
                    <x-admin.field name="sku" label="SKU / código" :value="$produto->sku" col="col-12 col-md-4" maxlength="64" :opcional="true" />
                    <x-admin.field name="resumo" label="Resumo (aparece no card da loja)" :value="$produto->resumo" maxlength="255" :opcional="true" hint="Uma frase curta dizendo para quem é o produto." />
                    <x-admin.field name="descricao" type="textarea" label="Descrição completa" :value="$produto->descricao" rows="7" :opcional="true" />
                </div>
            </x-admin.card>

            <x-admin.card title="Preços (em reais)" icon="fa-tag" class="mt-3">
                <div class="row g-3">
                    <x-admin.field name="preco" type="number" label="Preço à vista" :value="$produto->preco_cents ? $produto->preco_cents / 100 : null" col="col-4" step="0.01" min="0" :opcional="true" placeholder="0,00" />
                    <x-admin.field name="preco_promocional" type="number" label="Preço promocional" :value="$produto->preco_promocional_cents ? $produto->preco_promocional_cents / 100 : null" col="col-4" step="0.01" min="0" :opcional="true" />
                    <x-admin.field name="mensalidade" type="number" label="Mensalidade" :value="$produto->mensalidade_cents ? $produto->mensalidade_cents / 100 : null" col="col-4" step="0.01" min="0" :opcional="true" />
                </div>
                <div class="mt-3 pt-2 border-top">
                    <x-admin.switch name="sob_consulta" label="Preço sob consulta (ocultar valores)" :checked="old('sob_consulta', $editando && (int) $produto->preco_cents === 0 && !(int) $produto->mensalidade_cents)" hint="Deixe Preço à vista em branco para planos apenas mensais, ou ative esta opção para exibir 'Consulte' na loja." />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Publicação" icon="fa-store">
                <div class="row g-3">
                    <x-admin.field name="category_id" type="select" label="Categoria" :value="$produto->category_id" :options="$categorias->pluck('nome', 'id')->all()" empty="Sem categoria" />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$produto->ordem ?? 0" col="col-6" min="0" hint="Menor aparece primeiro." />
                    <div class="col-6 d-flex flex-column justify-content-end pb-2">
                        <x-admin.switch name="ativo" label="Ativo (visível na loja)" :checked="$produto->ativo ?? true" />
                        <x-admin.switch name="destaque" label="Destaque na vitrine" :checked="$produto->destaque ?? false" col="col-12" />
                    </div>
                </div>
                <div class="border-top mt-3 pt-3">
                    <x-admin.switch name="controle_estoque" label="Controlar estoque" :checked="$produto->controle_estoque ?? true" hint="Com controle ligado, a loja bloqueia a venda quando o saldo zera." />
                    <div class="row g-3 mt-1">
                        <x-admin.field name="estoque" type="number" label="Estoque" :value="$produto->estoque ?? 0" col="col-6" min="0" />
                        <x-admin.field name="estoque_minimo" type="number" label="Mínimo" :value="$produto->estoque_minimo ?? 0" col="col-6" min="0" />
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
                        <div class="small fw-semibold text-secondary mb-2">Imagens atuais ({{ count($previewFotos) }})</div>
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
                        'dica' => 'Aparece no vídeo da página do produto na loja.',
                    ])
                </div>
            </x-admin.card>

            <button class="btn btn-golden w-100 mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar alterações' : 'Criar produto' }}</button>
        </div>
    </div>
</form>
@endsection
