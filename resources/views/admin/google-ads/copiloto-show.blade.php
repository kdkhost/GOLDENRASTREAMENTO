@extends('admin.layouts.app')
@section('title', 'Plano: '.$plano->nome)
@section('page_title', $plano->nome)
@section('page_description', 'A campanha que o copiloto escreveu, conferida contra as travas do Google antes de virar algo que gasta.')

@section('page_actions')
@if ($plano->status !== \App\Models\GoogleAdsPlano::APLICADO)
    <a href="{{ route('admin.google-ads.copiloto.editar', $plano) }}" class="btn btn-sm btn-golden"><i class="fas fa-pen me-1" aria-hidden="true"></i> Editar o plano</a>
@endif
<a href="{{ route('admin.google-ads.copiloto.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Todos os planos</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
@php
    $ehAdmin = auth()->user()->isAdmin();
    $estrategias = (array) config('google_ads.copiloto.estrategias');
    $objetivos = (array) config('google_ads.copiloto.objetivos');
    $anuncios = (array) ($conteudo['anuncios'] ?? []);
    $grupos = (array) ($conteudo['grupos'] ?? []);
    $briefing = (array) ($plano->briefing_json ?? []);
    $forcaChave = (string) ($forca['chave'] ?? '');
    $tentarDeNovo = $plano->status === \App\Models\GoogleAdsPlano::FALHOU;
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <x-admin.status :rotulo="$plano->rotuloStatus()" :tom="tom_por_status('plano_ads', $plano->status)" />
                    @if (($forca['rotulo'] ?? '') !== '')
                        <x-admin.status :rotulo="'Força estimada: '.$forca['rotulo']" :tom="tom_por_status('forca_anuncio', $forcaChave)" />
                    @endif
                    <span class="small text-secondary">
                        {{ $objetivos[$plano->objetivo] ?? $plano->objetivo }}
                        · {{ $estrategias[$conteudo['estrategia_lance'] ?? ''] ?? ($conteudo['estrategia_lance'] ?? '—') }}
                        @if ($plano->modelo)
                            · escrito por <code>{{ $plano->modelo }}</code>
                        @endif
                    </span>
                </div>

                <div class="row g-3 small">
                    <div class="col-12 col-md-4">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">Orçamento diário</div>
                            <div class="fs-5 fw-bold">{{ brl($plano->orcamento_diario_centavos) }}</div>
                            <div class="text-secondary">
                                Teto de lance {{ brl($conteudo['cpc_maximo_centavos'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">Destino e regiões</div>
                            <div class="text-break">{{ $conteudo['url'] ?? '—' }}</div>
                            <div class="mt-2">
                                <ul class="ads-chips">
                                    @foreach ((array) ($conteudo['regioes'] ?? []) as $regiao)
                                        <li class="ads-chip">{{ $regiao }}</li>
                                    @endforeach
                                    @foreach ((array) ($conteudo['idiomas'] ?? []) as $idioma)
                                        <li class="ads-chip"><i class="fas fa-language" aria-hidden="true"></i> {{ $idioma }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($plano->erro)
                    <p class="small text-tone-red mt-3 mb-0">
                        <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i> {{ $plano->erro }}
                    </p>
                @endif
            </div>
        </div>

        @if ($problemas !== [] || $avisos !== [])
            <x-admin.card title="Conferência do plano" icon="fa-clipboard-check" class="mb-3"
                descricao="O vermelho impede aprovar e aplicar; o amarelo é escolha sua.">
                @if ($problemas !== [])
                    <div class="ads-aviso ps-3 mb-3">
                        <div class="fw-semibold text-tone-red mb-1">Pendências para resolver</div>
                        <ul class="mb-0 ps-3 small">
                            @foreach ($problemas as $problema)
                                <li>{{ $problema }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($avisos !== [])
                    <div class="fw-semibold text-tone-amber mb-1">Avisos</div>
                    <ul class="mb-0 ps-3 small text-secondary">
                        @foreach ($avisos as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        @endif

        <x-admin.card title="O anúncio responsivo" icon="fa-rectangle-ad" class="mb-3"
            descricao="Os títulos e descrições que o Google combina na busca. O contador mostra o limite de cada campo.">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <p class="form-label mb-2">Títulos <span class="form-hint">({{ count((array) ($anuncios['titulos'] ?? [])) }} de {{ config('google_ads.rsa.titulos_max') }})</span></p>
                    <ul class="ads-chips">
                        @foreach ((array) ($anuncios['titulos'] ?? []) as $titulo)
                            <li class="ads-chip ads-chip--titulo">
                                {{ $titulo }}
                                <small>{{ mb_strlen($titulo) }}/{{ config('google_ads.rsa.caracteres_titulo') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-12 col-lg-6">
                    <p class="form-label mb-2">Descrições <span class="form-hint">({{ count((array) ($anuncios['descricoes'] ?? [])) }} de {{ config('google_ads.rsa.descricoes_max') }})</span></p>
                    <ul class="ads-chips">
                        @foreach ((array) ($anuncios['descricoes'] ?? []) as $descricao)
                            <li class="ads-chip ads-chip--descricao">
                                {{ $descricao }}
                                <small>{{ mb_strlen($descricao) }}/{{ config('google_ads.rsa.caracteres_descricao') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ((array) ($anuncios['caminhos'] ?? []) !== [])
                    <div class="col-12">
                        <p class="form-label mb-2">Rotas de exibição</p>
                        <ul class="ads-chips">
                            @foreach ($anuncios['caminhos'] as $caminho)
                                <li class="ads-chip">{{ $caminho }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <p class="form-hint mt-3 mb-0">
                <a href="{{ route('admin.google-ads.criativo') }}">Conferir a prévia na busca do Google</a>
                — o criativo abre com o seu próprio texto para testar antes de publicar.
            </p>
        </x-admin.card>

        {{-- SEÇÃO DE SITELINKS DO ANÚNCIO --}}
        @php
            $sitelinksPlano = (array) ($conteudo['sitelinks'] ?? config('google_ads.copiloto.sitelinks_padrao', []));
            $imagensPlano = (array) ($conteudo['imagens_recomendadas'] ?? \App\Services\GoogleAds\PlanoDeCampanha::imagensRecomendadas());
        @endphp

        <x-admin.card title="Sitelinks do anúncio (links adicionais no Google)" icon="fa-link" class="mb-3"
            descricao="Extensões de sitelink que aumentam o espaço do anúncio no Google e levam direto às seções estratégicas do site.">
            <div class="row g-2">
                @foreach ($sitelinksPlano as $sitelink)
                    <div class="col-12 col-md-6">
                        <div class="recurso-item h-100 p-2" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold text-primary small"><i class="fas fa-arrow-up-right-from-square me-1"></i> {{ $sitelink['texto'] }}</span>
                                <span class="badge bg-secondary-subtle text-secondary small py-0" style="font-size: 0.7rem;">Sitelink</span>
                            </div>
                            @if (!empty($sitelink['descricao1']))
                                <div class="text-light small" style="font-size: 0.8rem;">
                                    {{ $sitelink['descricao1'] }}
                                    @if (!empty($sitelink['descricao2']))
                                        · {{ $sitelink['descricao2'] }}
                                    @endif
                                </div>
                            @endif
                            <div class="text-secondary small text-truncate mt-1" style="font-size: 0.75rem;" title="{{ $sitelink['url'] }}">
                                {{ $sitelink['url'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        {{-- SEÇÃO DE IMAGENS E PRODUTOS REAIS --}}
        <x-admin.card title="Recursos visuais e produtos reais da Golden" icon="fa-images" class="mb-3"
            descricao="Imagens dos produtos e serviços reais sincronizados com a página de destino e catálogo da Golden Rastreamento.">
            <div class="row g-2">
                @foreach ($imagensPlano as $imgRec)
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="recurso-item p-2 h-100 d-flex flex-column justify-content-between" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;">
                            <div>
                                <div class="position-relative mb-2 mx-auto rounded overflow-hidden" style="width: 100%; height: 95px; background: #111; display: flex; align-items: center; justify-content: center;">
                                    @if ($imgRec['existe'])
                                        <img src="{{ $imgRec['url'] }}" alt="{{ $imgRec['nome'] }}" style="max-width: 100%; max-height: 100%; object-fit: cover;" loading="lazy">
                                    @else
                                        <i class="fas fa-image text-secondary"></i>
                                    @endif
                                    <span class="position-absolute top-0 end-0 m-1 badge {{ $imgRec['tipo'] === 'BUSINESS_LOGO' ? 'bg-warning text-dark' : 'bg-primary' }} py-0" style="font-size: 0.65rem;">
                                        {{ $imgRec['tipo'] === 'BUSINESS_LOGO' ? 'Logotipo (1:1)' : 'Extensão Imagem' }}
                                    </span>
                                </div>
                                <div class="fw-semibold small text-truncate" title="{{ $imgRec['nome'] }}">{{ $imgRec['nome'] }}</div>
                                <div class="text-secondary small mt-1" style="font-size: 0.75rem; line-height: 1.2;">{{ $imgRec['descricao'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="alert alert-info py-2 px-3 small border-0 bg-info-subtle text-info-emphasis mt-3 mb-0">
                <i class="fas fa-shield-halved me-1"></i> Ao aplicar este plano, o <strong>Logotipo da Golden Rastreamento</strong> e os <strong>Sitelinks</strong> são vinculados automaticamente à campanha no Google Ads.
            </div>
        </x-admin.card>

        <x-admin.card title="Grupos de anúncios" icon="fa-layer-group"
            descricao="Cada grupo tem o seu conjunto de palavras-chave e as negativas que barram o que não serve.">
            <div class="row g-3">
                @foreach ($grupos as $grupo)
                    <div class="col-12">
                        <div class="ads-painel">
                            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
                                <div class="fw-bold">{{ $grupo['nome'] }}</div>
                                <div class="small text-secondary">
                                    {{ count((array) ($grupo['palavras_chave'] ?? [])) }} palavra(s)-chave
                                </div>
                            </div>
                            @if (($grupo['tema'] ?? '') !== '')
                                <div class="small text-secondary mb-2">{{ $grupo['tema'] }}</div>
                            @endif

                            <ul class="ads-chips mb-2">
                                @foreach ((array) ($grupo['palavras_chave'] ?? []) as $palavra)
                                    <li class="ads-chip">
                                        {{ $palavra['texto'] }}
                                        <small>{{ \App\Services\GoogleAds\PlanoDeCampanha::correspondenciaEmPortugues((string) ($palavra['correspondencia'] ?? '')) }}</small>
                                    </li>
                                @endforeach
                            </ul>

                            @if ((array) ($grupo['negativas'] ?? []) !== [])
                                <div class="small text-secondary mb-1">Negativas</div>
                                <ul class="ads-chips mb-0">
                                    @foreach ($grupo['negativas'] as $negativa)
                                        <li class="ads-chip ads-chip--negativa">{{ $negativa }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        @if (($conteudo['justificativa'] ?? '') !== '')
            <x-admin.card title="Por que este plano" icon="fa-comment-dots" class="mt-3">
                <p class="mb-0 small">{{ $conteudo['justificativa'] }}</p>
            </x-admin.card>
        @endif

        @if ($aplicacao !== [])
            @php
                $campanhaCriada = (string) ($aplicacao['campanha_id'] ?? '');
                $contaAplicada = $plano->google_ads_account_id;
                $parametrosCampanha = array_filter(['campanha' => $campanhaCriada, 'conta' => $contaAplicada, 'plano' => $plano->id]);
            @endphp
            <x-admin.card title="Aplicado no Google Ads" icon="fa-circle-check" class="mt-3">
                <div class="row g-3 small">
                    <div class="col-12 col-md-4">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">Campanha</div>
                            <code>{{ $campanhaCriada !== '' ? $campanhaCriada : '—' }}</code>
                            <div class="mt-1">
                                @if (($aplicacao['status'] ?? 'PAUSED') === 'ENABLED')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fas fa-play me-1" aria-hidden="true"></i> {{ $aplicacao['status_rotulo'] ?? 'Ativa' }}
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="fas fa-pause me-1" aria-hidden="true"></i> {{ $aplicacao['status_rotulo'] ?? 'Pausada' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="ads-painel h-100">
                            <div class="ads-painel__titulo">O que foi criado</div>
                            <ul class="ads-chips mb-0">
                                <li class="ads-chip">{{ (int) ($aplicacao['grupos'] ?? 0) }} grupo(s)</li>
                                <li class="ads-chip">{{ (int) ($aplicacao['anuncios'] ?? 0) }} anúncio(s)</li>
                                <li class="ads-chip">{{ (int) ($aplicacao['palavras_chave'] ?? 0) }} palavra(s)-chave</li>
                                <li class="ads-chip">{{ (int) ($aplicacao['negativas'] ?? 0) }} negativa(s)</li>
                                @if ($plano->aplicado_em)
                                    <li class="ads-chip">em {{ $plano->aplicado_em->format('d/m/Y H:i') }}</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
                @if ($campanhaCriada !== '')
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('admin.google-ads.campanhas.show', $parametrosCampanha) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-bullhorn me-1" aria-hidden="true"></i> Abrir a campanha
                        </a>
                        @if (($aplicacao['status'] ?? 'PAUSED') === 'ENABLED')
                            <form method="POST" action="{{ route('admin.google-ads.campanhas.status', ['campanha' => $campanhaCriada]) }}"
                                data-sem-ajax
                                data-confirmar="Pausar a campanha no Google Ads agora? Ela deixará de veicular e de gastar orçamento.">
                                @csrf
                                <input type="hidden" name="status" value="PAUSED">
                                <input type="hidden" name="conta" value="{{ $contaAplicada }}">
                                <input type="hidden" name="voltar" value="plano">
                                <input type="hidden" name="plano" value="{{ $plano->id }}">
                                <button class="btn btn-outline-warning btn-sm"><i class="fas fa-pause me-1" aria-hidden="true"></i> Pausar campanha</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.google-ads.campanhas.status', ['campanha' => $campanhaCriada]) }}"
                                data-sem-ajax
                                data-confirmar="Ativar a campanha no Google Ads agora? Ela começa a veicular e a gastar o orçamento diário.">
                                @csrf
                                <input type="hidden" name="status" value="ENABLED">
                                <input type="hidden" name="conta" value="{{ $contaAplicada }}">
                                <input type="hidden" name="voltar" value="plano">
                                <input type="hidden" name="plano" value="{{ $plano->id }}">
                                <button class="btn btn-golden btn-sm"><i class="fas fa-play me-1" aria-hidden="true"></i> Ativar agora</button>
                            </form>
                        @endif
                    </div>
                    @if (($aplicacao['status'] ?? 'PAUSED') === 'ENABLED')
                        <p class="form-hint mt-2 mb-0 text-success">
                            <i class="fas fa-circle-check me-1"></i> A campanha está ativa e veiculando no Google Ads conforme o orçamento configurado.
                        </p>
                    @else
                        <p class="form-hint mt-2 mb-0">
                            A campanha está pausada e não consome orçamento. Na ficha dela você edita orçamento, regiões, grupos e imagens — ou clique em "Ativar agora" para iniciar a veiculação.
                        </p>
                    @endif
                @else
                    <p class="form-hint mt-3 mb-0">
                        A campanha está pausada. <a href="{{ route('admin.google-ads.index') }}">Abra as métricas</a> para revisar e ativar quando quiser começar a veicular.
                    </p>
                @endif
            </x-admin.card>
        @endif
    </div>

    <div class="col-lg-4">
        <x-admin.card title="O que foi pedido" icon="fa-clipboard-list"
            descricao="O briefing que gerou este plano, guardado como foi escrito.">
            <dl class="row small mb-0 g-0">
                @foreach ([
                    'nome' => 'Nome',
                    'objetivo' => 'Objetivo',
                    'orcamento' => 'Orçamento (R$)',
                    'url' => 'Destino',
                    'regioes' => 'Regiões',
                    'publico' => 'Público',
                    'oferta' => 'Oferta',
                    'observacoes' => 'Observações',
                ] as $chave => $rotulo)
                    @if (filled($briefing[$chave] ?? null))
                        <dt class="col-5 text-secondary fw-normal">{{ $rotulo }}</dt>
                        <dd class="col-7 mb-2">{{ $chave === 'objetivo' ? ($objetivos[$briefing[$chave]] ?? $briefing[$chave]) : $briefing[$chave] }}</dd>
                    @endif
                @endforeach
            </dl>
        </x-admin.card>

        <x-admin.card title="O que fazer agora" icon="fa-gavel" class="mt-3">
            @if ($plano->status === \App\Models\GoogleAdsPlano::RASCUNHO)
                @if ($problemas !== [])
                    <p class="small text-secondary mb-3">
                        Há pendências na conferência e o Google recusaria este plano. Corrija na tela de edição:
                        a conferência roda de novo a cada salvamento.
                    </p>
                    <a href="{{ route('admin.google-ads.copiloto.editar', $plano) }}" class="btn btn-golden w-100">
                        <i class="fas fa-pen me-1" aria-hidden="true"></i> Corrigir o plano
                    </a>
                @elseif ($ehAdmin)
                    <p class="small text-secondary mb-3">Confira a estrutura acima. Aprovar não publica nada: só libera o passo seguinte.</p>
                    <form method="POST" action="{{ route('admin.google-ads.copiloto.aprovar', $plano) }}" data-atualiza=".app-content">
                        @csrf
                        <button class="btn btn-golden w-100"><i class="fas fa-user-check me-1" aria-hidden="true"></i> Aprovar o plano</button>
                    </form>
                @else
                    <p class="small text-secondary mb-0">O plano está pronto. Quem aprova é o administrador.</p>
                @endif
            @elseif ($plano->podeAplicar())
                @if ($tentarDeNovo)
                    <p class="small text-secondary mb-3">
                        A tentativa anterior parou no meio: {{ rtrim($plano->erro ?: 'o Google não concluiu a escrita', '. ') }}.
                        Nenhuma campanha ficou de pé por inteiro. Confira a conta no Google Ads, apague o que sobrou, e tente de novo — a aprovação continua valendo.
                    </p>
                @endif
                @if (! $ehAdmin)
                    <p class="small text-secondary mb-0">Plano aprovado. Quem aplica no Google é o administrador.</p>
                @elseif ($contas->isEmpty())
                    <p class="small text-secondary mb-2">Nenhuma conta do Google Ads conectada ainda. Conecte uma para poder aplicar.</p>
                    <a href="{{ route('admin.google-ads.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fa-brands fa-google me-1" aria-hidden="true"></i> Conectar uma conta
                    </a>
                @elseif ($credenciais_faltando->isNotEmpty())
                    <p class="small text-secondary mb-2">Faltam credenciais da API para publicar:</p>
                    <ul class="ads-faltas list-unstyled small mb-3">
                        @foreach ($credenciais_faltando as $nome)
                            <li><i class="fas fa-circle-dot" aria-hidden="true"></i> <code>{{ $nome }}</code></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.settings.index') }}#aba-chaves" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-key me-1" aria-hidden="true"></i> Abrir as chaves
                    </a>
                @else
                    <form method="POST" action="{{ route('admin.google-ads.copiloto.aplicar', $plano) }}" class="row g-3"
                        data-atualiza=".app-content"
                        data-confirmar="{{ $tentarDeNovo
                            ? 'Tentar de novo no Google Ads? Se a tentativa anterior deixou uma campanha pela metade, apague-a antes. A campanha nova nasce pausada e não gasta nada.'
                            : 'Criar esta campanha no Google Ads? Ela nasce pausada e não gasta nada até você ativar.' }}">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="aplicar-conta">Conta do Google Ads</label>
                            <select id="aplicar-conta" name="conta" class="form-select" required>
                                @foreach ($contas as $c)
                                    <option value="{{ $c->id }}" @selected((int) $plano->google_ads_account_id === (int) $c->id)>{{ $c->nomeTela() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-golden w-100">
                                <i class="fas {{ $tentarDeNovo ? 'fa-rotate-right' : 'fa-cloud-arrow-up' }} me-1" aria-hidden="true"></i>
                                {{ $tentarDeNovo ? 'Tentar de novo no Google Ads' : 'Aplicar no Google Ads' }}
                            </button>
                        </div>
                    </form>
                @endif
            @elseif ($plano->status === \App\Models\GoogleAdsPlano::DESCARTADO)
                <p class="small text-secondary mb-3">
                    Este plano foi descartado. Ele volta a rascunho no primeiro salvamento — edite o que precisa.
                </p>
                <a href="{{ route('admin.google-ads.copiloto.editar', $plano) }}" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-pen me-1" aria-hidden="true"></i> Editar o plano
                </a>
            @else
                <p class="small text-secondary mb-3">
                    Este plano já virou campanha no Google Ads e fica guardado como a foto do que foi publicado.
                    Para mudar orçamento, regiões, palavras ou anúncio, faça uma cópia: ela nasce em rascunho e é
                    dela que você edita tudo. A campanha no Google não muda por aqui.
                </p>
                <form method="POST" action="{{ route('admin.google-ads.copiloto.duplicar', $plano) }}"
                    data-confirmar="Copiar este plano como novo rascunho? A campanha que já está no Google Ads continua igual.">
                    @csrf
                    <button class="btn btn-golden w-100">
                        <i class="fas fa-clone me-1" aria-hidden="true"></i> Criar um plano igual a partir deste
                    </button>
                </form>
            @endif
        </x-admin.card>

        @if ($plano->status !== \App\Models\GoogleAdsPlano::APLICADO)
            <x-admin.card title="Sair deste plano" icon="fa-box-archive" class="mt-3">
                <form method="POST" action="{{ route('admin.google-ads.copiloto.descartar', $plano) }}" class="mb-2"
                    data-atualiza=".app-content"
                    data-confirmar="Descartar este plano? O briefing continua guardado na ficha dele.">
                    @csrf
                    <button class="btn btn-outline-secondary w-100"><i class="fas fa-box-archive me-1" aria-hidden="true"></i> Descartar</button>
                </form>

                @if ($ehAdmin)
                    <form method="POST" action="{{ route('admin.google-ads.copiloto.destroy', $plano) }}"
                        data-confirmar="Excluir este plano definitivamente? Não tem como voltar atrás.">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger w-100"><i class="fas fa-trash me-1" aria-hidden="true"></i> Excluir definitivamente</button>
                    </form>
                @endif
            </x-admin.card>
        @endif
    </div>
</div>
@endsection
