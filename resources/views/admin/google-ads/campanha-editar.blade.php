@extends('admin.layouts.app')
@section('title', 'Editar a campanha: '.$campanha['nome'])
@section('page_title', 'Editar a campanha')
@section('page_description', 'Nome, orçamento diário, regiões e negativas da campanha que já está no Google Ads. Salvar escreve no Google na hora — só o que mudou.')

@section('page_actions')
<a href="{{ route('admin.google-ads.campanhas.show', ['campanha' => $campanha['id'], 'conta' => $conta->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar à campanha</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
@php
    $endereco = ['campanha' => $campanha['id'], 'conta' => $conta->id];
    $regioes = implode("\n", array_column($campanha['regioes'], 'nome'));
    $negativas = implode("\n", array_column($campanha['negativas'], 'texto'));
@endphp

<form method="POST" action="{{ route('admin.google-ads.campanhas.atualizar', $endereco) }}">
    @csrf

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="A campanha" icon="fa-bullhorn"
                descricao="O que o Google recebe como ajuste da campanha inteira.">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome da campanha" :value="$campanha['nome']" :required="true" maxlength="128" col="col-12 col-md-8" />

                    <x-admin.field name="orcamento" type="number" label="Orçamento diário (R$)"
                        :value="number_format($campanha['orcamento']['diario_centavos'] / 100, 2, '.', '')"
                        :required="true" step="0.01" min="1" max="5000" col="col-12 col-md-4"
                        hint="Quanto pode ser gasto por dia, no máximo." />

                    <x-admin.field name="regioes" type="textarea" label="Regiões" :value="$regioes" :required="true" :rows="4" maxlength="500" col="col-12 col-md-6"
                        hint="Uma região por linha. Vale cidade, estado ou país — o Google resolve o resto. Tirar uma linha tira a região da campanha." />

                    <x-admin.field name="negativas" type="textarea" label="Palavras negativas da campanha" :value="$negativas" :rows="4" maxlength="6000" col="col-12 col-md-6" :opcional="true"
                        placeholder="grátis&#10;emprego&#10;como funciona"
                        hint="Buscas que não devem mostrar nenhum anúncio desta campanha. Uma por linha." />
                </div>
            </x-admin.card>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button class="btn btn-golden">
                    <i class="fas fa-cloud-arrow-up me-1" aria-hidden="true"></i> Salvar no Google Ads
                </button>
                <a href="{{ route('admin.google-ads.campanhas.show', $endereco) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="O que esta tela escreve" icon="fa-shield-halved"
                descricao="Só a diferença: o que já estava igual no Google não é tocado.">
                <ul class="small text-secondary ps-3 mb-0">
                    <li>O <strong>nome</strong> e o <strong>orçamento</strong> mudam na campanha que já existe — a métrica não recomeça.</li>
                    <li>Uma <strong>região</strong> nova é acrescentada e a que saiu da lista é removida da segmentação.</li>
                    <li>As <strong>negativas</strong> seguem a mesma regra, em correspondência de frase.</li>
                    <li>Grupos, palavras-chave e anúncio ficam na ficha da campanha, cada grupo com a sua edição.</li>
                    <li>A veiculação (ativar e pausar) não muda por aqui.</li>
                </ul>
            </x-admin.card>

            <x-admin.card title="Como está hoje" icon="fa-clock-rotate-left" class="mt-3">
                <dl class="row small mb-0 g-0">
                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7 mb-2"><x-admin.status :rotulo="$campanha['status_rotulo']" :tom="tom_por_status('campanha_ads', $campanha['status'])" /></dd>
                    <dt class="col-5 text-secondary fw-normal">Orçamento</dt>
                    <dd class="col-7 mb-2">{{ brl($campanha['orcamento']['diario_centavos']) }} por dia</dd>
                    <dt class="col-5 text-secondary fw-normal">Regiões</dt>
                    <dd class="col-7 mb-2">{{ count($campanha['regioes']) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Negativas</dt>
                    <dd class="col-7 mb-2">{{ count($campanha['negativas']) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Grupos</dt>
                    <dd class="col-7 mb-0">{{ count($campanha['grupos']) }}</dd>
                </dl>
            </x-admin.card>
        </div>
    </div>
</form>
@endsection
