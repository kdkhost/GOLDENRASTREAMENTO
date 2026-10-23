@extends('admin.layouts.app')
@section('title', 'Jornada da visita #'.$visita->id)
@section('page_title', $visita->canalRotulo().' · '.$visita->origemDetalhada())
@section('page_description', 'A trilha completa deste visitante: o que viu, onde clicou e se cadastrou.')

@section('page_actions')
    @if($visita->pago)
        <x-admin.status rotulo="tráfego pago" tom="red" class="align-self-center" />
    @endif
    <a href="{{ route('admin.atribuicao.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Como chegou" icon="fa-location-arrow">
            <dl class="defs mb-0">
                <dt>Canal</dt><dd><x-admin.status :rotulo="$visita->canalRotulo()" :tom="tom_por_status('canal', $visita->canal)" /></dd>
                <dt>Rede</dt><dd>{{ $visita->redeRotulo() }}</dd>
                <dt>Por que o sistema decidiu isso</dt><dd><code>{{ $visita->motivo ?: '—' }}</code></dd>
                <dt>Chegou de</dt><dd>{{ $visita->origem_host ?: '—' }}@if($visita->origem_path) <span class="text-secondary">{{ $visita->origem_path }}</span>@endif</dd>
                <dt>Pousou em</dt><dd><code>{{ $visita->landing_path ?: '/' }}</code></dd>
                <dt>utm_source / medium</dt><dd>{{ $visita->utm_source ?: '—' }} / {{ $visita->utm_medium ?: '—' }}</dd>
                <dt>utm_campaign / content</dt><dd>{{ $visita->utm_campaign ?: '—' }} / {{ $visita->utm_content ?: '—' }}</dd>
                <dt>utm_term</dt><dd>{{ $visita->utm_term ?: '—' }}</dd>
                <dt>Clique da plataforma</dt>
                <dd>
                    @if($visita->clique_id)
                        <code>{{ $visita->clique_id }}</code> {{ \Illuminate\Support\Str::limit($visita->clique_valor, 24, '…') }}
                    @else
                        —
                    @endif
                </dd>
                <dt>Aparelho</dt><dd>{{ \App\Models\Visit::rotuloDispositivo($visita->dispositivo) }} · {{ $visita->navegador ?: '—' }} · {{ $visita->sistema ?: '—' }}</dd>
                <dt>Aberta / última atividade</dt><dd>{{ $visita->aberta_em?->format('d/m/Y H:i') }} → {{ $visita->ultima_em?->format('d/m/Y H:i') }}</dd>
                <dt>Tamanho da visita</dt><dd>{{ $visita->visualizacoes }} tela(s) · {{ $visita->eventos }} evento(s) medido(s)</dd>
                <dt>Visitante</dt><dd><code>{{ \Illuminate\Support\Str::limit($visita->visitor_id, 12, '…') }}</code> <span class="text-secondary small">(cookie anônimo)</span></dd>
            </dl>
        </x-admin.card>

        @if($outras->isNotEmpty())
            <x-admin.card title="Outras visitas deste visitante" icon="fa-layer-group" class="mt-3"
                descricao="{{ $outras->count() }} acesso(s) antes ou depois desta — é isso que separa primeiro e último toque.">
                <ul class="timeline">
                    @foreach($outras as $outra)
                        <li>
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold small">
                                    <i class="fas {{ $outra->iconeCanal() }} me-1 text-gold" aria-hidden="true"></i>
                                    {{ $outra->canalRotulo() }} · {{ $outra->landing_path ?: '/' }}
                                </span>
                                <small class="text-secondary text-nowrap">{{ $outra->aberta_em?->format('d/m H:i') }}</small>
                            </div>
                            <div class="small text-secondary">
                                {{ $outra->origemDetalhada() }} · {{ $outra->visualizacoes }} tela(s)
                                @if($outra->leads_count)
                                    · <a href="{{ route('admin.atribuicao.show', $outra) }}">{{ $outra->leads_count }} cadastro(s)</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        @endif

        @if($visita->leads->isNotEmpty())
            <x-admin.card title="Cadastros desta visita" icon="fa-user-plus" class="mt-3">
                @foreach($visita->leads as $lead)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom">
                        <div>
                            <a href="{{ route('admin.leads.show', $lead) }}" class="fw-semibold text-decoration-none">{{ $lead->name }}</a>
                            <div class="small text-secondary">{{ $lead->phone ?: $lead->email }} · {{ \App\Models\Lead::label($lead->status) }}</div>
                        </div>
                        <x-admin.status :rotulo="$lead->canalRotulo()" :tom="$lead->tomCanal()" />
                    </div>
                @endforeach
            </x-admin.card>
        @endif
    </div>

    <div class="col-lg-7">
        <x-admin.card title="Linha do tempo" icon="fa-route"
            descricao="Da primeira tela ao cadastro, na ordem em que aconteceu.">
            <ul class="timeline">
                @forelse($jornada as $index => $passo)
                    <li class="{{ $index === count($jornada) - 1 ? 'atual' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">
                                <i class="fas {{ $passo['icone'] }} me-1 text-gold" aria-hidden="true"></i>
                                {{ $passo['titulo'] }}
                                @if($passo['tom'] !== 'neutral')
                                    <x-admin.status :rotulo="$passo['tipo']" :tom="$passo['tom']" class="ms-1" />
                                @endif
                            </span>
                            <small class="text-secondary text-nowrap">{{ $passo['quando']?->format('d/m/Y H:i') }}</small>
                        </div>
                        @if($passo['detalhe'])
                            <div class="small text-secondary">{{ $passo['detalhe'] }}</div>
                        @endif
                    </li>
                @empty
                    <li>Nenhum evento registrado nesta visita.</li>
                @endforelse
            </ul>
        </x-admin.card>
    </div>
</div>
@endsection
