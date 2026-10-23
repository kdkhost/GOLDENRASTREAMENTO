@extends('admin.layouts.app')
@php $editando = $produto->exists; @endphp
@section('title', $editando ? 'Editar produto' : 'Novo produto')
@section('page_title', $editando ? 'Editar: ' . $produto->nome : 'Novo produto')
@section('page_description', 'Preencha os dados que aparecerão na loja virtual.')

@section('page_actions')
<a href="{{ route('admin.catalogo.produtos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('admin.catalogo.produtos.update',$produto) : route('admin.catalogo.produtos.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label small">Nome *</label><input name="nome" class="form-control" value="{{ old('nome', $produto->nome) }}" required></div>
                    <div class="col-md-4"><label class="form-label small">SKU / código</label><input name="sku" class="form-control" value="{{ old('sku', $produto->sku) }}"></div>
                </div>
                <div class="mt-2"><label class="form-label small">Resumo (aparece no card)</label><input name="resumo" class="form-control" value="{{ old('resumo', $produto->resumo) }}" maxlength="255"></div>
                <div class="mt-2"><label class="form-label small">Descrição completa</label><textarea name="descricao" rows="6" class="form-control">{{ old('descricao', $produto->descricao) }}</textarea></div>
            </div></div>

            <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
                <h6 class="fw-bold mb-3">Preços (em reais)</h6>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label small">Preço *</label><input type="number" step="0.01" min="0" name="preco" class="form-control" value="{{ old('preco', $produto->preco_cents ? $produto->preco_cents/100 : '') }}" required></div>
                    <div class="col-md-4"><label class="form-label small">Preço promocional</label><input type="number" step="0.01" min="0" name="preco_promocional" class="form-control" value="{{ old('preco_promocional', $produto->preco_promocional_cents ? $produto->preco_promocional_cents/100 : '') }}"></div>
                    <div class="col-md-4"><label class="form-label small">Mensalidade (se plano)</label><input type="number" step="0.01" min="0" name="mensalidade" class="form-control" value="{{ old('mensalidade', $produto->mensalidade_cents ? $produto->mensalidade_cents/100 : '') }}"></div>
                </div>
                <div class="text-secondary small mt-2">Deixe promocional/mensalidade em branco quando não se aplicarem.</div>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
                <label class="form-label small">Categoria</label>
                <select name="category_id" class="form-select">
                    <option value="">Sem categoria</option>
                    @foreach($categorias as $c)<option value="{{ $c->id }}" @selected(old('category_id', $produto->category_id)==$c->id)>{{ $c->nome }}</option>@endforeach
                </select>

                <div class="mt-3"><label class="form-label small">Imagem</label>
                    @if($produto->imagem)<div class="mb-1"><img src="{{ asset('storage/'.$produto->imagem) }}" class="img-fluid rounded" style="max-height:120px"></div>@endif
                    <input type="file" name="imagem" accept="image/*" class="form-control form-control-sm">
                </div>

                <div class="row g-2 mt-3">
                    <div class="col-6"><label class="form-label small">Estoque</label><input type="number" name="estoque" min="0" class="form-control" value="{{ old('estoque', $produto->estoque ?? 0) }}"></div>
                    <div class="col-6"><label class="form-label small">Mínimo</label><input type="number" name="estoque_minimo" min="0" class="form-control" value="{{ old('estoque_minimo', $produto->estoque_minimo ?? 0) }}"></div>
                </div>
                <div class="mt-2"><label class="form-label small">Ordem de exibição</label><input type="number" name="ordem" min="0" class="form-control" value="{{ old('ordem', $produto->ordem ?? 0) }}"></div>

                <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" @checked(old('ativo', $produto->ativo ?? true))><label class="form-check-label small">Ativo (visível na loja)</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="destaque" value="1" @checked(old('destaque', $produto->destaque ?? false))><label class="form-check-label small">Destaque</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="controle_estoque" value="1" @checked(old('controle_estoque', $produto->controle_estoque ?? true))><label class="form-check-label small">Controlar estoque</label></div>
            </div></div>

            <button class="btn btn-golden w-100 mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar alterações' : 'Criar produto' }}</button>
        </div>
    </div>
</form>
@endsection
