@extends('admin.layouts.app')
@section('title', 'Imagens do site')
@section('page_title', 'Imagens do site')
@section('page_description', 'Cada fotografia exposta no site, pela página onde aparece. O que for enviado aqui vale no ar; sem envio, a foto do pacote continua.')

@section('page_actions')
    <a href="{{ route('admin.conteudo.textos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-heading me-1"></i> Textos das páginas</a>
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver o site</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.conteudo.imagens.update') }}">
    @csrf

    <div class="callout">
        <span>
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
            {{ $total }} imagens do site gerenciadas aqui. Nada é gravado por cima dos arquivos do
            pacote: enviar uma foto guarda o arquivo em <code>storage/site</code> e marcar
            <strong>Voltar à foto original</strong> apaga o envio — a imagem do pacote volta a valer na hora.
        </span>
    </div>

    @foreach($grupos as $grupo)
        <x-admin.card :title="$grupo['pagina']" icon="fa-photo-film" class="mt-3">
            <x-slot:actions>
                <a href="{{ route($grupo['slots'][0]['rota']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-up-right-from-square me-1"></i> Ver esta página
                </a>
            </x-slot:actions>

            <div class="row g-3">
                @foreach($grupo['slots'] as $item)
                    @php
                        $slot = $item['slot'];
                        $registro = $item['registro'];
                        $linhas = collect([$registro['dimensao'] ?? null, $registro['proporcao'] ?? null])
                            ->filter()->implode(' · ');
                    @endphp
                    <div class="col-12 col-lg-6 col-xxl-4">
                        <div class="slot">
                            <div class="slot-cabeca">
                                <b>{{ $registro['titulo'] }}</b>
                                <span class="slot-selo {{ $item['enviada'] ? 'tone-gold' : 'tone-neutral' }}">
                                    {{ $item['enviada'] ? 'enviada pelo painel' : ($item['url'] ? 'foto do pacote' : 'sem foto no ar') }}
                                </span>
                            </div>

                            @if($item['url'])
                                <img class="slot-miniatura" src="{{ $item['url'] }}"
                                     alt="Imagem que está no ar: {{ $item['alt'] }}">
                            @else
                                <div class="slot-miniatura vazia"><i class="fas fa-image" aria-hidden="true"></i> esta seção aparece só depois do envio</div>
                            @endif

                            @if($linhas)
                                <div class="slot-meta"><i class="fas fa-ruler-combined me-1" aria-hidden="true"></i>{{ $linhas }}</div>
                            @endif
                            @if(!empty($registro['dica']))
                                <div class="slot-meta">{{ $registro['dica'] }}</div>
                            @endif
                            @if($item['sem_arquivo'])
                                <div class="slot-meta text-tone-red"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
                                    a foto do pacote não está no servidor — envie uma aqui.</div>
                            @endif

                            @include('partials.upload', [
                                'campo' => 'site_imagem',
                                'name' => 'imagem['.$slot.']',
                                'valor' => old('imagem.'.$slot, $item['enviada']),
                                'titulo' => 'Trocar a foto',
                                'dica' => $item['enviada'] ? null : ($item['url'] ? 'Sem envio, continua a foto atual.' : 'Sem envio, o título fica sobre o fundo padrão da página.'),
                            ])
                            @error('imagem['.$slot.']')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                            <label class="form-label mt-2" for="alt-{{ $slot }}">
                                Texto alternativo
                                <span class="form-hint">(leitor de tela, Google e a legenda quando a foto não carrega)</span>
                            </label>
                            <textarea id="alt-{{ $slot }}" name="alt[{{ $slot }}]" rows="2" maxlength="190"
                                      class="form-control {{ $errors->has('alt['.$slot.']') ? 'is-invalid' : '' }}"
                                      placeholder="{{ $registro['alt'] ?? '' }}">{{ old('alt.'.$slot, $item['alt']) }}</textarea>
                            @error('alt['.$slot.']')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                            @if($item['enviada'])
                                <label class="slot-voltar">
                                    <input class="form-check-input" type="checkbox" name="voltar_ao_padrao[{{ $slot }}]" value="1">
                                    <span>Voltar à foto original do pacote</span>
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
    @endforeach

    <button class="btn btn-golden mt-3"><i class="fas fa-save me-1"></i> Salvar imagens do site</button>
</form>
@endsection
