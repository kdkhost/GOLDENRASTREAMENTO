@extends('admin.layouts.app')
@php $editando = $pagina->exists; @endphp
@section('title', $editando ? 'Editar página' : 'Nova página')
@section('page_title', $editando ? 'Editar: ' . $pagina->titulo : 'Nova página')
@section('page_description', 'Conteúdo institucional do site, gerenciado pelo CMS.')

@section('page_actions')
<a href="{{ route('admin.conteudo.paginas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.conteudo.paginas.update',$pagina) : route('admin.conteudo.paginas.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label small">Título *</label><input name="titulo" class="form-control" value="{{ old('titulo', $pagina->titulo) }}" required></div>
                    <div class="col-md-4"><label class="form-label small">Slug (URL /p/...)</label><input name="slug" class="form-control" value="{{ old('slug', $pagina->slug) }}" placeholder="gerado automaticamente"></div>
                </div>
                <div class="mt-2"><label class="form-label small">Eyebrow (linha de apoio)</label><input name="eyebrow" class="form-control" value="{{ old('eyebrow', $pagina->eyebrow) }}"></div>
                <div class="mt-2"><label class="form-label small">Introdução</label><textarea name="intro" rows="2" class="form-control">{{ old('intro', $pagina->intro) }}</textarea></div>
                <div class="mt-2"><label class="form-label small">Corpo (HTML permitido: títulos, parágrafos, listas, links, imagens)</label><textarea name="corpo" rows="14" class="form-control font-monospace" style="font-size:.85rem">{{ old('corpo', $pagina->corpo) }}</textarea></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
                <h6 class="fw-bold mb-3">Publicação</h6>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="publicada" value="1" @checked(old('publicada', $pagina->publicada ?? true))><label class="form-check-label small">Publicada (visível no site)</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="exibir_menu" value="1" @checked(old('exibir_menu', $pagina->exibir_menu ?? false))><label class="form-check-label small">Mostrar no menu</label></div>
                <div class="mt-3"><label class="form-label small">Ordem no menu</label><input type="number" name="ordem_menu" min="0" class="form-control form-control-sm" value="{{ old('ordem_menu', $pagina->ordem_menu ?? 0) }}"></div>
                <hr class="my-3">
                <label class="form-label small">Meta título (SEO)</label><input name="meta_title" class="form-control form-control-sm" value="{{ old('meta_title', $pagina->meta_title) }}">
                <div class="mt-2"><label class="form-label small">Meta descrição (SEO)</label><textarea name="meta_description" rows="2" class="form-control form-control-sm">{{ old('meta_description', $pagina->meta_description) }}</textarea></div>
            </div></div>
            <button class="btn btn-golden w-100 mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar página' : 'Criar página' }}</button>
        </div>
    </div>
</form>
@endsection
