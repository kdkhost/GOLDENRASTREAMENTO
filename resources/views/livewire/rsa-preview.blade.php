{{--
    Estúdio de Criativo RSA (Anúncio Responsivo de Pesquisa) do Google Ads.
--}}
<div class="d-flex flex-column gap-3">
    {{-- Barra superior do estúdio: Força do anúncio + Modelos prontos --}}
    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-xl-5">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="small fw-semibold text-uppercase text-secondary">Força do anúncio</span>
                        <span class="badge {{ $forca['tom'] === 'green' ? 'text-bg-success' : 'text-bg-warning' }}">
                            {{ $forca['rotulo'] }} ({{ $forca['percentual'] }}%)
                        </span>
                    </div>
                    <div class="progress mb-1" style="height: 6px;" role="progressbar" aria-valuenow="{{ $forca['percentual'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $forca['tom'] === 'green' ? 'bg-success' : 'bg-warning' }}" style="width: {{ $forca['percentual'] }}%"></div>
                    </div>
                    <div class="form-hint mb-0">{{ $forca['dica'] }}</div>
                </div>

                <div class="col-12 col-xl-7">
                    <div class="d-flex flex-wrap align-items-center justify-content-xl-end gap-2">
                        <span class="small text-secondary me-1">Modelos rápidos:</span>
                        @foreach ($modelos as $chave => $modelo)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="aplicarModelo('{{ $chave }}')">
                                <i class="fas {{ $modelo['icone'] }} me-1" aria-hidden="true"></i> {{ $modelo['nome'] }}
                            </button>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="limparRascunho" title="Limpar todos os campos">
                            <i class="fas fa-eraser me-1" aria-hidden="true"></i> Limpar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-lg-7">
            <x-admin.card title="Títulos" icon="fa-heading"
                descricao="Cabeçalhos que o Google mistura. Todo título precisa fazer sentido sozinho: a busca sorteia as combinações.">
                <x-slot:actions>
                    <button type="button" class="btn btn-sm btn-golden" wire:click="adicionarTitulo"
                        @disabled(count($titulos) >= $limites['titulos_max'])>
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> Título
                    </button>
                </x-slot:actions>

                <div class="d-flex flex-column gap-2">
                    @foreach ($titulos as $posicao => $titulo)
                        @php $tomTitulo = $contadores['titulos'][$posicao]['tom']; @endphp
                        <div class="ads-linha">
                            <span class="ads-indice">#{{ $posicao + 1 }}</span>
                            <input type="text"
                                class="form-control {{ $tomTitulo === 'estourado' ? 'is-invalid' : '' }}"
                                wire:model.live.debounce.250ms="titulos.{{ $posicao }}"
                                placeholder="Título {{ $posicao + 1 }} (ex.: Rastreamento Veicular 24h)"
                                aria-label="Título {{ $posicao + 1 }}">
                            <span class="contador contador--{{ $tomTitulo }}">
                                {{ $contadores['titulos'][$posicao]['caracteres'] }}/{{ $limites['caracteres_titulo'] }}
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary ads-remover" wire:click="removerTitulo({{ $posicao }})"
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
                descricao="Textos de apoio com benefícios, diferenciais e chamada para ação.">
                <x-slot:actions>
                    <button type="button" class="btn btn-sm btn-golden" wire:click="adicionarDescricao"
                        @disabled(count($descricoes) >= $limites['descricoes_max'])>
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> Descrição
                    </button>
                </x-slot:actions>

                <div class="d-flex flex-column gap-3">
                    @foreach ($descricoes as $posicao => $descricao)
                        @php $tomDesc = $contadores['descricoes'][$posicao]['tom']; @endphp
                        <div class="ads-linha ads-linha--textarea">
                            <div class="d-flex align-items-center justify-content-between gap-2 w-100">
                                <span class="ads-indice">#{{ $posicao + 1 }}</span>
                                <div class="d-flex align-items-center gap-2 ms-auto">
                                    <span class="contador contador--{{ $tomDesc }}">
                                        {{ $contadores['descricoes'][$posicao]['caracteres'] }}/{{ $limites['caracteres_descricao'] }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ads-remover" wire:click="removerDescricao({{ $posicao }})"
                                        title="Tirar esta descrição" @disabled(count($descricoes) <= $limites['descricoes_min'])>
                                        <i class="fas fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea class="form-control {{ $tomDesc === 'estourado' ? 'is-invalid' : '' }}" rows="2"
                                wire:model.live.debounce.250ms="descricoes.{{ $posicao }}"
                                placeholder="Descrição {{ $posicao + 1 }} (ex.: Acompanhe seu veículo em tempo real com bloqueio pelo app.)"
                                aria-label="Descrição {{ $posicao + 1 }}">{{ $descricao }}</textarea>
                        </div>
                    @endforeach
                </div>

                <div class="form-hint mt-2">
                    {{ count($descricoes) }} de {{ $limites['descricoes_max'] }} descrições · mínimo de {{ $limites['descricoes_min'] }}.
                </div>
            </x-admin.card>

            <x-admin.card title="URL final e rotas de display" icon="fa-link" class="mt-3"
                descricao="As rotas aparecem ao lado do domínio na busca para reforçar relevância do anúncio.">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="rsa-url">URL final</label>
                        <input type="text" id="rsa-url" class="form-control" wire:model.live.debounce.400ms="url_final"
                            placeholder="https://goldenrastreamento.com.br/loja">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="rsa-rota1">Rota 1</label>
                        <div class="ads-linha">
                            <input type="text" id="rsa-rota1"
                                class="form-control {{ $contadores['rotas'][0]['tom'] === 'estourado' ? 'is-invalid' : '' }}"
                                wire:model.live.debounce.250ms="rota1"
                                placeholder="rastreamento">
                            <span class="contador contador--{{ $contadores['rotas'][0]['tom'] }}">
                                {{ $contadores['rotas'][0]['caracteres'] }}/{{ $limites['caracteres_rota'] }}
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="rsa-rota2">Rota 2</label>
                        <div class="ads-linha">
                            <input type="text" id="rsa-rota2"
                                class="form-control {{ $contadores['rotas'][1]['tom'] === 'estourado' ? 'is-invalid' : '' }}"
                                wire:model.live.debounce.250ms="rota2"
                                placeholder="24h">
                            <span class="contador contador--{{ $contadores['rotas'][1]['tom'] }}">
                                {{ $contadores['rotas'][1]['caracteres'] }}/{{ $limites['caracteres_rota'] }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-hint mt-2">
                    Exibição na busca: <code>{{ $dominio }}{{ $caminho !== '' ? '/'.$caminho : '' }}</code>
                </div>
            </x-admin.card>

            <x-admin.card title="Publicar no Google Ads" icon="fa-rocket" class="mt-3"
                descricao="Envie este anúncio diretamente para um grupo de anúncios da sua conta conectada.">
                <x-slot:actions>
                    @if ($contasDisponiveis !== [])
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recarregarDestinos"
                            wire:loading.attr="disabled" wire:target="recarregarDestinos">
                            <i class="fas fa-rotate me-1" aria-hidden="true"></i> Atualizar listas
                        </button>
                    @endif
                </x-slot:actions>

                @if ($contasDisponiveis === [])
                    <div class="alert alert-warning mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="small">Nenhuma conta ativa do Google Ads conectada no momento.</span>
                        <a href="{{ route('admin.google-ads.index') }}" class="btn btn-sm btn-outline-dark">
                            Conectar conta
                        </a>
                    </div>
                @else
                    @if ($erroDestinos !== '')
                        <div class="alert alert-warning small mb-3">{{ $erroDestinos }}</div>
                    @endif

                    @if ($erroPublicacao !== '')
                        <div class="alert alert-danger small mb-3">{{ $erroPublicacao }}</div>
                    @endif

                    @if ($sucessoPublicacao !== '')
                        <div class="alert alert-success small mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span><i class="fas fa-circle-check me-1" aria-hidden="true"></i> {{ $sucessoPublicacao }}</span>
                            @if ($publicadoCampanhaUrl !== '')
                                <a href="{{ $publicadoCampanhaUrl }}" class="btn btn-sm btn-success">
                                    Ver campanha <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                                </a>
                            @endif
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="rsa-conta">Conta Google Ads</label>
                            <select id="rsa-conta" class="form-select" wire:model.live="contaId">
                                @foreach ($contasDisponiveis as $contaItem)
                                    <option value="{{ $contaItem['id'] }}">{{ $contaItem['nome'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="rsa-status-pub">Status inicial</label>
                            <select id="rsa-status-pub" class="form-select" wire:model="statusPublicacao">
                                <option value="PAUSED">Pausado (recomendado para revisar)</option>
                                <option value="ENABLED">Ativo imediatamente</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="rsa-campanha">Campanha</label>
                            <select id="rsa-campanha" class="form-select" wire:model.live="campanhaId" @disabled($campanhasDisponiveis === [])>
                                @forelse ($campanhasDisponiveis as $campanhaItem)
                                    <option value="{{ $campanhaItem['id'] }}">
                                        {{ $campanhaItem['nome'] }} ({{ $campanhaItem['status_rotulo'] }})
                                    </option>
                                @empty
                                    <option value="">Nenhuma campanha com grupos encontrada</option>
                                @endforelse
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="rsa-grupo">Grupo de anúncios</label>
                            <select id="rsa-grupo" class="form-select" wire:model="grupoResource" @disabled($gruposDisponiveis === [])>
                                @forelse ($gruposDisponiveis as $grupoItem)
                                    <option value="{{ $grupoItem['resource'] }}">{{ $grupoItem['nome'] }}</option>
                                @empty
                                    <option value="">Selecione uma campanha primeiro</option>
                                @endforelse
                            </select>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-2 border-top">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="form-hint mb-0">
                                Cria um novo anúncio responsivo (RSA) no grupo escolhido sem alterar os anúncios atuais.
                            </span>
                            @if ($campanhaId !== '' && $contaId !== null)
                                <a href="{{ route('admin.google-ads.campanhas.recursos', ['campanha' => $campanhaId, 'conta' => $contaId]) }}"
                                    class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-images me-1" aria-hidden="true"></i> Gerenciar imagens e sitelinks
                                </a>
                            @endif
                        </div>
                        <button type="button" class="btn btn-golden" wire:click="publicarNoGoogleAds"
                            wire:loading.attr="disabled" wire:target="publicarNoGoogleAds"
                            @disabled($faltam !== [] || $grupoResource === '')>
                            <span wire:loading.remove wire:target="publicarNoGoogleAds">
                                <i class="fas fa-paper-plane me-1" aria-hidden="true"></i> Publicar no Google Ads
                            </span>
                            <span wire:loading wire:target="publicarNoGoogleAds">
                                <i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i> Publicando…
                            </span>
                        </button>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm ads-preview">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="fw-bold"><i class="fas fa-magnifying-glass me-1 text-gold" aria-hidden="true"></i> Prévia na busca</span>
                        <span class="form-hint d-block fw-normal small text-secondary">Simulação de combinações no Google.</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
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
                </div>

                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="sortearCombinacoes"
                            @disabled($combinacoes === [])>
                            <i class="fas fa-shuffle me-1" aria-hidden="true"></i> Sortear combinações
                        </button>

                        <div x-data="{ copiado: false }">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                @click="navigator.clipboard.writeText(@js($textoExportacao)).then(() => { copiado = true; setTimeout(() => copiado = false, 2000); })"
                                @disabled($textoExportacao === '')>
                                <i class="fas" :class="copiado ? 'fa-check text-success' : 'fa-copy'" aria-hidden="true"></i>
                                <span x-text="copiado ? 'Copiado!' : 'Copiar criativo'">Copiar criativo</span>
                            </button>
                        </div>
                    </div>

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
                        <div class="text-center py-4 px-3 border rounded-3 bg-body-tertiary">
                            <i class="fas fa-wand-magic-sparkles fs-4 text-gold mb-2 d-block" aria-hidden="true"></i>
                            <p class="text-secondary small mb-2">
                                Escreva pelo menos um título e uma descrição — ou carregue um dos <strong>Modelos rápidos</strong> acima para visualizar a prévia na hora.
                            </p>
                        </div>
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
</div>
