@extends('admin.layouts.app')
@section('title', 'Campanha: '.$campanha['nome'])
@section('page_title', $campanha['nome'])
@section('page_description', 'A campanha como o Google Ads a tem agora: status, orçamento, regiões, grupos de anúncios e os recursos vinculados. Tudo o que você muda aqui vai direto para o Google.')

@section('page_actions')
@if ($plano)
    <a href="{{ route('admin.google-ads.copiloto.show', $plano) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i> O plano que a criou</a>
@endif
<a href="{{ route('admin.google-ads.index', ['conta' => $conta->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Todas as campanhas</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
@php
    $ehAdmin = auth()->user()->isAdmin();
    $endereco = ['campanha' => $campanha['id'], 'conta' => $conta->id];
    $ativa = $campanha['status'] === 'ENABLED';
    $escrevivel = in_array($campanha['status'], \App\Services\GoogleAds\GoogleAdsService::STATUS_ESCREVEVEIS, true);
    $recursos = $campanha['recursos'];
    $totalRecursos = count($recursos['imagens']) + count($recursos['sitelinks']) + count($recursos['frases']);
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <x-admin.status :rotulo="$campanha['status_rotulo']" :tom="tom_por_status('campanha_ads', $campanha['status'])" />
                    <span class="small text-secondary">
                        Conta {{ $conta->nomeTela() }} · campanha <code>{{ $campanha['id'] }}</code>
                        @if ($campanha['inicio'] !== '')
                            · desde {{ \Illuminate\Support\Carbon::parse($campanha['inicio'])->format('d/m/Y') }}
                        @endif
                    </span>
                </div>

                <div class="row g-3 small">
                    <div class="col-12 col-md-4">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">Orçamento diário</div>
                            <div class="fs-5 fw-bold">{{ brl($campanha['orcamento']['diario_centavos']) }}</div>
                            <div class="text-secondary">{{ count($campanha['grupos']) }} grupo(s) de anúncios</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">Regiões e idiomas</div>
                            @if ($campanha['regioes'] === [] && $campanha['idiomas'] === [])
                                <div class="text-secondary">Sem segmentação: o anúncio vale para qualquer lugar.</div>
                            @else
                                <ul class="ads-chips">
                                    @foreach ($campanha['regioes'] as $regiao)
                                        <li class="ads-chip"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $regiao['nome'] }}</li>
                                    @endforeach
                                    @foreach ($campanha['idiomas'] as $idioma)
                                        <li class="ads-chip"><i class="fas fa-language" aria-hidden="true"></i> {{ $idioma }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    @if ($campanha['negativas'] !== [])
                        <div class="col-12">
                            <div class="ads-painel">
                                <div class="ads-painel__titulo">Negativas da campanha</div>
                                <ul class="ads-chips mb-0">
                                    @foreach ($campanha['negativas'] as $negativa)
                                        <li class="ads-chip ads-chip--negativa">{{ $negativa['texto'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-admin.card title="Grupos de anúncios" icon="fa-layer-group" class="mb-3"
            descricao="Cada grupo tem as suas palavras-chave e o anúncio responsivo que o Google combina na busca.">
            @if ($ehAdmin)
                <x-slot:actions>
                    <a href="{{ route('admin.google-ads.campanhas.grupos.novo', $endereco) }}" class="btn btn-sm btn-golden">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> Novo grupo
                    </a>
                </x-slot:actions>
            @endif

            @if ($campanha['grupos'] === [])
                <div class="text-center py-4">
                    <i class="fas fa-layer-group fa-2x text-secondary mb-2" aria-hidden="true"></i>
                    <p class="mb-1 fw-semibold">Esta campanha ainda não tem grupo de anúncios.</p>
                    <p class="small text-secondary mb-3">Sem grupo não há palavra-chave nem anúncio — a campanha fica no ar, mas não aparece para ninguém.</p>
                    @if ($ehAdmin)
                        <a href="{{ route('admin.google-ads.campanhas.grupos.novo', $endereco) }}" class="btn btn-golden">
                            <i class="fas fa-plus me-1" aria-hidden="true"></i> Criar o primeiro grupo
                        </a>
                    @endif
                </div>
            @else
                <div class="row g-3">
                    @foreach ($campanha['grupos'] as $grupo)
                        @php $grupoAtivo = $grupo['status'] === 'ENABLED'; @endphp
                        <div class="col-12">
                            <div class="ads-painel">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="fw-bold">{{ $grupo['nome'] }}</span>
                                        <x-admin.status :rotulo="$grupo['status_rotulo']" :tom="tom_por_status('campanha_ads', $grupo['status'])" />
                                        <span class="small text-secondary">lance até {{ brl($grupo['cpc_maximo_centavos']) }} por clique</span>
                                    </div>
                                    @if ($ehAdmin)
                                        <div class="table-actions">
                                            <a href="{{ route('admin.google-ads.campanhas.grupos.editar', $endereco + ['grupo' => $grupo['id']]) }}"
                                                class="btn btn-sm btn-outline-secondary" title="Editar o grupo">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </a>
                                            @if (in_array($grupo['status'], ['ENABLED', 'PAUSED'], true))
                                                <form method="POST" action="{{ route('admin.google-ads.campanhas.grupos.status', $endereco + ['grupo' => $grupo['id']]) }}"
                                                    data-atualiza=".app-content"
                                                    data-confirmar="{{ $grupoAtivo ? 'Pausar o grupo '.$grupo['nome'].'? Os anúncios dele param de aparecer até você reativar.' : 'Ativar o grupo '.$grupo['nome'].'? Os anúncios dele voltam a concorrer na busca.' }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $grupoAtivo ? 'PAUSED' : 'ENABLED' }}">
                                                    <button class="btn btn-sm btn-outline-secondary" title="{{ $grupoAtivo ? 'Pausar o grupo' : 'Ativar o grupo' }}">
                                                        <i class="fas fa-{{ $grupoAtivo ? 'pause' : 'play' }}" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.google-ads.campanhas.grupos.remover', $endereco + ['grupo' => $grupo['id']]) }}"
                                                data-atualiza=".app-content"
                                                data-confirmar="Remover o grupo {{ $grupo['nome'] }} do Google Ads? As palavras-chave e o anúncio dele vão junto, e não dá para desfazer.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Remover o grupo">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                <div class="small text-secondary mb-1">{{ count($grupo['palavras']) }} palavra(s)-chave</div>
                                @if ($grupo['palavras'] === [])
                                    <p class="small text-secondary mb-2">Nenhuma palavra-chave: este grupo não dispara em busca nenhuma.</p>
                                @else
                                    <ul class="ads-chips mb-3">
                                        @foreach ($grupo['palavras'] as $palavra)
                                            <li class="ads-chip">{{ $palavra['texto'] }} <small>{{ $palavra['correspondencia_rotulo'] }}</small></li>
                                        @endforeach
                                    </ul>
                                @endif

                                @forelse ($grupo['anuncios'] as $anuncio)
                                    <div class="border-top pt-2 mt-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                                            <span class="fw-semibold">Anúncio responsivo</span>
                                            <x-admin.status :rotulo="$anuncio['status_rotulo']" :tom="tom_por_status('campanha_ads', $anuncio['status'])" />
                                            @if ($anuncio['aprovacao_rotulo'] !== '')
                                                <x-admin.status :rotulo="$anuncio['aprovacao_rotulo']" :tom="tom_por_status('aprovacao_anuncio', $anuncio['aprovacao'])" />
                                            @endif
                                            <span class="text-secondary text-break">{{ $anuncio['url'] }}</span>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <ul class="ads-chips">
                                                    @foreach ($anuncio['titulos'] as $titulo)
                                                        <li class="ads-chip ads-chip--titulo">{{ $titulo }} <small>{{ mb_strlen($titulo) }}/{{ config('google_ads.rsa.caracteres_titulo') }}</small></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <ul class="ads-chips">
                                                    @foreach ($anuncio['descricoes'] as $descricao)
                                                        <li class="ads-chip ads-chip--descricao">{{ $descricao }} <small>{{ mb_strlen($descricao) }}/{{ config('google_ads.rsa.caracteres_descricao') }}</small></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="small text-secondary mb-0 border-top pt-2 mt-2">Sem anúncio neste grupo. Edite o grupo para escrever um.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Recursos vinculados" icon="fa-images"
            descricao="Imagens, sitelinks e frases de destaque que o Google acrescenta ao anúncio quando cabem.">
            @if ($ehAdmin)
                <x-slot:actions>
                    <a href="{{ route('admin.google-ads.campanhas.recursos', $endereco) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> Gerir os recursos
                    </a>
                </x-slot:actions>
            @endif

            @if ($totalRecursos === 0)
                <div class="text-center py-4">
                    <i class="fas fa-images fa-2x text-secondary mb-2" aria-hidden="true"></i>
                    <p class="mb-1 fw-semibold">Nenhum recurso vinculado ainda.</p>
                    <p class="small text-secondary mb-3">Uma imagem, dois ou três sitelinks e algumas frases de destaque deixam o anúncio maior na busca — e costumam render mais cliques.</p>
                    @if ($ehAdmin)
                        <a href="{{ route('admin.google-ads.campanhas.recursos', $endereco) }}" class="btn btn-golden">
                            <i class="fas fa-plus me-1" aria-hidden="true"></i> Vincular o primeiro recurso
                        </a>
                    @endif
                </div>
            @else
                <div class="row g-3 small">
                    @if ($recursos['imagens'] !== [])
                        <div class="col-12">
                            <div class="ads-painel__titulo">Imagens</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($recursos['imagens'] as $imagem)
                                    <figure class="ads-imagem mb-0">
                                        @if ($imagem['url'] !== '')
                                            <img src="{{ $imagem['url'] }}" alt="{{ $imagem['nome'] }}" loading="lazy">
                                        @endif
                                        <figcaption>{{ $imagem['nome'] }} @if ($imagem['largura'])<small>{{ $imagem['largura'] }} × {{ $imagem['altura'] }}</small>@endif</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($recursos['sitelinks'] !== [])
                        <div class="col-12 col-lg-6">
                            <div class="ads-painel__titulo">Sitelinks</div>
                            <ul class="list-unstyled mb-0">
                                @foreach ($recursos['sitelinks'] as $sitelink)
                                    <li class="mb-2">
                                        <span class="fw-semibold">{{ $sitelink['texto'] }}</span>
                                        <span class="text-secondary text-break">— {{ $sitelink['url'] }}</span>
                                        @if ($sitelink['descricao1'] !== '')
                                            <div class="text-secondary">{{ $sitelink['descricao1'] }} · {{ $sitelink['descricao2'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($recursos['frases'] !== [])
                        <div class="col-12 col-lg-6">
                            <div class="ads-painel__titulo">Frases de destaque</div>
                            <ul class="ads-chips">
                                @foreach ($recursos['frases'] as $frase)
                                    <li class="ads-chip">{{ $frase['texto'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Veiculação" icon="fa-toggle-on"
            :descricao="$ativa ? 'A campanha está no ar e gastando o orçamento.' : 'A campanha está parada: não aparece e não gasta.'">
            @if (! $escrevivel)
                <p class="small text-secondary mb-0">O painel só liga e desliga campanhas ativas ou pausadas. Esta está {{ mb_strtolower($campanha['status_rotulo']) }}.</p>
            @elseif ($conta->status !== \App\Models\GoogleAdsAccount::ATIVO)
                <p class="small text-secondary mb-2">A conta {{ $conta->nomeTela() }} está desconectada. Reconecte antes de mudar a veiculação.</p>
                <a href="{{ route('admin.google-ads.index') }}" class="btn btn-outline-secondary w-100"><i class="fa-brands fa-google me-1" aria-hidden="true"></i> Reconectar a conta</a>
            @else
                {{-- O status muda pelo cliente oficial com prova (`validate_only`) antes da escrita.
                     Sem AJAX: a tela inteira é relida do Google depois, nada aqui fica para trás. --}}
                <form method="POST" action="{{ route('admin.google-ads.campanhas.status', $endereco) }}" data-sem-ajax
                    data-confirmar="{{ $ativa ? 'Pausar '.$campanha['nome'].' no Google Ads? Os anúncios param de ser exibidos até você reativar.' : 'Ativar '.$campanha['nome'].' no Google Ads? Ela começa a veicular e a gastar assim que o Google aceitar.' }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $ativa ? 'PAUSED' : 'ENABLED' }}">
                    <input type="hidden" name="conta" value="{{ $conta->id }}">
                    <input type="hidden" name="voltar" value="campanha">
                    <button class="btn {{ $ativa ? 'btn-outline-secondary' : 'btn-golden' }} w-100">
                        <i class="fas fa-{{ $ativa ? 'pause' : 'play' }} me-1" aria-hidden="true"></i>
                        {{ $ativa ? 'Pausar a campanha' : 'Ativar a campanha' }}
                    </button>
                </form>
                @if (! $ativa && $campanha['grupos'] === [])
                    <p class="form-hint mt-2 mb-0">Ativar sem grupo de anúncios não exibe nada: crie um grupo antes.</p>
                @endif
            @endif
        </x-admin.card>

        @if ($ehAdmin)
            <x-admin.card title="Mexer na campanha" icon="fa-pen-to-square" class="mt-3"
                descricao="Nome, orçamento, regiões e negativas. Cada salvamento é escrito no Google na hora.">
                <a href="{{ route('admin.google-ads.campanhas.editar', $endereco) }}" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-pen me-1" aria-hidden="true"></i> Editar a campanha
                </a>
                <a href="{{ route('admin.google-ads.campanhas.grupos.novo', $endereco) }}" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-layer-group me-1" aria-hidden="true"></i> Novo grupo de anúncios
                </a>
                <a href="{{ route('admin.google-ads.campanhas.recursos', $endereco) }}" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-images me-1" aria-hidden="true"></i> Imagens, sitelinks e frases
                </a>
            </x-admin.card>
        @else
            <x-admin.card title="Quem edita" icon="fa-user-shield" class="mt-3">
                <p class="small text-secondary mb-0">Mudar orçamento, grupos e recursos é do administrador. Você pode acompanhar e ligar ou pausar a veiculação.</p>
            </x-admin.card>
        @endif

        <x-admin.card title="Métricas" icon="fa-chart-line" class="mt-3">
            <p class="small text-secondary mb-2">Impressões, cliques, custo e conversões ficam na lista de campanhas, sincronizada pela fila.</p>
            <a href="{{ route('admin.google-ads.index', ['conta' => $conta->id, 'busca' => $campanha['nome']]) }}" class="btn btn-outline-secondary w-100">
                <i class="fas fa-chart-line me-1" aria-hidden="true"></i> Ver os números desta campanha
            </a>
        </x-admin.card>
    </div>
</div>
@endsection
