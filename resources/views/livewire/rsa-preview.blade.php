{{--
    O editor de anúncio responsivo (RSA) e a prévia da busca.

    A coluna da esquerda escreve com as travas do Google — quinze títulos de 30, quatro descrições
    de 90, duas rotas de 15 — e a da direita mostra o que a busca desenha com o que já foi
    aprovado. As travas vêm todas de config('google_ads.rsa'): contador e prévia lêem a mesma
    lista, então nenhum dos dois pode discordar do outro.

    A prévia é um mock em HTML: o Google não tem API de preview de RSA, e o padrão do mercado é
    isso — escrever aqui, onde o corte é visível, em vez de descobrir na aprovação.
--}}
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.card title="Títulos" icon="fa-heading"
            descricao="Cabeçalhos que o Google mistura. Todo título precisa fazer sentido sozinho: a busca sorteia as combinações.">
            <x-slot:actions>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="adicionarTitulo"
                    @disabled(count($titulos) >= $limites['titulos_max'])>
                    <i class="fas fa-plus me-1" aria-hidden="true"></i> Título
                </button>
            </x-slot:actions>

            <div class="d-flex flex-column gap-2">
                @foreach ($titulos as $posicao => $titulo)
                    <div class="ads-linha">
                        <input type="text" class="form-control" wire:model.live.debounce.250ms="titulos.{{ $posicao }}"
                            placeholder="Título {{ $posicao + 1 }}" aria-label="Título {{ $posicao + 1 }}">
                        <span class="contador contador--{{ $contadores['titulos'][$posicao]['tom'] }}">
                            {{ $contadores['titulos'][$posicao]['caracteres'] }}/{{ $limites['caracteres_titulo'] }}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="removerTitulo({{ $posicao }})"
                            title="Tirar este título" @disabled(count($titulos) <= $limites['titulos_min'])>
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="form-hint mt-2">
                {{ count($titulos) }} de {{ $limites['titulos_max'] }} títulos · o Google exige pelo menos {{ $limites['titulos_min'] }}.
            </div>
        </x-admin.card>

        <x-admin.card title="Descrições" icon="fa-paragraph" class="mt-3"
            descricao="Textos de apoio. A primeira linha é a que quase todo mundo lê.">
            <x-slot:actions>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="adicionarDescricao"
                    @disabled(count($descricoes) >= $limites['descricoes_max'])>
                    <i class="fas fa-plus me-1" aria-hidden="true"></i> Descrição
                </button>
            </x-slot:actions>

            <div class="d-flex flex-column gap-3">
                @foreach ($descricoes as $posicao => $descricao)
                    <div class="ads-linha ads-linha--textarea">
                        <textarea class="form-control" rows="2"
                            wire:model.live.debounce.250ms="descricoes.{{ $posicao }}"
                            placeholder="Descrição {{ $posicao + 1 }}" aria-label="Descrição {{ $posicao + 1 }}">{{ $descricao }}</textarea>
                        <div class="d-flex align-items-center gap-2">
                            <span class="contador contador--{{ $contadores['descricoes'][$posicao]['tom'] }}">
                                {{ $contadores['descricoes'][$posicao]['caracteres'] }}/{{ $limites['caracteres_descricao'] }}
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="removerDescricao({{ $posicao }})"
                                title="Tirar esta descrição" @disabled(count($descricoes) <= $limites['descricoes_min'])>
                                <i class="fas fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        <x-admin.card title="URL final e rotas de display" icon="fa-link" class="mt-3"
            descricao="As rotas são só o que a busca mostra embaixo do domínio: não precisam bater com a URL de verdade.">
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label" for="rsa-url">URL final</label>
                    <input type="text" id="rsa-url" class="form-control" wire:model.live.debounce.400ms="url_final"
                        placeholder="https://goldenrastreamento.com.br/planos">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="rsa-rota1">Rota 1</label>
                    <div class="ads-linha">
                        <input type="text" id="rsa-rota1" class="form-control" wire:model.live.debounce.250ms="rota1"
                            placeholder="rastreio-veicular">
                        <span class="contador contador--{{ $contadores['rotas'][0]['tom'] }}">
                            {{ $contadores['rotas'][0]['caracteres'] }}/{{ $limites['caracteres_rota'] }}
                        </span>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="rsa-rota2">Rota 2</label>
                    <div class="ads-linha">
                        <input type="text" id="rsa-rota2" class="form-control" wire:model.live.debounce.250ms="rota2"
                            placeholder="24h">
                        <span class="contador contador--{{ $contadores['rotas'][1]['tom'] }}">
                            {{ $contadores['rotas'][1]['caracteres'] }}/{{ $limites['caracteres_rota'] }}
                        </span>
                    </div>
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm ads-preview">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="fas fa-magnifying-glass me-1 text-gold" aria-hidden="true"></i>
                    <span class="fw-bold">Prévia na busca</span>
                    <span class="form-hint d-block fw-normal small text-secondary">Como o Google desenha o anúncio no resultado.</span>
                </span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Tamanho da tela">
                    <button type="button" class="btn btn-outline-secondary {{ $dispositivo === 'desktop' ? 'active' : '' }}"
                        aria-pressed="{{ $dispositivo === 'desktop' ? 'true' : 'false' }}" wire:click="trocarDispositivo('desktop')">
                        <i class="fas fa-desktop me-1" aria-hidden="true"></i> Desktop
                    </button>
                    <button type="button" class="btn btn-outline-secondary {{ $dispositivo === 'celular' ? 'active' : '' }}"
                        aria-pressed="{{ $dispositivo === 'celular' ? 'true' : 'false' }}" wire:click="trocarDispositivo('celular')">
                        <i class="fas fa-mobile-screen me-1" aria-hidden="true"></i> Celular
                    </button>
                </div>
            </div>

            <div class="card-body">
                @forelse ($combinacoes as $n => $anuncio)
                    <div class="ads-serp ads-serp--{{ $dispositivo }} mb-2">
                        <div class="ads-serp__rotulo">
                            <span>Patrocinado</span>
                            <i class="fas fa-circle-info" aria-hidden="true" title="Por que este anúncio?"></i>
                        </div>
                        <div class="ads-serp__destino">
                            <span class="ads-serp__marca" aria-hidden="true">{{ mb_strtoupper(mb_substr($dominio, 0, 1)) }}</span>
                            <span class="ads-serp__dominio">{{ $dominio }}</span>
                            @if ($caminho !== '')
                                <i class="fas fa-chevron-right ads-serp__seta" aria-hidden="true"></i>
                                <span class="ads-serp__rotas">{{ $caminho }}</span>
                            @endif
                        </div>
                        <div class="ads-serp__titulo">
                            {{ $anuncio['titulo'] }}
                            @if ($anuncio['titulo_extra'])<span class="ads-serp__titulo-extra"> — {{ $anuncio['titulo_extra'] }}</span>@endif
                        </div>
                        <div class="ads-serp__descricao {{ $descricao_aberta ? 'is-aberta' : '' }}">
                            {{ $dispositivo === 'celular' ? $anuncio['cortada'] : $anuncio['descricao'] }}
                            @if ($dispositivo === 'celular' && mb_strlen($anuncio['descricao']) > mb_strlen($anuncio['cortada']))<span class="ads-serp__corte">…</span>@endif
                        </div>
                        @if ($dispositivo === 'desktop' && mb_strlen($anuncio['descricao']) > 120)
                            <button type="button" class="ads-serp__mais" wire:click="abrirDescricao">
                                {{ $descricao_aberta ? 'menos' : '… mais' }}
                            </button>
                        @endif
                    </div>
                    <div class="form-hint mb-3">
                        Combinação {{ $n + 1 }} — o Google sorteia título e descrição entre as opções aceitas.
                    </div>
                @empty
                    <p class="text-secondary mb-0">
                        Escreva pelo menos um título e uma descrição para a prévia aparecer.
                    </p>
                @endforelse
            </div>

            <div class="card-footer bg-body-tertiary">
                @if ($faltam === [])
                    <div class="d-flex align-items-center gap-2 text-tone-green">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <span class="small">Dentro das travas do Google: este anúncio passaria na validação de tamanho.</span>
                    </div>
                @else
                    <div class="small fw-semibold text-tone-amber mb-1">
                        <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i> Antes de levar ao Google:
                    </div>
                    <ul class="ads-faltas list-unstyled mb-0 small">
                        @foreach ($faltam as $falta)
                            <li><i class="fas fa-minus" aria-hidden="true"></i> {{ $falta }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
