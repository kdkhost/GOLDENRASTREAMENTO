@extends('admin.layouts.app')
@section('title', 'Recursos da campanha: '.$campanha['nome'])
@section('page_title', 'Recursos da campanha')
@section('page_description', 'Imagens, sitelinks e frases de destaque da campanha no Google Ads. Vincule recursos para aumentar o espaço e o CTR dos seus anúncios.')

@section('page_actions')
<a href="{{ route('admin.google-ads.campanhas.show', ['campanha' => $campanha['id'], 'conta' => $conta->id]) }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar à campanha
</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
<style>
.recurso-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 12px 14px;
    transition: all 0.2s ease;
}
.recurso-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(212, 160, 23, 0.3);
}
.recurso-img-thumb {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 6px;
    background: #111;
}
</style>
@endsection

@section('content')
@php
    $endereco = ['campanha' => $campanha['id'], 'conta' => $conta->id];
    $recursos = $campanha['recursos'] ?? ['imagens' => [], 'sitelinks' => [], 'frases' => [], 'empresa' => null];
    $imagens = $recursos['imagens'] ?? [];
    $sitelinks = $recursos['sitelinks'] ?? [];
    $frases = $recursos['frases'] ?? [];
    $empresa = $recursos['empresa'] ?? null;
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        {{-- SEÇÃO 1: IMAGENS --}}
        <x-admin.card title="Imagens vinculadas" icon="fa-image"
            descricao="Imagens e logotipos exibidos junto ao anúncio nas buscas móveis e computadores.">
            <div class="alert alert-info d-flex align-items-start gap-2 mb-3 py-2 px-3 small border-0 bg-info-subtle text-info-emphasis">
                <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Recursos Visuais no Google Ads:</strong> O Google Ads libera <strong>Logotipos da Empresa (1:1 quadrado)</strong> de imediato para qualquer conta ativa. Já as <strong>Extensões de Imagem de Anúncio (AD_IMAGE)</strong> exigem que a conta tenha mais de 60 dias de criação e histórico de veiculação em pesquisas.
                </div>
            </div>

            @if ($imagens !== [])
                <div class="row g-3 mb-4">
                    @foreach ($imagens as $imagem)
                        <div class="col-12 col-sm-6">
                            <div class="recurso-item d-flex align-items-center justify-content-between gap-3 h-100">
                                <div class="d-flex align-items-center gap-3 overflow-hidden">
                                    @if ($imagem['url'] !== '')
                                        <img src="{{ $imagem['url'] }}" alt="{{ $imagem['nome'] }}" class="recurso-img-thumb" loading="lazy">
                                    @else
                                        <div class="recurso-img-thumb d-flex align-items-center justify-content-center text-secondary">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    @endif
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold text-truncate small" title="{{ $imagem['nome'] }}">{{ $imagem['nome'] ?: 'Imagem #'.$imagem['asset_id'] }}</div>
                                        <div class="text-secondary small">
                                            @if ($imagem['largura'])
                                                {{ $imagem['largura'] }} × {{ $imagem['altura'] }} px
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            @if ($imagem['tipo_rotulo'] ?? '')
                                                <span class="badge bg-primary-subtle text-primary small py-0 px-1">{{ $imagem['tipo_rotulo'] }}</span>
                                            @endif
                                            @if ($imagem['status'])
                                                <span class="badge bg-secondary-subtle text-secondary small py-0 px-1">{{ $imagem['status'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.remover', $endereco) }}"
                                    data-confirmar="Desvincular esta imagem da campanha? O arquivo continuará salvo na sua biblioteca do Google Ads.">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="vinculo" value="{{ $imagem['vinculo'] }}">
                                    <button class="btn btn-outline-danger btn-sm" title="Desvincular">
                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="small text-secondary mb-3">Nenhuma imagem ou logotipo vinculado a esta campanha no momento.</p>
            @endif

            <div class="border-top pt-3">
                <h6 class="fw-semibold mb-2 small"><i class="fas fa-plus me-1 text-golden"></i> Vincular nova imagem ou logotipo</h6>
                <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.imagem', $endereco) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            @include('partials.upload', [
                                'campo' => 'google_ads_imagem',
                                'name' => 'imagem',
                                'valor' => old('imagem'),
                                'dica' => 'Proporção recomendada: 1:1 quadrada (ex: 512×512 ou 1200×1200 px) para Logotipo (liberado) ou 1,91:1 (ex: 1200×628 px) para Extensão de Anúncio.',
                            ])
                        </div>
                        <div class="col-12 col-md-5 d-flex flex-column justify-content-between">
                            <div>
                                <label class="form-label small" for="tipo-imagem">Tipo do recurso</label>
                                <select id="tipo-imagem" name="tipo" class="form-select form-select-sm mb-2">
                                    <option value="AUTO" selected>Automático (identificar proporção)</option>
                                    <option value="BUSINESS_LOGO">Logotipo da Empresa (1:1 quadrado — Liberado)</option>
                                    <option value="AD_IMAGE">Extensão de Anúncio (1,91:1 ou 1:1 — 60 dias)</option>
                                </select>
                                <label class="form-label small" for="nome-imagem">Nome do recurso <span class="text-secondary">(opcional)</span></label>
                                <input type="text" id="nome-imagem" name="nome" class="form-control form-control-sm"
                                    maxlength="128" placeholder="Ex: Logotipo Golden Rastreamento" value="{{ old('nome') }}">
                                <div class="form-text small">Ajuda a identificar o arquivo na biblioteca.</div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-golden btn-sm w-100">
                                    <i class="fas fa-cloud-arrow-up me-1"></i> Vincular imagem
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </x-admin.card>

        {{-- SEÇÃO 2: SITELINKS --}}
        <x-admin.card title="Sitelinks (links adicionais)" icon="fa-link" class="mt-3"
            descricao="Links extras abaixo do anúncio que levam o usuário direto a seções específicas (ex: Preços, Contato, Multi-operadora).">
            @if ($sitelinks !== [])
                <div class="row g-3 mb-4">
                    @foreach ($sitelinks as $sitelink)
                        <div class="col-12 col-sm-6">
                            <div class="recurso-item d-flex align-items-start justify-content-between gap-2 h-100">
                                <div class="overflow-hidden">
                                    <div class="fw-semibold small text-primary text-truncate">{{ $sitelink['texto'] }}</div>
                                    <div class="text-secondary small text-truncate" title="{{ $sitelink['url'] }}">{{ $sitelink['url'] }}</div>
                                    @if ($sitelink['descricao1'] !== '')
                                        <div class="text-muted small mt-1">
                                            {{ $sitelink['descricao1'] }}
                                            @if ($sitelink['descricao2'] !== '')
                                                · {{ $sitelink['descricao2'] }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.remover', $endereco) }}"
                                    data-confirmar="Desvincular este sitelink da campanha?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="vinculo" value="{{ $sitelink['vinculo'] }}">
                                    <button class="btn btn-outline-danger btn-sm" title="Desvincular">
                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="small text-secondary mb-3">Nenhum sitelink vinculado a esta campanha.</p>
            @endif

            <div class="border-top pt-3">
                <h6 class="fw-semibold mb-2 small"><i class="fas fa-plus me-1 text-golden"></i> Adicionar novo sitelink</h6>
                <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.sitelink', $endereco) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label small" for="st-texto">Texto do link <span class="text-danger">*</span></label>
                            <input type="text" id="st-texto" name="texto" class="form-control form-control-sm"
                                maxlength="25" required placeholder="Ex: Planos e Preços" value="{{ old('texto') }}">
                            <div class="form-text small">Máximo 25 caracteres.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small" for="st-url">URL final do link <span class="text-danger">*</span></label>
                            <input type="url" id="st-url" name="url" class="form-control form-control-sm"
                                maxlength="255" required placeholder="https://goldenrastreamento.com.br/planos" value="{{ old('url') }}">
                            <div class="form-text small">URL completa com https://</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small" for="st-d1">Linha de descrição 1 <span class="text-secondary">(opcional)</span></label>
                            <input type="text" id="st-d1" name="descricao1" class="form-control form-control-sm"
                                maxlength="35" placeholder="Ex: A partir de R$ 49,90/mês" value="{{ old('descricao1') }}">
                            <div class="form-text small">Máx. 35 caracteres.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small" for="st-d2">Linha de descrição 2 <span class="text-secondary">(opcional)</span></label>
                            <input type="text" id="st-d2" name="descricao2" class="form-control form-control-sm"
                                maxlength="35" placeholder="Ex: Sem fidelidade e app incluso" value="{{ old('descricao2') }}">
                            <div class="form-text small">Obrigatória se preencher a linha 1.</div>
                        </div>
                        <div class="col-12 mt-3">
                            <button class="btn btn-golden btn-sm">
                                <i class="fas fa-plus me-1"></i> Vincular sitelink
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </x-admin.card>

        {{-- SEÇÃO 3: FRASES DE DESTAQUE --}}
        <x-admin.card title="Frases de destaque (Callouts)" icon="fa-comment-dots" class="mt-3"
            descricao="Pequenos destaques de texto exibidos com o anúncio (ex: Suporte 24h, Instalação Grátis, Tecnologia J6).">
            @if ($frases !== [])
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach ($frases as $frase)
                        <div class="recurso-item d-inline-flex align-items-center gap-2 py-1 px-2">
                            <span class="small fw-semibold">{{ $frase['texto'] }}</span>
                            <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.remover', $endereco) }}"
                                data-confirmar="Desvincular esta frase da campanha?" class="d-inline m-0">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="vinculo" value="{{ $frase['vinculo'] }}">
                                <button class="btn btn-link text-danger p-0 border-0 ms-1" style="font-size: 0.75rem;" title="Remover">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="small text-secondary mb-3">Nenhuma frase de destaque vinculada a esta campanha.</p>
            @endif

            <div class="border-top pt-3">
                <h6 class="fw-semibold mb-2 small"><i class="fas fa-plus me-1 text-golden"></i> Adicionar frase de destaque</h6>
                <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.frase', $endereco) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <label class="form-label small" for="fr-texto">Texto da frase <span class="text-danger">*</span></label>
                            <input type="text" id="fr-texto" name="texto" class="form-control form-control-sm"
                                maxlength="25" required placeholder="Ex: Monitoramento 24 Horas" value="{{ old('texto') }}">
                            <div class="form-text small">Frases curtas e objetivas (máx. 25 caracteres).</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <button class="btn btn-golden btn-sm w-100">
                                <i class="fas fa-plus me-1"></i> Adicionar frase
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Nome da Empresa (no anúncio)" icon="fa-id-badge" class="mb-3"
            descricao="Nome da sua marca exibido no topo do anúncio de pesquisa no Google.">
            @if ($empresa)
                <div class="recurso-item d-flex align-items-center justify-content-between gap-2 py-2 px-3 mb-3">
                    <div>
                        <div class="fw-semibold small text-primary">{{ $empresa['nome'] }}</div>
                        <span class="badge bg-success-subtle text-success small py-0 px-1 mt-1">{{ $empresa['status'] }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.remover', $endereco) }}"
                        data-confirmar="Desvincular o nome da empresa desta campanha?">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="vinculo" value="{{ $empresa['vinculo'] }}">
                        <button class="btn btn-outline-danger btn-sm" title="Desvincular">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            @else
                <p class="small text-secondary mb-3">Nenhum nome de empresa vinculado diretamente a esta campanha.</p>
            @endif

            <form method="POST" action="{{ route('admin.google-ads.campanhas.recursos.nome-empresa', $endereco) }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small" for="ne-nome">Nome da empresa (máx. 25 carac.)</label>
                    <input type="text" id="ne-nome" name="nome" class="form-control form-control-sm"
                        maxlength="25" required placeholder="Ex: Golden Rastreamento" value="{{ old('nome', $empresa['nome'] ?? 'Golden Rastreamento') }}">
                </div>
                <button class="btn btn-golden btn-sm w-100">
                    <i class="fas fa-save me-1"></i> {{ $empresa ? 'Atualizar nome da marca' : 'Vincular nome da marca' }}
                </button>
            </form>
        </x-admin.card>

        <x-admin.card title="Sobre os recursos" icon="fa-circle-info"
            descricao="Como o Google Ads utiliza recursos nas campanhas.">
            <ul class="small text-secondary ps-3 mb-0">
                <li class="mb-2"><strong>Mais visibilidade:</strong> Anúncios com recursos completos ocupam maior área nos resultados de pesquisa.</li>
                <li class="mb-2"><strong>Melhor CTR:</strong> Recursos aumentam a taxa de cliques em média de 10% a 15% segundo a Google.</li>
                <li class="mb-2"><strong>Exibição inteligente:</strong> O algoritmo do Google decide automaticamente quais recursos mostrar em cada leilão com base na relevância e no dispositivo.</li>
                <li><strong>Revisão automática:</strong> Imagens e sitelinks passam por análise rápida de políticas do Google antes de veicular.</li>
            </ul>
        </x-admin.card>

        <x-admin.card title="Dados da campanha" icon="fa-bullhorn" class="mt-3">
            <dl class="row small mb-0 g-0">
                <dt class="col-5 text-secondary fw-normal">Campanha</dt>
                <dd class="col-7 mb-2 fw-semibold text-truncate">{{ $campanha['nome'] }}</dd>

                <dt class="col-5 text-secondary fw-normal">ID</dt>
                <dd class="col-7 mb-2"><code>{{ $campanha['id'] }}</code></dd>

                <dt class="col-5 text-secondary fw-normal">Status</dt>
                <dd class="col-7 mb-2"><x-admin.status :rotulo="$campanha['status_rotulo'] ?? $campanha['status'] ?? 'Desconhecido'" :tom="tom_por_status('campanha_ads', $campanha['status'] ?? 'UNKNOWN')" /></dd>

                <dt class="col-5 text-secondary fw-normal">Conta</dt>
                <dd class="col-7 mb-0">{{ $conta->nomeTela() }}</dd>
            </dl>
        </x-admin.card>
    </div>
</div>
@endsection

