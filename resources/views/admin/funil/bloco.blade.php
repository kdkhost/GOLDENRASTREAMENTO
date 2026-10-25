@extends('admin.layouts.app')
@php
    $editando = $bloco->exists;
    $tipo = (string) $bloco->tipo;
    $campos = \App\Models\FunnelBlock::camposPara($tipo);
    $colunas = \App\Models\FunnelBlock::rotulosDasColunas($tipo);
@endphp
@section('title', $editando ? 'Editar bloco' : 'Novo bloco')
@section('page_title', ($editando ? 'Editar bloco: ' : 'Novo bloco: ').\App\Models\FunnelBlock::rotuloTipo($tipo))
@section('page_description', $pagina->nome.' — '.$pagina->url())

@section('page_actions')
    <a href="{{ route('admin.funil.edit', $pagina) }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar à página
    </a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ $editando ? route('admin.funil.blocos.update', [$pagina, $bloco]) : route('admin.funil.blocos.store', $pagina) }}">
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo }}">

            <x-admin.card title="O bloco" icon="{{ \App\Models\FunnelBlock::iconeTipo($tipo) }}"
                :descricao="\App\Models\FunnelBlock::descricaoTipo($tipo)">
                @unless($editando)
                    <div class="mb-3">
                        <span class="form-label d-block">Tipo de bloco</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($tipos as $chave => $rotulo)
                                <a href="{{ route('admin.funil.blocos.create', [$pagina, 'tipo' => $chave]) }}"
                                    class="btn btn-sm {{ $chave === $tipo ? 'btn-golden' : 'btn-outline-secondary' }}">
                                    <i class="fas {{ \App\Models\FunnelBlock::iconeTipo($chave) }} me-1" aria-hidden="true"></i>{{ $rotulo }}
                                </a>
                            @endforeach
                        </div>
                        <span class="form-hint d-block mt-2">Cada tipo mostra só os campos que ele usa. Para trocar o tipo depois, crie o bloco novo e remova este.</span>
                    </div>
                @else
                    <div class="callout ok mb-3">
                        <span><i class="fas {{ \App\Models\FunnelBlock::iconeTipo($tipo) }} me-1" aria-hidden="true"></i>
                        Tipo: <strong>{{ \App\Models\FunnelBlock::rotuloTipo($tipo) }}</strong> — para outro tipo, crie um bloco novo e remova este.</span>
                    </div>
                @endunless

                <div class="row g-3">
                    @foreach($campos as $nome => $def)
                        @if($def['type'] === 'imagem')
                            <div class="col-12">
                                <label class="form-label">{{ $def['label'] }} <span class="form-hint">(opcional)</span></label>
                                @include('partials.upload', [
                                    'campo' => 'site_imagem',
                                    'name' => $nome,
                                    'valor' => old($nome, $bloco->{$nome}),
                                    'titulo' => $def['label'],
                                ])
                                @error($nome)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @else
                            @php
                                $valor = $nome === 'limite_em' ? (string) $bloco->limite_em?->format('Y-m-d\TH:i') : $bloco->{$nome};
                                $dica = match (true) {
                                    $nome === 'itens' && $colunas !== [] => 'Uma linha por item, colunas separadas por | — na ordem da barra ao lado.',
                                    in_array($nome, ['cta_destino', 'cta2_destino'], true) && $tipo !== 'video' => '#formulario rola até a captura; ou cole o link do WhatsApp.',
                                    default => null,
                                };
                            @endphp
                            <x-admin.field :name="$nome" :label="$def['label']" :type="$def['type']" :value="$valor"
                                col="{{ $def['type'] === 'textarea' ? 'col-12' : 'col-12 col-md-6' }}"
                                :rows="$def['rows'] ?? 4" :maxlength="$def['maxlength'] ?? null"
                                :placeholder="str_contains($nome, 'destino') && $tipo !== 'video' ? '#formulario' : null"
                                :hint="$dica" />
                        @endif
                    @endforeach

                    <x-admin.field name="ordem" type="number" label="Ordem na página" :value="$bloco->ordem" col="col-6 col-md-3" min="1" />
                    <x-admin.switch name="ativa" label="Mostrar este bloco na página" :checked="(bool) ($bloco->ativa ?? true)" col="col-6 col-md-9"
                        hint="Desligue para esconder sem perder o texto — o contador continua somando a visita inteira." />
                </div>
            </x-admin.card>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-golden"><i class="fas fa-save me-1" aria-hidden="true"></i> {{ $editando ? 'Salvar bloco' : 'Adicionar bloco' }}</button>
                <a href="{{ route('admin.funil.edit', $pagina) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Como preencher" icon="fa-circle-info">
            @if(\App\Models\FunnelBlock::dicaTipo($tipo))
                <p class="small mb-3">{{ \App\Models\FunnelBlock::dicaTipo($tipo) }}</p>
            @endif

            @if($colunas !== [])
                <span class="form-label d-block">Colunas de cada linha</span>
                <p class="small mb-2"><code>{{ implode(' | ', array_keys($colunas)) }}</code></p>
                <ul class="small list-unstyled mb-2">
                    @foreach($colunas as $chave => $rotulo)
                        <li class="mb-1"><code>{{ $chave }}</code> <span class="text-secondary">— {{ $rotulo }}</span></li>
                    @endforeach
                </ul>
                <p class="small text-secondary mb-0">Escreva uma linha por item, nessa ordem, separando as colunas com <code>|</code>. O que faltar fica vazio; o que sobrar é descartado.</p>
            @else
                <p class="small text-secondary mb-0">Este bloco não usa lista de itens — só os campos do formulário.</p>
            @endif
        </x-admin.card>

        <x-admin.card title="Ordem atual da página" icon="fa-layer-group" class="mt-3"
            descricao="A lista segue a ordem gravada; o número de cada bloco está no formulário.">
            <ul class="small list-unstyled mb-0">
                @foreach($pagina->blocks->sortBy('ordem') as $existente)
                    <li class="mb-1 d-flex align-items-center gap-2">
                        <i class="fas {{ \App\Models\FunnelBlock::iconeTipo($existente->tipo) }} text-gold flex-shrink-0" aria-hidden="true"></i>
                        <span>{{ \App\Models\FunnelBlock::rotuloTipo($existente->tipo) }}
                            <span class="text-secondary">— {{ $existente->titulo ?: '(sem título)' }}</span>
                        </span>
                        @if($existente->id === $bloco->id)
                            <i class="fas fa-arrow-right text-tone-gold ms-auto flex-shrink-0" aria-hidden="true" title="Este bloco"></i>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    </div>
</div>
@endsection
