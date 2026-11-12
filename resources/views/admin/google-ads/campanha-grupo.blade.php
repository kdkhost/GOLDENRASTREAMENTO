@extends('admin.layouts.app')

@php
    $ehEdicao = $grupo !== null;
    $endereco = ['campanha' => $campanha['id'], 'conta' => $conta->id];
    $actionUrl = $ehEdicao
        ? route('admin.google-ads.campanhas.grupos.atualizar', $endereco + ['grupo' => $grupo['id']])
        : route('admin.google-ads.campanhas.grupos.criar', $endereco);

    $palavrasTexto = '';
    if ($ehEdicao && !empty($grupo['palavras'])) {
        $palavrasTexto = implode("\n", array_map(
            static fn (array $p): string => \App\Services\GoogleAds\PlanoDeCampanha::linhaDePalavra($p),
            $grupo['palavras']
        ));
    }

    $titulosTexto = '';
    if (!empty($anuncio['titulos'])) {
        $titulosTexto = implode("\n", (array) $anuncio['titulos']);
    }

    $descricoesTexto = '';
    if (!empty($anuncio['descricoes'])) {
        $descricoesTexto = implode("\n", (array) $anuncio['descricoes']);
    }

    $caminhosTexto = '';
    if (!empty($anuncio['caminhos'])) {
        $caminhosTexto = implode("\n", (array) $anuncio['caminhos']);
    }

    $cpcReais = number_format(($cpc ?? 200) / 100, 2, '.', '');
    $urlDestino = $anuncio['url'] ?? (url('/') ?: 'https://goldenrastreamento.com.br');
    $statusCampanha = $campanha['status'] ?? 'UNKNOWN';
    $statusRotulo = $campanha['status_rotulo'] ?? $campanha['status'] ?? 'Desconhecido';
@endphp

@section('title', ($ehEdicao ? 'Editar grupo: '.$grupo['nome'] : 'Novo grupo de anúncios').': '.$campanha['nome'])
@section('page_title', $ehEdicao ? 'Editar grupo de anúncios' : 'Novo grupo de anúncios')
@section('page_description', $ehEdicao
    ? 'Atualize o nome, lance de CPC, palavras-chave e o anúncio responsivo deste grupo no Google Ads.'
    : 'Crie um novo grupo de anúncios com palavras-chave e anúncio responsivo diretamente no Google Ads.')

@section('page_actions')
<a href="{{ route('admin.google-ads.campanhas.show', $endereco) }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar à campanha
</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
<form method="POST" action="{{ $actionUrl }}">
    @csrf

    <div class="row g-3">
        <div class="col-lg-8">
            {{-- BLOCO 1: O GRUPO DE ANÚNCIOS --}}
            <x-admin.card title="O grupo de anúncios" icon="fa-layer-group"
                descricao="Nome do grupo, limite de lance por clique e palavras-chave que disparam a exibição.">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome do grupo" :value="$grupo['nome'] ?? ''"
                        :required="true" maxlength="255" col="col-12 col-md-8"
                        placeholder="Ex.: Rastreamento Veicular para Frotas" />

                    <x-admin.field name="cpc_maximo" type="number" label="Teto de CPC (R$)"
                        :value="$cpcReais" :required="true" step="0.01" min="0.10" max="50" col="col-12 col-md-4"
                        hint="Lance máximo por clique para este grupo." />

                    <x-admin.field name="palavras" type="textarea" label="Palavras-chave"
                        :value="$palavrasTexto" :rows="7" :required="true" maxlength="2500" col="col-12"
                        placeholder="rastreamento veicular | frase&#10;rastreador de frota | ampla&#10;empresa de rastreamento | exata"
                        hint="Uma palavra por linha. Especifique a correspondência com barra: ampla, frase ou exata. Se não informar, assume correspondência de frase." />
                </div>
            </x-admin.card>

            {{-- BLOCO 2: O ANÚNCIO RESPONSIVO DE PESQUISA (RSA) --}}
            <x-admin.card title="O anúncio responsivo de pesquisa" icon="fa-rectangle-ad" class="mt-3"
                descricao="O Google combina automaticamente os títulos e descrições para encontrar a melhor combinação em cada busca.">
                <div class="row g-3">
                    <x-admin.field name="url" type="url" label="Página de destino (URL final)"
                        :value="$urlDestino" :required="true" maxlength="255" col="col-12 col-md-8"
                        placeholder="https://goldenrastreamento.com.br/planos"
                        hint="Página que o usuário abre ao clicar no anúncio." />

                    <x-admin.field name="caminhos" type="text" label="Rotas de exibição amigáveis"
                        :value="$caminhosTexto" maxlength="60" col="col-12 col-md-4" :opcional="true"
                        placeholder="rastreamento/frota"
                        hint="Até 2 termos curtos de até 15 letras." />

                    <x-admin.field name="titulos" type="textarea" label="Títulos do anúncio"
                        :value="$titulosTexto" :rows="8" :required="true" maxlength="600" col="col-12 col-md-6"
                        placeholder="Golden Rastreamento&#10;Monitore em Tempo Real&#10;Bloqueio Via Aplicativo&#10;Cotação Rápida e Fácil"
                        hint="Um título por linha (de 3 a 15 títulos). Limite de 30 caracteres cada." />

                    <x-admin.field name="descricoes" type="textarea" label="Descrições do anúncio"
                        :value="$descricoesTexto" :rows="8" :required="true" maxlength="500" col="col-12 col-md-6"
                        placeholder="Proteja seu veículo 24h por dia com tecnologia de ponta e suporte dedicado.&#10;Instalação rápida e cobertura em todo o território nacional. Faça sua simulação."
                        hint="Uma descrição por linha (de 2 a 4 descrições). Limite de 90 caracteres cada." />
                </div>
            </x-admin.card>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="submit" class="btn btn-golden">
                    <i class="fas fa-cloud-arrow-up me-1" aria-hidden="true"></i>
                    {{ $ehEdicao ? 'Salvar alterações no Google Ads' : 'Criar grupo no Google Ads' }}
                </button>
                <a href="{{ route('admin.google-ads.campanhas.show', $endereco) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- BLOCO 3: RESUMO DA CAMPANHA --}}
            <x-admin.card title="A campanha no Google Ads" icon="fa-bullhorn"
                descricao="Este grupo pertencerá a esta campanha.">
                <dl class="row small mb-0 g-0">
                    <dt class="col-5 text-secondary fw-normal">Campanha</dt>
                    <dd class="col-7 mb-2 fw-semibold text-truncate" title="{{ $campanha['nome'] }}">{{ $campanha['nome'] }}</dd>

                    <dt class="col-5 text-secondary fw-normal">ID</dt>
                    <dd class="col-7 mb-2 font-monospace">{{ $campanha['id'] }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7 mb-2">
                        <x-admin.status :rotulo="$statusRotulo" :tom="tom_por_status('campanha_ads', $statusCampanha)" />
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Orçamento</dt>
                    <dd class="col-7 mb-2">{{ brl($campanha['orcamento']['diario_centavos'] ?? 0) }}/dia</dd>

                    <dt class="col-5 text-secondary fw-normal">Conta</dt>
                    <dd class="col-7 mb-0">{{ $conta->descriptive_name ?: $conta->numeroConta() }}</dd>
                </dl>
            </x-admin.card>

            {{-- BLOCO 4: DICAS E REGRAS --}}
            <x-admin.card title="Regras de validação do Google" icon="fa-shield-halved" class="mt-3"
                descricao="Requisitos exigidos pela API do Google Ads:">
                <ul class="small text-secondary ps-3 mb-0">
                    <li class="mb-2"><strong>Títulos:</strong> Mínimo de 3 e máximo de 15 títulos. Cada título pode ter no máximo 30 caracteres.</li>
                    <li class="mb-2"><strong>Descrições:</strong> Mínimo de 2 e máximo de 4 descrições. Cada uma com até 90 caracteres.</li>
                    <li class="mb-2"><strong>Palavras-chave:</strong> Ao menos uma palavra obrigatória. Use a sintaxe <code>palavra | ampla</code>, <code>palavra | frase</code> ou <code>palavra | exata</code>.</li>
                    <li class="mb-2"><strong>Rotas de exibição:</strong> Opcionais, até 2 partes amigáveis (ex: <code>frota/24h</code>).</li>
                    <li><strong>Sincronização imediata:</strong> Ao clicar em salvar, a operação é submetida em tempo real à API do Google Ads.</li>
                </ul>
            </x-admin.card>
        </div>
    </div>
</form>
@endsection

