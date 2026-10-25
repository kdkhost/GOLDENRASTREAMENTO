@extends('admin.layouts.app')
@php $editando = $video->exists; @endphp
@section('title', $editando ? 'Editar tutorial' : 'Novo tutorial')
@section('page_title', $editando ? 'Editar tutorial' : 'Novo tutorial')
@section('page_description', 'O vídeo entra na galeria do site em /videos.')

@section('page_actions')
<a href="{{ route('admin.conteudo.videos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Tutorial em vídeo" icon="fa-play">
            <form method="POST" action="{{ $editando ? route('admin.conteudo.videos.update', $video) : route('admin.conteudo.videos.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="titulo" label="Título" :value="$video->titulo" :required="true" maxlength="160" placeholder="Ex.: Como usar o bloqueio e a âncora no app" />
                    <x-admin.field name="codigo" label="Link ou código do vídeo" :value="$video->codigo" :required="true" maxlength="200"
                        hint="Cole o endereço completo do YouTube (watch, youtu.be ou shorts); o código de 11 caracteres é salvo sozinho." />
                    <x-admin.field name="categoria" type="select" label="Categoria" :value="$video->categoria" :options="$categorias" :required="true" col="col-12 col-md-6" />
                    <x-admin.field name="ordem" type="number" label="Ordem de exibição" :value="$video->ordem ?? 0" col="col-12 col-md-6" min="0" />
                    <x-admin.field name="descricao" type="textarea" label="Descrição" :value="$video->descricao" :rows="3" maxlength="500" :opcional="true"
                        hint="Aparece abaixo do título, quando abrir o player." />
                    <x-admin.switch name="ativa" label="Ativa (visível no site)" :checked="$video->ativa ?? true" col="col-12 d-flex align-items-end pb-2" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar tutorial' : 'Adicionar tutorial' }}</button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Miniatura" icon="fa-image" descricao="É a capa que o visitante vê na galeria.">
            @if($video->codigo)
                <img class="w-100 rounded" src="{{ \App\Models\Video::thumb($video->codigo) }}" alt="Miniatura de {{ $video->titulo }}" loading="lazy">
                <p class="small text-secondary mt-2 mb-0">
                    <i class="fas fa-film me-1" aria-hidden="true"></i> {{ $video->codigo }}
                    · <a href="{{ 'https://www.youtube.com/watch?v=' . $video->codigo }}" target="_blank" rel="noopener">assistir no YouTube</a>
                </p>
            @else
                <p class="text-secondary mb-0">Salve o vídeo para ver a miniatura aqui.</p>
            @endif
        </x-admin.card>
    </div>
</div>
@endsection
