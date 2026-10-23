@extends('admin.layouts.app')
@php $editando = $pagina->exists; @endphp
@section('title', $editando ? 'Editar página' : 'Nova página')
@section('page_title', $editando ? 'Editar: '.$pagina->titulo : 'Nova página')
@section('page_description', 'Conteúdo institucional publicado em /p/slug pelo CMS.')

@section('page_actions')
@if($editando && $pagina->publicada)
    <a href="{{ route('site.pagina', $pagina->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver no site</a>
@endif
<a href="{{ route('admin.conteudo.paginas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.conteudo.paginas.update', $pagina) : route('admin.conteudo.paginas.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Conteúdo" icon="fa-file-lines">
                <div class="row g-3">
                    <x-admin.field name="titulo" label="Título" :value="$pagina->titulo" col="col-12 col-md-8" :required="true" maxlength="190" />
                    <x-admin.field name="slug" label="Slug" :value="$pagina->slug" col="col-12 col-md-4" maxlength="190" :opcional="true" hint="Fica em /p/slug. Em branco, geramos a partir do título." />
                    <x-admin.field name="eyebrow" label="Eyebrow" :value="$pagina->eyebrow" maxlength="120" :opcional="true" hint="A linha curta acima do título, em destaque." />
                    <x-admin.field name="intro" type="textarea" label="Introdução" :value="$pagina->intro" rows="2" :opcional="true" />
                    <x-admin.field name="corpo" type="textarea" label="Corpo da página" :value="$pagina->corpo" rows="16" :opcional="true"
                        class="font-monospace" hint="HTML simples é permitido: títulos, parágrafos, listas, links e imagens." />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Publicação" icon="fa-rocket">
                <div class="row g-3">
                    <x-admin.switch name="publicada" label="Publicada (visível no site)" :checked="$pagina->publicada ?? true" />
                    <x-admin.switch name="exibir_menu" label="Mostrar no menu do site" :checked="$pagina->exibir_menu ?? false" />
                    <x-admin.field name="ordem_menu" type="number" label="Ordem no menu" :value="$pagina->ordem_menu ?? 0" col="col-6" min="0" hint="Menor aparece primeiro." />
                </div>
            </x-admin.card>

            <x-admin.card title="SEO" icon="fa-magnifying-glass" class="mt-3">
                <div class="row g-3">
                    <x-admin.field name="meta_title" label="Meta título" :value="$pagina->meta_title" maxlength="190" :opcional="true" hint="Aparece na aba do navegador e no Google." />
                    <x-admin.field name="meta_description" type="textarea" label="Meta descrição" :value="$pagina->meta_description" rows="3" :opcional="true" hint="Resumo de até 160 caracteres que aparece no resultado da busca." />
                </div>
            </x-admin.card>

            <button class="btn btn-golden w-100 mt-3"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar página' : 'Criar página' }}</button>
        </div>
    </div>
</form>
@endsection
