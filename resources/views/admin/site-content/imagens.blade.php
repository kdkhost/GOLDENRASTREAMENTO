@extends('admin.layouts.app')
@section('title', 'Imagens do site')
@section('page_title', 'Imagens do site')
@section('page_description', 'Cada fotografia exposta no site, pela área onde ela aparece. O que é enviado aqui vale no ar; sem envio, continua a foto do pacote.')

@section('page_actions')
    <a href="{{ route('admin.conteudo.textos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-heading me-1"></i> Textos das páginas</a>
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver o site</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Fotografias do site" :value="$total" icon="fa-photo-film" :pequeno="true" hint="Cada uma é um lugar do site, não um arquivo." />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Vêm do painel" :value="$no_painel" icon="fa-arrow-up-from-bracket" tone="green" :pequeno="true" hint="Foto enviada por aqui, no ar agora." />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Foto do pacote" :value="$no_pacote" icon="fa-box-open" tone="neutral" :pequeno="true" hint="Ninguém trocou: vale a imagem instalada." />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat label="Sem foto no ar" :value="$sem_foto" icon="fa-camera" :tone="$sem_foto > 0 ? 'amber' : 'neutral'" :pequeno="true" hint="Seção que só aparece depois do envio." />
    </div>
</div>

<form method="POST" action="{{ route('admin.conteudo.imagens.update') }}" class="mt-3">
    @csrf

    <div class="abas" data-abas="imagens-site">
        <div class="abas-barra">
            <ul class="nav abas-nav" role="tablist" aria-label="Áreas do site">
                @foreach($abas as $area)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" role="tab"
                            id="aba-{{ $area['id'] }}-botao" data-bs-toggle="tab" data-bs-target="#aba-{{ $area['id'] }}"
                            data-aba="{{ $area['id'] }}" aria-controls="aba-{{ $area['id'] }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="fas {{ $area['icone'] }}" aria-hidden="true"></i>
                            {{ $area['titulo'] }}
                            <span class="abas-contagem">{{ count($area['itens']) }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="tab-content">
            @foreach($abas as $area)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="aba-{{ $area['id'] }}"
                    data-aba-painel="{{ $area['id'] }}" role="tabpanel" aria-labelledby="aba-{{ $area['id'] }}-botao" tabindex="0">
                    <x-admin.card :title="$area['titulo']" :icon="$area['icone']" :descricao="$area['descricao']" class="mt-1">
                        <div class="row g-3">
                            @foreach($area['itens'] as $item)
                                @php
                                    $registro = $item['registro'];
                                    $medidas = collect([$registro['dimensao'] ?? null, $registro['proporcao'] ?? null])->filter()->implode(' · ');
                                @endphp
                                <div class="col-12 col-lg-6 col-xxl-4">
                                    <div class="slot">
                                        @if($item['onde'])
                                            <div class="slot-onde">{{ $item['onde'] }}</div>
                                        @endif

                                        <div class="slot-cabeca">
                                            <b>{{ $registro['titulo'] }}</b>
                                            <x-admin.status :rotulo="$item['estado_rotulo']" :tom="tom_por_status('slot_imagem', $item['estado'])" />
                                        </div>

                                        {{-- Moldura igual para todos os cards: foto quadrada aparece inteira; faixa larga (16:9, 20:9) aparece recortada, como fica no site. Um clique abre a foto grande. --}}
                                        @if($item['url'])
                                            <button type="button" class="slot-quadro @if((int) $registro['largura'] >= 1.6 * (int) $registro['altura'])faixa @endif"
                                                data-slot-imagem data-src="{{ $item['url'] }}" data-titulo="{{ $registro['titulo'] }}"
                                                data-pagina="{{ route($item['rota']) }}"
                                                aria-label="Ver “{{ $registro['titulo'] }}” em tamanho grande">
                                                <img src="{{ $item['url'] }}" alt="Imagem que está no ar: {{ $item['alt'] }}" loading="lazy">
                                            </button>
                                        @else
                                            <div class="slot-quadro vazio">
                                                <i class="fas fa-image" aria-hidden="true"></i>
                                                <span>esta seção aparece só depois do envio</span>
                                            </div>
                                        @endif

                                        @if($medidas)
                                            <div class="slot-meta"><i class="fas fa-ruler-combined me-1" aria-hidden="true"></i>{{ $medidas }}</div>
                                        @endif
                                        @if(!empty($registro['dica']))
                                            <p class="slot-dica">{{ $registro['dica'] }}</p>
                                        @endif
                                        @if($item['sem_arquivo'])
                                            <div class="slot-meta text-tone-amber"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
                                                a foto do pacote não está no servidor — envie uma aqui.</div>
                                        @endif

                                        @include('partials.upload', [
                                            'campo' => 'site_imagem',
                                            'name' => 'imagem['.$item['slot'].']',
                                            'valor' => old('imagem.'.$item['slot'], $item['enviada']),
                                            'titulo' => 'Trocar a foto',
                                            'dica' => $item['enviada'] ? null : ($item['url'] ? 'Sem envio, continua a foto atual.' : 'Sem envio, o título fica sobre o fundo padrão da página.'),
                                        ])
                                        @error('imagem.'.$item['slot'])<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                                        <x-admin.field name="alt[{{ $item['slot'] }}]" type="textarea" label="Texto alternativo" rows="2" maxlength="190"
                                            :hint="'leitor de tela, Google e a legenda quando a foto não carrega'"
                                            :value="$item['alt']" :opcional="true" />

                                        @if($item['enviada'])
                                            <x-admin.switch name="voltar_ao_padrao[{{ $item['slot'] }}]" label="Voltar à foto original do pacote" />
                                        @endif

                                        <div class="slot-pe">
                                            <a href="{{ route($item['rota']) }}" target="_blank" rel="noopener">
                                                <i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i>Ver esta foto no site
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>
            @endforeach
        </div>

        <div class="abas-salvar aderente">
            <span class="abas-salvar-note">Nada sobrescreve os arquivos do pacote: enviar guarda em <code>storage/site</code>, e <strong>Voltar à foto original</strong> apaga o envio.</span>
            <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar imagens do site</button>
        </div>
    </div>
</form>

{{-- Uma lupa para todas as fichas: o painel enche o src e abre — sem duplicar modal por slot. --}}
<div class="modal fade" id="slot-imagem" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="slot-imagem-titulo">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6" id="slot-imagem-titulo"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body slot-lupa">
                <img src="" alt="">
            </div>
            <div class="modal-footer">
                <a class="btn btn-sm btn-outline-secondary" data-slot-abrir target="_blank" rel="noopener">
                    <i class="fas fa-magnifying-glass-plus me-1" aria-hidden="true"></i>Abrir arquivo
                </a>
                <a class="btn btn-sm btn-outline-secondary" data-slot-pagina target="_blank" rel="noopener">
                    <i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i>Ver no site
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        'use strict';

        var lupa = document.getElementById('slot-imagem');
        if (!lupa || !window.bootstrap) {
            return;
        }

        var foto = lupa.querySelector('.slot-lupa img');
        var titulo = lupa.querySelector('.modal-title');
        var abrir = lupa.querySelector('[data-slot-abrir]');
        var pagina = lupa.querySelector('[data-slot-pagina]');

        document.querySelectorAll('[data-slot-imagem]').forEach(function (moldura) {
            moldura.addEventListener('click', function () {
                var origem = moldura.dataset.src;

                foto.src = origem;
                foto.alt = 'Foto no ar: ' + moldura.dataset.titulo;
                titulo.textContent = moldura.dataset.titulo;
                abrir.href = origem;
                pagina.href = moldura.dataset.pagina;
                window.bootstrap.Modal.getOrCreateInstance(lupa).show();
            });
        });

        // A foto grande some quando o modal fecha: a próxima abertura mostra a próxima ficha,
        // não a anterior meia-troca.
        lupa.addEventListener('hidden.bs.modal', function () {
            foto.removeAttribute('src');
        });
    })();
</script>
@endpush
