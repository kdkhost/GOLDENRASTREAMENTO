@extends('admin.layouts.app')
@section('title', 'Lead — '.$lead->name)
@section('page_title', e($lead->name))
@section('page_description', 'Ficha do lead, funil e trilha de atendimento.')

@section('page_actions')
@if($lead->customer_id)
    <a href="{{ route('admin.clientes.show', $lead->customer_id) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-user-check me-1"></i> Ver cliente no CRM</a>
@else
    <form method="POST" action="{{ route('admin.leads.converter', $lead) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-success" title="Criar cliente no CRM a partir deste lead"><i class="fas fa-user-plus me-1"></i> Converter em cliente</button>
    </form>
@endif
@if($lead->phone)
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $lead->phone) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
@endif
<a href="{{ route('admin.leads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.card title="Mover no funil" icon="fa-filter">
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach(\App\Models\Lead::STATUSES as $s)
                    <form method="POST" action="{{ route('admin.leads.status', $lead) }}" class="d-inline" data-atualiza=".app-content">
                        @csrf
                        <input type="hidden" name="status" value="{{ $s }}">
                        <button class="btn btn-sm {{ $lead->status === $s ? 'btn-golden' : 'btn-outline-secondary' }}" {{ $lead->status === $s ? 'disabled' : '' }}>
                            {{ \App\Models\Lead::label($s) }}
                        </button>
                    </form>
                @endforeach
            </div>
            <form method="POST" action="{{ route('admin.leads.nota', $lead) }}" class="row g-2 align-items-end" data-atualiza=".app-content">
                @csrf
                <x-admin.field name="note" label="Nota de atendimento" col="col" placeholder="O que foi combinado com o cliente..." :required="true" maxlength="1000" />
                <div class="col-auto">
                    <button class="btn btn-sm btn-golden" title="Salvar nota"><i class="fas fa-note-sticky" aria-hidden="true"></i></button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Dados do lead" icon="fa-pen-to-square" class="mt-3">
            <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="row g-3">
                @csrf
                <x-admin.field name="name" label="Nome" :value="$lead->name" col="col-md-6" :required="true" maxlength="90" />
                <x-admin.field name="phone" label="WhatsApp" :value="$lead->phone" col="col-md-6" :required="true" maxlength="32" />
                <x-admin.field name="email" type="email" label="E-mail" :value="$lead->email" col="col-md-6" maxlength="160" :opcional="true" autocomplete="off" />
                <x-admin.field name="profile" type="select" label="Perfil" :value="$lead->profile" :options="config('golden.perfis')" empty="—" col="col-md-6" :opcional="true" />
                <x-admin.field name="fleet_size" label="Frota" :value="$lead->fleet_size" col="col-md-6" maxlength="16" :opcional="true" hint="Número de veículos ou uma faixa, como 12 a 30." />
                <x-admin.field name="kit" label="Kit" :value="$lead->kit" col="col-md-6" maxlength="80" :opcional="true" />
                <x-admin.field name="campaign_id" type="select" label="Campanha" :value="$lead->campaign_id" :options="$campanhas->mapWithKeys(fn ($c) => [$c->id => Str::limit($c->name, 26)])" empty="Sem campanha" col="col-md-6" :opcional="true" />
                <x-admin.field name="owner_id" type="select" label="Responsável" :value="$lead->owner_id" :options="$responsaveis->pluck('name', 'id')" empty="—" col="col-md-6" :opcional="true" />
                <x-admin.field name="value" type="number" label="Valor fechado (R$)" :value="$lead->value ? $lead->value / 100 : null" col="col-md-6" step="0.01" min="0" :opcional="true" placeholder="ex.: 1290.00" />
                <x-admin.field name="message" label="Mensagem do lead" :value="Str::limit($lead->message, 120, '')" col="col-md-6" :opcional="true" />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Salvar alterações</button>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="De onde este lead veio" icon="fa-diagram-project">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <x-admin.status :rotulo="$lead->canalRotulo()" :tom="$lead->tomCanal()" />
                @if($lead->rede)
                    <x-admin.status :rotulo="$lead->redeRotulo()" tom="neutral" />
                @endif
                @if($lead->pago)
                    <x-admin.status rotulo="tráfego pago" tom="red" />
                @endif
                @if($lead->dispositivo)
                    <x-admin.status :rotulo="\App\Models\Visit::rotuloDispositivo($lead->dispositivo)" tom="neutral" />
                @endif
            </div>
            <dl class="defs">
                <dt>Primeiro toque</dt>
                <dd>
                    {{ \App\Services\Atribuidor::rotuloCanal($lead->canal_primeiro) }}
                    @if($lead->rede_primeiro && $lead->rede_primeiro !== $lead->rede)<span class="text-secondary">· {{ \App\Services\Atribuidor::rotuloRede($lead->rede_primeiro) }}</span>@endif
                    @if($lead->primeiro_toque_em)<div class="small text-secondary">{{ $lead->primeiro_toque_em->format('d/m/Y H:i') }}</div>@endif
                </dd>
                <dt>Último toque</dt><dd>{{ $lead->canalRotulo() }}@if($lead->rede)<span class="text-secondary"> · {{ $lead->redeRotulo() }}</span>@endif</dd>
                <dt>Visitas até cadastrar</dt><dd>{{ $lead->toques ?: '—' }}</dd>
                <dt>Pousou em</dt><dd><code>{{ $lead->landing_page ?: '—' }}</code></dd>
                <dt>Veio de</dt><dd>{{ $lead->referrer ?: '—' }}</dd>
                @if($lead->visit)
                    <dt>O sistema decidiu por</dt><dd><code>{{ $lead->visit->motivo ?: '—' }}</code></dd>
                @endif
            </dl>
            @if($lead->visit)
                <a href="{{ route('admin.atribuicao.show', $lead->visit) }}" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="fas fa-route me-1" aria-hidden="true"></i> Ver a jornada completa desta visita
                </a>
            @endif
        </x-admin.card>

        @if($lead->jornada->count() > 1)
            <x-admin.card title="A trilha antes do cadastro" icon="fa-layer-group" class="mt-3"
                :descricao="$lead->jornada->count().' acessos deste visitante, da primeira tela ao último clique.'">
                <ul class="timeline">
                    @foreach($lead->jornada as $v)
                        <li class="{{ $v->id === $lead->visit_id ? 'atual' : '' }}">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold small">
                                    <i class="fas {{ $v->iconeCanal() }} me-1 text-gold" aria-hidden="true"></i>
                                    {{ $v->canalRotulo() }} · {{ $v->landing_path ?: '/' }}
                                </span>
                                <small class="text-secondary text-nowrap">{{ $v->aberta_em?->format('d/m H:i') }}</small>
                            </div>
                            <div class="small text-secondary">
                                {{ $v->origemDetalhada() }} · {{ $v->visualizacoes }} tela(s) · {{ $v->eventos }} clique(s)
                                @if($v->id)
                                    · <a href="{{ route('admin.atribuicao.show', $v) }}">abrir</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        @endif

        <x-admin.card title="Marcações de campanha" icon="fa-bullseye" class="mt-3">
            <dl class="defs">
                <dt>Campanha</dt><dd>{{ $lead->campaign?->name ?? 'Sem campanha' }}</dd>
                <dt>utm_source</dt><dd>{{ $lead->utm_source ?: '—' }}</dd>
                <dt>utm_medium</dt><dd>{{ $lead->utm_medium ?: '—' }}</dd>
                <dt>utm_campaign</dt><dd>{{ $lead->utm_campaign ?: '—' }}</dd>
                <dt>utm_content</dt><dd>{{ $lead->utm_content ?: '—' }}</dd>
                <dt>gclid</dt><dd>{{ $lead->gclid ?: '—' }}</dd>
                <dt>fbclid</dt><dd>{{ $lead->fbclid ?: '—' }}</dd>
                <dt>Visitante</dt><dd><code>{{ Str::limit($lead->visitor_id, 12, '') ?: '—' }}</code> <span class="text-secondary small">(cookie anônimo)</span></dd>
                <dt>Consentimento LGPD</dt><dd class="{{ $lead->consent_given ? 'text-success' : 'text-danger' }}">{{ $lead->consent_given ? 'Autorizado' : 'Não autorizado' }}</dd>
                <dt>IP (pseudonimizado)</dt><dd><code>{{ Str::limit($lead->ip_hash, 12, '') ?: '—' }}</code></dd>
            </dl>
        </x-admin.card>

        <x-admin.card title="Histórico de atendimento" icon="fa-timeline" class="mt-3">
            <ul class="timeline">
                @forelse($lead->histories as $h)
                    <li class="{{ $loop->first ? 'atual' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">
                                @if($h->from_status && $h->from_status !== $h->to_status)
                                    {{ \App\Models\Lead::label($h->from_status) }} &rarr; {{ \App\Models\Lead::label($h->to_status) }}
                                @elseif($h->from_status === $h->to_status)
                                    Nota
                                @else
                                    {{ \App\Models\Lead::label($h->to_status) }}
                                @endif
                            </span>
                            <small class="text-secondary text-nowrap">{{ $h->created_at->format('d/m H:i') }}</small>
                        </div>
                        @if($h->note)<div class="small">{{ $h->note }}</div>@endif
                        @if($h->user)<div class="small text-secondary">— {{ $h->user->name }}</div>@endif
                    </li>
                @empty
                    <li class="text-secondary small">Sem registros.</li>
                @endforelse
            </ul>
            @can('admin')
                <div class="border-top mt-2 pt-3">
                    <x-admin.delete :action="route('admin.leads.destroy', $lead)" label="Excluir lead" confirm="Excluir definitivamente este lead?" />
                </div>
            @endcan
        </x-admin.card>
    </div>
</div>
@endsection
